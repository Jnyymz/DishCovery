<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/models.php';
require_once __DIR__ . '/../algo/substitution.php';

requireLogin();

$recipeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($recipeId <= 0) {
    header("Location: dashboard.php?error=recipe_not_found");
    exit();
}

$recipe = getRecipeById($recipeId);
if (!$recipe) {
    header("Location: dashboard.php?error=recipe_not_found");
    exit();
}

$ingredientLines = array_map('trim', explode(',', $recipe['ingredient_list']));

$nutritionSummary = [];
if (!empty($recipe['nutritional_summary'])) {
    $decoded = json_decode($recipe['nutritional_summary'], true);
    if (is_array($decoded)) {
        $nutritionSummary = $decoded;
    }
}

$requiredIngredients = array_map('trim', explode(',', $recipe['ingredient_list']));
$userIngredients = $_SESSION['user_ingredients'] ?? [];
$missingIngredients = getMissingIngredients($requiredIngredients, $userIngredients);
$missingCount = count($missingIngredients);
$substitutions = handleIngredientSubstitution($requiredIngredients, $userIngredients);
$isSavedRecipe = isRecipeBookmarkedByUser((int)$_SESSION['user_id'], (int)$recipe['recipe_id']);
$currentUserRating = getUserRecipeLatestRating((int)$_SESSION['user_id'], (int)$recipe['recipe_id']);

$source = trim((string)($_GET['source'] ?? ''));
$allowRating = $source !== 'dashboard_random';

$similarityScore = null;
if (!empty($_SESSION['recommendation_results']) && is_array($_SESSION['recommendation_results'])) {
    foreach ($_SESSION['recommendation_results'] as $item) {
        $candidateId = $item['recipe']['recipe_id'] ?? null;
        if ((int)$candidateId === (int)$recipe['recipe_id']) {
            $similarityScore = isset($item['similarity_score']) ? round((float)$item['similarity_score'], 3) : null;
            break;
        }
    }
}

$calorieDisplay = $recipe['calories'] ?? null;
$nutritionRows = [];

if (!empty($nutritionSummary)) {
    foreach ($nutritionSummary as $key => $value) {
        if (stripos((string)$key, 'calorie') !== false) {
            if (empty($calorieDisplay)) {
                $calorieDisplay = $value;
            }
            continue;
        }
        $nutritionRows[] = ['label' => $key, 'value' => $value];
    }
}

$instructionText = trim((string)($recipe['instructions'] ?? ''));
$processSteps = preg_split('/\r\n|\r|\n/', $instructionText);
$processSteps = array_values(array_filter(array_map('trim', $processSteps), static fn($step) => $step !== ''));

if (count($processSteps) <= 1 && $instructionText !== '') {
    $splitByNumber = preg_split('/\s*(?=\d+\s*[\.)\-:])/', $instructionText);
    $splitByNumber = array_values(array_filter(array_map('trim', $splitByNumber), static fn($step) => $step !== ''));

    if (count($splitByNumber) > 1) {
        $processSteps = $splitByNumber;
    } else {
        $splitBySentence = preg_split('/(?<=[.!?])\s+/', $instructionText);
        $processSteps = array_values(array_filter(array_map('trim', $splitBySentence), static fn($step) => $step !== ''));
    }
}

$processSteps = array_values(array_filter(array_map(static function ($step) {
    $cleaned = preg_replace('/^(?:\d+\s*[\.|\)|\-|:]\s*)+/', '', (string)$step);
    return trim((string)$cleaned);
}, $processSteps), static fn($step) => $step !== '' && !preg_match('/^\d+$/', $step)));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe['recipe_name']); ?> | DishCovery</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/layout.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/layout.css'); ?>">
</head>
<body>

<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/sidebar.php'; ?>

