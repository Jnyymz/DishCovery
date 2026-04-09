<?php
require_once __DIR__ . '/dbConfig.php';
require_once __DIR__ . '/../algo/tfidf.php';
require_once __DIR__ . '/../algo/tfidf_vocab.php';
require_once __DIR__ . '/../evaluation/precision.php';
require_once __DIR__ . '/../api/spoonacular_nutri.php';

function getAdminDashboardStats(): array {
    $totalRecipes = 0;
    $incompleteRecipes = 0;
    $totalSubstitutionPairs = 0;
    $recipesNeedingTfidfRebuild = 0;

    $totalRecipesRow = dbFetchOne("SELECT COUNT(*) AS total FROM recipes");
    $totalRecipes = (int)($totalRecipesRow['total'] ?? 0);

    $hasApiStatus = adminHasColumn('recipes', 'api_status');
    if ($hasApiStatus) {
        $incompleteRow = dbFetchOne("SELECT COUNT(*) AS total FROM recipes WHERE api_status = 'incomplete'");
        $incompleteRecipes = (int)($incompleteRow['total'] ?? 0);
    }

    if (adminHasTable('ingredient_substitutions')) {
        $substitutionRows = dbFetchOne("SELECT COUNT(*) AS total FROM ingredient_substitutions");
        $totalSubstitutionPairs = (int)($substitutionRows['total'] ?? 0);
    }

    if (adminHasTable('tfidf_vectors')) {
        $needsRebuildRow = dbFetchOne(
            "SELECT COUNT(*) AS total
             FROM recipes r
             LEFT JOIN (
                 SELECT DISTINCT recipe_id
                 FROM tfidf_vectors
             ) tv ON tv.recipe_id = r.recipe_id
             WHERE tv.recipe_id IS NULL"
        );
        $recipesNeedingTfidfRebuild = (int)($needsRebuildRow['total'] ?? 0);
    } else {
        $recipesNeedingTfidfRebuild = $totalRecipes;
    }

    return [
        'total_recipes' => $totalRecipes,
        'incomplete_api_recipes' => $incompleteRecipes,
        'total_substitution_pairs' => $totalSubstitutionPairs,
        'recipes_needing_tfidf_rebuild' => $recipesNeedingTfidfRebuild,
    ];
}

