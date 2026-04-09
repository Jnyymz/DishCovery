<?php
/* =========================================================
   similarity.php
   Cosine Similarity Computation
   DishCovery – Content-Based Filtering
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/preprocess.php';

function getMainIngredientModifierWords(): array {
    return [
        'sauce', 'powder', 'paste', 'oil', 'broth', 'stock', 'dressing',
        'extract', 'seasoning', 'marinade', 'gravy', 'bouillon'
    ];
}

function containsMainIngredientModifierWord(string $text): bool {
    $normalizedText = normalizeIngredient($text);
    if ($normalizedText === '') {
        return false;
    }

    foreach (getMainIngredientModifierWords() as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $normalizedText) === 1) {
            return true;
        }
    }

    return false;
}

function getMainIngredientMatchStrength(string $ingredientList, string $mainIngredient): string {
    $normalizedMain = normalizeIngredient($mainIngredient);
    if ($normalizedMain === '') {
        return 'none';
    }

    $isSingleWordMain = strpos($normalizedMain, ' ') === false;
    $parts = explode(',', $ingredientList);
    $escapedMain = preg_quote($normalizedMain, '/');
    $mainPattern = '/\b' . str_replace('\\ ', '\\s+', $escapedMain) . '\b/i';

    foreach ($parts as $part) {
        $normalizedPart = normalizeIngredient((string)$part);
        if ($normalizedPart === '') {
            continue;
        }

        if ($normalizedPart === $normalizedMain) {
            return 'strong';
        }

        if (preg_match($mainPattern, $normalizedPart) === 1) {
            if ($isSingleWordMain && containsMainIngredientModifierWord($normalizedPart)) {
                return 'weak';
            }
            return 'strong';
        }
    }

    return 'none';
}

/**
 * Compute dot product of two vectors
 */
function dotProduct(array $v1, array $v2) {
    $sum = 0.0;

    foreach ($v1 as $key => $value) {
        if (isset($v2[$key])) {
            $sum += $value * $v2[$key];
        }
    }

    return $sum;
}

/**
 * Compute magnitude of a vector
 */
function vectorMagnitude(array $vector) {
    $sum = 0.0;

    foreach ($vector as $value) {
        $sum += pow($value, 2);
    }

    return sqrt($sum);
}

/**
 * Compute cosine similarity between two vectors
 */
function cosineSimilarity(array $vectorA, array $vectorB) {
    $dot = dotProduct($vectorA, $vectorB);
    $magnitudeA = vectorMagnitude($vectorA);
    $magnitudeB = vectorMagnitude($vectorB);

    if ($magnitudeA == 0 || $magnitudeB == 0) {
        return 0;
    }

    return $dot / ($magnitudeA * $magnitudeB);
}

/**
 * Compare user vector with all recipe vectors
 * Returns ranked array of recipe_id => similarity_score
 */
function recipeContainsMainIngredient(string $ingredientList, string $mainIngredient): bool {
    return getMainIngredientMatchStrength($ingredientList, $mainIngredient) === 'strong';
}

function computeSimilarityScores(array $userVector, string $mainIngredient = '') {
    global $conn;

    $scores = [];

    $normalizedMainIngredient = normalizeIngredient($mainIngredient);
    $mainIngredientBonus = 0.2;

    $result = $conn->query(
        "SELECT t.recipe_id, t.ingredient_vector, r.ingredient_list
         FROM tfidf_vectors t
         JOIN recipes r ON r.recipe_id = t.recipe_id"
    );

    while ($row = $result->fetch_assoc()) {
        $recipeVector = json_decode($row['ingredient_vector'], true);

        if (!is_array($recipeVector)) {
            continue;
        }

        $similarity = cosineSimilarity($userVector, $recipeVector);

        if ($normalizedMainIngredient !== '') {
            $mainMatchStrength = getMainIngredientMatchStrength((string)($row['ingredient_list'] ?? ''), $normalizedMainIngredient);
            if ($mainMatchStrength === 'strong') {
                $similarity += $mainIngredientBonus;
            }
        }

        if ($similarity > 0) {
            $scores[$row['recipe_id']] = $similarity;
        }
    }

    // Sort descending (highest similarity first)
    arsort($scores);

    return $scores;
}
