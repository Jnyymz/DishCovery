<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/api_keys.php';

/* =========================================================
   CONSTANTS
   ========================================================= */

/**
 * Minimum fallback similarity score for ingredient substitutions
 */
const SPOONACULAR_MIN_SIMILARITY_SCORE = 0.55;

/**
 * Maximum allowed similarity score for ingredient substitutions
 */
const SPOONACULAR_MAX_SIMILARITY_SCORE = 0.98;

/**
 * HTTP timeout for API requests
 */
const SPOONACULAR_API_TIMEOUT = 12;

/* =========================================================
   INPUT VALIDATION FUNCTIONS
   ========================================================= */

/**
 * Validates ingredient name: not empty, reasonable length
 */
function spoonacularValidateIngredientName($name) {
    $name = trim($name);
    return !empty($name) && strlen($name) <= 100 && preg_match('/^[a-zA-Z\s\-]+$/', $name);
}

/* =========================================================
   UTILITY FUNCTIONS
   ========================================================= */

/**
 * Normalizes ingredient name for API queries by removing measurements and common words
 * @param string $ingredientName Raw ingredient name
 * @return string Normalized name or empty string if invalid
 */
function spoonacularNormalizeIngredientQuery(string $ingredientName): string {
    $name = strtolower(trim($ingredientName));
    if ($name === '') {
        return '';
    }

    // Remove measurements (fractions, numbers)
    $name = preg_replace('/\b\d+\s*\/\s*\d+\b|\b\d+(?:\.\d+)?\b/u', ' ', $name);

    // Remove common cooking terms
    $name = preg_replace('/\b(tbsp|tsp|tablespoon|teaspoon|cup|cups|oz|ounce|ounces|g|kg|ml|l|lb|lbs|pound|pounds|pinch|dash|clove|cloves|slice|slices|can|cans|pack|packs|bunch|handful|to taste)\b/u', ' ', $name);

    // Remove connecting words
    $name = preg_replace('/\b(of|and|or|fresh|ground|minced|chopped|diced)\b/u', ' ', $name);

    // Remove non-alphanumeric except spaces and hyphens
    $name = preg_replace('/[^a-z\s\-]/u', ' ', $name);

    // Normalize whitespace
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = trim($name);

    if ($name === '') {
        return '';
    }

    // Limit to last 3 words for better API matching
    $parts = explode(' ', $name);
    if (count($parts) > 3) {
        $parts = array_slice($parts, -3);
    }

    return trim(implode(' ', $parts));
}

/**
 * Tokenizes ingredient text for similarity calculation.
 * @param string $value
 * @return array
 */
function spoonacularSimilarityTokens(string $value): array {
    $normalized = spoonacularNormalizeIngredientQuery($value);
    if ($normalized === '') {
        $normalized = strtolower(trim($value));
    }

    $parts = preg_split('/\s+/u', $normalized);
    if (!is_array($parts)) {
        return [];
    }

    $tokens = [];
    foreach ($parts as $part) {
        $token = trim((string)$part);
        if ($token === '' || strlen($token) <= 1) {
            continue;
        }
        if (str_ends_with($token, 's') && strlen($token) > 3) {
            $token = substr($token, 0, -1);
        }
        $tokens[$token] = true;
    }

    return array_keys($tokens);
}

/**
 * Estimates similarity score between source ingredient and substitute string.
 * Spoonacular does not provide numeric confidence, so we derive one from text similarity.
 * @param string $ingredientName
 * @param string $substituteName
 * @return float
 */
function spoonacularEstimateSimilarityScore(string $ingredientName, string $substituteName): float {
    $source = strtolower(trim($ingredientName));
    $target = strtolower(trim($substituteName));

    if ($source === '' || $target === '') {
        return SPOONACULAR_MIN_SIMILARITY_SCORE;
    }

    if ($source === $target) {
        return SPOONACULAR_MAX_SIMILARITY_SCORE;
    }

    $sourceTokens = spoonacularSimilarityTokens($source);
    $targetTokens = spoonacularSimilarityTokens($target);

    $overlapScore = 0.0;
    if (!empty($sourceTokens) && !empty($targetTokens)) {
        $common = array_intersect($sourceTokens, $targetTokens);
        $unionCount = count(array_unique(array_merge($sourceTokens, $targetTokens)));
        if ($unionCount > 0) {
            $overlapScore = count($common) / $unionCount;
        }
    }

    similar_text($source, $target, $percent);
    $charScore = max(0.0, min(1.0, ((float)$percent) / 100));

    $containsBonus = 0.0;
    if (strpos($target, $source) !== false || strpos($source, $target) !== false) {
        $containsBonus = 0.10;
    }

    // Weighted blend tuned to keep scores realistic for food substitutions.
    $rawScore = (0.60 * $overlapScore) + (0.35 * $charScore) + $containsBonus;

    $bounded = max(SPOONACULAR_MIN_SIMILARITY_SCORE, min(SPOONACULAR_MAX_SIMILARITY_SCORE, $rawScore));
    return round($bounded, 2);
}

