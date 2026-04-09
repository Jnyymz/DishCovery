<?php
require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/helpers.php';

/* =========================================================
   CONSTANTS FOR CALORIE ESTIMATION
   ========================================================= */

/**
 * Calorie values per 100g for common ingredients
 */
const CALORIE_MAP = [
    // Proteins
    'chicken' => 165, 'beef' => 250, 'pork' => 242, 'fish' => 100, 'salmon' => 208,
    'tuna' => 132, 'shrimp' => 99, 'lamb' => 294, 'turkey' => 135, 'egg' => 155,

    // Vegetables
    'tomato' => 18, 'onion' => 40, 'garlic' => 149, 'carrot' => 41, 'pepper' => 31,
    'cucumber' => 16, 'lettuce' => 15, 'spinach' => 23, 'broccoli' => 34, 'potato' => 77,
    'corn' => 86, 'rice' => 130, 'beans' => 127, 'lentils' => 116, 'chickpeas' => 164,

    // Dairy
    'milk' => 61, 'cheese' => 402, 'butter' => 717, 'yogurt' => 59, 'cream' => 340,

    // Oils & Fats
    'oil' => 884, 'olive oil' => 884, 'coconut oil' => 892,

    // Grains
    'bread' => 265, 'pasta' => 131, 'flour' => 364, 'oat' => 389,

    // Other
    'salt' => 0, 'pepper' => 251, 'sugar' => 387, 'honey' => 304, 'soy sauce' => 53
];

/**
 * Base calories to add to all recipes
 */
const BASE_CALORIES = 100;

/**
 * Minimum estimated calories for a recipe
 */
const MIN_CALORIES = 200;

/**
 * Maximum estimated calories for a recipe
 */
const MAX_CALORIES = 2000;

/* =========================================================
   VALIDATION FUNCTIONS
   ========================================================= */

/**
 * Validates ingredients array for calorie estimation
 * @param array $ingredients List of ingredient strings
 * @return bool True if valid
 */
function validateIngredientsForCalorieEstimation($ingredients) {
    return is_array($ingredients) && !empty($ingredients);
}

/* =========================================================
   CALORIE ESTIMATION FUNCTIONS
   ========================================================= */

/**
 * Estimates calories for a recipe based on ingredients
 * @param array $ingredients List of ingredient strings
 * @return int Estimated calories (capped between MIN_CALORIES and MAX_CALORIES)
 */
function estimateCalories($ingredients) {
    if (!validateIngredientsForCalorieEstimation($ingredients)) {
        return MIN_CALORIES;
    }

    $totalCalories = BASE_CALORIES;

    foreach ($ingredients as $ingredient) {
        $ingredient = strtolower(trim($ingredient));
        foreach (CALORIE_MAP as $key => $calories) {
            if (strpos($ingredient, $key) !== false) {
                $totalCalories += $calories / 4; // Average portion estimation
                break;
            }
        }
    }

    return max(MIN_CALORIES, min($totalCalories, MAX_CALORIES));
}

/* =========================================================
   MAIN EXECUTION
   ========================================================= */

/**
 * Processes recipes with null calories and estimates them
 * @return array ['status' => string, 'updated' => int]
 */
function processCalorieEstimation() {
    global $conn;

    $result = $conn->query(
        "SELECT recipe_id, ingredient_list, instructions
         FROM recipes
         WHERE calories IS NULL"
    );

    if (!$result) {
        return ['status' => 'Database query failed', 'updated' => 0];
    }

    $updated = 0;
    while ($row = $result->fetch_assoc()) {
        $ingredients = normalizeIngredients($row['ingredient_list']);
        $estimatedCalories = estimateCalories($ingredients);

        $stmt = $conn->prepare(
            "UPDATE recipes SET calories = ? WHERE recipe_id = ?"
        );
        $stmt->bind_param("ii", $estimatedCalories, $row['recipe_id']);

        if ($stmt->execute()) {
            $updated++;
        }
    }

    return [
        'status' => 'Calories estimated successfully',
        'updated' => $updated
    ];
}

// Execute the process
$response = processCalorieEstimation();
echo json_encode($response);
?>
