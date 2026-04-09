<?php
$activePage = $activePage ?? '';
$adminLoggedIn = isAdminLoggedIn();
$adminUser = $adminLoggedIn ? currentAdmin() : null;
?>
<header class="admin-navbar">
    <div class="admin-navbar-inner">
        <a class="admin-brand" href="<?php echo $adminLoggedIn ? 'dashboard.php' : 'login.php'; ?>" aria-label="DishCovery Admin Home">
            <span class="admin-brand-logo-wrap">
                <img src="../img/logo.png" alt="DishCovery Logo" class="admin-brand-logo">
            </span>
        </a>

        <button class="admin-nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" data-admin-nav-toggle>
            <i class="bi bi-grid-3x3-gap-fill"></i>
        </button>

        <nav class="admin-nav-links" data-admin-nav-links>
            <?php if ($adminLoggedIn): ?>
                <div class="admin-nav-main">
                    <a href="dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="manage_recipes.php" class="<?php echo $activePage === 'recipes' ? 'active' : ''; ?>">
                        <i class="bi bi-journal-richtext"></i>
                        <span>Manage Recipes</span>
                    </a>
                    <a href="manage_users.php" class="<?php echo $activePage === 'users' ? 'active' : ''; ?>">
                        <i class="bi bi-people-fill"></i>
                        <span>Manage Users</span>
                    </a>
                    <a href="manage_substitutions.php" class="<?php echo $activePage === 'substitutions' ? 'active' : ''; ?>">
                        <i class="bi bi-shuffle"></i>
                        <span>Substitutions</span>
                    </a>
                </div>

                <div class="admin-nav-right">
                    <span class="admin-nav-user">
                        <i class="bi bi-person-circle"></i>
                        <span><?php echo htmlspecialchars($adminUser['username'] ?? 'Admin'); ?></span>
                    </span>
                    <a href="../core/adminHandle.php?admin_logout=1" class="btn btn-light btn-sm" data-confirm-logout="admin">Logout</a>
                </div>
            <?php else: ?>
                <div class="admin-nav-right admin-nav-right-guest">
                    <a href="login.php" class="<?php echo $activePage === 'login' ? 'active' : ''; ?>">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Login</span>
                    </a>
                    <a href="register.php" class="<?php echo $activePage === 'register' ? 'active' : ''; ?>">
                        <i class="bi bi-person-plus-fill"></i>
                        <span>Register</span>
                    </a>
                    <a href="../public/login.php">
                        <i class="bi bi-house-door-fill"></i>
                        <span>User Site</span>
                    </a>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>
