<?php
/* =========================================================
   filterProcessor.php
   Filter Processing & Preference Management
   DishCovery – Filter Coordination Layer
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/../core/auth.php';

/**
 * Process and validate filter input from POST request
 * Returns standardized preferences array
 */
function processFilterInput(array $postData) {
    $preferences = [];

    // Diet type
    if (!empty($postData['diet_type'])) {
        $preferences['diet_type'] = trim($postData['diet_type']);
    }

    // Max calories
    if (!empty($postData['max_calories'])) {
        $preferences['max_calories'] = (int)$postData['max_calories'];
    }

    // Max cooking time
    if (!empty($postData['max_cooking_time'])) {
        $preferences['max_cooking_time'] = (int)$postData['max_cooking_time'];
    }

    // Cuisine preference
    if (!empty($postData['cuisine_preference'])) {
        $preferences['cuisine_preference'] = trim($postData['cuisine_preference']);
    }

    // Meal type
    if (!empty($postData['meal_type'])) {
        $preferences['meal_type'] = trim($postData['meal_type']);
    }

    return $preferences;
}

/**
 * Get user preferences from database
 * Returns preferences array
 */
function getUserPreferencesFromDB($userId) {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT diet_type, max_calories, max_cooking_time, cuisine_preference
         FROM user_preferences WHERE user_id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        // Filter out null values
        return array_filter($result, function ($value) {
            return $value !== null;
        });
    }

    return [];
}

/**
 * Merge inline filters with user preferences
 * Inline filters take priority
 */
function mergePreferences($inlineFilters, $userId = null) {
    $merged = [];

    // Start with user preferences if user is logged in
    if ($userId) {
        $merged = getUserPreferencesFromDB($userId);
    }

    // Override with inline filters (they take priority)
    foreach ($inlineFilters as $key => $value) {
        if ($value !== null && $value !== '') {
            $merged[$key] = $value;
        }
    }

    return $merged;
}

/**
 * Store filter state in session for persistence
 */
function storeFilterState(array $preferences, $ingredientsInput = '') {
    $_SESSION['filter_state'] = $preferences;
    if (!empty($ingredientsInput)) {
        $_SESSION['ingredients_input'] = $ingredientsInput;
    }
}

/**
 * Get stored filter state from session
 */
function getStoredFilterState() {
    return $_SESSION['filter_state'] ?? [];
}

/**
 * Clear all filter state from session
 */
function clearFilterState() {
    unset(
        $_SESSION['filter_state'],
        $_SESSION['ingredients_input'],
        $_SESSION['recommendation_results'],
        $_SESSION['recommendation_fallback'],
        $_SESSION['user_ingredients'],
        $_SESSION['selected_cuisine_preference'],
        $_SESSION['selected_diet_type'],
        $_SESSION['selected_meal_type'],
        $_SESSION['selected_max_cooking_time'],
        $_SESSION['selected_max_calories']
    );
}

/**
 * Get ingredients from input string
 * Splits by comma and filters empty values
 */
function parseIngredientsInput($ingredientsInput) {
    $ingredients = array_map('trim', explode(',', $ingredientsInput));
    return array_filter($ingredients, function ($ing) {
        return !empty($ing);
    });
}
