<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../algo/substitution.php';

requireLogin();

$user = currentUser();
$results = $_SESSION['recommendation_results'] ?? [];
$isFallback = $_SESSION['recommendation_fallback'] ?? false;
$bookmarkRecipeIds = array_flip(getUserBookmarkRecipeIds((int)$_SESSION['user_id']));
$latestRecommendationLogId = (int)($_SESSION['latest_recommendation_log_id'] ?? 0);

if (empty($results)) {
    header("Location: dashboard.php");
    exit();
}

// Store ingredients in session for substitution feature
if (!isset($_SESSION['user_ingredients'])) {
    $_SESSION['user_ingredients'] = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results | DishCovery</title>

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

<div class="main-content">

    <div class="container-fluid pt-2">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="text-white mb-0">Recommended Recipes</h4>
            <?php if ($isFallback): ?>
                <span class="badge bg-warning text-dark">Fallback results</span>
            <?php endif; ?>
        </div>

        <div class="row g-4">

            <?php foreach ($results as $item): ?>

                <?php
                $recipe = $item['recipe'];
                $score = round($item['similarity_score'], 3);

                $requiredIngredients = array_map(
                    'trim',
                    explode(',', $recipe['ingredient_list'])
                );

                $userIngredients = $_SESSION['user_ingredients'] ?? [];
                $missingIngredients = getMissingIngredients($requiredIngredients, $userIngredients);
                $missingCount = count($missingIngredients);

                $substitutions = handleIngredientSubstitution(
                    $requiredIngredients,
                    $userIngredients
                );
                ?>

                <div class="col-xl-4 col-lg-6">
                    <div class="recipe-card listing-card h-100" data-id="<?php echo (int)$recipe['recipe_id']; ?>" data-recommended="1" data-log-id="<?php echo $latestRecommendationLogId; ?>" role="link" tabindex="0" aria-label="View <?php echo htmlspecialchars($recipe['recipe_name']); ?> recipe details">

                        <div class="recipe-image-wrapper">
                            <?php if (!empty($recipe['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>"
                                     class="recipe-img" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/400x250?text=No+Image"
                                     class="recipe-img" alt="No image">
                            <?php endif; ?>

                            <?php $isSaved = isset($bookmarkRecipeIds[(int)$recipe['recipe_id']]); ?>
                            <button type="button" class="save-icon <?php echo $isSaved ? 'is-saved' : ''; ?>" data-recipe-id="<?php echo (int)$recipe['recipe_id']; ?>" aria-label="Save recipe">
                                <i class="bi <?php echo $isSaved ? 'bi-bookmark-heart-fill' : 'bi-bookmark-heart'; ?>"></i>
                            </button>
                        </div>

                        <div class="recipe-body">
                            <h6 class="recipe-title mb-2">
                                <?php echo htmlspecialchars($recipe['recipe_name']); ?>
                            </h6>

                            <div class="small text-light mb-2">
                                <?php if ($score > 0): ?>
                                    <span class="me-2">Score: <?php echo $score; ?></span>
                                <?php endif; ?>
                                <span class="me-2"><?php echo $recipe['calories']; ?> kcal</span>
                                <span><?php echo $recipe['cooking_time']; ?> mins</span>
                            </div>

                            <div class="small text-light mb-2">
                                <?php echo htmlspecialchars($recipe['cuisine_type']); ?>
                                <?php if (!empty($recipe['diet_label'])): ?>
                                    · <?php echo htmlspecialchars($recipe['diet_label']); ?>
                                <?php endif; ?>
                            </div>

                            <div class="small mb-2 suggestion-count">
                                <?php if ($missingCount > 0): ?>
                                    Missing <?php echo (int)$missingCount; ?> ingredient<?php echo $missingCount === 1 ? '' : 's'; ?>
                                <?php else: ?>
                                    Complete ingredients
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($substitutions)): ?>
                                <div class="small text-warning mb-2">
                                    Available substitution suggestions
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($substitutions)): ?>
                                <div class="small text-warning">
                                    <?php foreach ($substitutions as $missing => $options): ?>
                                        <?php if (!empty($options)): ?>
                                            <div>
                                                Missing <?php echo htmlspecialchars($missing); ?> →
                                                <?php foreach ($options as $opt): ?>
                                                        <?php echo htmlspecialchars($opt['ingredient']); ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/layout.js'); ?>"></script>
</body>
</html>
