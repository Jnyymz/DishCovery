<?php
/* =========================================================
   recommend.php
   Main Recommendation Controller
   DishCovery – CBF Pipeline
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/../core/models.php';
require_once __DIR__ . '/preprocess.php';
require_once __DIR__ . '/tfidf.php';
require_once __DIR__ . '/similarity.php';
require_once __DIR__ . '/filters.php';
require_once __DIR__ . '/substitution.php';

/**
 * Generate recipe recommendations
 */
function generateRecommendations(
    int $userId,
    array $userIngredients,
    array $preferences,
    int $limit = 10
) {
    global $conn;

    $similarityThreshold = getSimilarityThreshold();

    // Step 0: Validate input
    if (empty($userIngredients)) {
        error_log("generateRecommendations: No user ingredients provided");
        return [];
    }

    // Filter out empty ingredients
    $userIngredients = array_filter($userIngredients, function ($ing) {
        return !empty(trim($ing));
    });

    if (empty($userIngredients)) {
        error_log("generateRecommendations: All user ingredients empty after filtering");
        return [];
    }

    error_log("generateRecommendations: Processing " . count($userIngredients) . " ingredients");

    // Step 1: User TF-IDF vector
    $userVector = generateUserTFIDFVector($userIngredients);

    if (empty($userVector)) {
        error_log("generateRecommendations: User vector is empty");
        return [];
    }

    error_log("generateRecommendations: User vector has " . count($userVector) . " dimensions");

    // Step 2: Build ingredient-first ranking base.
    $mainIngredient = (string)(reset($userIngredients) ?: '');
    $similarityRanked = computeSimilarityScores($userVector, $mainIngredient);
    $ingredientCentricCandidates = buildIngredientFallbackCandidates($userIngredients, 700, $mainIngredient);

    $ingredientFirstRanked = [];
    foreach ($ingredientCentricCandidates as $recipeId => $ingredientScore) {
        $baseSimilarity = $similarityRanked[$recipeId] ?? 0.0;
        // Ingredient overlap is primary; similarity is secondary tie-breaker.
        $ingredientFirstRanked[$recipeId] = ($ingredientScore * 0.80) + ($baseSimilarity * 0.20);
    }

    // Soft protein preference bonus (+0.08 to +0.12 range, fixed at +0.10).
    $ingredientFirstRanked = applyProteinIngredientBoost($ingredientFirstRanked, $userIngredients);

    // If ingredient overlap is sparse, backfill with top similarity candidates.
    if (count($ingredientFirstRanked) < 60 && !empty($similarityRanked)) {
        foreach (array_slice($similarityRanked, 0, 200, true) as $recipeId => $score) {
            if (!isset($ingredientFirstRanked[$recipeId])) {
                $ingredientFirstRanked[$recipeId] = $score * 0.12;
            }
        }
    }

    arsort($ingredientFirstRanked);

    error_log("generateRecommendations: Ingredient-first candidate pool: " . count($ingredientFirstRanked));

    if (empty($ingredientFirstRanked)) {
        error_log("generateRecommendations: No ingredient-first candidates found, returning empty");
        return [];
    }

    // Step 3: Ingredient-centric thresholding (keep meaningful pool without over-pruning)
    $thresholded = array_filter($ingredientFirstRanked, function ($score) use ($similarityThreshold) {
        return $score >= max(0.06, $similarityThreshold * 0.45);
    });

    $effectiveThreshold = $similarityThreshold;
    $minDesired = max(3, min($limit, 5));

    if (count($thresholded) < $minDesired) {
        $relaxedThreshold = max(0.05, $similarityThreshold * 0.5);
        $relaxed = array_filter($ingredientFirstRanked, function ($score) use ($relaxedThreshold) {
            return $score >= $relaxedThreshold;
        });

        if (count($relaxed) > count($thresholded)) {
            $thresholded = $relaxed;
            $effectiveThreshold = $relaxedThreshold;
        }
    }

    if (empty($thresholded)) {
        // Final fallback: keep top ranked candidates instead of returning empty,
        // then let strict/relaxed filters narrow the list.
        $thresholded = array_slice($ingredientFirstRanked, 0, 120, true);
        $effectiveThreshold = 0.0;
    }

    arsort($thresholded);

    error_log("generateRecommendations: After threshold (effective=" . $effectiveThreshold . "): " . count($thresholded) . " recipes");

    // Step 4: Apply filters (after similarity ranking and threshold)
    // Keep numeric filters active in all fallback stages.
    $normalizedPreferences = $preferences;
    if (empty($normalizedPreferences['max_cooking_time']) && !empty($normalizedPreferences['cooking_time'])) {
        $normalizedPreferences['max_cooking_time'] = (int)$normalizedPreferences['cooking_time'];
    }
    if (empty($normalizedPreferences['max_calories']) && !empty($normalizedPreferences['calories'])) {
        $normalizedPreferences['max_calories'] = (int)$normalizedPreferences['calories'];
    }

    $applyMealTypeFilter = function (array $scoredRecipes, array $activePreferences) use ($conn) {
        if (empty($scoredRecipes) || empty($activePreferences['meal_type'])) {
            return $scoredRecipes;
        }

        $mealPreference = strtolower(trim((string)$activePreferences['meal_type']));
        $recipeIds = array_keys($scoredRecipes);
        $placeholders = str_repeat('?,', count($recipeIds) - 1) . '?';
        $types = str_repeat('i', count($recipeIds));

        $stmt = $conn->prepare(
            "SELECT recipe_id, meal_type FROM recipes WHERE recipe_id IN ($placeholders)"
        );
        $stmt->bind_param($types, ...$recipeIds);
        $stmt->execute();
        $result = $stmt->get_result();
        $recipes = [];
        while ($row = $result->fetch_assoc()) {
            $recipes[$row['recipe_id']] = $row;
        }

        $mealFiltered = [];

        foreach ($scoredRecipes as $recipeId => $score) {
            $recipe = $recipes[$recipeId] ?? null;

            if (!$recipe) {
                continue;
            }

            // Follow existing filter behavior: NULL values are allowed.
            if ($recipe['meal_type'] !== null && strtolower($recipe['meal_type']) !== $mealPreference) {
                continue;
            }

            $mealFiltered[$recipeId] = $score;
        }

        return $mealFiltered;
    };

    $applyStrictFilters = function (array $scoredRecipes, array $activePreferences) use ($applyMealTypeFilter) {
        $baseFiltered = applyFilters($scoredRecipes, $activePreferences);
        return $applyMealTypeFilter($baseFiltered, $activePreferences);
    };

    $targetResults = max(3, min($limit, 5));

    $runCategoricalFallbackStages = function (array $candidates, array $strictPreferences, int $targetResults) use ($applyStrictFilters) {
        $filtered = $applyStrictFilters($candidates, $strictPreferences);
        $filterStage = 'strict';
        $canRelaxCuisine = empty($strictPreferences['cuisine_preference']);

        if ($canRelaxCuisine && count($filtered) < $targetResults) {
            $fallbackPreferences = $strictPreferences;
            unset($fallbackPreferences['cuisine_preference']);
            $filtered = $applyStrictFilters($candidates, $fallbackPreferences);
            $filterStage = 'no_cuisine';
        }

        if (count($filtered) < $targetResults) {
            $fallbackPreferences = $strictPreferences;
            unset($fallbackPreferences['diet_type']);
            if ($canRelaxCuisine) {
                unset($fallbackPreferences['cuisine_preference']);
            }
            $filtered = $applyStrictFilters($candidates, $fallbackPreferences);
            $filterStage = $canRelaxCuisine ? 'no_cuisine_no_diet' : 'keep_cuisine_no_diet';
        }

        if (count($filtered) < $targetResults) {
            $fallbackPreferences = $strictPreferences;
            unset($fallbackPreferences['diet_type'], $fallbackPreferences['meal_type']);
            if ($canRelaxCuisine) {
                unset($fallbackPreferences['cuisine_preference']);
            }
            $filtered = $applyStrictFilters($candidates, $fallbackPreferences);
            $filterStage = $canRelaxCuisine ? 'no_cuisine_no_diet_no_meal' : 'keep_cuisine_no_diet_no_meal';
        }

        return [$filtered, $filterStage];
    };

    // Filters are secondary: they only refine ingredient-relevant candidates,
    // never expand into unrelated recipes.
    $filterCandidates = $thresholded;
    if (count($filterCandidates) < 50) {
        $filterCandidates = $filterCandidates + array_slice($ingredientFirstRanked, 0, 200, true);
        arsort($filterCandidates);
    }

    // Strict pass: cuisine + diet + meal + cooking_time + calories
    $strictPreferences = $normalizedPreferences;
    [$filtered, $filterStage] = $runCategoricalFallbackStages($filterCandidates, $strictPreferences, $targetResults);

    // Final fallback path: ingredient-overlap candidates from full recipe corpus.
    // This prevents hard zero-results when similarity vectors are sparse/noisy.
    $ingredientFallbackCandidates = $ingredientCentricCandidates;
    if (empty($filtered)) {
        if (empty($ingredientFallbackCandidates)) {
            $ingredientFallbackCandidates = buildIngredientFallbackCandidates($userIngredients, 300, $mainIngredient);
        }
        if (!empty($ingredientFallbackCandidates)) {
            [$filtered, $fallbackStage] = $runCategoricalFallbackStages($ingredientFallbackCandidates, $strictPreferences, $targetResults);
            if (!empty($filtered)) {
                $filterStage = 'ingredient_fallback_' . $fallbackStage;
            }
        }
    }

    // Last-resort behavior: if user-selected filters are too restrictive,
    // still return ingredient-driven recommendations instead of empty state.
    if (empty($filtered)) {
        if (!empty($ingredientFallbackCandidates)) {
            $filtered = array_slice($ingredientFallbackCandidates, 0, 250, true);
            $filterStage = 'ingredient_fallback_unfiltered';
        } else {
            $filtered = array_slice($ingredientFirstRanked, 0, 250, true);
            $filterStage = 'ingredient_first_unfiltered';
        }
    }

    // Ingredients take priority: if categorical filters leave out strongest ingredient matches,
    // prefer ingredient-driven ranking regardless of filters.
    $hasCategoricalFilters = !empty($strictPreferences['cuisine_preference'])
        || !empty($strictPreferences['diet_type'])
        || !empty($strictPreferences['meal_type']);

    if ($hasCategoricalFilters && !empty($filtered) && !empty($ingredientFirstRanked)) {
        $topIngredientScore = reset($ingredientFirstRanked);
        if ($topIngredientScore !== false && (float)$topIngredientScore > 0.0) {
            $priorityThreshold = max(0.60, (float)$topIngredientScore * 0.95);
            $priorityCandidates = array_filter($ingredientFirstRanked, function ($score) use ($priorityThreshold) {
                return (float)$score >= $priorityThreshold;
            });

            if (!empty($priorityCandidates)) {
                $priorityIds = array_fill_keys(array_keys($priorityCandidates), true);
                $filteredHasPriorityMatch = false;
                foreach ($filtered as $recipeId => $score) {
                    if (isset($priorityIds[$recipeId])) {
                        $filteredHasPriorityMatch = true;
                        break;
                    }
                }

                if (!$filteredHasPriorityMatch) {
                    $filtered = array_slice($ingredientFirstRanked, 0, 250, true);
                    $filterStage = 'ingredient_priority_override';
                }
            }
        }
    }

    error_log("generateRecommendations: After filtering (" . $filterStage . "): " . count($filtered) . " recipes");

    // Step 5: Re-sort, apply relative relevance cutoff, and limit results
    $ranked = $filtered;
    arsort($ranked);

    if ($mainIngredient !== '') {
        $mainFocused = prioritizeMainIngredientMatches($ranked, $mainIngredient, max(1, min($limit, 5)));
        if (!empty($mainFocused)) {
            $ranked = $mainFocused;
        }
    }

    $topScore = reset($ranked);
    if ($topScore === false || (float)$topScore <= 0.0) {
        $final = [];
    } else {
        $minimumRelevantScore = (float)$topScore * 0.30;
        $relevantRanked = array_filter($ranked, function ($score) use ($minimumRelevantScore) {
            return (float)$score >= $minimumRelevantScore;
        });
        $finalLimit = max(1, min($limit, 5));
        $final = array_slice($relevantRanked, 0, $finalLimit, true);
    }

    error_log("generateRecommendations: Final limit to " . count($final) . " recipes");

    // Step 6: Fetch recipe details
    $recipes = [];
    foreach ($final as $recipeId => $score) {
        $stmt = $conn->prepare(
            "SELECT * FROM recipes WHERE recipe_id = ?"
        );
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();
        $recipe = $stmt->get_result()->fetch_assoc();

        if ($recipe) {
            $recipes[] = [
                'recipe' => $recipe,
                'similarity_score' => $score
            ];
        }
    }

    error_log("generateRecommendations: Returning " . count($recipes) . " recipes with details");

    // Step 7: Log recommendation
    $inputJson = json_encode($userIngredients);
    $topRecipeIds = array_map('intval', array_keys($final));
    $topRecipeIds = array_values(array_filter($topRecipeIds, static function ($recipeId) {
        return $recipeId > 0;
    }));
    $recipesJson = json_encode($topRecipeIds);

    ensureRecommendationLogsTopKColumnExists();

    $topKCount = count($topRecipeIds);
    $stmt = $conn->prepare(
        "INSERT INTO recommendation_logs
         (user_id, input_ingredients, recommended_recipes, top_k_count)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "issi",
        $userId,
        $inputJson,
        $recipesJson,
        $topKCount
    );
    $stmt->execute();

    $logId = (int)$conn->insert_id;
    if ($logId > 0) {
        seedRecommendationResultsForLog($logId, $topRecipeIds);
    }

    return $recipes;
}

