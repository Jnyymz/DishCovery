<?php
require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/helpers.php';

/* ---------- FETCH ALL RECIPES TO RECOMPUTE FILTERS ---------- */
$result = $conn->query(
    "SELECT recipe_id, ingredient_list, instructions
     FROM recipes"
);

while ($row = $result->fetch_assoc()) {
    /* ---------- INFER FILTERS USING SHARED FUNCTION ---------- */
    $filters = inferRecipeFilters($row['ingredient_list'], $row['instructions']);

    /* ---------- UPDATE DB ---------- */
    $stmt = $conn->prepare(
        "UPDATE recipes
         SET diet_label=?, meal_type=?, cooking_time=?
         WHERE recipe_id=?"
    );
    $stmt->bind_param(
        "ssii",
        $filters['diet_label'],
        $filters['meal_type'],
        $filters['cooking_time'],
        $row['recipe_id']
    );
    $stmt->execute();
}

echo json_encode(["status" => "Filters computed successfully"]);
