<?php
/* =========================================================
   apiRecommender.php
   API-Based Recommendation Engine
   DishCovery – Fetch from TheMealDB, Score, and Save
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/tfidf.php';
require_once __DIR__ . '/similarity.php';
require_once __DIR__ . '/filters.php';
require_once __DIR__ . '/preprocess.php';

/**
 * Fetch recipes from TheMealDB API for each ingredient
 * Combines results and removes duplicates
 */
function fetchFromTheMealDB(array $ingredients) {
    global $conn;
    
    $allRecipes = [];
    $seen = [];
    
    error_log("apiRecommender: Fetching from TheMealDB for ingredients: " . json_encode($ingredients));
    
    foreach ($ingredients as $ingredient) {
        $ingredient = trim($ingredient);
        if (empty($ingredient)) continue;
        
        error_log("Fetching: $ingredient");
        
        // Query TheMealDB API
        $url = "https://www.themealdb.com/api/json/v1/1/filter.php?i=" . urlencode($ingredient);
        
        $response = @file_get_contents($url);
        if (!$response) {
            error_log("Failed to fetch from TheMealDB for: $ingredient");
            continue;
        }
        
        $data = json_decode($response, true);
        
        // Also search by recipe name
        $url2 = "https://www.themealdb.com/api/json/v1/1/search.php?s=" . urlencode($ingredient);
        $response2 = @file_get_contents($url2);
        
        if ($response2) {
            $data2 = json_decode($response2, true);
            if (!empty($data2['meals'])) {
                $data['meals'] = $data['meals'] ?? [];
                $data['meals'] = array_merge($data['meals'], $data2['meals']);
            }
        }
        
        if (!empty($data['meals'])) {
            $batchCount = 0;
            foreach ($data['meals'] as $meal) {
                // Limit to first 10 per ingredient to avoid excessive API calls
                if ($batchCount >= 10) break;
                
                $mealId = $meal['idMeal'];
                
                // Skip duplicates
                if (isset($seen[$mealId])) continue;
                $seen[$mealId] = true;
                
                // Fetch full recipe details
                $details_url = "https://www.themealdb.com/api/json/v1/1/lookup.php?i=" . $mealId;
                $details_response = @file_get_contents($details_url);
                
                if ($details_response) {
                    $details_data = json_decode($details_response, true);
                    if (!empty($details_data['meals'])) {
                        $allRecipes[] = $details_data['meals'][0];
                        $batchCount++;
                    }
                }
                
                // Rate limit - sleep to avoid hitting API limits
                usleep(100000); // 100ms sleep
            }
        }
    }
    
    error_log("apiRecommender: Fetched " . count($allRecipes) . " recipes from API");
    return $allRecipes;
}

/**
 * Fetch a general list of meals for the dashboard
 */
function fetchAllMealsFromTheMealDB(int $limit = 0) {
    $url = "https://www.themealdb.com/api/json/v1/1/search.php?s=";
    $response = @file_get_contents($url);

    if (!$response) {
        error_log("fetchAllMealsFromTheMealDB: Failed to fetch from TheMealDB");
        return [];
    }

    $data = json_decode($response, true);
    if (empty($data['meals'])) {
        return [];
    }

    $meals = $data['meals'];

    if ($limit > 0) {
        $meals = array_slice($meals, 0, $limit);
    }

    $results = [];
    foreach ($meals as $meal) {
        $results[] = extractRecipeData($meal);
    }

    return $results;
}

/**
 * Extract recipe data from TheMealDB format
 */
function extractRecipeData($meal) {
    $ingredients = [];
    for ($i = 1; $i <= 20; $i++) {
        $ingredient = trim($meal["strIngredient$i"] ?? '');
        $measure = trim($meal["strMeasure$i"] ?? '');
        if ($ingredient !== '') {
            $ingredients[] = ($measure ? "$measure " : '') . $ingredient;
        }
    }
    
    return [
        'recipe_name' => $meal['strMeal'] ?? 'Unknown',
        'ingredient_list' => implode(', ', $ingredients),
        'instructions' => $meal['strInstructions'] ?? '',
        'cuisine_type' => $meal['strArea'] ?? 'Unknown',
        'image_url' => $meal['strMealThumb'] ?? null,
        'external_id' => $meal['idMeal'] ?? null,
        'source_api' => 'TheMealDB'
    ];
}

/**
 * Check if recipe already exists in database
 */
