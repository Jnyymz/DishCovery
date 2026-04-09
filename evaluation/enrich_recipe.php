<?php
require_once __DIR__ . '/../core/dbConfig.php';

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function ensureApiStatusColumn(mysqli $conn): void {
    if (!hasColumn($conn, 'recipes', 'api_status')) {
        $conn->query("ALTER TABLE recipes ADD COLUMN api_status VARCHAR(20) DEFAULT 'complete'");
    }
}

function containsAny(string $haystack, array $terms): bool {
    foreach ($terms as $term) {
        if (stripos($haystack, $term) !== false) {
            return true;
        }
    }
    return false;
}

function inferDietLabel(string $ingredientsText): string {
    $ingredients = strtolower($ingredientsText);
    $animalTerms = ['chicken', 'beef', 'pork', 'fish', 'egg', 'milk', 'cheese', 'butter', 'shrimp', 'lamb'];
    return containsAny($ingredients, $animalTerms) ? 'general' : 'vegan';
}

function inferMealType(string $recipeName, string $ingredientsText): string {
    $text = strtolower($recipeName . ' ' . $ingredientsText);
    $breakfastKeywords = ['breakfast', 'omelette', 'omelet', 'pancake', 'waffle', 'cereal', 'oatmeal', 'porridge', 'toast'];
    $lunchKeywords = ['lunch', 'salad', 'sandwich', 'soup', 'wrap', 'burger', 'rice bowl', 'noodle', 'pasta', 'stir-fry', 'curry'];
    $snackKeywords = ['snack', 'dessert', 'cake', 'cookie', 'brownie', 'muffin', 'pie', 'tart', 'pudding', 'donut'];
    $dinnerKeywords = ['dinner', 'roast', 'grill', 'bake', 'braise', 'casserole', 'steak'];

    if (containsAny($text, $breakfastKeywords)) {
        return 'breakfast';
    }
    if (containsAny($text, $snackKeywords)) {
        return 'snack';
    }
    if (containsAny($text, $lunchKeywords)) {
        return 'lunch';
    }
    if (containsAny($text, $dinnerKeywords)) {
        return 'dinner';
    }

    return 'lunch';
}

function estimateInstructionSteps(string $instructions): int {
    $clean = trim($instructions);
    if ($clean === '') {
        return 1;
    }

    $lines = preg_split('/\r\n|\r|\n/', $clean);
    $lineSteps = 0;
    foreach ($lines as $line) {
        if (preg_match('/^\s*(step\s*\d+|\d+[.)-])\s+/i', $line)) {
            $lineSteps++;
        }
    }

    if ($lineSteps > 0) {
        return $lineSteps;
    }

    $sentences = preg_split('/[.!?]+/', $clean);
    $sentenceSteps = 0;
    foreach ($sentences as $sentence) {
        if (trim($sentence) !== '') {
            $sentenceSteps++;
        }
    }

    return max(1, $sentenceSteps);
}

function estimateIngredientCount(string $ingredientList): int {
    $parts = array_filter(array_map('trim', explode(',', $ingredientList)), function ($value) {
        return $value !== '';
    });
    return count($parts);
}

function inferCookingTime(string $instructions, string $ingredientList): int {
    $steps = estimateInstructionSteps($instructions);
    $ingredientCount = estimateIngredientCount($ingredientList);

    // Base estimate from step count
    $estimated = 15 + ($steps * 5);

    // Small complexity adjustment from ingredient count
    if ($ingredientCount >= 6 && $ingredientCount <= 10) {
        $estimated += 5;
    } elseif ($ingredientCount >= 11 && $ingredientCount <= 15) {
        $estimated += 10;
    } elseif ($ingredientCount > 15) {
        $estimated += 15;
    }

    // Technique-based extra time
    $instructionsLower = strtolower($instructions);
    $keywordAdjustments = [
        'bake' => 20,
        'roast' => 15,
        'simmer' => 15,
        'marinate' => 20,
    ];

    foreach ($keywordAdjustments as $keyword => $minutes) {
        if (strpos($instructionsLower, $keyword) !== false) {
            $estimated += $minutes;
        }
    }

    // Hard safety cap
    if ($estimated > 120) {
        $estimated = 120;
    }

    // Required operational range for stored cooking time
    $estimated = max(20, min(90, $estimated));

    return (int)$estimated;
}

