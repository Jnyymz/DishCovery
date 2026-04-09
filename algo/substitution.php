<?php
/* =========================================================
   substitution.php
   Ingredient Substitution Logic
   DishCovery – Flexibility & Usability
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';

function normalizeIngredientName(string $value): string {
    $name = strtolower(trim($value));

    if ($name === '') {
        return '';
    }

    // Remove parenthetical notes and punctuation that do not affect ingredient identity.
    $name = preg_replace('/\([^)]*\)/u', ' ', $name);
    $name = preg_replace('/[^a-z0-9\s\/-]/u', ' ', $name);

    // Remove leading quantities like "250g", "1/2", "2", "2.5".
    $name = preg_replace('/^\s*(\d+\s*\/\s*\d+|\d+(?:\.\d+)?)(\s*[a-z]+)?\b/u', ' ', $name);

    // Remove common measurement units and prep descriptors.
    $noiseWords = [
        'g','kg','gram','grams','ml','l','tbsp','tablespoon','tablespoons','tsp','teaspoon','teaspoons',
        'cup','cups','oz','ounce','ounces','lb','lbs','pound','pounds','pinch','dash','clove','cloves',
        'slice','slices','can','cans','pack','packs','fresh','dried','ground','minced','chopped','diced',
        'large','small','medium'
    ];

    $name = preg_replace('/\b(' . implode('|', $noiseWords) . ')\b/u', ' ', $name);

    // Normalize separators/spaces.
    $name = str_replace(['/', '-'], ' ', $name);
    $name = preg_replace('/\s+/u', ' ', $name);

    return trim($name);
}

function getMissingIngredients(array $required, array $available): array {
    $requiredNormalized = [];
    foreach ($required as $ingredient) {
        $name = normalizeIngredientName((string)$ingredient);
        if ($name !== '') {
            $requiredNormalized[$name] = trim((string)$ingredient);
        }
    }

    $availableLookup = [];
    foreach ($available as $ingredient) {
        $name = normalizeIngredientName((string)$ingredient);
        if ($name !== '') {
            $availableLookup[$name] = true;
        }
    }

    $missing = [];
    foreach ($requiredNormalized as $normalized => $display) {
        if (!isset($availableLookup[$normalized])) {
            $missing[] = $display;
        }
    }

    return $missing;
}

/**
 * Get ingredient ID by name
 */
function getIngredientId($ingredientName) {
    global $conn;

    $normalized = normalizeIngredientName((string)$ingredientName);
    if ($normalized === '') {
        return null;
    }

    $candidates = [$normalized];
    $parts = explode(' ', $normalized);
    if (count($parts) >= 2) {
        $candidates[] = implode(' ', array_slice($parts, -2));
    }
    if (count($parts) >= 1) {
        $candidates[] = end($parts);
    }

    $singularCandidates = [];
    foreach ($candidates as $candidate) {
        $singular = preg_replace('/s\b/u', '', $candidate);
        if ($singular !== null && $singular !== '' && $singular !== $candidate) {
            $singularCandidates[] = $singular;
        }
    }
    $candidates = array_merge($candidates, $singularCandidates);

    $candidates = array_values(array_unique(array_filter($candidates, static fn($v) => $v !== '')));

    // 1) Exact normalized name match.
    $stmt = $conn->prepare(
        "SELECT ingredient_id FROM ingredients WHERE LOWER(TRIM(ingredient_name)) = ? LIMIT 1"
    );
    $stmt->bind_param("s", $normalized);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        return $result['ingredient_id'] ?? null;
    }

    // 2) Exact match on compact candidate forms (e.g., last two words, last word).
    foreach ($candidates as $candidate) {
        $stmt = $conn->prepare(
            "SELECT ingredient_id FROM ingredients WHERE LOWER(TRIM(ingredient_name)) = ? LIMIT 1"
        );
        $stmt->bind_param("s", $candidate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return $row['ingredient_id'] ?? null;
        }
    }

    // 3) Fallback partial match (prefer shortest match to avoid over-specific misses).
    foreach ($candidates as $candidate) {
        $like = '%' . $candidate . '%';
        $stmt = $conn->prepare(
            "SELECT ingredient_id
             FROM ingredients
             WHERE LOWER(ingredient_name) LIKE ?
             ORDER BY CHAR_LENGTH(ingredient_name) ASC
             LIMIT 1"
        );
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return $row['ingredient_id'] ?? null;
        }
    }

    return null;
}

/**
 * Suggest substitutes for a missing ingredient
 */
function suggestSubstitutes($ingredientName, $limit = 3) {
    global $conn;

    $ingredientId = getIngredientId($ingredientName);

    if (!$ingredientId) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT i.ingredient_name, s.similarity_score
         FROM ingredient_substitutions s
         JOIN ingredients i
           ON s.substitute_ingredient_id = i.ingredient_id
         WHERE s.ingredient_id = ?
         ORDER BY s.similarity_score DESC
         LIMIT ?"
    );
    $stmt->bind_param("ii", $ingredientId, $limit);
    $stmt->execute();

    $substitutes = [];
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $substitutes[] = [
            'ingredient' => $row['ingredient_name'],
            'score' => $row['similarity_score']
        ];
    }

    return $substitutes;
}

/**
 * Check missing ingredients and attach substitutions
 */
function handleIngredientSubstitution(array $required, array $available) {
    $missing = getMissingIngredients($required, $available);
    $suggestions = [];

    foreach ($missing as $ingredient) {
        $options = suggestSubstitutes($ingredient, 3);
        if (!empty($options)) {
            $suggestions[$ingredient] = $options;
        }
    }

    return $suggestions;
}