function recipeExists($recipeName, $externalId = null) {
    global $conn;
    
    if ($externalId) {
        $stmt = $conn->prepare("SELECT recipe_id FROM recipes WHERE external_id = ?");
        $stmt->bind_param("s", $externalId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            return true;
        }
    }
    
    $stmt = $conn->prepare("SELECT recipe_id FROM recipes WHERE recipe_name = ?");
    $stmt->bind_param("s", $recipeName);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        return true;
    }
    
    return false;
}

/**
 * Save new recipe to database
 */
function saveRecipeToDB($recipeData) {
    global $conn;
    
    // Check if already exists
    if (recipeExists($recipeData['recipe_name'], $recipeData['external_id'] ?? null)) {
        error_log("Recipe already exists: " . $recipeData['recipe_name']);
        return false;
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO recipes 
         (recipe_name, ingredient_list, instructions, cuisine_type, image_url, external_id, source_api)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    
    $recipeName = $recipeData['recipe_name'];
    $ingredientList = $recipeData['ingredient_list'];
    $instructions = $recipeData['instructions'];
    $cuisineType = $recipeData['cuisine_type'];
    $imageUrl = $recipeData['image_url'];
    $externalId = $recipeData['external_id'];
    $sourceApi = $recipeData['source_api'];
    
    $stmt->bind_param(
        "sssssss",
        $recipeName,
        $ingredientList,
        $instructions,
        $cuisineType,
        $imageUrl,
        $externalId,
        $sourceApi
    );
    
    $result = $stmt->execute();
    
    if ($result) {
        $recipeId = $conn->insert_id;
        error_log("Saved new recipe ID $recipeId: " . $recipeData['recipe_name']);
        
        // Build TF-IDF vector for new recipe
        $ingredients = preprocessIngredients($recipeData['ingredient_list']);
        $tf = computeTF($ingredients);
        
        // Get global vocabulary
        $vocab = getGlobalVocabulary();
        
        // Compute TF-IDF with vocabulary
        $tfidfVector = [];
        foreach (array_keys($vocab) as $term) {
            $tfValue = $tf[$term] ?? 0;
            $idfValue = log((count($vocab)) / (($vocab[$term] ?? 0) + 1));
            $tfidfVector[$term] = $tfValue * $idfValue;
        }
        
        $vectorJson = json_encode($tfidfVector);
        
        $stmt2 = $conn->prepare(
            "INSERT INTO tfidf_vectors (recipe_id, ingredient_vector) VALUES (?, ?)"
        );
        $stmt2->bind_param("is", $recipeId, $vectorJson);
        $stmt2->execute();
        
        return true;
    }
    
    return false;
}

/**
 * Compute a TF-IDF vector for a recipe using the global vocabulary
 */
function computeRecipeVectorWithVocab(string $ingredientList, array $vocab) {
    if (empty($vocab)) {
        return [];
    }

    $ingredients = preprocessIngredients($ingredientList);
    $tf = computeTF($ingredients);

    $vector = [];
    $docCount = count($vocab);
    foreach (array_keys($vocab) as $term) {
        $tfValue = $tf[$term] ?? 0;
        $idfValue = log($docCount / (($vocab[$term] ?? 0) + 1));
        $vector[$term] = $tfValue * $idfValue;
    }

    return $vector;
}

/**
 * Save API recipe on demand with filters and nutrition
 */
function saveRecipeFromApiDetailed(array $recipeData, array $filters, array $nutrition) {
    global $conn;

    if (recipeExists($recipeData['recipe_name'], $recipeData['external_id'] ?? null)) {
        $stmt = $conn->prepare("SELECT recipe_id FROM recipes WHERE external_id = ? OR recipe_name = ?");
        $externalId = $recipeData['external_id'];
        $recipeName = $recipeData['recipe_name'];
        $stmt->bind_param("ss", $externalId, $recipeName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            return (int)$row['recipe_id'];
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO recipes
         (recipe_name, ingredient_list, instructions, cuisine_type, image_url, external_id, source_api,
          diet_label, meal_type, cooking_time, calories, nutritional_summary)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $recipeName = $recipeData['recipe_name'];
    $ingredientList = $recipeData['ingredient_list'];
    $instructions = $recipeData['instructions'];
    $cuisineType = $recipeData['cuisine_type'];
    $imageUrl = $recipeData['image_url'];
    $externalId = $recipeData['external_id'];
    $sourceApi = $recipeData['source_api'];

    $dietLabel = $filters['diet_label'] ?? null;
    $mealType = $filters['meal_type'] ?? null;
    $cookingTime = $filters['cooking_time'] ?? null;

    $calories = $nutrition['calories'] ?? null;
    $summary = $nutrition['summary'] ?? null;

    $stmt->bind_param(
        "sssssssssiis",
        $recipeName,
        $ingredientList,
        $instructions,
        $cuisineType,
        $imageUrl,
        $externalId,
        $sourceApi,
        $dietLabel,
        $mealType,
        $cookingTime,
        $calories,
        $summary
    );

    if (!$stmt->execute()) {
        error_log("saveRecipeFromApiDetailed: Failed to save recipe " . $recipeName);
        return null;
    }

    $recipeId = $conn->insert_id;
    if (!$recipeId) {
        return null;
    }

    saveIngredients($recipeId, normalizeIngredients($ingredientList));

    $vocab = getGlobalVocabulary();
    $vector = computeRecipeVectorWithVocab($ingredientList, $vocab);
    if (!empty($vector)) {
        $vectorJson = json_encode($vector);
        $stmt2 = $conn->prepare(
            "REPLACE INTO tfidf_vectors (recipe_id, ingredient_vector) VALUES (?, ?)"
        );
        $stmt2->bind_param("is", $recipeId, $vectorJson);
        $stmt2->execute();
    }

    return $recipeId;
}

/**
 * Main API recommendation function
 */
function generateAPIRecommendations(
    int $userId,
    array $userIngredients,
    array $preferences,
    int $limit = 5
) {
    global $conn;
    
    error_log("=== API RECOMMENDATION START ===");
    error_log("User ingredients: " . json_encode($userIngredients));
    error_log("Preferences: " . json_encode($preferences));
    
    // Step 1: Fetch from API
    $apiRecipes = fetchFromTheMealDB($userIngredients);
    
    if (empty($apiRecipes)) {
        error_log("No recipes fetched from API");
        return [];
    }
    
    error_log("Got " . count($apiRecipes) . " recipes from API");
    
    // Step 2: Generate user TF-IDF vector
    $userVector = generateUserTFIDFVector($userIngredients);
    error_log("User vector dimensions: " . count($userVector));
    
    if (empty($userVector)) {
        return [];
    }
    
    // Step 3: Score API recipes (no DB saves here)
    $recommendations = [];
    $vocab = getGlobalVocabulary();
    
    foreach ($apiRecipes as $meal) {
        $recipeData = extractRecipeData($meal);
        
        $recipeVector = computeRecipeVectorWithVocab(
            $recipeData['ingredient_list'],
            $vocab
        );

        $similarity = empty($recipeVector)
            ? 0
            : cosineSimilarity($userVector, $recipeVector);

        $externalId = $recipeData['external_id'] ?? uniqid('meal_', true);

        $recommendations[$externalId] = [
            'recipe' => $recipeData,
            'similarity_score' => $similarity,
            'recipe_id' => $externalId
        ];
    }

    error_log("Scored " . count($recommendations) . " API recipes (no DB saves)");
    
    // Step 4: Apply filters
    $ranked = [];
    foreach ($recommendations as $recipeId => $item) {
        $ranked[$recipeId] = $item['similarity_score'];
    }
    
    $filtered = applyFiltersForAPI($ranked, $preferences, $recommendations);
    error_log("After filtering: " . count($filtered) . " recipes");
    
    // Step 5: Sort by similarity and limit
    arsort($filtered);
    $final = array_slice($filtered, 0, $limit, true);
    
    // Step 6: Build result array with nested structure matching results.php expectations
    $results = [];
    foreach ($final as $recipeKey => $score) {
        if (isset($recommendations[$recipeKey])) {
            $recipeData = $recommendations[$recipeKey]['recipe'];
            $results[] = [
                'recipe' => [
                    'recipe_id' => $recipeData['external_id'],
                    'recipe_name' => $recipeData['recipe_name'],
                    'ingredient_list' => $recipeData['ingredient_list'],
                    'instructions' => $recipeData['instructions'],
                    'cuisine_type' => $recipeData['cuisine_type'],
                    'image_url' => $recipeData['image_url'],
                    'source_api' => $recipeData['source_api'],
                    'external_id' => $recipeData['external_id'],
                    'calories' => 'N/A',
                    'cooking_time' => 'N/A'
                ],
                'similarity_score' => $score
            ];
        }
    }
    
    error_log("Returning " . count($results) . " recommendations");
    error_log("=== API RECOMMENDATION END ===");
    
    return $results;
}

/**
 * Apply filters to API recommendations
 */
function applyFiltersForAPI(array $rankedRecipes, array $preferences, array $recipeDetails) {
    $filtered = [];
    
    foreach ($rankedRecipes as $recipeId => $score) {
        $recipe = $recipeDetails[$recipeId]['recipe'] ?? null;
        if (!$recipe) continue;
        
        $filtered[$recipeId] = $score;
    }
    
    return $filtered;
}