function getIngredientTypeLexicon(): array {
    return [
        'primary' => [
            'pork', 'beef', 'chicken', 'fish', 'duck', 'shrimp', 'prawn', 'lamb', 'turkey',
            'tuna', 'salmon', 'mackerel', 'sardine', 'anchovy', 'crab', 'lobster', 'oyster',
            'mussel', 'squid', 'octopus'
        ],
        'secondary' => [
            'carrot', 'potato', 'tomato', 'cabbage', 'lettuce', 'spinach', 'broccoli', 'pepper',
            'eggplant', 'zucchini', 'cucumber', 'cauliflower', 'bean', 'peas', 'mushroom'
        ],
        'aromatics' => [
            'garlic', 'onion', 'ginger'
        ],
        'condiments' => [
            'soy sauce', 'fish sauce', 'vinegar', 'ketchup', 'mustard', 'mayonnaise', 'mayo',
            'chili sauce', 'oyster sauce', 'hot sauce', 'salt', 'pepper', 'sugar'
        ],
    ];
}

function hasWordBoundaryMatch(string $needle, string $haystack): bool {
    $normalizedNeedle = normalizeIngredient($needle);
    $normalizedHaystack = normalizeIngredient($haystack);

    if ($normalizedNeedle === '' || $normalizedHaystack === '') {
        return false;
    }

    $escapedNeedle = preg_quote($normalizedNeedle, '/');
    $escapedNeedle = str_replace('\\ ', '\\s+', $escapedNeedle);
    return preg_match('/\\b' . $escapedNeedle . '\\b/i', $normalizedHaystack) === 1;
}

