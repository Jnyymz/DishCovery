<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/models.php';

requireLogin();

// DEBUG
error_log("=== DASHBOARD PAGE LOAD ===");
error_log("SESSION data: " . json_encode($_SESSION));

$recommendations = $_SESSION['recommendation_results'] ?? [];
$isFallback = $_SESSION['recommendation_fallback'] ?? false;
$showRecommendations = !empty($recommendations);

// Determine if user submitted search/filters
$hasSubmittedSearch = !empty($_SESSION['ingredients_input']);

error_log("showRecommendations: " . ($showRecommendations ? 'true' : 'false'));
error_log("hasSubmittedSearch: " . ($hasSubmittedSearch ? 'true' : 'false'));
error_log("Recommendations count: " . count($recommendations));

// Load random recipes ONLY on first page load (no search submitted)
if (!$showRecommendations && !$hasSubmittedSearch) {
    $recipes = getAllRecipes();
    shuffle($recipes);
    $recipes = array_slice($recipes, 0, 25);
} else {
    $recipes = [];
}

$selectedCuisine = $_SESSION['selected_cuisine_preference'] ?? '';
$selectedDiet = $_SESSION['selected_diet_type'] ?? '';
$selectedMeal = $_SESSION['selected_meal_type'] ?? '';
$selectedTime = $_SESSION['selected_max_cooking_time'] ?? '';
$selectedCalories = $_SESSION['selected_max_calories'] ?? '';
$enteredIngredients = $_SESSION['ingredients_input'] ?? '';
$userPreferences = getUserPreferences($_SESSION['user_id']);

// Pass submitted SESSION values to sidebar for server-side active state rendering
$submittedCuisine = strtolower($_SESSION['selected_cuisine_preference'] ?? '');
$submittedDiet = strtolower($_SESSION['selected_diet_type'] ?? '');
$submittedMeal = strtolower($_SESSION['selected_meal_type'] ?? '');
$submittedTime = (string)($_SESSION['selected_max_cooking_time'] ?? '');
$submittedCalories = (string)($_SESSION['selected_max_calories'] ?? '');
$latestRecommendationLogId = (int)($_SESSION['latest_recommendation_log_id'] ?? 0);
$bookmarkRecipeIds = array_flip(getUserBookmarkRecipeIds((int)$_SESSION['user_id']));
$dashboardSearchQuery = trim((string)($_GET['q'] ?? ''));
$isDbSearchMode = $dashboardSearchQuery !== '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