function adminExtractRecipeIdsFromLog(string $recommendedRecipesJson, int $k = 5): array {
    $decoded = json_decode($recommendedRecipesJson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $recipeIds = [];
    foreach ($decoded as $item) {
        $candidateId = null;

        if (is_numeric($item)) {
            $candidateId = (int)$item;
        } elseif (is_array($item)) {
            if (isset($item['recipe_id']) && is_numeric($item['recipe_id'])) {
                $candidateId = (int)$item['recipe_id'];
            } elseif (isset($item['recipe']['recipe_id']) && is_numeric($item['recipe']['recipe_id'])) {
                $candidateId = (int)$item['recipe']['recipe_id'];
            }
        }

        if ($candidateId !== null && $candidateId > 0) {
            $recipeIds[] = $candidateId;
        }

        if (count($recipeIds) >= $k) {
            break;
        }
    }

    return $recipeIds;
}

function getAdminAveragePrecisionScore(int $k = 5): float {
    $stats = computeOverallPrecisionStats(max(1, $k));
    if ((int)($stats['total_logs'] ?? 0) <= 0) {
        return -1.0;
    }

    return round((float)($stats['precision'] ?? 0.0), 3);
}

function getAdminEvaluationCardData(int $k = 5): array {
    $stats = computeOverallPrecisionStats(max(1, $k));
    $totalInteractions = (int)($stats['total_logs'] ?? 0);
    $evaluatedLogsCount = (int)($stats['evaluated_logs'] ?? 0);
    $evaluatedLogsCount = min($evaluatedLogsCount, $totalInteractions);
    $precision = $totalInteractions > 0
        ? round((float)($stats['precision'] ?? 0.0), 3)
        : -1.0;

    return [
        'total_logged_interactions' => $totalInteractions,
        'evaluated_logs_count' => $evaluatedLogsCount,
        'evaluated_logs_ratio' => $evaluatedLogsCount . ' / ' . $totalInteractions,
        'average_precision_score' => $precision,
        'has_evaluation_data' => $precision >= 0,
    ];
}

function getAdminRecentRecommendationLogs(int $limit = 20): array {
    $safeLimit = max(1, min(200, $limit));
    return dbFetchAll(
        "SELECT l.log_id,
                l.user_id,
                l.input_ingredients,
                l.recommended_recipes,
                l.created_at,
                u.username
         FROM recommendation_logs l
         LEFT JOIN users u ON l.user_id = u.user_id
         ORDER BY l.log_id DESC
         LIMIT ?",
        'i',
        [$safeLimit]
    );
}

function getAdminRecipesList(int $limit = 200): array {
    $hasApiStatus = adminHasColumn('recipes', 'api_status');

    $sql = $hasApiStatus
        ? "SELECT recipe_id, recipe_name, source_api, cuisine_type, diet_label, meal_type, cooking_time, calories, api_status FROM recipes ORDER BY recipe_id DESC LIMIT ?"
        : "SELECT recipe_id, recipe_name, source_api, cuisine_type, diet_label, meal_type, cooking_time, calories FROM recipes ORDER BY recipe_id DESC LIMIT ?";

    return dbFetchAll($sql, 'i', [max(1, min(200, $limit))]);
}

function getAdminUsersList(int $limit = 200): array {
    return dbFetchAll(
        "SELECT u.user_id,
                u.username,
                u.email,
                u.created_at,
                COUNT(uf.feedback_id) AS rated_recipes_count
         FROM users u
         LEFT JOIN user_feedback uf ON uf.user_id = u.user_id
         GROUP BY u.user_id, u.username, u.email, u.created_at
         ORDER BY u.user_id DESC
         LIMIT ?",
        'i',
        [max(1, min(200, $limit))]
    );
}

function getAdminUsersStats(): array {
    global $conn;

    $totalUsers = 0;
    $totalRatings = 0;

    $usersResult = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($usersResult) {
        $row = $usersResult->fetch_assoc();
        $totalUsers = (int)($row['total'] ?? 0);
    }

    if (adminHasTable('user_feedback')) {
        $ratingsResult = dbFetchOne("SELECT COUNT(*) AS total FROM user_feedback");
        $totalRatings = (int)($ratingsResult['total'] ?? 0);
    }

    return [
        'total_users' => $totalUsers,
        'total_ratings' => $totalRatings,
    ];
}

function adminDeleteUsersByIds(array $userIds): array {
    global $conn;

    $safeUserIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function ($id) {
        return $id > 0;
    })));

    if (empty($safeUserIds)) {
        return ['ok' => false, 'message' => 'No users selected for deletion.'];
    }

    $idListSql = implode(',', $safeUserIds);

    try {
        $conn->begin_transaction();

        if (adminHasTable('recommendation_logs')) {
            $logIds = [];
            $logResult = dbFetchAll(
                "SELECT log_id
                 FROM recommendation_logs
                 WHERE user_id IN ({$idListSql})"
            );

            if ($logResult) {
                foreach ($logResult as $row) {
                    $logId = (int)($row['log_id'] ?? 0);
                    if ($logId > 0) {
                        $logIds[] = $logId;
                    }
                }
            }

            if (!empty($logIds) && adminHasTable('recommendation_results')) {
                $logIdListSql = implode(',', $logIds);
                dbExecute("DELETE FROM recommendation_results WHERE log_id IN ({$logIdListSql})");
            }

            $conn->query("DELETE FROM recommendation_logs WHERE user_id IN ({$idListSql})");
        }

        if (adminHasTable('recommendation_relevance')) {
            dbExecute("DELETE FROM recommendation_relevance WHERE user_id IN ({$idListSql})");
        }

        if (adminHasTable('user_feedback')) {
            dbExecute("DELETE FROM user_feedback WHERE user_id IN ({$idListSql})");
        }

        if (adminHasTable('bookmarks')) {
            dbExecute("DELETE FROM bookmarks WHERE user_id IN ({$idListSql})");
        }

        if (adminHasTable('user_preferences')) {
            dbExecute("DELETE FROM user_preferences WHERE user_id IN ({$idListSql})");
        }

        $conn->query("DELETE FROM users WHERE user_id IN ({$idListSql})");
        $deletedUsers = (int)$conn->affected_rows;

        $conn->commit();

        if ($deletedUsers <= 0) {
            return ['ok' => false, 'message' => 'No users were deleted. They may have already been removed.'];
        }

        return [
            'ok' => true,
            'message' => "Deleted {$deletedUsers} user(s). Related logs and feedback were also removed.",
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'Failed to delete selected users: ' . $e->getMessage()];
    }
}