function getIngredientModifierWords(): array {
    return [
        'sauce', 'powder', 'paste', 'oil', 'broth', 'stock', 'dressing', 'extract'
    ];
}

function normalizeIngredientForTokenMatch(string $ingredient): string {
    $normalized = strtolower($ingredient);

    // Remove bracketed notes
    $normalized = preg_replace('/\([^)]*\)/', ' ', $normalized);

    // Remove leading quantities/fractions only
    $normalized = preg_replace('/^\s*[\d\/.\-¼½¾⅓⅔\s]+/', '', $normalized);

    // Keep alphabetic words and spaces
    $normalized = preg_replace('/[^a-z\s]/', ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', trim($normalized));

    return trim($normalized);
}

function getNormalizedIngredientTokensFromList(string $ingredientList): array {
    $rawTokens = preg_split('/[,;\n]+/', $ingredientList);
    $tokens = [];

    foreach ($rawTokens as $token) {
        $normalized = normalizeIngredientForTokenMatch((string)$token);
        if ($normalized !== '') {
            $tokens[] = $normalized;
        }
    }

    return array_values(array_unique($tokens));
}

function tokenContainsModifierWord(string $token): bool {
    $normalized = normalizeIngredientForTokenMatch($token);
    if ($normalized === '') {
        return false;
    }

    $modifierWords = getIngredientModifierWords();
    foreach ($modifierWords as $modifierWord) {
        if (hasWordBoundaryMatch($modifierWord, $normalized)) {
            return true;
        }
    }

    return false;
}

function evaluateIngredientTokenMatchStrength(string $userIngredient, string $recipeToken): string {
    $normalizedUserIngredient = normalizeIngredientForTokenMatch($userIngredient);
    $normalizedRecipeToken = normalizeIngredientForTokenMatch($recipeToken);

    if ($normalizedUserIngredient === '' || $normalizedRecipeToken === '') {
        return 'none';
    }

    if ($normalizedRecipeToken === $normalizedUserIngredient) {
        return 'strong';
    }

    if (hasBoundaryMatchInToken($normalizedUserIngredient, $normalizedRecipeToken) && !tokenContainsModifierWord($normalizedRecipeToken)) {
        return 'strong';
    }

    $escapedUserIngredient = preg_quote($normalizedUserIngredient, '/');
    $startsWithPattern = '/^' . str_replace('\\ ', '\\s+', $escapedUserIngredient) . '\\b/i';
    if (preg_match($startsWithPattern, $normalizedRecipeToken) === 1 && !tokenContainsModifierWord($normalizedRecipeToken)) {
        return 'strong';
    }

    if (hasWordBoundaryMatch($normalizedUserIngredient, $normalizedRecipeToken) && tokenContainsModifierWord($normalizedRecipeToken)) {
        return 'weak';
    }

    return 'none';
}

function getBestTokenMatchStrength(string $userIngredient, array $recipeTokens): string {
    $bestStrength = 'none';

    foreach ($recipeTokens as $recipeToken) {
        $matchStrength = evaluateIngredientTokenMatchStrength($userIngredient, (string)$recipeToken);
        if ($matchStrength === 'strong') {
            return 'strong';
        }
        if ($matchStrength === 'weak') {
            $bestStrength = 'weak';
        }
    }

    return $bestStrength;
}

function hasBoundaryMatchInToken(string $needle, string $token): bool {
    $normalizedNeedle = normalizeIngredientForTokenMatch($needle);
    $normalizedToken = normalizeIngredientForTokenMatch($token);

    if ($normalizedNeedle === '' || $normalizedToken === '') {
        return false;
    }

    $escapedNeedle = preg_quote($normalizedNeedle, '/');
    $escapedNeedle = str_replace('\\ ', '\\s+', $escapedNeedle);
    return preg_match('/\\b' . $escapedNeedle . '\\b/i', $normalizedToken) === 1;
}

function classifyIngredientType(string $ingredient): string {
    $normalized = normalizeIngredient($ingredient);
    if ($normalized === '') {
        return 'unknown';
    }

    $lexicon = getIngredientTypeLexicon();
    foreach ($lexicon as $type => $terms) {
        foreach ($terms as $term) {
            $term = normalizeIngredient($term);
            if ($term === '') {
                continue;
            }

            if ($normalized === $term) {
                return $type;
            }

            if (hasWordBoundaryMatch($term, $normalized) || hasWordBoundaryMatch($normalized, $term)) {
                return $type;
            }
        }
    }

    return 'unknown';
}

function getProteinBaseTerms(): array {
    $lexicon = getIngredientTypeLexicon();
    $primaryTerms = $lexicon['primary'] ?? [];
    $normalized = array_map('normalizeIngredient', $primaryTerms);
    $normalized = array_filter($normalized, function ($term) {
        return $term !== '';
    });
    return array_values(array_unique($normalized));
}

function extractProteinBoostSignals(array $userIngredients): array {
    $proteinBaseTerms = getProteinBaseTerms();
    $signals = [];

    foreach ($userIngredients as $rawIngredient) {
        $normalizedPhrase = normalizeIngredientForTokenMatch((string)$rawIngredient);
        if ($normalizedPhrase === '') {
            continue;
        }

        $parts = array_values(array_filter(preg_split('/\s+/', $normalizedPhrase), function ($part) {
            return trim((string)$part) !== '';
        }));
        if (empty($parts)) {
            continue;
        }

        $baseMeat = (string)end($parts);
        if (!in_array($baseMeat, $proteinBaseTerms, true)) {
            continue;
        }

        $signals[] = [
            'phrase' => $normalizedPhrase,
            'base_meat' => $baseMeat,
            'is_compound' => count($parts) > 1,
        ];
    }

    $uniqueSignals = [];
    foreach ($signals as $signal) {
        $key = $signal['phrase'] . '|' . $signal['base_meat'];
        $uniqueSignals[$key] = $signal;
    }

    return array_values($uniqueSignals);
}

function recipeHasExactProteinPhrase(array $recipeTokens, string $phrase): bool {
    $normalizedPhrase = normalizeIngredientForTokenMatch($phrase);
    if ($normalizedPhrase === '') {
        return false;
    }

    foreach ($recipeTokens as $recipeToken) {
        if (hasBoundaryMatchInToken($normalizedPhrase, (string)$recipeToken)) {
            return true;
        }
    }

    return false;
}

function recipeHasBaseProtein(array $recipeTokens, string $baseMeat): bool {
    foreach ($recipeTokens as $recipeToken) {
        $normalizedToken = normalizeIngredientForTokenMatch((string)$recipeToken);
        if ($normalizedToken === '') {
            continue;
        }

        if ($normalizedToken === normalizeIngredientForTokenMatch($baseMeat)) {
            return true;
        }

        if (hasWordBoundaryMatch($baseMeat, $normalizedToken) && !tokenContainsModifierWord($normalizedToken)) {
            return true;
        }
    }

    return false;
}

function applyProteinIngredientBoost(array $similarityScores, array $userIngredients): array {
    global $conn;

    if (empty($similarityScores) || empty($userIngredients)) {
        return $similarityScores;
    }

    $proteinSignals = extractProteinBoostSignals($userIngredients);
    if (empty($proteinSignals)) {
        return $similarityScores;
    }

    $boosted = $similarityScores;
    $stmt = $conn->prepare("SELECT ingredient_list FROM recipes WHERE recipe_id = ?");
    if (!$stmt) {
        return $similarityScores;
    }

    foreach ($similarityScores as $recipeId => $score) {
        $stmt->bind_param('i', $recipeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            continue;
        }

        $ingredientList = (string)($row['ingredient_list'] ?? '');
        $recipeTokens = getNormalizedIngredientTokensFromList($ingredientList);
        if (empty($recipeTokens)) {
            continue;
        }

        $hasExactCompoundMatch = false;
        $hasBaseOnlyMatch = false;

        foreach ($proteinSignals as $signal) {
            if (empty($signal['is_compound'])) {
                continue;
            }

            if (recipeHasExactProteinPhrase($recipeTokens, (string)$signal['phrase'])) {
                $hasExactCompoundMatch = true;
                break;
            }

            if (recipeHasBaseProtein($recipeTokens, (string)$signal['base_meat'])) {
                $hasBaseOnlyMatch = true;
            }
        }

        if ($hasExactCompoundMatch) {
            // Strong compound-meat boost.
            $boosted[$recipeId] = $score * 1.5;
            continue;
        }

        if ($hasBaseOnlyMatch) {
            // Weaker fallback boost when only base meat appears.
            $boosted[$recipeId] = $score + 0.05;
        }
    }

    arsort($boosted);
    return $boosted;
}

function buildIngredientFallbackCandidates(array $userIngredients, int $limit = 300, string $mainIngredient = ''): array {
    global $conn;

    $normalizedInputPhrases = [];
    foreach ($userIngredients as $ingredient) {
        $normalized = normalizeIngredientForTokenMatch((string)$ingredient);
        if ($normalized !== '') {
            $normalizedInputPhrases[] = $normalized;
        }
    }

    $normalizedInputPhrases = array_values(array_unique(array_filter($normalizedInputPhrases, function ($phrase) {
        return strlen($phrase) >= 3;
    })));
    if (empty($normalizedInputPhrases)) {
        return [];
    }

    $normalizedMainIngredient = normalizeIngredientForTokenMatch($mainIngredient);

    $candidates = [];
    $result = $conn->query("SELECT recipe_id, ingredient_list FROM recipes");
    if (!$result) {
        return [];
    }

    while ($row = $result->fetch_assoc()) {
        $recipeId = (int)$row['recipe_id'];
        $ingredientList = (string)($row['ingredient_list'] ?? '');
        if ($recipeId <= 0 || $ingredientList === '') {
            continue;
        }

        $recipeTokens = getNormalizedIngredientTokensFromList($ingredientList);
        if (empty($recipeTokens)) {
            continue;
        }

        $strongHits = 0;
        $weakHits = 0;
        foreach ($normalizedInputPhrases as $userIngredient) {
            $bestStrength = getBestTokenMatchStrength($userIngredient, $recipeTokens);
            if ($bestStrength === 'strong') {
                $strongHits++;
                continue;
            }
            if ($bestStrength === 'weak') {
                $weakHits++;
            }
        }

        if (($strongHits + $weakHits) <= 0) {
            continue;
        }

        $strongCoverage = $strongHits / max(1, count($normalizedInputPhrases));
        $weakCoverage = $weakHits / max(1, count($normalizedInputPhrases));

        $score = 0.05
            + ($strongCoverage * 0.75)
            + ($weakCoverage * 0.08);

        if ($normalizedMainIngredient !== '') {
            $mainStrength = getBestTokenMatchStrength($normalizedMainIngredient, $recipeTokens);
            if ($mainStrength === 'strong') {
                $score += 0.35;
            } elseif ($mainStrength === 'weak') {
                $score *= 0.70;
            } else {
                $score *= 0.45;
            }
        }

        $candidates[$recipeId] = $score;
    }

    arsort($candidates);

    if ($limit > 0 && count($candidates) > $limit) {
        $candidates = array_slice($candidates, 0, $limit, true);
    }

    return $candidates;
}

function prioritizeMainIngredientMatches(array $rankedScores, string $mainIngredient, int $requiredCount = 3): array {
    global $conn;

    $normalizedMainIngredient = normalizeIngredient($mainIngredient);
    if ($normalizedMainIngredient === '' || empty($rankedScores)) {
        return $rankedScores;
    }

    $stmt = $conn->prepare("SELECT ingredient_list FROM recipes WHERE recipe_id = ?");
    if (!$stmt) {
        return $rankedScores;
    }

    $mainMatches = [];
    foreach ($rankedScores as $recipeId => $score) {
        $safeRecipeId = (int)$recipeId;
        $stmt->bind_param('i', $safeRecipeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            continue;
        }

        if (recipeContainsMainIngredient((string)($row['ingredient_list'] ?? ''), $normalizedMainIngredient)) {
            $mainMatches[$safeRecipeId] = $score;
        }
    }

    if (count($mainMatches) >= max(1, $requiredCount)) {
        arsort($mainMatches);
        return $mainMatches;
    }

    return $rankedScores;
}

function expandIngredientMatchTerms(string $text): array {
    $terms = [];
    $normalized = normalizeIngredient($text);
    if ($normalized === '') {
        return [];
    }

    $terms[] = $normalized;
    $parts = preg_split('/\s+/', $normalized);
    foreach ($parts as $part) {
        $token = trim($part);
        if ($token === '' || strlen($token) < 3) {
            continue;
        }
        $terms[] = $token;

        // Simple singular variants for plural tokens (e.g., peppers -> pepper).
        if (strlen($token) > 4 && substr($token, -2) === 'es') {
            $terms[] = substr($token, 0, -2);
        }
        if (strlen($token) > 3 && substr($token, -1) === 's') {
            $terms[] = substr($token, 0, -1);
        }
    }

    return array_values(array_unique($terms));
}

function getSimilarityThreshold() {
    global $conn;

    $defaultThreshold = 0.15;  // Lowered from 0.5 to 0.15 to catch more recipes
    $smallDatasetThreshold = 0.1;  // Lowered from 0.2 to 0.1
    $smallDatasetLimit = 50;

    $result = $conn->query("SELECT COUNT(*) AS total FROM recipes");
    if ($result) {
        $row = $result->fetch_assoc();
        $total = (int)($row['total'] ?? 0);
        if ($total > 0 && $total < $smallDatasetLimit) {
            return $smallDatasetThreshold;
        }
    }

    return $defaultThreshold;
}
