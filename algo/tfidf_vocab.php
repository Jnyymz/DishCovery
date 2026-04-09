<?php
/* =========================================================
   tfidf_vocab.php
   Vocabulary Management for Consistent TF-IDF
   DishCovery – Vocabulary Consistency Layer
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/preprocess.php';

/**
 * Build and cache global vocabulary from all recipes
 * Ensures user vectors use the same vocabulary as recipe vectors
 */
function getGlobalVocabulary() {
    global $conn;

    // Try to get from cache (this would be better in Redis, but file cache works)
    $cacheFile = __DIR__ . '/../cache/vocabulary.json';
    
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }
    }

    // Build vocabulary from scratch
    $vocab = [];
    $result = $conn->query("SELECT ingredient_list FROM recipes");

    while ($row = $result->fetch_assoc()) {
        $ingredients = preprocessIngredients($row['ingredient_list']);
        foreach ($ingredients as $ingredient) {
            if (!isset($vocab[$ingredient])) {
                $vocab[$ingredient] = 0;
            }
            $vocab[$ingredient]++;
        }
    }

    // Cache it
    @mkdir(__DIR__ . '/../cache', 0755, true);
    file_put_contents($cacheFile, json_encode($vocab));

    return $vocab;
}

/**
 * Compute TF-IDF with consistent vocabulary
 * Maps ingredient terms to a fixed vocabulary space
 */
function computeUserTFIDFWithVocab(array $userIngredients, array $vocabulary) {
    $userIngredients = preprocessIngredients(
        implode(',', $userIngredients)
    );

    // Compute TF for user
    $tf = [];
    $totalTerms = count($userIngredients);

    foreach ($userIngredients as $term) {
        if (!isset($tf[$term])) {
            $tf[$term] = 0;
        }
        $tf[$term]++;
    }

    // Normalize
    foreach ($tf as $term => &$count) {
        $count = $count / max(1, $totalTerms);
    }

    // Build vector with full vocabulary (zeros for missing terms)
    $vector = [];
    $docCount = count($vocabulary);

    foreach (array_keys($vocabulary) as $term) {
        $tfValue = $tf[$term] ?? 0;
        
        // Compute IDF as log(docCount / (docFreq + 1))
        $idfValue = log($docCount / (($vocabulary[$term] ?? 0) + 1));
        
        $vector[$term] = $tfValue * $idfValue;
    }

    return $vector;
}

/**
 * Clear vocabulary cache
 */
function clearVocabularyCache() {
    $cacheFile = __DIR__ . '/../cache/vocabulary.json';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
}
