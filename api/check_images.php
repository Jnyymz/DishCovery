<?php
require_once __DIR__ . '/../core/dbConfig.php';

echo "Checking recipe images:\n\n";

$result = $conn->query("SELECT recipe_id, recipe_name, image_url FROM recipes LIMIT 5");

while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['recipe_id']}\n";
    echo "Name: {$row['recipe_name']}\n";
    echo "Image: " . ($row['image_url'] ?: 'NULL') . "\n";
    echo "---\n";
}
