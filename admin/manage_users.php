<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/adminService.php';

requireAdminLogin();

$users = getAdminUsersList(300);
$stats = getAdminUsersStats();
$flash = $_SESSION['admin_flash'] ?? '';
$flashType = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);
$activePage = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | DishCovery Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=20260220-1">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-page-flat">
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main class="admin-container">
        <section class="admin-card">
            <h2>Users</h2>
            <p class="admin-page-subtitle">Manage user accounts and review rating activity.</p>
            <?php if (!empty($flash)): ?>
                <div
                    data-admin-flash="1"
                    data-admin-flash-type="<?php echo htmlspecialchars($flashType, ENT_QUOTES); ?>"
                    data-admin-flash-message="<?php echo htmlspecialchars($flash, ENT_QUOTES); ?>"
                    hidden
                ></div>
            <?php endif; ?>

            <div class="stats-grid admin-users-kpi-grid">
                <article class="stat-card">
                    <h3>Total Users</h3>
                    <p><?php echo (int)($stats['total_users'] ?? 0); ?></p>
                </article>
                <article class="stat-card">
                    <h3>Total Ratings</h3>
                    <p><?php echo (int)($stats['total_ratings'] ?? 0); ?></p>
                </article>
            </div>

            <form method="POST" action="../core/adminHandle.php" class="admin-inline-form admin-users-delete-form" data-users-delete-form>
                <input type="hidden" name="admin_action" value="delete_users_bulk">
                <div class="admin-users-toolbar">
                    <button type="submit" name="delete_users_bulk" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>
                                <input
                                    type="checkbox"
                                    id="select_all_users"
                                    class="admin-checkbox"
                                    data-users-select-all
                                    aria-label="Select all users"
                                >
                            </th>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Rated Recipes</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    name="user_ids[]"
                                    value="<?php echo (int)$user['user_id']; ?>"
                                    class="user-select-checkbox admin-checkbox"
                                    data-user-row-checkbox
                                    aria-label="Select user <?php echo (int)$user['user_id']; ?>"
                                >
                            </td>
                            <td><?php echo (int)$user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                            <td><?php echo (int)($user['rated_recipes_count'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars($user['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </form>
        </section>
    </main>
    <script src="assets/admin.js?v=20260220-1"></script>
</body>
</html>
