<?php
/* =========================================================
   preprocess.php
   Data Preprocessing & Ingredient Normalization
   DishCovery – Content-Based Filtering
   ========================================================= */

function normalizeIngredientSynonyms(string $ingredient): string {
    $normalized = strtolower(trim($ingredient));

    $patterns = [
        '/\bprawns?\b/' => 'shrimp',
        '/\bbell\s+peppers?\b/' => 'capsicum',
        '/\bcapsicums?\b/' => 'capsicum',
        '/\bground\s+pork\b/' => 'minced pork',
        '/\bminced\s+pork\b/' => 'minced pork',
    ];

    foreach ($patterns as $pattern => $replacement) {
        $normalized = preg_replace($pattern, $replacement, $normalized);
    }

    return $normalized;
}

/**
 * Normalize raw ingredient text
 * - lowercase
 * - remove special characters and quantities
 * - extract just the ingredient name
 * - trim whitespace
 */
function normalizeIngredient($ingredient) {
    $ingredient = strtolower($ingredient);

    // Remove bracketed notes
    $ingredient = preg_replace('/\([^)]*\)/', ' ', $ingredient);

    // Remove leading quantities/fractions
    $ingredient = preg_replace('/^\s*[\d\/.\-¼½¾⅓⅔\s]+/', '', $ingredient);

    // Normalize known ingredient synonyms before token cleanup
    $ingredient = normalizeIngredientSynonyms($ingredient);
    
    // Remove common quantity words at the start
    $quantityWords = ['cup', 'cups', 'tbsp', 'tsp', 'oz', 'grams', 'gram', 'g', 'kg', 'l', 'ml',
                      'lb', 'lbs', 'pound', 'pounds',
                      'pinch', 'handful', 'clove', 'cloves', 'tablesp', 'teaspoon',
                      'tablespoon', 'tablespoons', 'tblsp', 'tbs', 'large', 'small',
                      'medium', 'finely', 'thinly', 'chopped', 'sliced', 'ground', 'minced', 'diced',
                      'fresh', 'dried', 'optional', 'to taste'];
    
    foreach ($quantityWords as $word) {
        // Remove quantity/descriptor word anywhere as full word
        $ingredient = preg_replace('/\b' . preg_quote($word, '/') . '\b/', ' ', $ingredient);
    }
    
    // Remove special characters but keep spaces
    $ingredient = preg_replace('/[^a-z\s]/', '', $ingredient);
    
    // Remove extra whitespace
    $ingredient = preg_replace('/\s+/', ' ', trim($ingredient));
    
    return trim($ingredient);
}

/**
 * Tokenize ingredient list (comma-separated)
 */
function tokenizeIngredients($ingredientList) {
    $tokens = explode(',', $ingredientList);
    $cleaned = [];

    $wordStop = [
        'and', 'or', 'with', 'of', 'for', 'in', 'to', 'taste', 'extra', 'virgin',
        'oil', 'sauce', 'stock', 'powder', 'paste', 'water', 'salt', 'pepper'
    ];

    foreach ($tokens as $token) {
        $normalized = normalizeIngredient($token);
        if (!empty($normalized)) {
            $cleaned[] = $normalized;

            // Add meaningful individual words for better single-ingredient matching
            $words = preg_split('/\s+/', $normalized);
            foreach ($words as $word) {
                $word = trim($word);
                if ($word === '' || strlen($word) < 3 || in_array($word, $wordStop, true)) {
                    continue;
                }
                $cleaned[] = $word;
            }
        }
    }

    return array_unique($cleaned);
}

/**
 * Remove stop words (common cooking fillers)
 */
function removeStopWords(array $ingredients) {
    $stopWords = [
        'fresh', 'chopped', 'sliced', 'ground',
        'optional', 'to taste', 'and', 'or',
        'cup', 'cups', 'tbsp', 'tsp'
    ];

    return array_values(array_filter($ingredients, function ($item) use ($stopWords) {
        return !in_array($item, $stopWords);
    }));
}

/**
 * Full preprocessing pipeline
 * Returns a clean array of ingredients
 */
function preprocessIngredients($ingredientList) {
    $tokens = tokenizeIngredients($ingredientList);
    $filtered = removeStopWords($tokens);
    return $filtered;
}

/**
 * Convert ingredient array back to string
 * (used before TF-IDF vectorization)
 */
function ingredientsToString(array $ingredients) {
    return implode(' ', $ingredients);
}
