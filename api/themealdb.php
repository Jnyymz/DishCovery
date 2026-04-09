<?php
require_once __DIR__ . "/../core/dbConfig.php";
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/edamam.php';
require_once __DIR__ . '/spoonacular_nutri.php';

/**
 * Maximum number of recipes to process to avoid API limits
 */
const MAX_RECIPES_TO_PROCESS = 5;

/**
 * TheMealDB API base URL
 */
const MEALDB_API_URL = "https://www.themealdb.com/api/json/v1/1/search.php?s=";

/* =========================================================
   UTILITY FUNCTIONS
   ========================================================= */

/**
 * Processes a single meal from TheMealDB API
 * @param array $meal Meal data from API
 * @return array ['success' => bool, 'recipe_id' => int|null, 'message' => string]
 */
function processMeal($meal) {
    try {
        $recipeName   = $meal['strMeal'];
        $instructions = $meal['strInstructions'];
        $cuisine      = $meal['strArea'];
        $imageUrl     = $meal['strMealThumb'] ?? null;

        /* ---------- INGREDIENT EXTRACTION ---------- */
        $ingredientList = [];

        for ($i = 1; $i <= 20; $i++) {
            $ingredient = trim($meal["strIngredient$i"] ?? '');
            $measure    = trim($meal["strMeasure$i"] ?? '');

            if ($ingredient !== '') {
                $ingredientList[] = $measure ? "$measure $ingredient" : $ingredient;
            }
        }

        $ingredientText = implode(", ", $ingredientList);

        /* ---------- VALIDATE ESSENTIAL DATA ---------- */
        if (empty($recipeName) || empty($ingredientList)) {
            return ['success' => false, 'recipe_id' => null, 'message' => "Missing essential data for recipe: $recipeName"];
        }

        /* ---------- INFER FILTERS ---------- */
        $filters = inferRecipeFilters($ingredientText, $instructions ?? '');

        /* ---------- SAVE OR UPDATE RECIPE ---------- */
        global $conn;

        // Check if recipe already exists
        $checkStmt = $conn->prepare("SELECT recipe_id FROM recipes WHERE recipe_name = ?");
        $checkStmt->bind_param("s", $recipeName);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $recipeId = $result->fetch_assoc()['recipe_id'];
            $message = "Recipe already exists: $recipeName";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO recipes
                (recipe_name, ingredient_list, cuisine_type, instructions, image_url, source_api,
                 diet_label, meal_type, cooking_time)
                VALUES (?, ?, ?, ?, ?, 'TheMealDB', ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssssssi",
                $recipeName,
                $ingredientText,
                $cuisine,
                $instructions,
                $imageUrl,
                $filters['diet_label'],
                $filters['meal_type'],
                $filters['cooking_time']
            );

            if (!$stmt->execute()) {
                return ['success' => false, 'recipe_id' => null, 'message' => "Failed to save recipe: $recipeName"];
            }

            $recipeId = $conn->insert_id;
            if (!$recipeId || !is_numeric($recipeId) || $recipeId <= 0) {
                return ['success' => false, 'recipe_id' => null, 'message' => "Failed to get recipe ID for: $recipeName"];
            }
            $message = "Recipe saved: $recipeName";
        }

        // Update image if available
        if (!empty($imageUrl)) {
            $stmt = $conn->prepare("UPDATE recipes SET image_url = ? WHERE recipe_id = ?");
            $stmt->bind_param("si", $imageUrl, $recipeId);
            $stmt->execute();
        }

        // Update filters if not set
        $stmt = $conn->prepare(
            "UPDATE recipes
             SET diet_label = IFNULL(diet_label, ?),
                 meal_type = IFNULL(meal_type, ?),
                 cooking_time = IFNULL(cooking_time, ?)
             WHERE recipe_id = ?"
        );
        $stmt->bind_param(
            "ssii",
            $filters['diet_label'],
            $filters['meal_type'],
            $filters['cooking_time'],
            $recipeId
        );
        $stmt->execute();

        /* ---------- SAVE INGREDIENT RELATIONS ---------- */
        saveIngredients($recipeId, normalizeIngredients($ingredientText));

        /* ---------- FETCH NUTRITION ---------- */
        $nutrition = fetchNutritionEdamam($recipeId, $ingredientList);

        if (!$nutrition) {
            $nutrition = fetchNutritionSpoonacular($recipeId, $ingredientList);
        }

        if ($nutrition) {
            $stmt = $conn->prepare(
                "UPDATE recipes
                 SET calories=?, nutritional_summary=?, source_api=?
                 WHERE recipe_id=?"
            );
            $stmt->bind_param(
                "issi",
                $nutrition['calories'],
                $nutrition['summary'],
                $nutrition['source'],
                $recipeId
            );
            $stmt->execute();
            $message .= " | Nutrition saved from {$nutrition['source']}";
        } else {
            $message .= " | No nutrition data available";
        }

        return ['success' => true, 'recipe_id' => $recipeId, 'message' => $message];

    } catch (Exception $e) {
        return ['success' => false, 'recipe_id' => null, 'message' => "Error processing recipe: " . $e->getMessage()];
    }
}

/* =========================================================
   MAIN SCRIPT EXECUTION
   ========================================================= */

$search = $_GET['query'] ?? '';
if (empty(trim($search))) {
    echo json_encode(["error" => "Missing or empty recipe query"]);
    exit;
}

if (strlen($search) > 100) {
    echo json_encode(["error" => "Query too long"]);
    exit;
}

$response = file_get_contents(MEALDB_API_URL . urlencode($search));
$data = json_decode($response, true);

if (empty($data['meals'])) {
    echo json_encode(["error" => "No recipes found"]);
    exit;
}

$processedCount = 0;

foreach ($data['meals'] as $meal) {
    if ($processedCount >= MAX_RECIPES_TO_PROCESS) {
        echo "⚠️ Reached limit of " . MAX_RECIPES_TO_PROCESS . " recipes to avoid API quota\n";
        break;
    }

    $result = processMeal($meal);
    echo $result['success'] ? "✔ " : "❌ ";
    echo $result['message'] . "\n";

    if ($result['success']) {
        $processedCount++;
    }
}

echo json_encode(["status" => "Recipes imported successfully"]);
