<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/adminService.php';

requireAdminLogin();

$activePage = 'substitutions';
$stats = getAdminIngredientSubstitutionStats();
$substitutions = getAdminIngredientSubstitutionsList(400);

$flash = $_SESSION['admin_flash'] ?? '';
$flashType = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Substitutions | DishCovery Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=20260220-1">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-scroll-under-navbar admin-page-flat">
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main class="admin-container">
        <section class="admin-card">
            <h2>Ingredient Substitutions</h2>
            <p class="admin-page-subtitle">Review substitution coverage and fetch missing ingredient alternatives.</p>
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
                    <h3>Total Substitution Pairs</h3>
                    <p><?php echo (int)$stats['total_pairs']; ?></p>
                </article>
                <article class="stat-card">
                    <h3>Ingredients With Substitutes</h3>
                    <p><?php echo (int)$stats['ingredients_with_substitutions']; ?></p>
                </article>
            </div>

            <form method="POST" action="../core/adminHandle.php" class="admin-inline-form">
                <input type="hidden" name="admin_action" value="fetch_substitutions_api">
                <label for="sub_limit" class="admin-inline-label">Fetch substitutions</label>
                <input type="number" id="sub_limit" name="substitution_limit" min="1" max="300" value="30" placeholder="Count">
                <input type="text" name="substitution_ingredient" placeholder="Optional ingredient (e.g. milk)">
                <button type="submit" name="fetch_substitutions_api"><i class="bi bi-cloud-download"></i> Fetch Substitutions</button>
            </form>
        </section>

        <section class="admin-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ingredient</th>
                            <th>Substitute</th>
                            <th>Similarity</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($substitutions)): ?>
                        <tr>
                            <td colspan="4">No substitution data found yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($substitutions as $item): ?>
                            <tr>
                                <td><?php echo (int)$item['substitution_id']; ?></td>
                                <td><?php echo htmlspecialchars($item['ingredient_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['substitute_name'] ?? ''); ?></td>
                                <td><?php echo number_format((float)($item['similarity_score'] ?? 0), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="assets/admin.js?v=20260220-1"></script>
</body>
</html>