<div class="main-content recipe-detail-page">
    <div class="container-fluid pt-2">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="text-white mb-0">Recipe Details</h4>
            <a class="btn btn-outline-light btn-sm" href="dashboard.php">Back to Dashboard</a>
        </div>

        <section class="recipe-section mb-4">
            <div class="recipe-card recipe-hero-card p-3 p-lg-4">
                <div class="row g-3 align-items-stretch">
                    <div class="col-xl-4 col-lg-5">
                        <div class="recipe-hero-image-wrap h-100">
                            <?php if (!empty($recipe['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>"
                                     class="recipe-hero-img"
                                     alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>"
                                     onerror="this.src='../img/no-image.png'">
                            <?php else: ?>
                                <img src="../img/no-image.png"
                                     class="recipe-hero-img"
                                     alt="No image">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-xl-5 col-lg-7">
                        <div class="recipe-title-stack h-100">
                            <h2 class="recipe-hero-title mb-3"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h2>

                            <div class="recipe-meta-pills">
                                <?php if (!empty($recipe['cuisine_type'])): ?>
                                    <span class="recipe-meta-pill"><?php echo htmlspecialchars($recipe['cuisine_type']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($recipe['diet_label'])): ?>
                                    <span class="recipe-meta-pill"><?php echo htmlspecialchars($recipe['diet_label']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($recipe['meal_type'])): ?>
                                    <span class="recipe-meta-pill"><?php echo htmlspecialchars($recipe['meal_type']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($recipe['cooking_time'])): ?>
                                    <span class="recipe-meta-pill"><?php echo htmlspecialchars($recipe['cooking_time']); ?> mins</span>
                                <?php endif; ?>

                                <span class="recipe-meta-pill recipe-score-pill">
                                    Similarity: <?php echo $similarityScore !== null ? htmlspecialchars((string)$similarityScore) : 'N/A'; ?>
                                </span>
                            </div>

                            <div class="nutrition-panel mt-3">
                                <div class="nutrition-title">Nutritional</div>
                                <div class="nutrition-calories">
                                    <span class="nutrition-calories-value"><?php echo htmlspecialchars((string)($calorieDisplay ?? 'N/A')); ?></span>
                                    <span class="nutrition-calories-unit">kcal</span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-dark table-sm nutrition-table mb-0">
                                        <tbody>
                                        <?php if (!empty($nutritionRows)): ?>
                                            <?php foreach ($nutritionRows as $row): ?>
                                                <tr>
                                                    <th scope="row"><?php echo htmlspecialchars((string)$row['label']); ?></th>
                                                    <td><?php echo htmlspecialchars((string)$row['value']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <th scope="row">Protein</th>
                                                <td><?php echo htmlspecialchars((string)($recipe['protein'] ?? 'N/A')); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Carbs</th>
                                                <td><?php echo htmlspecialchars((string)($recipe['carbs'] ?? 'N/A')); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Fat</th>
                                                <td><?php echo htmlspecialchars((string)($recipe['fat'] ?? 'N/A')); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3">
                        <div class="recipe-side-stack h-100">
                            <div class="recipe-save-wrap mb-3">
                                <button type="button" class="recipe-save-btn <?php echo $isSavedRecipe ? 'is-saved' : ''; ?>" data-recipe-id="<?php echo (int)$recipe['recipe_id']; ?>" aria-label="Save recipe">
                                    <i class="bi <?php echo $isSavedRecipe ? 'bi-bookmark-heart-fill' : 'bi-bookmark-heart'; ?>"></i>
                                </button>
                                <div class="recipe-save-label">Save</div>
                            </div>

                            <?php if ($currentUserRating > 0): ?>
                                <div class="recipe-rating-wrap recipe-rating-readonly">
                                    <div class="recipe-rating-label">Your Rating</div>
                                    <div class="recipe-rating-static" aria-label="Your rating: <?php echo (int)$currentUserRating; ?> out of 5">
                                        <?php for ($star = 1; $star <= 5; $star++): ?>
                                            <i class="bi <?php echo $star <= $currentUserRating ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php elseif (!$allowRating): ?>
                                <div class="recipe-rating-wrap recipe-rating-disabled">
                                    <div class="recipe-rating-label">Rating unavailable</div>
                                    <div class="text-muted">&nbsp;</div>
                                </div>
                            <?php else: ?>
                                <form class="recipe-rating-wrap" method="post" action="../core/handleForms.php">
                                    <input type="hidden" name="submit_feedback" value="1">
                                    <input type="hidden" name="recipe_id" value="<?php echo (int)$recipe['recipe_id']; ?>">

                                    <div class="recipe-rating-label">Rate</div>
                                    <div class="recipe-rating-stars" role="radiogroup" aria-label="Rate this recipe">
                                        <?php for ($star = 5; $star >= 1; $star--): ?>
                                            <input
                                                type="radio"
                                                id="rating-<?php echo $star; ?>"
                                                name="rating"
                                                value="<?php echo $star; ?>"
                                                required
                                            >
                                            <label for="rating-<?php echo $star; ?>" title="<?php echo $star; ?> star<?php echo $star === 1 ? '' : 's'; ?>">
                                                <i class="bi bi-star-fill"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    <button type="submit" class="btn btn-sm recipe-rating-submit">Submit</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="recipe-section mb-4">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="recipe-card h-100">
                        <div class="recipe-body recipe-detail-body">
                            <h5 class="recipe-section-title">Ingredients</h5>
                            <ul class="ingredient-list mb-0">
                                <?php foreach ($ingredientLines as $line): ?>
                                    <li><?php echo htmlspecialchars($line); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="recipe-card h-100">
                        <div class="recipe-body recipe-detail-body">
                            <h5 class="recipe-section-title">Process</h5>

                            <?php if (!empty($processSteps)): ?>
                                <ol class="process-list mb-0">
                                    <?php foreach ($processSteps as $step): ?>
                                        <li><?php echo htmlspecialchars($step); ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php else: ?>
                                <p class="text-light mb-0">No instructions available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="recipe-section mb-3">
            <div class="recipe-card suggestion-card">
                <div class="recipe-body recipe-detail-body">
                    <h5 class="recipe-section-title mb-3">Suggestion</h5>

                    <div class="suggestion-summary mb-3">
                        <?php if ($missingCount > 0): ?>
                            You are missing <strong><?php echo (int)$missingCount; ?></strong>
                            ingredient<?php echo $missingCount === 1 ? '' : 's'; ?> for this recipe.
                        <?php else: ?>
                            You already have all listed ingredients for this recipe.
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($substitutions)): ?>
                        <p class="text-light mb-3">Available substitution suggestions:</p>
                        <div class="suggestion-list">
                            <?php foreach ($substitutions as $missing => $options): ?>
                                <?php if (!empty($options)): ?>
                                    <div class="suggestion-item mb-3">
                                        <div class="suggestion-missing mb-2"><?php echo htmlspecialchars($missing); ?></div>
                                        <div class="suggestion-options">
                                            <?php foreach ($options as $opt): ?>
                                                <span class="recipe-meta-pill">
                                                        <?php echo htmlspecialchars($opt['ingredient']); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($missingCount > 0): ?>
                        <p class="text-light mb-0">No saved substitutions found for your missing ingredients yet.</p>
                    <?php else: ?>
                        <p class="text-light mb-0">No suggestions available for this recipe right now.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/layout.js'); ?>"></script>
</body>
</html>
