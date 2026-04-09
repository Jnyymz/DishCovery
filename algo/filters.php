<?php
/* =========================================================
   filters.php
   Recipe Filtering Logic
   DishCovery – Information Filtering Layer
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';

/**
 * Apply user preference filters to ranked recipe list
 */
function applyFilters(array $rankedRecipes, array $preferences) {
    global $conn;

    if (empty($rankedRecipes)) {
        return [];
    }

    $recipeIds = array_keys($rankedRecipes);
    $placeholders = str_repeat('?,', count($recipeIds) - 1) . '?';
    $types = str_repeat('i', count($recipeIds));

    $stmt = $conn->prepare(
        "SELECT recipe_id, cooking_time, calories, cuisine_type, diet_label
         FROM recipes WHERE recipe_id IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$recipeIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipes = [];
    while ($row = $result->fetch_assoc()) {
        $recipes[$row['recipe_id']] = $row;
    }

    $filtered = [];

    foreach ($rankedRecipes as $recipeId => $score) {
        $recipe = $recipes[$recipeId] ?? null;

        if (!$recipe) {
            continue;
        }

        $adjustedScore = (float)$score;
        $numericPenalty = 0.0;

        // Cooking time proximity penalty (NULL values are allowed)
        if (!empty($preferences['max_cooking_time']) && $recipe['cooking_time'] !== null) {
            $targetTime = (int)$preferences['max_cooking_time'];
            $timeDiff = abs((float)$recipe['cooking_time'] - $targetTime);
            $timePenalty = $timeDiff / 100;
            $numericPenalty += min($timePenalty, 0.12);
        }

        // Calories proximity penalty (NULL values are allowed)
        if (!empty($preferences['max_calories']) && $recipe['calories'] !== null) {
            $targetCalories = (int)$preferences['max_calories'];
            $calDiff = abs((float)$recipe['calories'] - $targetCalories);
            $calPenalty = $calDiff / 1000;
            $numericPenalty += min($calPenalty, 0.12);
        }

        // Keep ingredient similarity dominant: cap total numeric impact.
        $adjustedScore -= min($numericPenalty, 0.22);

        // Cuisine filter (NULL values are allowed)
        if (!empty($preferences['cuisine_preference']) &&
            $recipe['cuisine_type'] !== null &&
            strtolower($recipe['cuisine_type']) !== strtolower($preferences['cuisine_preference'])) {
            continue;
        }

        // Diet filter (NULL values are allowed)
        if (!empty($preferences['diet_type']) &&
            $recipe['diet_label'] !== null &&
            strtolower($recipe['diet_label']) !== strtolower($preferences['diet_type'])) {
            continue;
        }

        $filtered[$recipeId] = max(0.0, $adjustedScore);
    }

    return $filtered;
}
