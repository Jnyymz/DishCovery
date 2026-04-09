<?php
require_once __DIR__ . "/../core/dbConfig.php";

/* =========================================================
   CONSTANTS FOR FILTER INFERENCE
   ========================================================= */

/**
 * Ingredients that indicate non-vegan recipes
 */
const NON_VEGAN_INGREDIENTS = [
    'beef','pork','chicken','fish','egg','milk','cheese','butter',
    'meat','lamb','turkey','duck','shrimp','prawn','crab','lobster',
    'bacon','ham','sausage','salami','pepperoni','tuna','salmon',
    'anchovy','oyster','mussel','scallop','squid','octopus','meat',
    'lard','ghee','yogurt','cream','sour cream','whey','gelatin',
    'chicken breast','ground beef','ground pork','bone broth'
];

/**
 * Ingredients that indicate non-keto recipes
 */
const NON_KETO_INGREDIENTS = ['rice','bread','sugar','pasta','flour','oat','cereal','grain','starch','potato','corn'];

/**
 * Ingredients containing gluten
 */
const GLUTEN_INGREDIENTS = ['wheat','flour','bread','pasta','oat','barley','rye','bulgur'];

/**
 * Ingredients that are haram (not halal)
 */
const HARAM_INGREDIENTS = ['pork','bacon','ham','wine','beer','alcohol'];

/**
 * Ingredients associated with breakfast
 */
const BREAKFAST_INGREDIENTS = ['egg','bread','milk','cereal','oat'];

/**
 * Ingredients associated with lunch
 */
const LUNCH_INGREDIENTS = ['rice','chicken','beef'];

/* =========================================================
   UTILITY FUNCTIONS
   ========================================================= */

/**
 * Checks if any ingredient contains keywords from a list
 * @param array $ingredients List of ingredient strings
 * @param array $keywords List of keywords to check
 * @return bool True if any keyword matches
 */
function hasKeyword($ingredients, $keywords) {
    foreach ($ingredients as $ingredient) {
        foreach ($keywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $ingredient)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Infers recipe filters from ingredients and instructions
 * @param string $ingredientText Comma-separated ingredients
 * @param string $instructions Recipe instructions
 * @return array ['diet_label', 'meal_type', 'cooking_time']
 */
function inferRecipeFilters($ingredientText, $instructions) {
    $ingredients = normalizeIngredients($ingredientText);
    $text = strtolower($instructions);

    /* ---------- DIET LABEL INFERENCE ---------- */
    $diet = 'general';
    $isVegan = !hasKeyword($ingredients, NON_VEGAN_INGREDIENTS);
    $isKeto = !hasKeyword($ingredients, NON_KETO_INGREDIENTS);
    $isGlutenFree = !hasKeyword($ingredients, GLUTEN_INGREDIENTS);
    $isHalal = !hasKeyword($ingredients, HARAM_INGREDIENTS);

    // Prioritize specific diets
    if ($isVegan) {
        $diet = 'vegan';
    } elseif ($isKeto) {
        $diet = 'keto';
    } elseif ($isGlutenFree) {
        $diet = 'gluten free';
    } elseif ($isHalal) {
        $diet = 'halal';
    }

    /* ---------- MEAL TYPE INFERENCE ---------- */
    $meal = 'dinner';

    if (!empty(array_intersect($ingredients, BREAKFAST_INGREDIENTS))) {
        $meal = 'breakfast';
    } elseif (!empty(array_intersect($ingredients, LUNCH_INGREDIENTS))) {
        $meal = 'lunch';
    }

    /* ---------- COOKING TIME ESTIMATION ---------- */
    // Estimate based on number of steps (sentences/paragraphs)
    $steps = substr_count($text, '.') + substr_count($text, "\n");
    if ($steps <= 2) {
        $minutes = 15;
    } elseif ($steps <= 4) {
        $minutes = 25;
    } elseif ($steps <= 6) {
        $minutes = 35;
    } elseif ($steps <= 8) {
        $minutes = 45;
    } elseif ($steps <= 10) {
        $minutes = 55;
    } elseif ($steps <= 14) {
        $minutes = 70;
    } else {
        $minutes = 90;
    }

    return [
        'diet_label' => $diet,
        'meal_type' => $meal,
        'cooking_time' => $minutes
    ];
}

/* =========================================================
   LEGACY FUNCTIONS (REFACTORED)
   ========================================================= */

/* ---------- INGREDIENT NORMALIZATION ---------- */
function normalizeIngredients($ingredientText) {
    $items = explode(",", strtolower($ingredientText));
    return array_map("trim", $items);
}

/* ---------- SAVE INGREDIENTS & PIVOT ---------- */
function saveIngredients($recipeId, $ingredients) {
    global $conn;

    if (!is_numeric($recipeId) || $recipeId <= 0) {
        // Return error instead of echo
        return ['ok' => false, 'error' => "Invalid recipeId: $recipeId"];
    }

    foreach ($ingredients as $item) {
        if (!$item) continue;

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO ingredients (ingredient_name) VALUES (?)"
        );
        $stmt->bind_param("s", $item);
        $stmt->execute();

        $ingredientId = $conn->insert_id;
        
        // If insert was ignored (duplicate), fetch existing ID
        if (!$ingredientId) {
            $result = $conn->query("SELECT ingredient_id FROM ingredients WHERE ingredient_name='" . $conn->real_escape_string($item) . "'");
            if ($result && $result->num_rows > 0) {
                $ingredientId = $result->fetch_assoc()['ingredient_id'];
            }
        }
        
        if (!$ingredientId) {
            continue; // Skip instead of echo
        }

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_id)
             VALUES (?, ?)"
        );
        $stmt->bind_param("ii", $recipeId, $ingredientId);
        
        if (!$stmt->execute()) {
            continue; // Skip instead of echo
        }
    }

    return ['ok' => true];
}