ensureApiStatusColumn($conn);
$hasApiStatus = hasColumn($conn, 'recipes', 'api_status');

$sql = "SELECT recipe_id, recipe_name, ingredient_list, instructions, cuisine_type, diet_label, meal_type, cooking_time, calories
        FROM recipes
        WHERE cuisine_type IS NULL OR cuisine_type = ''
           OR diet_label IS NULL OR diet_label = ''
           OR meal_type IS NULL OR meal_type = ''
       OR LOWER(meal_type) = 'dinner'
           OR cooking_time IS NULL OR cooking_time <= 0
           OR calories IS NULL OR calories <= 0";

$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) {
    echo "No incomplete filter fields found.\n";
    exit(0);
}

$updated = 0;
while ($row = $result->fetch_assoc()) {
    $recipeId = (int)$row['recipe_id'];
    $recipeName = trim((string)($row['recipe_name'] ?? ''));
    $ingredientList = trim((string)($row['ingredient_list'] ?? ''));
    $instructions = trim((string)($row['instructions'] ?? ''));

    $safeIngredientList = $ingredientList === '' ? 'not specified' : $ingredientList;
    $safeInstructions = $instructions === '' ? 'No instructions provided.' : $instructions;
    $safeCuisine = trim((string)($row['cuisine_type'] ?? ''));
    $safeCuisine = $safeCuisine === '' ? 'Unknown' : $safeCuisine;

    $safeDiet = trim((string)($row['diet_label'] ?? ''));
    if ($safeDiet === '') {
        $safeDiet = inferDietLabel($safeIngredientList);
    }

    $safeMeal = trim((string)($row['meal_type'] ?? ''));
    if ($safeMeal === '' || strtolower($safeMeal) === 'dinner') {
        $safeMeal = inferMealType($recipeName, $safeIngredientList);
    }

    $safeCookingTime = (int)($row['cooking_time'] ?? 0);
    if ($safeCookingTime <= 0) {
        $safeCookingTime = inferCookingTime($safeInstructions, $safeIngredientList);
    }
    if ($safeCookingTime > 120) {
        $safeCookingTime = 120;
    }
    $safeCookingTime = max(20, min(90, $safeCookingTime));

    $safeCalories = (int)($row['calories'] ?? 0);
    if ($safeCalories <= 0) {
        $safeCalories = 500;
    }

    $apiStatus = ($ingredientList === '' || $instructions === '') ? 'incomplete' : 'complete';

    if ($hasApiStatus) {
        $stmt = $conn->prepare(
            "UPDATE recipes
             SET ingredient_list = ?,
                 instructions = ?,
                 cuisine_type = ?,
                 diet_label = ?,
                 meal_type = ?,
                 cooking_time = ?,
                 calories = ?,
                 api_status = ?
             WHERE recipe_id = ?"
        );
        $stmt->bind_param(
            'sssssissi',
            $safeIngredientList,
            $safeInstructions,
            $safeCuisine,
            $safeDiet,
            $safeMeal,
            $safeCookingTime,
            $safeCalories,
            $apiStatus,
            $recipeId
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE recipes
             SET ingredient_list = ?,
                 instructions = ?,
                 cuisine_type = ?,
                 diet_label = ?,
                 meal_type = ?,
                 cooking_time = ?,
                 calories = ?
             WHERE recipe_id = ?"
        );
        $stmt->bind_param(
            'sssssiii',
            $safeIngredientList,
            $safeInstructions,
            $safeCuisine,
            $safeDiet,
            $safeMeal,
            $safeCookingTime,
            $safeCalories,
            $recipeId
        );
    }

    if ($stmt && $stmt->execute()) {
        $updated++;
    }
}

echo "Enrichment complete. Updated rows: {$updated}\n";