function getAdminIngredientSubstitutionStats(): array {
    global $conn;

    $totalPairs = 0;
    $ingredientCoverage = 0;

    $result = $conn->query("SELECT COUNT(*) AS total FROM ingredient_substitutions");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalPairs = (int)($row['total'] ?? 0);
    }

    $result = $conn->query(
        "SELECT COUNT(DISTINCT ingredient_id) AS total
         FROM ingredient_substitutions"
    );
    if ($result) {
        $row = $result->fetch_assoc();
        $ingredientCoverage = (int)($row['total'] ?? 0);
    }

    return [
        'total_pairs' => $totalPairs,
        'ingredients_with_substitutions' => $ingredientCoverage,
    ];
}

function getAdminIngredientSubstitutionsList(int $limit = 300): array {
    global $conn;

    $safeLimit = max(1, min(1000, $limit));
    $stmt = $conn->prepare(
        "SELECT s.substitution_id,
                s.similarity_score,
                i.ingredient_name AS ingredient_name,
                si.ingredient_name AS substitute_name
         FROM ingredient_substitutions s
         JOIN ingredients i ON s.ingredient_id = i.ingredient_id
         JOIN ingredients si ON s.substitute_ingredient_id = si.ingredient_id
         ORDER BY s.substitution_id DESC
         LIMIT ?"
    );
    $stmt->bind_param('i', $safeLimit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchIngredientSubstitutionsBackfill(int $limit = 30, string $ingredient = ''): array {
    global $conn;

    require_once __DIR__ . '/../api/spoonacular.php';

    $safeLimit = max(1, min(300, $limit));
    $safeIngredient = strtolower(trim($ingredient));
    $targets = [];

    if ($safeIngredient !== '') {
        $targets[] = $safeIngredient;
    } else {
        $stmt = $conn->prepare(
            "SELECT i.ingredient_name
             FROM ingredients i
             JOIN recipe_ingredients ri ON ri.ingredient_id = i.ingredient_id
             LEFT JOIN ingredient_substitutions s ON s.ingredient_id = i.ingredient_id
             WHERE LENGTH(TRIM(i.ingredient_name)) BETWEEN 2 AND 60
             AND i.ingredient_name NOT REGEXP '[0-9=+/]'
             GROUP BY i.ingredient_id, i.ingredient_name
             HAVING COUNT(s.substitution_id) = 0
             ORDER BY RAND()
             LIMIT ?"
        );
        $stmt->bind_param('i', $safeLimit);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $name = strtolower(trim((string)($row['ingredient_name'] ?? '')));
            if ($name !== '') {
                $targets[] = $name;
            }
        }

        // If no fresh zero-substitute ingredients remain, fill from least-populated substitutes to find more new data
        $remaining = $safeLimit - count($targets);
        if ($remaining > 0) {
            $existingNames = array_map('strtolower', $targets);
            $stmt2 = $conn->prepare(
                "SELECT i.ingredient_name
                 FROM ingredients i
                 JOIN recipe_ingredients ri ON ri.ingredient_id = i.ingredient_id
                 LEFT JOIN ingredient_substitutions s ON s.ingredient_id = i.ingredient_id
                 WHERE LENGTH(TRIM(i.ingredient_name)) BETWEEN 2 AND 60
                   AND i.ingredient_name NOT REGEXP '[0-9=+/]'
                 GROUP BY i.ingredient_id, i.ingredient_name
                 HAVING COUNT(s.substitution_id) > 0
                 ORDER BY COUNT(s.substitution_id) ASC, RAND()
                 LIMIT ?"
            );
            $stmt2->bind_param('i', $remaining);
            $stmt2->execute();

            $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rows2 as $row) {
                $name = strtolower(trim((string)($row['ingredient_name'] ?? '')));
                if ($name !== '' && !in_array($name, $existingNames, true)) {
                    $targets[] = $name;
                    $existingNames[] = $name;
                }
            }
        }
    }

    if (empty($targets)) {
        return ['ok' => true, 'message' => 'No ingredients to process for substitutions.'];
    }

    $processed = 0;
    $withResults = 0;
    $fetchedSubstitutes = 0;
    $failedIngredients = 0;
    $firstError = '';
    $apiSubstitutesTotal = 0;
    $skippedExistingTotal = 0;

    foreach ($targets as $name) {
        $processed++;
        $result = fetchIngredientSubstitutions($name);

        if (!(bool)($result['ok'] ?? false)) {
            $failedIngredients++;
            if ($firstError === '') {
                $firstError = (string)($result['error'] ?? 'Unknown error');
            }
            continue;
        }

        $count = (int)($result['inserted_count'] ?? 0);
        $apiSubstitutesTotal += (int)($result['api_substitutes_count'] ?? 0);
        $skippedExistingTotal += (int)($result['skipped_existing_count'] ?? 0);
        if ($count > 0) {
            $withResults++;
            $fetchedSubstitutes += $count;
        }
    }

    $message = "Substitution fetch complete. Processed: {$processed}, With inserts: {$withResults}, New substitutions added: {$fetchedSubstitutes}, API candidates: {$apiSubstitutesTotal}, Already existing: {$skippedExistingTotal}";
    if ($failedIngredients > 0) {
        $message .= ". Failed ingredients: {$failedIngredients}. First error: {$firstError}";
    }

    if ($fetchedSubstitutes <= 0) {
        if ($failedIngredients > 0) {
            $message .= '. No substitutions were added because one or more API calls failed (e.g., quota limit or invalid ingredient).';
        } else {
            $message .= '. No new substitutions were added. This usually means the API returned no substitutes for the selected ingredients.';
        }
    }

    $ok = $fetchedSubstitutes > 0;

    return [
        'ok' => $ok,
        'message' => $message,
    ];
}

