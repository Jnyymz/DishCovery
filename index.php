<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DishCovery | Thesis Landing Page</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=<?php echo (int) @filemtime(__DIR__ . '/index.css'); ?>">
</head>
<body>

    <div class="landing-navbar-wrap">
        <div class="container">
            <nav class="landing-navbar">
                <div class="landing-nav-desktop d-none d-lg-flex">
                    <div class="landing-nav-left">
                        <a class="landing-brand" href="#home" aria-label="DishCovery home">
                            <span class="landing-brand-logo-wrap">
                                <img src="img/logo2.png" alt="DishCovery logo" class="landing-brand-logo">
                            </span>
                        </a>
                    </div>
                    <div class="landing-nav-center">
                        <a class="landing-nav-link" href="#home">Home</a>
                        <a class="landing-nav-link" href="#why">Why</a>
                        <a class="landing-nav-link" href="#about">About</a>
                        <a class="landing-nav-link" href="#how">How It Works</a>
                        <a class="landing-nav-link" href="#features">Features</a>
                    </div>
                    <div class="landing-nav-right">
                        <a class="landing-nav-cta" href="public/login.php">Login</a>
                    </div>
                </div>

                <div class="d-flex d-lg-none align-items-center justify-content-between gap-3 landing-nav-mobile-bar">
                    <a class="landing-brand" href="#home" aria-label="DishCovery home">
                        <span class="landing-brand-logo-wrap">
                            <img src="img/logo2.png" alt="DishCovery logo" class="landing-brand-logo">
                        </span>
                    </a>
                    <button class="navbar-toggler landing-nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#landingNavMobile" aria-controls="landingNavMobile" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>

                <div class="collapse landing-nav-mobile d-lg-none" id="landingNavMobile">
                    <div class="d-flex flex-column gap-2">
                        <a class="landing-nav-link" href="#home">Home</a>
                        <a class="landing-nav-link" href="#why">Why</a>
                        <a class="landing-nav-link" href="#about">About</a>
                        <a class="landing-nav-link" href="#how">How It Works</a>
                        <a class="landing-nav-link" href="#features">Features</a>
                        <a class="landing-nav-cta mt-2 text-center" href="public/login.php">Login</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <main>
        <section class="hero-wrap section-wrap" id="home">
            <div class="container">
                <div class="row justify-content-start align-items-center g-4">
                    <div class="col-lg-5 text-start hero-copy">
                        <h1 class="display-4 fw-bold brand-title mb-3">DishCovery</h1>
                        <p class="hero-subtitle fs-5">Ingredient-Centric Recipe Recommendation System</p>
                        <p class="hero-text mb-4">
                            DishCovery recommends recipes based on your available ingredients using a Content-Based Filtering approach,
                            TF-IDF feature extraction, and cosine similarity scoring to rank the most relevant results.
                        </p>
                        <div class="d-flex flex-column flex-sm-row justify-content-start gap-3">
                            <a href="public/login.php" class="btn btn-dc-primary">Login</a>
                            <a href="public/register.php" class="btn btn-dc-outline">Register</a>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="hero-visual">
                            <img src="img/hero-bg.png" alt="DishCovery hero background" class="hero-bg-image">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-wrap" id="why">
            <div class="container">
                <div class="row justify-content-center align-items-center g-4">
                    <div class="col-lg-7 why-copy">
                        <h2 class="h1 mb-3">Why DishCovery?</h2>
                        <p class="section-text mb-0">
                            Unlike traditional keyword-based recipe platforms, DishCovery focuses on what you already have.
                            The system prioritizes ingredient relevance over popularity rankings, helping reduce food waste and improve meal planning efficiency.
                            By combining structured filtering and similarity-based matching, DishCovery delivers practical, personalized, and easy-to-use recipe recommendations.
                        </p>
                    </div>
                    <div class="col-lg-5">
                        <div class="why-visual">
                            <img src="img/why-img.png" alt="Why DishCovery illustration" class="why-image">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-wrap" id="about">
            <div class="container">
                <div class="row justify-content-center align-items-center g-4">
                    <div class="col-lg-7">
                        <div class="about-visual">
                            <img src="img/about-img.png" alt="About DishCovery illustration" class="about-image">
                        </div>
                    </div>
                    <div class="col-lg-5 about-copy">
                        <h2 class="h1 mb-3">About DishCovery</h2>
                        <h3 class="h4 mb-3 about-overview-pill">Overview</h3>
                        <p class="section-text mb-0">
                            DishCovery is an ingredient-centric, web-based recipe recommender system developed as an undergraduate thesis project under the Bachelor of Science in Computer Science program at Emilio Aguinaldo College–Cavite. The system is designed to assist users in discovering suitable recipes based on the ingredients they currently have available.
                        </p>
                        <p class="section-text mb-0 mt-3">
                            Unlike traditional keyword-based recipe platforms, DishCovery applies a content-based filtering approach to generate personalized recommendations. By analyzing ingredient similarity using computational techniques, the system provides relevant recipe suggestions that align with user input and selected dietary constraints.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-wrap" id="how">
            <div class="container">
                <div class="row justify-content-center mb-4">
                    <div class="col-12">
                        <h2 class="h1 mb-0">How It Works</h2>
                    </div>
                </div>

                <div class="row g-4 justify-content-center how-steps-grid">
                    <div class="col-md-6 col-lg-4">
                        <div class="how-step">
                            <span class="how-step-icon" aria-hidden="true"><i class="bi bi-basket2-fill"></i></span>
                            <p class="step-pill">Step 1</p>
                            <h3>Enter Your Ingredients</h3>
                            <p>Input the ingredients you currently have and apply optional filters.</p>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="how-step">
                            <span class="how-step-icon" aria-hidden="true"><i class="bi bi-gear-wide-connected"></i></span>
                            <p class="step-pill">Step 2</p>
                            <h3>System Preprocessing</h3>
                            <p>The system standardizes ingredient text for accurate comparison.</p>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="how-step">
                            <span class="how-step-icon" aria-hidden="true"><i class="bi bi-diagram-3-fill"></i></span>
                            <p class="step-pill">Step 3</p>
                            <h3>Similarity Computation</h3>
                            <p>TF-IDF and cosine similarity are used to measure ingredient relevance.</p>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="how-step">
                            <span class="how-step-icon" aria-hidden="true"><i class="bi bi-sliders2"></i></span>
                            <p class="step-pill">Step 4</p>
                            <h3>Filtering &amp; Ranking</h3>
                            <p>Recipes are ranked and filtered based on user constraints.</p>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="how-step">
                            <span class="how-step-icon" aria-hidden="true"><i class="bi bi-stars"></i></span>
                            <p class="step-pill">Step 5</p>
                            <h3>Get Top Recommendations</h3>
                            <p>The Top-5 most relevant recipes are displayed instantly.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-wrap" id="features">
            <div class="container">
                <div class="row justify-content-center mb-4">
                    <div class="col-12">
                        <h2 class="h1 mb-0">System Features</h2>
                    </div>
                </div>

                <div class="row g-4 justify-content-center features-grid">
                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon-circle" aria-hidden="true"><i class="bi bi-basket2"></i></span>
                            <div class="feature-content">
                                <h3>Ingredient-Based Recipe Matching</h3>
                                <p>Find recipes using the ingredients you already have for practical and efficient meal planning.</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon-circle" aria-hidden="true"><i class="bi bi-diagram-3"></i></span>
                            <div class="feature-content">
                                <h3>Similarity-Based Recommendation</h3>
                                <p>Recipes are ranked using TF-IDF and cosine similarity to ensure relevant ingredient matching.</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon-circle" aria-hidden="true"><i class="bi bi-sliders2"></i></span>
                            <div class="feature-content">
                                <h3>Smart Filtering Options</h3>
                                <p>Refine results by diet, cuisine, cooking time, and calorie range.</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon-circle" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span>
                            <div class="feature-content">
                                <h3>Ingredient Substitution</h3>
                                <p>Get predefined alternative ingredients when certain items are unavailable.</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon-circle" aria-hidden="true"><i class="bi bi-bar-chart"></i></span>
                            <div class="feature-content">
                                <h3>Nutritional Information</h3>
                                <p>View calorie and nutrition details for informed food choices.</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon-circle" aria-hidden="true"><i class="bi bi-bookmark"></i></span>
                            <div class="feature-content">
                                <h3>Recipe Bookmarking</h3>
                                <p>Save your favorite recipes for quick and easy access anytime.</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon-circle" aria-hidden="true"><i class="bi bi-gear"></i></span>
                            <div class="feature-content">
                                <h3>Admin Management</h3>
                                <p>Manage and update recipe data to maintain system accuracy.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="parallax-cta" aria-label="Join DishCovery">
            <div class="parallax-cta-overlay"></div>
            <div class="container">
                <div class="parallax-cta-content text-center">
                    <h2 class="h1 mb-3">Start Cooking Smarter</h2>
                    <p class="section-text mb-4">Sign in to discover personalized recipes based on the ingredients you already have.</p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <a href="public/login.php" class="btn btn-dc-primary">Login</a>
                        <a href="public/register.php" class="btn btn-dc-outline">Register</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer py-4" id="contact">
        <div class="container footer-container">
            <div class="row footer-grid g-3 g-md-4">
                <div class="col-12 col-md-6 col-lg-3 footer-col">
                    <article class="footer-card">
                        <h3 class="footer-title mb-2">DishCovery</h3>
                        <p class="footer-text mb-1">Ingredient-Centric Recipe Recommender System</p>
                        <p class="footer-text mb-1">Bachelor of Science in Computer Science</p>
                        <p class="footer-text mb-0">Emilio Aguinaldo College – Cavite</p>
                    </article>
                </div>
                <div class="col-12 col-md-6 col-lg-3 footer-col">
                    <article class="footer-card">
                        <p class="footer-note mb-0">Developed as part of the undergraduate thesis requirements for the Bachelor of Science in Computer Science program at Emilio Aguinaldo College – Cavite.</p>
                    </article>
                </div>
                <div class="col-12 col-md-6 col-lg-3 footer-col">
                    <article class="footer-card">
                        <p class="footer-meta mb-1">© 2025 DishCovery</p>
                        <p class="footer-meta mb-0">All Rights Reserved</p>
                    </article>
                </div>
                <div class="col-12 col-md-6 col-lg-3 footer-col footer-col-right">
                    <article class="footer-card">
                        <div class="footer-logo-wrap mb-2">
                            <img src="img/logo2.png" alt="DishCovery logo" class="footer-logo">
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var section = document.querySelector('.parallax-cta');
            if (!section) {
                return;
            }

            var mobileQuery = window.matchMedia('(max-width: 767px)');
            var reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            var ticking = false;

            function updateParallaxOffset() {
                if (!mobileQuery.matches || reduceMotionQuery.matches) {
                    section.style.removeProperty('--parallax-mobile-shift');
                    return;
                }

                var rect = section.getBoundingClientRect();
                var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
                var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                var sectionTop = scrollTop + rect.top;
                var sectionHeight = rect.height || section.offsetHeight || 1;
                var start = sectionTop - viewportHeight;
                var end = sectionTop + sectionHeight;
                var progress = (scrollTop - start) / (end - start);

                if (progress < 0) {
                    progress = 0;
                } else if (progress > 1) {
                    progress = 1;
                }

                var shift = (progress - 0.5) * 120;
                section.style.setProperty('--parallax-mobile-shift', shift.toFixed(2) + 'px');
            }

            function onScrollOrResize() {
                if (ticking) {
                    return;
                }

                ticking = true;
                window.requestAnimationFrame(function () {
                    updateParallaxOffset();
                    ticking = false;
                });
            }

            window.addEventListener('scroll', onScrollOrResize, { passive: true });
            window.addEventListener('resize', onScrollOrResize);
            window.addEventListener('orientationchange', onScrollOrResize);

            var mobileNav = document.getElementById('landingNavMobile');
            var mobileToggle = document.querySelector('.landing-nav-toggle');
            var navLinks = document.querySelectorAll('#landingNavMobile .landing-nav-link');
            var allNavLinks = document.querySelectorAll('.landing-nav-link');

            var hasBootstrapCollapse = Boolean(window.bootstrap && window.bootstrap.Collapse);

            if (mobileToggle && mobileNav && !hasBootstrapCollapse) {
                mobileToggle.addEventListener('click', function () {
                    var isOpen = mobileNav.classList.contains('show');
                    mobileNav.classList.toggle('show', !isOpen);
                    mobileToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                });
            }

            navLinks.forEach(function (link) {
                link.addEventListener('click', function () {
                    if (!mobileNav) {
                        return;
                    }

                    var bsCollapse = window.bootstrap && bootstrap.Collapse.getInstance(mobileNav);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    } else {
                        mobileNav.classList.remove('show');
                        if (mobileToggle) {
                            mobileToggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            });

            function updateActiveNavLink() {
                var scrollPosition = window.pageYOffset || document.documentElement.scrollTop || 0;
                var sections = document.querySelectorAll('section[id]');
                var currentActive = null;

                sections.forEach(function (section) {
                    var sectionTop = section.offsetTop;
                    var sectionHeight = section.offsetHeight;
                    if (scrollPosition >= sectionTop - 100 && scrollPosition < sectionTop + sectionHeight - 100) {
                        currentActive = section.getAttribute('id');
                    }
                });

                allNavLinks.forEach(function (link) {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === '#' + currentActive) {
                        link.classList.add('active');
                    }
                });
            }

            window.addEventListener('scroll', updateActiveNavLink, { passive: true });
            updateActiveNavLink();

            if (mobileQuery.addEventListener) {
                mobileQuery.addEventListener('change', onScrollOrResize);
            } else if (mobileQuery.addListener) {
                mobileQuery.addListener(onScrollOrResize);
            }

            if (reduceMotionQuery.addEventListener) {
                reduceMotionQuery.addEventListener('change', onScrollOrResize);
            } else if (reduceMotionQuery.addListener) {
                reduceMotionQuery.addListener(onScrollOrResize);
            }

            updateParallaxOffset();
        })();
    </script>
</body>
</html>
