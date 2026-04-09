<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/api_keys.php';

/* =========================================================
   CONSTANTS
   ========================================================= */

/**
 * Spoonacular API endpoint for ingredient parsing with nutrition
 */
const SPOONACULAR_NUTRITION_ENDPOINT = "https://api.spoonacular.com/recipes/parseIngredients";

/**
 * Timeout for Spoonacular API requests (seconds)
 */
const SPOONACULAR_NUTRITION_TIMEOUT = 2;

/**
 * Connection timeout for Spoonacular API requests (seconds)
 */
const SPOONACULAR_NUTRITION_CONNECTION_TIMEOUT = 1;

/* =========================================================
   VALIDATION FUNCTIONS
   ========================================================= */

/**
 * Validates recipe ID for nutrition fetching
 * @param int $recipeId The recipe ID to validate
 * @return bool True if valid
 */
function validateRecipeIdForSpoonacularNutrition($recipeId) {
    // recipeId is contextual metadata only and can be 0 for API-fetched meals.
    return is_numeric($recipeId) && $recipeId >= 0;
}

/**
 * Validates ingredients array for Spoonacular nutrition analysis
 * @param array $ingredients List of ingredient strings
 * @return bool True if valid
 */
function validateIngredientsForSpoonacularNutrition($ingredients) {
    return is_array($ingredients) && !empty($ingredients) &&
           count($ingredients) <= 50; // Reasonable limit
}

/* =========================================================
   API FUNCTIONS
   ========================================================= */

/**
 * Fetches nutrition data from Spoonacular API as backup
 * @param int $recipeId Recipe ID (for context, not used in API call)
 * @param array $ingredients List of ingredient strings
 * @return array|null Nutrition data or null on failure
 */
function fetchNutritionSpoonacular($recipeId, $ingredients) {
    // Validate inputs
    if (!validateRecipeIdForSpoonacularNutrition($recipeId)) {
        return null;
    }

    if (!validateIngredientsForSpoonacularNutrition($ingredients)) {
        return null;
    }

    // Prepare API request
    $params = [
        "apiKey" => SPOONACULAR_API_KEY,
        "servings" => 1,
        "includeNutrition" => "true"
    ];

    $ingredientList = implode("\n", $ingredients);

    // Initialize cURL
    $ch = curl_init(SPOONACULAR_NUTRITION_ENDPOINT . "?" . http_build_query($params));
    if (!$ch) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['ingredientList' => $ingredientList]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => SPOONACULAR_NUTRITION_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => SPOONACULAR_NUTRITION_CONNECTION_TIMEOUT
    ]);

    // Execute request
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Handle errors
    if ($error) {
        return null;
    }

    if ($status !== 200) {
        return null;
    }

    // Parse response
    $data = json_decode($response, true);
    if (empty($data)) {
        return null;
    }

    // Aggregate nutrition data
    $calories = 0;
    $protein = 0;
    $fat = 0;
    $carbs = 0;

    foreach ($data as $item) {
        // Skip items without nutrition data
        if (!isset($item['nutrition']) || !isset($item['nutrition']['nutrients'])) {
            continue;
        }

        foreach ($item['nutrition']['nutrients'] as $nutrient) {
            switch ($nutrient['name']) {
                case 'Calories':
                    $calories += $nutrient['amount'];
                    break;
                case 'Protein':
                    $protein += $nutrient['amount'];
                    break;
                case 'Fat':
                    $fat += $nutrient['amount'];
                    break;
                case 'Carbohydrates':
                    $carbs += $nutrient['amount'];
                    break;
            }
        }
    }

    // Build nutrition summary
    $summary = [
        "Protein" => round($protein, 1),
        "Fat"     => round($fat, 1),
        "Carbs"   => round($carbs, 1)
    ];

    return [
        "calories" => round($calories),
        "summary"  => json_encode($summary),
        "source"   => "Spoonacular"
    ];
}