function runRepairIncompleteRecipesScript(): array {
    $scriptPath = __DIR__ . '/../data_pipeline/repair_incomplete_recipes.php';
    if (!is_file($scriptPath)) {
        return ['ok' => false, 'message' => 'Repair script not found.'];
    }

    require_once $scriptPath;

    if (!function_exists('repairIncompleteRecipes')) {
        return ['ok' => false, 'message' => 'Repair function is unavailable.'];
    }

    $result = repairIncompleteRecipes($conn);
    if (!is_array($result)) {
        return ['ok' => false, 'message' => 'Repair script returned an invalid response.'];
    }

    return [
        'ok' => true,
        'message' => 'Repair complete. Updated: ' . (int)($result['updated'] ?? 0)
            . ', Remaining incomplete: ' . (int)($result['remaining_incomplete'] ?? 0),
    ];
}

function rebuildAllTfidfVectors(): array {
    try {
        clearVocabularyCache();
        buildRecipeTFIDFVectors();
        return ['ok' => true, 'message' => 'TF-IDF vectors rebuilt successfully.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Failed to rebuild TF-IDF vectors: ' . $e->getMessage()];
    }
}

function adminFetchJson(string $url): ?array {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function adminHasColumn(string $table, string $column): bool {
    $stmt = dbPrepareAndExecute(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1",
        'ss',
        [$table, $column]
    );

    return $stmt ? (bool)$stmt->get_result()->fetch_assoc() : false;
}

function adminHasTable(string $table): bool {
    $stmt = dbPrepareAndExecute(
        "SELECT 1
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         LIMIT 1",
        's',
        [$table]
    );

    return $stmt ? (bool)$stmt->get_result()->fetch_assoc() : false;
}

function adminEnsureApiStatusColumn(mysqli $conn): void {
    if (!adminHasColumn('recipes', 'api_status')) {
        dbExecute("ALTER TABLE recipes ADD COLUMN api_status VARCHAR(20) DEFAULT 'complete'");
    }
}

function adminContainsAny(string $haystack, array $terms): bool {
    foreach ($terms as $term) {
        if (stripos($haystack, $term) !== false) {
            return true;
        }
    }
    return false;
}

function adminInferDietLabel(string $ingredientsText): string {
    $ingredients = strtolower($ingredientsText);
    $animalTerms = ['chicken', 'beef', 'pork', 'fish', 'egg', 'milk', 'cheese', 'butter', 'shrimp', 'lamb'];
    return adminContainsAny($ingredients, $animalTerms) ? 'general' : 'vegan';
}

function adminInferMealType(array $meal): string {
    $category = strtolower(trim((string)($meal['strCategory'] ?? '')));
    if ($category === 'breakfast') {
        return 'breakfast';
    }
    if (in_array($category, ['starter', 'side', 'salad', 'soup'], true)) {
        return 'lunch';
    }
    if (in_array($category, ['dessert', 'snack'], true)) {
        return 'snack';
    }
    return 'dinner';
}

function adminParseIngredientList(array $meal): array {
    $parts = [];
    for ($index = 1; $index <= 20; $index++) {
        $ingredient = trim((string)($meal["strIngredient{$index}"] ?? ''));
        $measure = trim((string)($meal["strMeasure{$index}"] ?? ''));
        if ($ingredient === '') {
            continue;
        }
        $parts[] = $measure === '' ? $ingredient : ($measure . ' ' . $ingredient);
    }
    return $parts;
}

function adminEstimateInstructionSteps(string $instructions): int {
    $clean = trim($instructions);
    if ($clean === '') {
        return 1;
    }

    $lines = preg_split('/\r\n|\r|\n/', $clean);
    $lineSteps = 0;
    foreach ($lines as $line) {
        if (preg_match('/^\s*(step\s*\d+|\d+[.)-])\s+/i', $line)) {
            $lineSteps++;
        }
    }

    if ($lineSteps > 0) {
        return $lineSteps;
    }

    $sentences = preg_split('/[.!?]+/', $clean);
    $sentenceSteps = 0;
    foreach ($sentences as $sentence) {
        if (trim($sentence) !== '') {
            $sentenceSteps++;
        }
    }

    return max(1, $sentenceSteps);
}

function adminInferCookingTime(string $instructions, int $ingredientCount): int {
    $steps = adminEstimateInstructionSteps($instructions);
    $estimated = 15 + ($steps * 5);

    if ($ingredientCount >= 6 && $ingredientCount <= 10) {
        $estimated += 5;
    } elseif ($ingredientCount >= 11 && $ingredientCount <= 15) {
        $estimated += 10;
    } elseif ($ingredientCount > 15) {
        $estimated += 15;
    }

    $instructionsLower = strtolower($instructions);
    $keywordAdjustments = [
        'bake' => 20,
        'roast' => 15,
        'simmer' => 15,
        'marinate' => 20,
        'slow cook' => 30,
        'pressure cook' => 10,
    ];

    foreach ($keywordAdjustments as $keyword => $minutes) {
        if (strpos($instructionsLower, $keyword) !== false) {
            $estimated += $minutes;
        }
    }

    return max(15, min(120, (int)$estimated));
}

function adminEstimateCalories(string $mealType, int $ingredientCount, string $ingredientsText, string $instructions): int {
    $mealType = strtolower(trim($mealType));
    $base = match ($mealType) {
        'breakfast' => 380,
        'snack' => 320,
        'lunch' => 520,
        default => 620,
    };

    $estimated = $base + ($ingredientCount * 18);
    $text = strtolower($ingredientsText . ' ' . $instructions);

    $plusTerms = [
        'fried' => 120,
        'butter' => 80,
        'cream' => 90,
        'cheese' => 70,
        'bacon' => 120,
        'coconut milk' => 90,
    ];
    foreach ($plusTerms as $term => $value) {
        if (strpos($text, $term) !== false) {
            $estimated += $value;
        }
    }

    $minusTerms = [
        'salad' => 80,
        'soup' => 60,
        'steamed' => 50,
        'grilled' => 30,
        'baked' => 20,
    ];
    foreach ($minusTerms as $term => $value) {
        if (strpos($text, $term) !== false) {
            $estimated -= $value;
        }
    }

    return max(180, min(1200, (int)$estimated));
}

function adminFetchNutritionForMeal(array $ingredientParts): ?array {
    if (empty($ingredientParts)) {
        return null;
    }

    $nutrition = fetchNutritionSpoonacular(0, $ingredientParts);
    if (!is_array($nutrition)) {
        return null;
    }

    $calories = (int)($nutrition['calories'] ?? 0);
    $summaryRaw = (string)($nutrition['summary'] ?? '');
    $summary = json_decode($summaryRaw, true);

    if ($calories <= 0 || !is_array($summary)) {
        return null;
    }

    if (!array_key_exists('Protein', $summary) || !array_key_exists('Fat', $summary) || !array_key_exists('Carbs', $summary)) {
        return null;
    }

    return [
        'calories' => $calories,
        'summary' => json_encode([
            'Protein' => (string)$summary['Protein'],
            'Fat' => (string)$summary['Fat'],
            'Carbs' => (string)$summary['Carbs'],
        ]),
    ];
}

function adminEstimatedNutritionSummary(int $calories): string {
    $protein = round(($calories * 0.25) / 4, 1);
    $fat = round(($calories * 0.30) / 9, 1);
    $carbs = round(($calories * 0.45) / 4, 1);

    return json_encode([
        'Protein' => (string)$protein,
        'Fat' => (string)$fat,
        'Carbs' => (string)$carbs,
    ]);
}

function adminNormalizeMeal(array $meal, string &$errorMessage = ''): ?array {
    $ingredientParts = adminParseIngredientList($meal);
    $ingredientList = empty($ingredientParts) ? 'not specified' : implode(', ', $ingredientParts);

    $instructionsRaw = trim((string)($meal['strInstructions'] ?? ''));
    $instructions = $instructionsRaw === '' ? 'No instructions provided.' : $instructionsRaw;

    $rawCuisine = trim((string)($meal['strArea'] ?? ''));
    $cuisineType = $rawCuisine === '' ? 'Unknown' : $rawCuisine;

    $recipeNameRaw = trim((string)($meal['strMeal'] ?? ''));
    $recipeName = $recipeNameRaw === '' ? 'Untitled Recipe' : $recipeNameRaw;
    $mealType = adminInferMealType($meal);
    $ingredientCount = count($ingredientParts);
    $cookingTime = adminInferCookingTime($instructions, $ingredientCount);

    $nutrition = adminFetchNutritionForMeal($ingredientParts);
    if ($nutrition === null) {
        $estimatedCalories = adminEstimateCalories($mealType, $ingredientCount, $ingredientList, $instructions);
        $nutrition = [
            'calories' => $estimatedCalories,
            'summary' => adminEstimatedNutritionSummary($estimatedCalories),
        ];
    }

    $isIncomplete = (empty($ingredientParts) || $instructionsRaw === '' || $rawCuisine === '');

    return [
        'recipe_name' => $recipeName,
        'ingredient_list' => $ingredientList,
        'cuisine_type' => $cuisineType,
        'diet_label' => adminInferDietLabel($ingredientList),
        'meal_type' => $mealType,
        'cooking_time' => $cookingTime,
        'calories' => (int)$nutrition['calories'],
        'nutritional_summary' => (string)$nutrition['summary'],
        'image_url' => trim((string)($meal['strMealThumb'] ?? '')),
        'instructions' => $instructions,
        'source_api' => 'TheMealDB',
        'external_id' => trim((string)($meal['idMeal'] ?? '')),
        'api_status' => $isIncomplete ? 'incomplete' : 'complete',
    ];
}

function adminUpsertApiRecipe(mysqli $conn, array $recipe): string {
    $hasExternalId = adminHasColumn('recipes', 'external_id');

    if ($hasExternalId) {
        $stmt = $conn->prepare(
            "INSERT INTO recipes
             (recipe_name, ingredient_list, cuisine_type, diet_label, meal_type, cooking_time, calories,
              nutritional_summary, image_url, instructions, source_api, external_id, api_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               ingredient_list = VALUES(ingredient_list),
               cuisine_type = VALUES(cuisine_type),
               diet_label = VALUES(diet_label),
               meal_type = VALUES(meal_type),
               cooking_time = VALUES(cooking_time),
               calories = VALUES(calories),
               nutritional_summary = VALUES(nutritional_summary),
               image_url = VALUES(image_url),
               instructions = VALUES(instructions),
               source_api = VALUES(source_api),
               external_id = VALUES(external_id),
               api_status = VALUES(api_status)"
        );

        if (!$stmt) {
            return 'failed';
        }

        $stmt->bind_param(
            'sssssiissssss',
            $recipe['recipe_name'],
            $recipe['ingredient_list'],
            $recipe['cuisine_type'],
            $recipe['diet_label'],
            $recipe['meal_type'],
            $recipe['cooking_time'],
            $recipe['calories'],
            $recipe['nutritional_summary'],
            $recipe['image_url'],
            $recipe['instructions'],
            $recipe['source_api'],
            $recipe['external_id'],
            $recipe['api_status']
        );

        if (!$stmt->execute()) {
            return 'failed';
        }

        if ($stmt->affected_rows === 1) {
            return 'inserted';
        }
        if ($stmt->affected_rows === 2) {
            return 'updated';
        }
        return 'unchanged';
    }

    $stmt = $conn->prepare(
        "INSERT INTO recipes
         (recipe_name, ingredient_list, cuisine_type, diet_label, meal_type, cooking_time, calories,
          nutritional_summary, image_url, instructions, source_api, api_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           ingredient_list = VALUES(ingredient_list),
           cuisine_type = VALUES(cuisine_type),
           diet_label = VALUES(diet_label),
           meal_type = VALUES(meal_type),
           cooking_time = VALUES(cooking_time),
           calories = VALUES(calories),
           nutritional_summary = VALUES(nutritional_summary),
           image_url = VALUES(image_url),
           instructions = VALUES(instructions),
           source_api = VALUES(source_api),
           api_status = VALUES(api_status)"
    );

    if (!$stmt) {
        return 'failed';
    }

    $stmt->bind_param(
        'sssssiisssss',
        $recipe['recipe_name'],
        $recipe['ingredient_list'],
        $recipe['cuisine_type'],
        $recipe['diet_label'],
        $recipe['meal_type'],
        $recipe['cooking_time'],
        $recipe['calories'],
        $recipe['nutritional_summary'],
        $recipe['image_url'],
        $recipe['instructions'],
        $recipe['source_api'],
        $recipe['api_status']
    );

    if (!$stmt->execute()) {
        return 'failed';
    }

    if ($stmt->affected_rows === 1) {
        return 'inserted';
    }
    if ($stmt->affected_rows === 2) {
        return 'updated';
    }
    return 'unchanged';
}

function adminRecipeExists(mysqli $conn, string $recipeName, string $externalId = ''): bool {
    $hasExternalId = adminHasColumn('recipes', 'external_id');

    if ($hasExternalId && $externalId !== '') {
        $stmt = $conn->prepare("SELECT 1 FROM recipes WHERE external_id = ? OR recipe_name = ? LIMIT 1");
        $stmt->bind_param('ss', $externalId, $recipeName);
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM recipes WHERE recipe_name = ? LIMIT 1");
        $stmt->bind_param('s', $recipeName);
    }

    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function fetchMoreRecipesFromApi(int $limit = 20, string $query = ''): array {
    global $conn;

    $safeLimit = max(1, min(200, $limit));
    $safeQuery = trim($query);

    adminEnsureApiStatusColumn($conn);

    $meals = [];
    $seen = [];

    if ($safeQuery !== '') {
        $data = adminFetchJson('https://www.themealdb.com/api/json/v1/1/search.php?s=' . urlencode($safeQuery));
        $fetched = $data['meals'] ?? [];
        foreach ($fetched as $meal) {
            $id = (string)($meal['idMeal'] ?? '');
            if ($id !== '' && !isset($seen[$id])) {
                $name = trim((string)($meal['strMeal'] ?? ''));
                if (!adminRecipeExists($conn, $name, $id)) {
                    $meals[] = $meal;
                }
                $seen[$id] = true;
            }
            if (count($meals) >= $safeLimit) {
                break;
            }
        }
    } else {
        $maxAttempts = max(30, $safeLimit * 12);
        $attempts = 0;

        while (count($meals) < $safeLimit && $attempts < $maxAttempts) {
            $attempts++;
            $data = adminFetchJson('https://www.themealdb.com/api/json/v1/1/random.php');
            $meal = $data['meals'][0] ?? null;
            if (!$meal) {
                continue;
            }

            $id = (string)($meal['idMeal'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $name = trim((string)($meal['strMeal'] ?? ''));
            if (adminRecipeExists($conn, $name, $id)) {
                continue;
            }

            $meals[] = $meal;
        }
    }

    if (empty($meals)) {
        return ['ok' => false, 'message' => 'No new recipes found from TheMealDB for this request. Try a different keyword.'];
    }

    $normalizedMeals = [];
    foreach ($meals as $meal) {
        $normalizeError = '';
        $normalized = adminNormalizeMeal($meal, $normalizeError);
        if ($normalized === null) {
            return [
                'ok' => false,
                'message' => $normalizeError !== ''
                    ? $normalizeError
                    : 'Nutritional Api is unnavailable',
            ];
        }
        $normalizedMeals[] = $normalized;
    }

    $inserted = 0;
    $updated = 0;
    $unchanged = 0;
    $failed = 0;

    foreach ($normalizedMeals as $normalized) {
        $result = adminUpsertApiRecipe($conn, $normalized);
        if ($result === 'inserted') {
            $inserted++;
        } elseif ($result === 'updated') {
            $updated++;
        } elseif ($result === 'unchanged') {
            $unchanged++;
        } else {
            $failed++;
        }
    }

    return [
        'ok' => true,
        'message' => "Fetch complete. New: {$inserted}, Updated: {$updated}, Unchanged: {$unchanged}, Failed: {$failed}",
    ];
}

function adminCreateRecipe(array $input): array {
    global $conn;

    $recipeName = trim((string)($input['recipe_name'] ?? ''));
    $ingredientList = trim((string)($input['ingredient_list'] ?? ''));
    $instructions = trim((string)($input['instructions'] ?? ''));

    if ($recipeName === '' || $ingredientList === '' || $instructions === '') {
        return ['ok' => false, 'message' => 'Recipe name, ingredients, and instructions are required.'];
    }

    $cuisineType = trim((string)($input['cuisine_type'] ?? ''));
    $dietLabel = trim((string)($input['diet_label'] ?? ''));
    $mealType = trim((string)($input['meal_type'] ?? ''));
    $imageUrl = trim((string)($input['image_url'] ?? ''));
    $sourceApi = trim((string)($input['source_api'] ?? ''));

    $cookingTime = (int)($input['cooking_time'] ?? 0);
    $calories = (int)($input['calories'] ?? 0);

    if ($cookingTime <= 0) {
        $cookingTime = 30;
    }
    if ($calories <= 0) {
        $calories = 500;
    }

    if ($cuisineType === '') {
        $cuisineType = 'Unknown';
    }
    if ($dietLabel === '') {
        $dietLabel = 'general';
    }
    if ($mealType === '') {
        $mealType = 'dinner';
    }
    if ($sourceApi === '') {
        $sourceApi = 'Admin';
    }

    $protein = trim((string)($input['protein'] ?? '0'));
    $fat = trim((string)($input['fat'] ?? '0'));
    $carbs = trim((string)($input['carbs'] ?? '0'));

    $nutritionalSummary = json_encode([
        'Protein' => $protein === '' ? 0 : $protein,
        'Fat' => $fat === '' ? 0 : $fat,
        'Carbs' => $carbs === '' ? 0 : $carbs,
    ]);

    $hasApiStatus = adminHasColumn('recipes', 'api_status');

    if ($hasApiStatus) {
        $stmt = $conn->prepare(
            "INSERT INTO recipes
             (recipe_name, ingredient_list, cuisine_type, diet_label, meal_type, cooking_time, calories,
              nutritional_summary, image_url, instructions, source_api, api_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'complete')"
        );

        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not prepare recipe insert statement.'];
        }

        $stmt->bind_param(
            'sssssiissss',
            $recipeName,
            $ingredientList,
            $cuisineType,
            $dietLabel,
            $mealType,
            $cookingTime,
            $calories,
            $nutritionalSummary,
            $imageUrl,
            $instructions,
            $sourceApi
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO recipes
             (recipe_name, ingredient_list, cuisine_type, diet_label, meal_type, cooking_time, calories,
              nutritional_summary, image_url, instructions, source_api)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not prepare recipe insert statement.'];
        }

        $stmt->bind_param(
            'sssssiissss',
            $recipeName,
            $ingredientList,
            $cuisineType,
            $dietLabel,
            $mealType,
            $cookingTime,
            $calories,
            $nutritionalSummary,
            $imageUrl,
            $instructions,
            $sourceApi
        );
    }

    if (!$stmt->execute()) {
        return ['ok' => false, 'message' => 'Could not save recipe. Please check if the recipe name already exists.'];
    }

    return ['ok' => true, 'message' => 'Recipe added successfully.'];
}
