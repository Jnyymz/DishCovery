<?php
require_once __DIR__ . '/../core/dbConfig.php';

/* =========================================================
   CONSTANTS FOR MACRONUTRIENT ESTIMATION
   ========================================================= */

/**
 * Default macronutrient ratios by diet type (Protein%, Fat%, Carbs%)
 */
const DIET_MACRO_RATIOS = [
    'vegan' => ['protein' => 0.15, 'fat' => 0.25, 'carbs' => 0.60],
    'keto' => ['protein' => 0.30, 'fat' => 0.65, 'carbs' => 0.05],
    'gluten free' => ['protein' => 0.25, 'fat' => 0.35, 'carbs' => 0.40],
    'halal' => ['protein' => 0.30, 'fat' => 0.35, 'carbs' => 0.35],
    'general' => ['protein' => 0.25, 'fat' => 0.30, 'carbs' => 0.45],
];

/**
 * Cuisine-specific macronutrient adjustments
 */
const CUISINE_MACRO_ADJUSTMENTS = [
    'italian' => ['protein' => 0.20, 'fat' => 0.40, 'carbs' => 0.40],
    'chinese' => ['protein' => 0.25, 'fat' => 0.25, 'carbs' => 0.50],
    'indian' => ['protein' => 0.20, 'fat' => 0.30, 'carbs' => 0.50],
    'mexican' => ['protein' => 0.20, 'fat' => 0.35, 'carbs' => 0.45],
    'thai' => ['protein' => 0.25, 'fat' => 0.30, 'carbs' => 0.45],
    'japanese' => ['protein' => 0.30, 'fat' => 0.20, 'carbs' => 0.50],
    'spanish' => ['protein' => 0.25, 'fat' => 0.35, 'carbs' => 0.40],
    'french' => ['protein' => 0.25, 'fat' => 0.40, 'carbs' => 0.35],
    'british' => ['protein' => 0.25, 'fat' => 0.35, 'carbs' => 0.40],
    'jamaican' => ['protein' => 0.30, 'fat' => 0.35, 'carbs' => 0.35],
    'australian' => ['protein' => 0.35, 'fat' => 0.30, 'carbs' => 0.35],
];

/**
 * Calories per gram for macronutrients
 */
const CALORIES_PER_GRAM = [
    'protein' => 4,
    'fat' => 9,
    'carbs' => 4
];

/* =========================================================
   VALIDATION FUNCTIONS
   ========================================================= */

/**
 * Validates calories value for macronutrient estimation
 * @param mixed $calories The calories value to validate
 * @return bool True if valid
 */
function validateCaloriesForEstimation($calories) {
    return is_numeric($calories) && $calories > 0 && $calories <= 10000;
}

/**
 * Validates diet label for macronutrient estimation
 * @param string $dietLabel The diet label to validate
 * @return bool True if valid
 */
function validateDietLabelForEstimation($dietLabel) {
    return is_string($dietLabel) && !empty(trim($dietLabel));
}

/**
 * Validates cuisine type for macronutrient estimation
 * @param string $cuisineType The cuisine type to validate
 * @return bool True if valid
 */
function validateCuisineTypeForEstimation($cuisineType) {
    return is_string($cuisineType);
}

/* =========================================================
   MACRONUTRIENT ESTIMATION FUNCTIONS
   ========================================================= */

/**
 * Estimates macronutrients based on diet type and cuisine
 * @param int $calories Total calories
 * @param string $dietLabel Diet label (vegan, keto, etc.)
 * @param string $cuisineType Cuisine type (italian, chinese, etc.)
 * @return array ['Protein' => float, 'Fat' => float, 'Carbs' => float]
 */
function estimateMacronutrients($calories, $dietLabel, $cuisineType) {
    // Validate inputs
    if (!validateCaloriesForEstimation($calories)) {
        $calories = 500; // Default fallback
    }

    if (!validateDietLabelForEstimation($dietLabel)) {
        $dietLabel = 'general';
    }

    if (!validateCuisineTypeForEstimation($cuisineType)) {
        $cuisineType = '';
    }

    // Get diet-based ratio
    $ratio = DIET_MACRO_RATIOS[strtolower($dietLabel)] ?? DIET_MACRO_RATIOS['general'];

    // Override with cuisine if available
    $cuisineLower = strtolower($cuisineType);
    foreach (CUISINE_MACRO_ADJUSTMENTS as $cuisine => $adjustment) {
        if (strpos($cuisineLower, $cuisine) !== false) {
            $ratio = $adjustment;
            break;
        }
    }

    // Calculate grams from calories
    $proteinGrams = round(($calories * $ratio['protein']) / CALORIES_PER_GRAM['protein'], 1);
    $fatGrams = round(($calories * $ratio['fat']) / CALORIES_PER_GRAM['fat'], 1);
    $carbsGrams = round(($calories * $ratio['carbs']) / CALORIES_PER_GRAM['carbs'], 1);

    return [
        'Protein' => $proteinGrams,
        'Fat' => $fatGrams,
        'Carbs' => $carbsGrams
    ];
}

/* =========================================================
   MAIN EXECUTION
   ========================================================= */

/**
 * Processes recipes with null nutritional summary and estimates them
 * @return array ['status' => string, 'updated' => int]
 */
function processNutritionEstimation() {
    global $conn;

    $result = $conn->query(
        "SELECT recipe_id, calories, diet_label, cuisine_type
         FROM recipes
         WHERE nutritional_summary IS NULL"
    );

    if (!$result) {
        return ['status' => 'Database query failed', 'updated' => 0];
    }

    $updated = 0;
    while ($row = $result->fetch_assoc()) {
        $macros = estimateMacronutrients(
            $row['calories'],
            $row['diet_label'],
            $row['cuisine_type']
        );

        $nutritionalSummary = json_encode($macros);

        $stmt = $conn->prepare(
            "UPDATE recipes SET nutritional_summary = ? WHERE recipe_id = ?"
        );
        $stmt->bind_param("si", $nutritionalSummary, $row['recipe_id']);

        if ($stmt->execute()) {
            $updated++;
        }
    }

    return [
        'status' => 'Nutritional summary estimated successfully',
        'updated' => $updated
    ];
}

// Execute the process
$response = processNutritionEstimation();
echo json_encode($response);
?>
