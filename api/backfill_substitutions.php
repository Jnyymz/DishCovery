<?php
require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/spoonacular.php';
require_once __DIR__ . '/../algo/preprocess.php';

/* =========================================================
   CONSTANTS
   ========================================================= */

/**
 * Default limit for ingredients to process
 */
const DEFAULT_BACKFILL_LIMIT = 30;

/**
 * Minimum limit for ingredients to process
 */
const MIN_BACKFILL_LIMIT = 1;

/**
 * Maximum limit for ingredients to process
 */
const MAX_BACKFILL_LIMIT = 1000;

/* =========================================================
   VALIDATION FUNCTIONS
   ========================================================= */

/**
 * Validates limit parameter for backfill operation
 * @param mixed $limit The limit value to validate
 * @return int Validated limit
 */
function validateBackfillLimit($limit) {
    if (!is_numeric($limit)) {
        return DEFAULT_BACKFILL_LIMIT;
    }

    $limit = (int)$limit;
    return max(MIN_BACKFILL_LIMIT, min(MAX_BACKFILL_LIMIT, $limit));
}

/**
 * Validates ingredient name for backfill operation
 * @param mixed $ingredient The ingredient name to validate
 * @return string Validated ingredient name (lowercased and trimmed)
 */
function validateBackfillIngredient($ingredient) {
    if (!is_string($ingredient)) {
        return '';
    }

    // Reuse the main normalization pipeline so API requests do not include
    // quantities/units/noisy tokens (e.g., "1 tbsp sugar").
    return normalizeIngredient($ingredient);
}

/* =========================================================
   INPUT PARSING FUNCTIONS
   ========================================================= */

/**
 * Parses input parameters for backfill substitution operation
 * @return array ['limit' => int, 'ingredient' => string]
 */
function parseBackfillSubstitutionInput() {
    $limit = DEFAULT_BACKFILL_LIMIT;
    $ingredient = '';

    if (PHP_SAPI === 'cli') {
        $options = getopt('', ['limit::', 'ingredient::']);
        if (isset($options['limit'])) {
            $limit = validateBackfillLimit($options['limit']);
        }
        if (isset($options['ingredient'])) {
            $ingredient = validateBackfillIngredient($options['ingredient']);
        }
    } else {
        $limit = validateBackfillLimit($_GET['limit'] ?? DEFAULT_BACKFILL_LIMIT);
        $ingredient = validateBackfillIngredient($_GET['ingredient'] ?? '');
    }

    return ['limit' => $limit, 'ingredient' => $ingredient];
}

/* =========================================================
   INGREDIENT TARGET FUNCTIONS
   ========================================================= */

/**
 * Gets target ingredients for substitution backfill
 * @param string $specificIngredient Specific ingredient to process (empty for auto-selection)
 * @param int $limit Maximum number of ingredients to select
 * @return array List of ingredient names to process
 */
function getBackfillTargets($specificIngredient, $limit) {
    global $conn;

    $targets = [];

    if (!empty($specificIngredient)) {
        $targets[] = $specificIngredient;
    } else {
        $stmt = $conn->prepare(
            "SELECT i.ingredient_name
             FROM ingredients i
             LEFT JOIN ingredient_substitutions s ON s.ingredient_id = i.ingredient_id
             GROUP BY i.ingredient_id, i.ingredient_name
             HAVING COUNT(s.substitution_id) = 0
             ORDER BY i.ingredient_id ASC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $name = validateBackfillIngredient($row['ingredient_name'] ?? '');
            if (!empty($name)) {
                $targets[] = $name;
            }
        }
    }

    return $targets;
}

/* =========================================================
   MAIN EXECUTION
   ========================================================= */

/**
 * Processes ingredient substitution backfill
 * @return array Backfill results summary
 */
function processSubstitutionBackfill() {
    $input = parseBackfillSubstitutionInput();
    $limit = $input['limit'];
    $ingredient = $input['ingredient'];

    $targets = getBackfillTargets($ingredient, $limit);

    if (empty($targets)) {
        return [
            'ok' => true,
            'message' => 'No ingredients to process for substitutions.',
            'processed_ingredients' => 0,
            'ingredients_with_results' => 0,
            'fetched_substitutes' => 0,
            'failed_ingredients' => 0,
            'errors' => [],
        ];
    }

    $processed = 0;
    $withResults = 0;
    $fetchedSubstitutes = 0;
    $failedIngredients = 0;
    $errors = [];

    foreach ($targets as $name) {
        $processed++;
        $result = fetchIngredientSubstitutions($name);

        if (!(bool)($result['ok'] ?? false)) {
            $failedIngredients++;
            $errors[] = [
                'ingredient' => $name,
                'error' => (string)($result['error'] ?? 'Unknown error'),
            ];
            continue;
        }

        $count = (int)($result['inserted_count'] ?? 0);
        if ($count > 0) {
            $withResults++;
            $fetchedSubstitutes += $count;
        }
    }

    $message = "Substitution fetch complete. Processed: {$processed}, With results: {$withResults}, Substitutes fetched: {$fetchedSubstitutes}";
    if ($failedIngredients > 0) {
        $firstError = (string)($errors[0]['error'] ?? 'Unknown error');
        $message .= ". Failed ingredients: {$failedIngredients}. First error: {$firstError}";
    }

    return [
        'ok' => $failedIngredients < $processed,
        'message' => $message,
        'processed_ingredients' => $processed,
        'ingredients_with_results' => $withResults,
        'fetched_substitutes' => $fetchedSubstitutes,
        'failed_ingredients' => $failedIngredients,
        'errors' => $errors,
    ];
}

// Execute the backfill process
$response = processSubstitutionBackfill();
echo json_encode($response);
