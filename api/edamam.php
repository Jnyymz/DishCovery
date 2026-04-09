<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/api_keys.php';

/* =========================================================
   CONSTANTS
   ========================================================= */

/**
 * Edamam API endpoint for nutrition analysis
 */
const EDAMAM_NUTRITION_ENDPOINT = "https://api.edamam.com/api/nutrition-details";

/**
 * Timeout for Edamam API requests (seconds)
 */
const EDAMAM_API_TIMEOUT = 5;

/**
 * Connection timeout for Edamam API requests (seconds)
 */
const EDAMAM_CONNECTION_TIMEOUT = 3;

/* =========================================================
   VALIDATION FUNCTIONS
   ========================================================= */

/**
 * Validates recipe ID for nutrition fetching
 * @param int $recipeId The recipe ID to validate
 * @return bool True if valid
 */
function validateRecipeIdForNutrition($recipeId) {
    return is_numeric($recipeId) && $recipeId > 0;
}

/**
 * Validates ingredients array for nutrition analysis
 * @param array $ingredients List of ingredient strings
 * @return bool True if valid
 */
function validateIngredientsForNutrition($ingredients) {
    return is_array($ingredients) && !empty($ingredients) &&
           count($ingredients) <= 50; // Reasonable limit
}

/* =========================================================
   API FUNCTIONS
   ========================================================= */

/**
 * Fetches nutrition data from Edamam API
 * @param int $recipeId Recipe ID (for context, not used in API call)
 * @param array $ingredients List of ingredient strings
 * @return array|null Nutrition data or null on failure
 */
function fetchNutritionEdamam($recipeId, $ingredients) {
    // Validate inputs
    if (!validateRecipeIdForNutrition($recipeId)) {
        return null;
    }

    if (!validateIngredientsForNutrition($ingredients)) {
        return null;
    }

    // Prepare API request
    $query = http_build_query([
        "app_id"  => EDAMAM_APP_ID,
        "app_key" => EDAMAM_APP_KEY
    ]);

    $payload = [
        "title" => "DishCovery Recipe",
        "ingr"  => $ingredients
    ];

    // Initialize cURL
    $ch = curl_init(EDAMAM_NUTRITION_ENDPOINT . "?" . $query);
    if (!$ch) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => EDAMAM_API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => EDAMAM_CONNECTION_TIMEOUT
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
    if (!$data || !isset($data['calories'])) {
        return null;
    }

    // Extract nutrition summary
    $summary = [
        "Protein" => round($data['totalNutrients']['PROCNT']['quantity'] ?? 0, 1),
        "Fat"     => round($data['totalNutrients']['FAT']['quantity'] ?? 0, 1),
        "Carbs"   => round($data['totalNutrients']['CHOCDF']['quantity'] ?? 0, 1)
    ];

    return [
        "calories" => round($data['calories']),
        "summary"  => json_encode($summary),
        "source"   => "Edamam"
    ];
}
