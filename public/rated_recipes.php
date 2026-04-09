<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/models.php';

requireLogin();

$ratedRecipes = getUserRatedRecipes((int)$_SESSION['user_id']);
$bookmarkRecipeIds = array_flip(getUserBookmarkRecipeIds((int)$_SESSION['user_id']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rated Recipes | DishCovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/layout.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/layout.css'); ?>">
</head>
<body>

<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid pt-2">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="text-white mb-0">My Rated Recipes</h4>
            <span class="badge bg-light text-dark"><?php echo count($ratedRecipes); ?> rated</span>
        </div>

        <?php if (empty($ratedRecipes)): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-star me-2"></i>
                You have not rated any recipes yet.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($ratedRecipes as $recipe): ?>
                    <?php
                    $isSaved = isset($bookmarkRecipeIds[(int)$recipe['recipe_id']]);
                    $ratingValue = max(1, min(5, (int)($recipe['rating'] ?? 0)));
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="recipe-card listing-card h-100" data-id="<?php echo (int)$recipe['recipe_id']; ?>" role="link" tabindex="0" aria-label="View <?php echo htmlspecialchars($recipe['recipe_name']); ?> recipe details">
                            <div class="recipe-image-wrapper">
                                <?php if (!empty($recipe['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" class="recipe-img" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/400x250?text=No+Image" class="recipe-img" alt="No image">
                                <?php endif; ?>

                                <button type="button" class="save-icon <?php echo $isSaved ? 'is-saved' : ''; ?>" data-recipe-id="<?php echo (int)$recipe['recipe_id']; ?>" aria-label="Save recipe">
                                    <i class="bi <?php echo $isSaved ? 'bi-bookmark-heart-fill' : 'bi-bookmark-heart'; ?>"></i>
                                </button>
                            </div>

                            <div class="recipe-body">
                                <h6 class="recipe-title mb-1"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h6>
                                <div class="rated-card-stars" aria-label="Your rating: <?php echo $ratingValue; ?> out of 5">
                                    <?php for ($star = 1; $star <= 5; $star++): ?>
                                        <i class="bi <?php echo $star <= $ratingValue ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/layout.js'); ?>"></script>
</body>
</html>