if ($isDbSearchMode) {
    $recipes = searchRecipesInDatabase($dashboardSearchQuery, $perPage * $page);
    $recipes = array_slice($recipes, ($page - 1) * $perPage, $perPage);
    $showRecommendations = false;
    $hasSubmittedSearch = false;
    $isFallback = false;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DishCovery</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/layout.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/layout.css'); ?>">
</head>
<body>

<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main-content">

    <div class="container-fluid pt-2">

        <div class="dashboard-grid-wrap">

        <div class="dashboard-header-row mb-3">
            <h4 class="text-white mb-0">
                <?php 
                if ($isDbSearchMode) {
                    echo 'Search Results';
                } elseif ($showRecommendations) {
                    echo 'Recommended Recipes';
                } elseif ($hasSubmittedSearch) {
                    echo 'Search Results';
                } else {
                    echo 'Explore Recipes';
                }
                ?>
            </h4>

            <div class="dashboard-header-actions">
                <?php if ($showRecommendations && $isFallback): ?>
                    <span class="badge bg-warning text-dark">Fallback results</span>
                <?php endif; ?>

                <form method="GET" action="dashboard.php" class="dashboard-search" role="search" aria-label="Search recipes">
                    <button type="submit" class="dashboard-search-btn" aria-label="Search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                    <input
                        type="text"
                        id="dashboardRecipeSearch"
                        name="q"
                        placeholder="Search recipes"
                        value="<?php echo htmlspecialchars($dashboardSearchQuery); ?>"
                        aria-label="Search recipes"
                    >
                    <?php if ($isDbSearchMode): ?>
                        <a href="dashboard.php" class="dashboard-search-clear" aria-label="Clear search">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($showRecommendations): ?>
            <div class="row mb-2">
                <div class="col-12">
                    <div class="recipe-card suggestion-card">
                        <div class="recipe-body text-center">
                            <p class="suggestion-summary mb-0">Results are ranked based on overall relevance. Some results may not exactly match all filters but are considered the closest matches.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 g-4 dashboard-recipe-grid dashboard-limit-five-rows">

            <?php if ($showRecommendations): ?>
                <!-- Display recommendations -->
                <?php foreach ($recommendations as $item): ?>

                    <?php
                    $recipe = $item['recipe'];
                    $score = round($item['similarity_score'], 3);

                    ?>

                    <div class="col">
                        <div class="recipe-card listing-card h-100" data-id="<?php echo (int)$recipe['recipe_id']; ?>" data-recommended="1" data-log-id="<?php echo $latestRecommendationLogId; ?>" role="link" tabindex="0" aria-label="View <?php echo htmlspecialchars($recipe['recipe_name']); ?> recipe details">

                            <div class="recipe-image-wrapper">

                                <?php if (!empty($recipe['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>"
                                         class="recipe-img">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/400x250?text=No+Image"
                                         class="recipe-img">
                                <?php endif; ?>

                                <?php $isSaved = isset($bookmarkRecipeIds[(int)$recipe['recipe_id']]); ?>
                                <button type="button" class="save-icon <?php echo $isSaved ? 'is-saved' : ''; ?>" data-recipe-id="<?php echo (int)$recipe['recipe_id']; ?>" aria-label="Save recipe">
                                    <i class="bi <?php echo $isSaved ? 'bi-bookmark-heart-fill' : 'bi-bookmark-heart'; ?>"></i>
                                </button>

                            </div>

                            <div class="recipe-body">
                                <h6 class="recipe-title mb-1">
                                    <?php echo htmlspecialchars($recipe['recipe_name']); ?>
                                </h6>

                                <?php if ($score > 0): ?>
                                    <div class="small text-light">
                                        Score: <?php echo $score; ?>
                                    </div>
                                <?php endif; ?>

                            </div>

                        </div>
                    </div>

                <?php endforeach; ?>

            <?php elseif ($hasSubmittedSearch): ?>
                <!-- User submitted search but got no results -->
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        <strong>No recipes found</strong> matching your search criteria and filters.
                        <br/>
                        Try adjusting your ingredients, filters, or <a href="../core/handleForms.php?clear_filters=1" class="alert-link">clearing all filters</a>.
                    </div>
                </div>

            <?php else: ?>
                <?php if ($isDbSearchMode && empty($recipes)): ?>
                    <div class="col-12">
                        <div class="alert alert-warning" role="alert">
                            <i class="bi bi-search"></i>
                            <strong>No recipes found</strong> in the database for "<?php echo htmlspecialchars($dashboardSearchQuery); ?>".
                        </div>
                    </div>
                <?php else: ?>
                    <!-- DB search results or first page random recipes -->
                    <?php foreach ($recipes as $recipe): ?>

                        <div class="col">
                            <div class="recipe-card listing-card h-100" data-id="<?php echo (int)$recipe['recipe_id']; ?>" role="link" tabindex="0" aria-label="View <?php echo htmlspecialchars($recipe['recipe_name']); ?> recipe details">

                                <div class="recipe-image-wrapper">

                                    <?php if (!empty($recipe['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>"
                                             class="recipe-img">
                                    <?php else: ?>
                                        <img src="https://via.placeholder.com/400x250?text=No+Image"
                                             class="recipe-img">
                                    <?php endif; ?>

                                    <?php $isSaved = isset($bookmarkRecipeIds[(int)$recipe['recipe_id']]); ?>
                                    <button type="button" class="save-icon <?php echo $isSaved ? 'is-saved' : ''; ?>" data-recipe-id="<?php echo (int)$recipe['recipe_id']; ?>" aria-label="Save recipe">
                                        <i class="bi <?php echo $isSaved ? 'bi-bookmark-heart-fill' : 'bi-bookmark-heart'; ?>"></i>
                                    </button>

                                </div>

                                <div class="recipe-body">
                                    <h6 class="recipe-title">
                                        <?php echo htmlspecialchars($recipe['recipe_name']); ?>
                                    </h6>

                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

        </div>

        <!-- Loading Overlay (Recipe Grid Only) -->
        <div id="loading-overlay" class="loading-overlay" style="display: none;">
            <div class="loading-content">
                <div class="spinner-border text-light" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-light">Processing...</p>
            </div>
        </div>

        </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/layout.js'); ?>"></script>
<script>
    // Show loading on dashboard interactions only.
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form.dashboard-search, .sidebar form, .mobile-sidebar form');
        const loadingOverlay = document.getElementById('loading-overlay');
        const gridWrap = document.querySelector('.dashboard-grid-wrap');

        forms.forEach(form => {
            form.addEventListener('submit', function() {
                if (window.innerWidth <= 991) {
                    const navbarContent = document.getElementById('navbarContent');
                    if (navbarContent && navbarContent.classList.contains('show') && window.bootstrap && window.bootstrap.Collapse) {
                        window.bootstrap.Collapse.getOrCreateInstance(navbarContent, { toggle: false }).hide();
                    }
                }

                if (loadingOverlay) {
                    loadingOverlay.style.display = 'flex';
                }
                if (gridWrap) {
                    gridWrap.classList.add('is-loading');
                }
            });
        });
    });
</script>
</body>
</html>
