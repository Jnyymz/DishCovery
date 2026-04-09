<?php
require_once __DIR__ . "/../core/dbConfig.php";
require_once __DIR__ . '/helpers.php';

$results = $conn->query(
    "SELECT recipe_id, ingredient_list, instructions
     FROM recipes
     WHERE diet_label IS NULL
        OR meal_type IS NULL
        OR cooking_time IS NULL"
);

$updated = 0;
while ($row = $results->fetch_assoc()) {
    /* ---------- INFER FILTERS USING SHARED FUNCTION ---------- */
    $filters = inferRecipeFilters($row['ingredient_list'], $row['instructions'] ?? '');

    /* ---------- UPDATE DB WITH NULL COALESCING ---------- */
    $stmt = $conn->prepare(
        "UPDATE recipes
         SET diet_label = IFNULL(diet_label, ?),
             meal_type = IFNULL(meal_type, ?),
             cooking_time = IFNULL(cooking_time, ?)
         WHERE recipe_id = ?"
    );
    $stmt->bind_param("ssii", $filters['diet_label'], $filters['meal_type'], $filters['cooking_time'], $row['recipe_id']);
    $stmt->execute();

    $updated++;
}

echo json_encode([
    "status" => "backfill complete",
    "updated" => $updated
]);
