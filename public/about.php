<?php
require_once __DIR__ . '/../core/auth.php';

requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About DishCovery</title>
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
        <h1 class="page-title">About DishCovery</h1>

        <section class="about-card">
            <div class="section">
                <h2>Overview</h2>
                <p class="lead-copy">DishCovery is an ingredient-centric, web-based recipe recommender system developed as an undergraduate thesis project under the Bachelor of Science in Computer Science program at Emilio Aguinaldo College – Cavite.</p>
                <p class="lead-copy">The system is designed to assist users in discovering suitable recipes based on the ingredients they currently have available. Unlike traditional keyword-based recipe platforms, DishCovery applies a content-based filtering approach to generate personalized recommendations based on ingredient similarity.</p>
                <div class="highlight-strip" aria-label="Key highlights">
                    <span class="chip">Ingredient-Centric</span>
                    <span class="chip">Content-Based Filtering</span>
                    <span class="chip">TF-IDF + Cosine Similarity</span>
                    <span class="chip">Top-5 Recommendations</span>
                </div>
            </div>

            <div class="section">
                <h2>Purpose of the System</h2>
                <p>DishCovery aims to support efficient meal planning and reduce food waste by maximizing the use of available pantry ingredients.</p>
                <ul>
                    <li>Allows users to input available ingredients.</li>
                    <li>Applies similarity-based matching to identify relevant recipes.</li>
                    <li>Supports filtering by cuisine type, cooking time, calorie range, and dietary classification.</li>
                    <li>Provides predefined ingredient substitution suggestions.</li>
                </ul>
            </div>

            <div class="section">
                <h2>Key Features</h2>
                <div class="feature-grid">
                    <div class="feature-item">Ingredient-Based Recipe Recommendation</div>
                    <div class="feature-item">TF-IDF and Cosine Similarity Matching</div>
                    <div class="feature-item">Constraint-Based Filtering (Diet, Cuisine, Calories, Cooking Time)</div>
                    <div class="feature-item">Predefined Ingredient Substitution</div>
                    <div class="feature-item">Nutritional Information Display</div>
                    <div class="feature-item">Recipe Bookmarking</div>
                    <div class="feature-item">Administrative Dataset Management</div>
                </div>
            </div>

            <div class="section">
                <h2>Scope and Limitations</h2>
                <p>DishCovery operates as a web-based academic prototype and focuses exclusively on a content-based filtering approach. The system does not implement collaborative or hybrid recommendation models.</p>
                <p>It relies on token-based matching and predefined ingredient substitution mappings. Automatic spelling correction and advanced semantic embedding models are not currently implemented.</p>
            </div>

            <div class="section">
                <h2>Academic Context</h2>
                <p>This system was developed as part of the requirements for the degree of Bachelor of Science in Computer Science during Academic Year 2025-2026 at Emilio Aguinaldo College – Cavite.</p>
            </div>

            <div class="section">
                <h2>Developers and Adviser</h2>
                <div class="credit-grid">
                    <div class="credit-box">
                        <div class="credit-title">Developers</div>
                        <ul>
                            <li>JANIMAH G. ABDUL</li>
                            <li>LORAH JYN I. HERNANDEZ</li>
                            <li>KEN FERRARI ZETH D. SAURO</li>
                            <li>ROJEAN F. UNTALAN</li>
                        </ul>
                    </div>
                    <div class="credit-box">
                        <div class="credit-title">Research Adviser</div>
                        <p class="mb-0">Dennis S. Nava, MIT</p>
                    </div>
                </div>
                <p class="muted-small mt-3 mb-0">Academic Year 2025-2026</p>
                <p class="muted-small mb-0">Emilio Aguinaldo College – Cavite<br>School of Engineering and Technology</p>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/layout.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/layout.js'); ?>"></script>
</body>
</html>
