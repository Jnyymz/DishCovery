<?php
require_once __DIR__ . '/../core/auth.php';

requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How It Works - DishCovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/layout.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/layout.css'); ?>">
    <link rel="stylesheet" href="assets/css/about.css">
</head>
<body>
    <?php
    $hideMobileSidebar = true;
    include __DIR__ . '/components/navbar.php';
    ?>

    <main class="about-wrap">
        <h1 class="page-title">How It Works</h1>

        <section class="about-card">
            <div class="section">
                <h2>Overview</h2>
                <p class="lead-copy">DishCovery generates recipe recommendations by analyzing the similarity between the ingredients entered by the user and the ingredients stored in the system's recipe database. The recommendation process follows a structured, algorithm-driven workflow designed to ensure relevance, consistency, and usability.</p>
            </div>

            <div class="section">
                <h2>1. Ingredient Input</h2>
                <p>The process begins when the user enters the ingredients currently available in their pantry. Users may also apply optional filters such as:</p>
                <ul>
                    <li>Dietary preferences (e.g., vegetarian, halal, etc.)</li>
                    <li>Cuisine type</li>
                    <li>Maximum cooking time</li>
                    <li>Calorie range</li>
                </ul>
                <p>These inputs define the constraints that guide the recommendation process.</p>
            </div>

            <div class="section">
                <h2>2. Text Preprocessing and Normalization</h2>
                <p>Before similarity computation, the system standardizes both user input and recipe ingredient data to ensure consistent comparison. The preprocessing stage includes:</p>
                <ul>
                    <li>Converting all text to lowercase</li>
                    <li>Removing punctuation and irrelevant symbols</li>
                    <li>Eliminating common stop-words</li>
                    <li>Applying synonym mapping</li>
                    <li>Performing simple singular–plural harmonization</li>
                </ul>
                <p>This normalization reduces lexical variation and ensures that similar ingredients are treated consistently during analysis.</p>
            </div>

            <div class="section">
                <h2>3. Feature Representation using TF-IDF</h2>
                <p>After preprocessing, the system transforms ingredient data into numerical vectors using Term Frequency–Inverse Document Frequency (TF-IDF). TF-IDF assigns weights to ingredients based on:</p>
                <ul>
                    <li>How frequently they appear in a specific recipe</li>
                    <li>How unique they are across the entire recipe dataset</li>
                </ul>
                <p>This representation ensures that distinctive ingredients contribute more significantly to similarity scoring than commonly occurring ones.</p>
            </div>

            <div class="section">
                <h2>4. Similarity Computation using Cosine Similarity</h2>
                <p>To determine which recipes are most relevant to the user's input, the system computes cosine similarity between:</p>
                <ul>
                    <li>The user's TF-IDF ingredient vector</li>
                    <li>Each recipe's TF-IDF vector in the database</li>
                </ul>
                <p>Cosine similarity measures the angle between two vectors. A higher similarity score indicates greater ingredient overlap and relevance. Recipes are then ranked in descending order based on their similarity scores.</p>
            </div>

            <div class="section">
                <h2>5. Constraint-Based Filtering</h2>
                <p>After ranking, additional filters are applied to refine the results according to user-selected constraints, such as:</p>
                <ul>
                    <li>Cooking time</li>
                    <li>Dietary classification</li>
                    <li>Calorie limits</li>
                    <li>Cuisine type</li>
                </ul>
                <p>Recipes that do not meet these conditions are excluded from the final results.</p>
            </div>

            <div class="section">
                <h2>6. Top-N Recommendation Output</h2>
                <p>The system selects the Top-5 highest-ranking recipes that satisfy the similarity threshold and filtering conditions. These recipes are displayed to the user as personalized recommendations. Each recommendation includes:</p>
                <ul>
                    <li>Recipe title</li>
                    <li>Ingredient list</li>
                    <li>Cooking instructions</li>
                    <li>Nutritional information</li>
                    <li>Similarity-based relevance</li>
                </ul>
            </div>

            <div class="section">
                <h2>7. Ingredient Substitution (Optional Enhancement)</h2>
                <p>If certain ingredients are unavailable, the system provides predefined substitute suggestions based on stored ingredient mappings in the database. This feature helps users adapt recipes without significantly altering the recommendation logic.</p>
            </div>

            <div class="section">
                <h2>8. Continuous Evaluation and User Feedback</h2>
                <p>User interactions and feedback contribute to evaluating system usability and effectiveness. While the recommendation logic remains content-based, survey-based assessments measure:</p>
                <ul>
                    <li>Perceived usefulness</li>
                    <li>Ease of use</li>
                    <li>User satisfaction</li>
                </ul>
                <p>These evaluations help assess overall system performance from both technical and user-centered perspectives.</p>
            </div>

            <div class="section">
                <h2>Summary</h2>
                <p>DishCovery combines structured text preprocessing, TF-IDF vectorization, cosine similarity computation, and constraint-based filtering to generate ingredient-focused recipe recommendations. The system emphasizes transparency, interpretability, and practical usability within a web-based environment.</p>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>