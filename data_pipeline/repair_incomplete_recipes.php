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

function fetchJson(string $url): ?array {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function parseIngredientList(array $meal): array {
    $parts = [];
    for ($index = 1; $index <= 20; $index++) {
        $ingredient = trim((string)($meal["strIngredient{$index}"] ?? ''));
        $measure = trim((string)($meal["strMeasure{$index}"] ?? ''));
        if ($ingredient === '') {
            continue;
        }
        $parts[] = $measure === '' ? $ingredient : ($measure . ' ' . $ingredient);
    }
    return $parts;
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

function inferMealType(array $meal): string {
    $category = strtolower(trim((string)($meal['strCategory'] ?? '')));
    if ($category === 'breakfast') {
        return 'breakfast';
    }
    if (in_array($category, ['starter', 'side', 'salad', 'soup'], true)) {
        return 'lunch';
    }
    if (in_array($category, ['dessert', 'snack'], true)) {
        return 'snack';
    }

    $name = strtolower(trim((string)($meal['strMeal'] ?? '')));
    $tags = strtolower(trim((string)($meal['strTags'] ?? '')));
    $text = $name . ' ' . $category . ' ' . $tags;

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

function inferCookingTime(string $instructions): int {
    $sentences = preg_split('/[.!?]+/', $instructions);
    $steps = 0;
    foreach ($sentences as $sentence) {
        if (trim($sentence) !== '') {
            $steps++;
        }
    }

    if ($steps <= 2) {
        return 20;
    }
    if ($steps <= 5) {
        return 35;
    }
    if ($steps <= 8) {
        return 50;
    }
    return 65;
}

function normalizeApiMeal(array $meal): array {
    $ingredientsRaw = parseIngredientList($meal);
    $ingredientList = empty($ingredientsRaw) ? 'not specified' : implode(', ', $ingredientsRaw);

    $instructionsRaw = trim((string)($meal['strInstructions'] ?? ''));
    $instructions = $instructionsRaw === '' ? 'No instructions provided.' : $instructionsRaw;

    $rawCuisine = trim((string)($meal['strArea'] ?? ''));
    $cuisineType = $rawCuisine === '' ? 'Unknown' : $rawCuisine;

    return [
        'recipe_name' => trim((string)($meal['strMeal'] ?? 'Untitled Recipe')),
        'ingredient_list' => $ingredientList,
        'instructions' => $instructions,
        'cuisine_type' => $cuisineType,
        'diet_label' => inferDietLabel($ingredientList),
        'meal_type' => inferMealType($meal),
        'cooking_time' => inferCookingTime($instructions),
        'calories' => 500,
        'api_status' => (empty($ingredientsRaw) || $instructionsRaw === '' || $rawCuisine === '') ? 'incomplete' : 'complete',
        'external_id' => trim((string)($meal['idMeal'] ?? '')),
    ];
}

function fetchMealForRepair(array $row, bool $hasExternalId): ?array {
    if ($hasExternalId) {
        $externalId = trim((string)($row['external_id'] ?? ''));
        if ($externalId !== '') {
            $lookup = fetchJson('https://www.themealdb.com/api/json/v1/1/lookup.php?i=' . urlencode($externalId));
            $meal = $lookup['meals'][0] ?? null;
            if ($meal) {
                return $meal;
            }
        }
    }

    $name = trim((string)($row['recipe_name'] ?? ''));
    if ($name === '') {
        return null;
    }

    $search = fetchJson('https://www.themealdb.com/api/json/v1/1/search.php?s=' . urlencode($name));
    if (empty($search['meals'])) {
        return null;
    }

    foreach ($search['meals'] as $meal) {
        if (strcasecmp((string)($meal['strMeal'] ?? ''), $name) === 0) {
            return $meal;
        }
    }

    return $search['meals'][0] ?? null;
}

function repairIncompleteRecipes(mysqli $conn): array {
    ensureApiStatusColumn($conn);

    $hasExternalId = hasColumn($conn, 'recipes', 'external_id');
    $selectSql = $hasExternalId
        ? "SELECT recipe_id, recipe_name, ingredient_list, instructions, cuisine_type, diet_label, meal_type, cooking_time, calories, external_id
           FROM recipes WHERE api_status = 'incomplete'"
        : "SELECT recipe_id, recipe_name, ingredient_list, instructions, cuisine_type, diet_label, meal_type, cooking_time, calories
           FROM recipes WHERE api_status = 'incomplete'";

    $result = $conn->query($selectSql);
    if (!$result || $result->num_rows === 0) {
        return ['updated' => 0, 'remaining_incomplete' => 0, 'message' => "No recipes with api_status='incomplete' found."];
    }

    $updated = 0;
    $stillIncomplete = 0;

    while ($row = $result->fetch_assoc()) {
        $recipeId = (int)$row['recipe_id'];
        $meal = fetchMealForRepair($row, $hasExternalId);

        if ($meal) {
            $normalized = normalizeApiMeal($meal);
            $safeIngredientList = $normalized['ingredient_list'];
            $safeInstructions = $normalized['instructions'];
            $safeCuisine = $normalized['cuisine_type'];
            $safeDiet = $normalized['diet_label'];
            $safeMeal = $normalized['meal_type'];
            $safeCookingTime = $normalized['cooking_time'];
            $safeCalories = $normalized['calories'];
            $apiStatus = $normalized['api_status'];

            if ($hasExternalId) {
                $safeExternalId = $normalized['external_id'];
                $stmt = $conn->prepare(
                    "UPDATE recipes
                     SET ingredient_list = ?,
                         instructions = ?,
                         cuisine_type = ?,
                         diet_label = ?,
                         meal_type = ?,
                         cooking_time = ?,
                         calories = ?,
                         external_id = ?,
                         api_status = ?
                     WHERE recipe_id = ?"
                );
                $stmt->bind_param(
                    'sssssisssi',
                    $safeIngredientList,
                    $safeInstructions,
                    $safeCuisine,
                    $safeDiet,
                    $safeMeal,
                    $safeCookingTime,
                    $safeCalories,
                    $safeExternalId,
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
            }

            if ($stmt && $stmt->execute()) {
                $updated++;
                if ($apiStatus === 'incomplete') {
                    $stillIncomplete++;
                }
            }
            continue;
        }

        $safeIngredientList = trim((string)($row['ingredient_list'] ?? ''));
        $safeInstructions = trim((string)($row['instructions'] ?? ''));
        $safeCuisine = trim((string)($row['cuisine_type'] ?? ''));
        $safeDiet = trim((string)($row['diet_label'] ?? ''));
        $safeMeal = trim((string)($row['meal_type'] ?? ''));
        $safeCookingTime = (int)($row['cooking_time'] ?? 0);
        $safeCalories = (int)($row['calories'] ?? 0);

        if ($safeIngredientList === '') {
            $safeIngredientList = 'not specified';
        }
        if ($safeInstructions === '') {
            $safeInstructions = 'No instructions provided.';
        }
        if ($safeCuisine === '') {
            $safeCuisine = 'Unknown';
        }
        if ($safeDiet === '') {
            $safeDiet = inferDietLabel($safeIngredientList);
        }
        if ($safeMeal === '') {
            $safeMeal = 'dinner';
        }
        if ($safeCookingTime <= 0) {
            $safeCookingTime = inferCookingTime($safeInstructions);
        }
        if ($safeCalories <= 0) {
            $safeCalories = 500;
        }

        $stmt = $conn->prepare(
            "UPDATE recipes
             SET ingredient_list = ?,
                 instructions = ?,
                 cuisine_type = ?,
                 diet_label = ?,
                 meal_type = ?,
                 cooking_time = ?,
                 calories = ?,
                 api_status = 'incomplete'
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
        if ($stmt && $stmt->execute()) {
            $updated++;
            $stillIncomplete++;
        }
    }

    return [
        'updated' => $updated,
        'remaining_incomplete' => $stillIncomplete,
        'message' => "Repair complete. Updated: {$updated}, Remaining incomplete: {$stillIncomplete}",
    ];
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $result = repairIncompleteRecipes($conn);
    echo $result['message'] . "\n";
}
