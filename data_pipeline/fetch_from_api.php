<?php
require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/../api/spoonacular_nutri.php';

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return !empty($row);
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

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
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
    $ingredientsText = strtolower($ingredientsText);
    $animalTerms = ['chicken', 'beef', 'pork', 'fish', 'egg', 'milk', 'cheese', 'butter', 'shrimp', 'lamb'];
    if (containsAny($ingredientsText, $animalTerms)) {
        return 'general';
    }
    return 'vegan';
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

function fetchNutritionForMeal(array $ingredientParts): ?array {
    if (empty($ingredientParts)) {
        return null;
    }

    $nutrition = fetchNutritionSpoonacular(0, $ingredientParts);
    if (!is_array($nutrition)) {
        return null;
    }

    $calories = (int)($nutrition['calories'] ?? 0);
    $summaryRaw = (string)($nutrition['summary'] ?? '');
    $summary = json_decode($summaryRaw, true);

    if ($calories <= 0 || !is_array($summary)) {
        return null;
    }

    if (!array_key_exists('Protein', $summary) || !array_key_exists('Fat', $summary) || !array_key_exists('Carbs', $summary)) {
        return null;
    }

    return [
        'calories' => $calories,
        'summary' => json_encode([
            'Protein' => (string)$summary['Protein'],
            'Fat' => (string)$summary['Fat'],
            'Carbs' => (string)$summary['Carbs'],
        ]),
    ];
}

function normalizeMeal(array $meal, string &$errorMessage = ''): ?array {
    $ingredientParts = parseIngredientList($meal);
    $ingredientList = empty($ingredientParts) ? 'not specified' : implode(', ', $ingredientParts);
    $instructionsRaw = trim((string)($meal['strInstructions'] ?? ''));
    $instructions = $instructionsRaw === '' ? 'No instructions provided.' : $instructionsRaw;

    $rawCuisine = trim((string)($meal['strArea'] ?? ''));
    $cuisineType = $rawCuisine === '' ? 'Unknown' : $rawCuisine;

    $dietLabel = inferDietLabel($ingredientList);
    $mealType = inferMealType($meal);
    $cookingTime = inferCookingTime($instructions);

    $recipeNameRaw = trim((string)($meal['strMeal'] ?? ''));
    $recipeName = $recipeNameRaw === '' ? 'Untitled Recipe' : $recipeNameRaw;

    $nutrition = fetchNutritionForMeal($ingredientParts);
    if ($nutrition === null) {
        $errorMessage = 'Nutritional Api is unnavailable';
        return null;
    }

    $isIncomplete = (
        empty($ingredientParts)
        || $instructionsRaw === ''
        || $rawCuisine === ''
    );

    return [
        'recipe_name' => $recipeName,
        'ingredient_list' => $ingredientList,
        'instructions' => $instructions,
        'cuisine_type' => $cuisineType,
        'diet_label' => $dietLabel,
        'meal_type' => $mealType,
        'cooking_time' => $cookingTime,
        'calories' => (int)$nutrition['calories'],
        'nutritional_summary' => (string)$nutrition['summary'],
        'image_url' => trim((string)($meal['strMealThumb'] ?? '')),
        'source_api' => 'TheMealDB',
        'external_id' => trim((string)($meal['idMeal'] ?? '')),
        'api_status' => $isIncomplete ? 'incomplete' : 'complete',
    ];
}

