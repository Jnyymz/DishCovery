<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/adminService.php';

requireAdminLogin();

$stats = getAdminDashboardStats();
$recipes = getAdminRecipesList(300);
$flash = $_SESSION['admin_flash'] ?? '';
$flashType = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);
$activePage = 'recipes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Recipes | DishCovery Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=20260220-1">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-scroll-under-navbar admin-page-flat">
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main class="admin-container">
        <section class="admin-card">
            <h2>Recipes</h2>
            <p class="admin-page-subtitle">Monitor recipe records and fetch additional recipes from the API.</p>
            <?php if (!empty($flash)): ?>
                <div
                    data-admin-flash="1"
                    data-admin-flash-type="<?php echo htmlspecialchars($flashType, ENT_QUOTES); ?>"
                    data-admin-flash-message="<?php echo htmlspecialchars($flash, ENT_QUOTES); ?>"
                    hidden
                ></div>
            <?php endif; ?>

            <div class="stats-grid">
                <article class="stat-card">
                    <h3>Total Recipes</h3>
                    <p><?php echo (int)$stats['total_recipes']; ?></p>
                </article>
                <article class="stat-card">
                    <h3>Total Incomplete API Recipes</h3>
                    <p><?php echo (int)$stats['incomplete_api_recipes']; ?></p>
                </article>
            </div>

            <form method="POST" action="../core/adminHandle.php" class="admin-inline-form">
                <input type="hidden" name="admin_action" value="fetch_more_recipes_api">
                <label for="fetch_limit" class="admin-inline-label">Fetch from API</label>
                <input type="number" id="fetch_limit" name="fetch_limit" min="1" max="200" value="20" placeholder="Count">
                <input type="text" name="fetch_query" placeholder="Optional keyword (e.g. chicken)">
                <button type="submit" name="fetch_more_recipes_api"><i class="bi bi-cloud-download"></i> Fetch Recipes</button>
            </form>
        </section>

        <section class="admin-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Source</th>
                            <th>Cuisine</th>
                            <th>Diet</th>
                            <th>Meal</th>
                            <th>Time</th>
                            <th>Calories</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recipes as $recipe): ?>
                        <tr>
                            <td><?php echo (int)$recipe['recipe_id']; ?></td>
                            <td><?php echo htmlspecialchars($recipe['recipe_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($recipe['source_api'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($recipe['cuisine_type'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($recipe['diet_label'] ?? 'general'); ?></td>
                            <td><?php echo htmlspecialchars($recipe['meal_type'] ?? 'dinner'); ?></td>
                            <td><?php echo (int)($recipe['cooking_time'] ?? 0); ?> min</td>
                            <td><?php echo (int)($recipe['calories'] ?? 0); ?> kcal</td>
                            <td><?php echo htmlspecialchars($recipe['api_status'] ?? 'complete'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="assets/admin.js?v=20260220-1"></script>
</body>
</html>