/**
 * Converts substitute rank in Spoonacular response into a confidence score.
 * Earlier entries are treated as stronger substitutes.
 * @param int $position Zero-based index
 * @param int $total Total substitutes returned
 * @return float
 */
function spoonacularRankSimilarityScore(int $position, int $total): float {
    if ($total <= 1) {
        return 0.85;
    }

    $safePosition = max(0, min($position, $total - 1));
    $ratio = 1 - ($safePosition / ($total - 1));

    $score = SPOONACULAR_MIN_SIMILARITY_SCORE + ((SPOONACULAR_MAX_SIMILARITY_SCORE - SPOONACULAR_MIN_SIMILARITY_SCORE) * $ratio);
    return round($score, 2);
}

/**
 * Ensures an ingredient exists in the database and returns its ID
 * @param string $ingredientName
 * @return int Ingredient ID or 0 on failure
 */
function spoonacularEnsureIngredientId(string $ingredientName): int {
    global $conn;

    $name = strtolower(trim($ingredientName));
    if ($name === '') {
        return 0;
    }

    $stmt = $conn->prepare("SELECT ingredient_id FROM ingredients WHERE ingredient_name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        return (int)$row['ingredient_id'];
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO ingredients (ingredient_name) VALUES (?)");
    $stmt->bind_param('s', $name);
    $stmt->execute();

    $stmt = $conn->prepare("SELECT ingredient_id FROM ingredients WHERE ingredient_name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ? (int)$row['ingredient_id'] : 0;
}

/**
 * Fetches JSON data from a URL with error handling
 * @param string $url
 * @return array Structured response with ok, status, data, error
 */
function spoonacularFetchJson(string $url): array {
    $httpCode = 0;
    $warning = '';

    $context = stream_context_create([
        'http' => [
            'timeout' => SPOONACULAR_API_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]);

    set_error_handler(function ($severity, $message) use (&$warning) {
        $warning = (string)$message;
        return true;
    });

    $raw = file_get_contents($url, false, $context);
    restore_error_handler();

    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }

    if ($raw === false) {
        $err = $warning !== '' ? $warning : 'HTTP request failed.';
        if (stripos($err, 'refused') !== false) {
            $err = 'Connection refused by Spoonacular endpoint.';
        }
        return ['ok' => false, 'status' => $httpCode, 'data' => null, 'error' => $err];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $httpCode, 'data' => null, 'error' => 'Invalid JSON response from Spoonacular.'];
    }

    if ($httpCode >= 400) {
        $message = (string)($decoded['message'] ?? $decoded['status'] ?? 'Spoonacular request failed.');
        return ['ok' => false, 'status' => $httpCode, 'data' => $decoded, 'error' => $message];
    }

    return ['ok' => true, 'status' => $httpCode, 'data' => $decoded, 'error' => ''];
}


/**
 * Fetch and store ingredient substitutions from Spoonacular API
 * @param string $ingredientName Raw ingredient name
 * @return array Standardized response with ok, ingredient, substitutes, counts, error
 */
function fetchIngredientSubstitutions($ingredientName) {
    global $conn;

    // Validate input
    if (!spoonacularValidateIngredientName($ingredientName)) {
        return [
            'ok' => false,
            'ingredient' => $ingredientName,
            'substitutes' => [],
            'inserted_count' => 0,
            'api_substitutes_count' => 0,
            'skipped_existing_count' => 0,
            'error' => 'Invalid ingredient name.',
        ];
    }

    $ingredientName = strtolower(trim($ingredientName));
    if ($ingredientName === '') {
        return [
            'ok' => false,
            'ingredient' => '',
            'substitutes' => [],
            'inserted_count' => 0,
            'api_substitutes_count' => 0,
            'skipped_existing_count' => 0,
            'error' => 'Ingredient is required.',
        ];
    }

    $ingredientId = spoonacularEnsureIngredientId($ingredientName);
    if ($ingredientId <= 0) {
        return [
            'ok' => false,
            'ingredient' => $ingredientName,
            'substitutes' => [],
            'inserted_count' => 0,
            'api_substitutes_count' => 0,
            'skipped_existing_count' => 0,
            'error' => 'Could not resolve ingredient ID.',
        ];
    }

    $apiIngredientName = spoonacularNormalizeIngredientQuery($ingredientName);
    if ($apiIngredientName === '') {
        $apiIngredientName = $ingredientName;
    }

    /* ---------- SPOONACULAR API CALL ---------- */
    $url = "https://api.spoonacular.com/food/ingredients/substitutes";
    $query = http_build_query([
        "ingredientName" => $apiIngredientName,
        "apiKey" => SPOONACULAR_API_KEY
    ]);

    // Log for debugging (consider removing in production)
    error_log("Fetching substitutes for ingredient: '$ingredientName', normalized: '$apiIngredientName'");

    $httpResult = spoonacularFetchJson("$url?$query");
    if (!$httpResult['ok']) {
        return [
            'ok' => false,
            'ingredient' => $ingredientName,
            'substitutes' => [],
            'inserted_count' => 0,
            'api_substitutes_count' => 0,
            'skipped_existing_count' => 0,
            'error' => $httpResult['error'],
        ];
    }

    $data = $httpResult['data'];

    // Check for API failure status
    if (!empty($data['status']) && $data['status'] === 'failure') {
        $apiMessage = strtolower((string)($data['message'] ?? ''));
        $isQuotaError = strpos($apiMessage, 'limit') !== false ||
                        strpos($apiMessage, 'quota') !== false ||
                        strpos($apiMessage, 'exceeded') !== false;

        return [
            'ok' => !$isQuotaError, // Allow quota errors to be retried
            'ingredient' => $ingredientName,
            'substitutes' => [],
            'inserted_count' => 0,
            'api_substitutes_count' => 0,
            'skipped_existing_count' => 0,
            'error' => $data['message'] ?? 'No substitutes found.',
        ];
    }

    if (empty($data['substitutes'])) {
        return [
            'ok' => true,
            'ingredient' => $ingredientName,
            'substitutes' => [],
            'inserted_count' => 0,
            'api_substitutes_count' => 0,
            'skipped_existing_count' => 0,
            'error' => '',
        ];
    }

    $results = [];
    $insertedCount = 0;
    $skippedExistingCount = 0;
    $apiSubstitutesCount = count((array)$data['substitutes']);

    foreach ($data['substitutes'] as $index => $sub) {
        $sub = strtolower(trim($sub));
        if ($sub === '' || $sub === $ingredientName) {
            continue;
        }

        $textScore = spoonacularEstimateSimilarityScore($ingredientName, $sub);
        $rankScore = spoonacularRankSimilarityScore((int)$index, $apiSubstitutesCount);
        $score = round(($rankScore * 0.75) + ($textScore * 0.25), 2);

        /* ---------- SAVE SUBSTITUTE INGREDIENT ---------- */
        $subId = spoonacularEnsureIngredientId($sub);
        if ($subId <= 0) {
            continue;
        }

        // Check if substitution already exists
        $check = $conn->prepare(
            "SELECT substitution_id
             FROM ingredient_substitutions
             WHERE ingredient_id = ? AND substitute_ingredient_id = ?
             LIMIT 1"
        );
        $check->bind_param('ii', $ingredientId, $subId);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        if ($existing) {
            $update = $conn->prepare(
                "UPDATE ingredient_substitutions
                 SET similarity_score = ?
                 WHERE substitution_id = ?"
            );
            if ($update) {
                $existingId = (int)$existing['substitution_id'];
                $update->bind_param('di', $score, $existingId);
                $update->execute();
            }
            $skippedExistingCount++;
            continue;
        }

        /* ---------- STORE SUBSTITUTION ---------- */
        $stmt = $conn->prepare(
            "INSERT INTO ingredient_substitutions
             (ingredient_id, substitute_ingredient_id, similarity_score)
             VALUES (?, ?, ?)"
        );

        $stmt->bind_param("iid", $ingredientId, $subId, $score);
        if (!$stmt->execute()) {
            continue;
        }

        $results[] = $sub;
        $insertedCount++;
    }

    return [
        'ok' => true,
        'ingredient' => $ingredientName,
        'substitutes' => $results,
        'inserted_count' => $insertedCount,
        'api_substitutes_count' => $apiSubstitutesCount,
        'skipped_existing_count' => $skippedExistingCount,
        'error' => '',
    ];
}