function upsertRecipe(mysqli $conn, array $recipe): bool {
    $hasExternalId = hasColumn($conn, 'recipes', 'external_id');

    if ($hasExternalId) {
        $stmt = $conn->prepare(
            "INSERT INTO recipes
             (recipe_name, ingredient_list, cuisine_type, diet_label, meal_type, cooking_time, calories,
              nutritional_summary, image_url, instructions, source_api, external_id, api_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               ingredient_list = VALUES(ingredient_list),
               cuisine_type = VALUES(cuisine_type),
               diet_label = VALUES(diet_label),
               meal_type = VALUES(meal_type),
               cooking_time = VALUES(cooking_time),
               calories = VALUES(calories),
               nutritional_summary = VALUES(nutritional_summary),
               image_url = VALUES(image_url),
               instructions = VALUES(instructions),
               source_api = VALUES(source_api),
               external_id = VALUES(external_id),
               api_status = VALUES(api_status)"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'sssssiissssss',
            $recipe['recipe_name'],
            $recipe['ingredient_list'],
            $recipe['cuisine_type'],
            $recipe['diet_label'],
            $recipe['meal_type'],
            $recipe['cooking_time'],
            $recipe['calories'],
            $recipe['nutritional_summary'],
            $recipe['image_url'],
            $recipe['instructions'],
            $recipe['source_api'],
            $recipe['external_id'],
            $recipe['api_status']
        );

        return $stmt->execute();
    }

    $stmt = $conn->prepare(
        "INSERT INTO recipes
         (recipe_name, ingredient_list, cuisine_type, diet_label, meal_type, cooking_time, calories,
          nutritional_summary, image_url, instructions, source_api, api_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           ingredient_list = VALUES(ingredient_list),
           cuisine_type = VALUES(cuisine_type),
           diet_label = VALUES(diet_label),
           meal_type = VALUES(meal_type),
           cooking_time = VALUES(cooking_time),
           calories = VALUES(calories),
           nutritional_summary = VALUES(nutritional_summary),
           image_url = VALUES(image_url),
           instructions = VALUES(instructions),
           source_api = VALUES(source_api),
           api_status = VALUES(api_status)"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'sssssiisssss',
        $recipe['recipe_name'],
        $recipe['ingredient_list'],
        $recipe['cuisine_type'],
        $recipe['diet_label'],
        $recipe['meal_type'],
        $recipe['cooking_time'],
        $recipe['calories'],
        $recipe['nutritional_summary'],
        $recipe['image_url'],
        $recipe['instructions'],
        $recipe['source_api'],
        $recipe['api_status']
    );

    return $stmt->execute();
}

function fetchMeals(int $limit, ?string $query = null): array {
    $results = [];
    $seen = [];

    if ($query !== null && trim($query) !== '') {
        $data = fetchJson('https://www.themealdb.com/api/json/v1/1/search.php?s=' . urlencode($query));
        $meals = $data['meals'] ?? [];
        foreach ($meals as $meal) {
            $id = (string)($meal['idMeal'] ?? '');
            if ($id !== '' && !isset($seen[$id])) {
                $results[] = $meal;
                $seen[$id] = true;
            }
            if (count($results) >= $limit) {
                break;
            }
        }
        return $results;
    }

    for ($i = 0; $i < $limit; $i++) {
        $data = fetchJson('https://www.themealdb.com/api/json/v1/1/random.php');
        $meal = $data['meals'][0] ?? null;
        if (!$meal) {
            continue;
        }
        $id = (string)($meal['idMeal'] ?? '');
        if ($id === '' || isset($seen[$id])) {
            continue;
        }
        $results[] = $meal;
        $seen[$id] = true;
    }

    return $results;
}

ensureApiStatusColumn($conn);

$limit = isset($argv[1]) ? max(1, (int)$argv[1]) : 20;
$query = isset($argv[2]) ? trim((string)$argv[2]) : null;

$meals = fetchMeals($limit, $query);
if (empty($meals)) {
    echo "No meals fetched from API.\n";
    exit(0);
}

$normalizedMeals = [];
foreach ($meals as $meal) {
    $normalizeError = '';
    $normalized = normalizeMeal($meal, $normalizeError);
    if ($normalized === null) {
        echo ($normalizeError !== ''
            ? $normalizeError
            : 'Nutritional Api is unavailable') . "\n";
        exit(1);
    }
    $normalizedMeals[] = $normalized;
}

$inserted = 0;
$failed = 0;
foreach ($normalizedMeals as $normalized) {
    if (upsertRecipe($conn, $normalized)) {
        $inserted++;
    } else {
        $failed++;
    }
}

echo "Fetch complete. Success: {$inserted}, Failed: {$failed}\n";
