<?php
/* =========================================================
   tfidf.php
   TF-IDF Vectorization
   DishCovery – Content-Based Filtering
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/preprocess.php';
require_once __DIR__ . '/tfidf_vocab.php';

/**
 * Calculate Term Frequency (TF)
 */
function computeTF(array $terms) {
    $tf = [];
    $totalTerms = count($terms);

    foreach ($terms as $term) {
        if (!isset($tf[$term])) {
            $tf[$term] = 0;
        }
        $tf[$term]++;
    }

    // Normalize
    foreach ($tf as $term => $count) {
        $tf[$term] = $count / $totalTerms;
    }

    return $tf;
}

/**
 * Calculate Inverse Document Frequency (IDF)
 */
function computeIDF(array $documents) {
    $idf = [];
    $docCount = count($documents);

    foreach ($documents as $doc) {
        foreach (array_unique($doc) as $term) {
            if (!isset($idf[$term])) {
                $idf[$term] = 0;
            }
            $idf[$term]++;
        }
    }

    foreach ($idf as $term => $count) {
        $idf[$term] = log($docCount / ($count + 1));
    }

    return $idf;
}

/**
 * Generate TF-IDF vector
 */
function computeTFIDF(array $tf, array $idf) {
    $tfidf = [];

    foreach ($tf as $term => $tfValue) {
        $tfidf[$term] = $tfValue * ($idf[$term] ?? 0);
    }

    return $tfidf;
}

/**
 * Build TF-IDF vectors for all recipes
 * Stores serialized vectors in tfidf_vectors table
 * Uses consistent vocabulary across all vectors
 */
function buildRecipeTFIDFVectors() {
    global $conn;

    error_log("buildRecipeTFIDFVectors: Starting");

    // Step 1: Build global vocabulary
    $vocabulary = [];
    $result = $conn->query("SELECT recipe_id, ingredient_list FROM recipes");

    $recipeMap = [];
    while ($row = $result->fetch_assoc()) {
        $ingredients = preprocessIngredients($row['ingredient_list']);
        $recipeMap[$row['recipe_id']] = $ingredients;
        
        // Build vocabulary
        foreach ($ingredients as $ingredient) {
            if (!isset($vocabulary[$ingredient])) {
                $vocabulary[$ingredient] = 0;
            }
            $vocabulary[$ingredient]++;
        }
    }

    error_log("buildRecipeTFIDFVectors: Vocabulary has " . count($vocabulary) . " terms");
    error_log("buildRecipeTFIDFVectors: Processing " . count($recipeMap) . " recipes");

    // Step 2: Build vectors using consistent vocabulary
    $docCount = count($recipeMap);

    foreach ($recipeMap as $recipeId => $ingredients) {
        // Compute TF
        $tf = [];
        $totalTerms = count($ingredients);

        foreach ($ingredients as $term) {
            if (!isset($tf[$term])) {
                $tf[$term] = 0;
            }
            $tf[$term]++;
        }

        // Normalize TF
        foreach ($tf as $term => &$count) {
            $count = $count / max(1, $totalTerms);
        }

        // Build full vector with all vocabulary terms
        $tfidfVector = [];
        foreach (array_keys($vocabulary) as $term) {
            $tfValue = $tf[$term] ?? 0;
            $idfValue = log($docCount / (($vocabulary[$term] ?? 0) + 1));
            $tfidfVector[$term] = $tfValue * $idfValue;
        }

        $serializedVector = json_encode($tfidfVector);

        // Insert or update TF-IDF vector
        $stmt = $conn->prepare(
            "REPLACE INTO tfidf_vectors (recipe_id, ingredient_vector)
             VALUES (?, ?)"
        );
        $stmt->bind_param("is", $recipeId, $serializedVector);
        $stmt->execute();

        error_log("Processed recipe $recipeId with " . count($tfidfVector) . " dimensions");
    }

    // Cache the vocabulary
    @mkdir(__DIR__ . '/../cache', 0755, true);
    file_put_contents(__DIR__ . '/../cache/vocabulary.json', json_encode($vocabulary));

    error_log("buildRecipeTFIDFVectors: Complete. Cached vocabulary.");
}

/**
 * Generate TF-IDF vector for user input
 * Uses consistent vocabulary from all recipes
 */
function generateUserTFIDFVector(array $userIngredients) {
    // Get global vocabulary to ensure consistent vector space
    $vocabulary = getGlobalVocabulary();

    if (empty($vocabulary)) {
        error_log("generateUserTFIDFVector: Empty vocabulary!");
        return [];
    }

    error_log("generateUserTFIDFVector: Vocabulary has " . count($vocabulary) . " terms");

    $normalizeToken = function (string $token): string {
        $token = strtolower(trim($token));
        if ($token !== '' && strlen($token) > 3 && substr($token, -1) === 's') {
            $token = substr($token, 0, -1);
        }
        return $token;
    };

    // Normalize user ingredient tokens (singular/plural harmonization).
    $userTokens = preprocessIngredients(implode(',', $userIngredients));
    $normalizedUserTokens = [];
    foreach ($userTokens as $token) {
        $normalized = $normalizeToken((string)$token);
        if ($normalized !== '') {
            $normalizedUserTokens[] = $normalized;
        }
    }

    // Compute normalized TF from user input.
    $normalizedTF = [];
    $totalTerms = count($normalizedUserTokens);
    foreach ($normalizedUserTokens as $term) {
        if (!isset($normalizedTF[$term])) {
            $normalizedTF[$term] = 0;
        }
        $normalizedTF[$term]++;
    }
    foreach ($normalizedTF as $term => &$count) {
        $count = $count / max(1, $totalTerms);
    }
    unset($count);

    // Normalize recipe vocabulary terms the same way, then project back to original vocab keys.
    $docCount = count($vocabulary);
    $userVector = [];
    foreach ($vocabulary as $originalTerm => $docFreq) {
        $normalizedTerm = $normalizeToken((string)$originalTerm);
        $tfValue = $normalizedTF[$normalizedTerm] ?? 0;
        $idfValue = log($docCount / ((float)$docFreq + 1));
        $userVector[$originalTerm] = $tfValue * $idfValue;
    }

    error_log("generateUserTFIDFVector: User vector has " . count($userVector) . " dimensions");

    return $userVector;
}
