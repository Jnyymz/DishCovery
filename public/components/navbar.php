<nav class="navbar navbar-expand-lg main-navbar">
    <?php
    $currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
    $isHowItWorksPage = $currentPage === 'how_it_works.php';
    $isAboutPage = $currentPage === 'about.php';
    $isBookmarksPage = $currentPage === 'bookmarks.php';
    $isRatedRecipesPage = $currentPage === 'rated_recipes.php';
    $hideMobileSidebar = isset($hideMobileSidebar) ? (bool)$hideMobileSidebar : false;
    ?>
    <div class="container-fluid">

        <!-- Brand -->
        <a class="navbar-brand fw-bold text-white"
              href="dashboard.php"
              aria-label="DishCovery Home">
            <span class="navbar-brand-logo-wrap">
                <img src="../img/logo.png" alt="DishCovery Logo" class="navbar-brand-logo">
            </span>
        </a>

        <!-- Collapse Button (Mobile Navbar Links) -->
        <button class="navbar-toggler border-0 text-white"
                type="button"
                aria-label="Toggle navigation"
                data-bs-toggle="collapse"
                data-bs-target="#navbarContent"
                aria-controls="navbarContent"
                aria-expanded="false">
            <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
        </button>

        <!-- Navbar Content -->
        <div class="collapse navbar-collapse" id="navbarContent">

            <!-- MOBILE TOP ROW -->
            <div class="d-lg-none mobile-collapse-header">
                <div class="mobile-quick-links">
                    <a href="bookmarks.php" class="nav-link nav-link-custom d-flex align-items-center gap-2 <?php echo $isBookmarksPage ? 'is-active' : ''; ?>">
                        <i class="bi bi-bookmark-heart-fill"></i>
                        <span>Saves</span>
                    </a>
                    <a href="rated_recipes.php" class="nav-link nav-link-custom d-flex align-items-center gap-2 <?php echo $isRatedRecipesPage ? 'is-active' : ''; ?>">
                        <i class="bi bi-star-fill"></i>
                        <span>Rated</span>
                    </a>
                </div>
                <div class="mobile-account-row">
                    <span class="text-white fw-semibold mobile-user-name">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a href="../core/handleForms.php?logout=1"
                       class="btn btn-outline-light btn-sm mobile-logout-btn"
                       data-confirm-logout="public">
                        Logout
                    </a>
                </div>
            </div>

            <!-- LEFT LINKS -->
            <ul class="navbar-nav ms-4">

                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?php echo $isHowItWorksPage ? 'is-active' : ''; ?>"
                       href="how_it_works.php">
                        <i class="bi bi-diagram-3-fill me-1"></i>
                        How It Works
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?php echo $isAboutPage ? 'is-active' : ''; ?>"
                       href="about.php">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        About
                    </a>
                </li>

            </ul>

            <!-- MOBILE FILTERS -->
            <?php if (!$hideMobileSidebar): ?>
                <div class="d-lg-none mt-3">
                    <?php include __DIR__ . '/sidebar_mobile.php'; ?>
                </div>
            <?php endif; ?>

            <!-- RIGHT SIDE -->
            <div class="ms-auto d-none d-lg-flex align-items-center mt-3 mt-lg-0">

                <a href="bookmarks.php"
                         class="nav-icon me-3 <?php echo $isBookmarksPage ? 'is-active' : ''; ?>">
                    <i class="bi bi-bookmark-heart-fill"></i>
                </a>

                <a href="rated_recipes.php"
                         class="nav-icon me-3 <?php echo $isRatedRecipesPage ? 'is-active' : ''; ?>">
                    <i class="bi bi-star-fill"></i>
                </a>

                <span class="me-3 text-white fw-semibold">
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>

                <a href="../core/handleForms.php?logout=1"
                         class="btn btn-light btn-sm"
                         data-confirm-logout="public">
                    Logout
                </a>

            </div>

        </div>
    </div>
</nav>
