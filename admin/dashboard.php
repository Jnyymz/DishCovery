<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/adminService.php';

requireAdminLogin();

$stats = getAdminDashboardStats();
$evaluationStats = getAdminEvaluationCardData(5);
$evaluationLogs = getAdminRecentRecommendationLogs(20);
$flash = $_SESSION['admin_flash'] ?? '';
$flashType = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | DishCovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=20260220-1">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-scroll-under-navbar">
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <main class="admin-container">
        <?php if (!empty($flash)): ?>
            <div
                data-admin-flash="1"
                data-admin-flash-type="<?php echo htmlspecialchars($flashType, ENT_QUOTES); ?>"
                data-admin-flash-message="<?php echo htmlspecialchars($flash, ENT_QUOTES); ?>"
                hidden
            ></div>
        <?php endif; ?>

        <section class="dashboard-top-section">
            <div class="dashboard-top-intro">
                <h2>Admin Dashboard</h2>
                <p>System trackers and maintenance status.</p>
            </div>

            <div class="dashboard-top-content">
                <div class="admin-card dashboard-shell">
                    <div class="dashboard-trackers">
                        <a class="tracker-card tracker-link" href="manage_recipes.php" aria-label="Go to Manage Recipes">
                            <div class="tracker-icon" aria-hidden="true"><i class="bi bi-journal-richtext"></i></div>
                            <div class="tracker-body">
                                <h3>Total Recipes</h3>
                                <p><?php echo (int)$stats['total_recipes']; ?></p>
                            </div>
                        </a>

                        <a class="tracker-card tracker-link" href="manage_recipes.php" aria-label="Go to Manage Recipes">
                            <div class="tracker-icon" aria-hidden="true"><i class="bi bi-exclamation-octagon"></i></div>
                            <div class="tracker-body">
                                <h3>Incomplete API Recipes</h3>
                                <p><?php echo (int)$stats['incomplete_api_recipes']; ?></p>
                            </div>
                        </a>

                        <a class="tracker-card tracker-link" href="manage_substitutions.php" aria-label="Go to Manage Substitutions">
                            <div class="tracker-icon" aria-hidden="true"><i class="bi bi-arrow-left-right"></i></div>
                            <div class="tracker-body">
                                <h3>Substitution Pairs</h3>
                                <p><?php echo (int)$stats['total_substitution_pairs']; ?></p>
                            </div>
                        </a>

                        <a class="tracker-card tracker-card-warning tracker-link" href="#maintenance" aria-label="Jump to TF-IDF maintenance">
                            <div class="tracker-icon" aria-hidden="true"><i class="bi bi-cpu"></i></div>
                            <div class="tracker-body">
                                <h3>Needs TF-IDF Rebuild</h3>
                                <p><?php echo (int)$stats['recipes_needing_tfidf_rebuild']; ?></p>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="admin-card dashboard-shell">
                    <section class="dashboard-maintenance" id="maintenance">
                        <div class="dashboard-actions">
                            <form method="POST" action="../core/adminHandle.php">
                                <button type="submit" name="run_repair_script"><i class="bi bi-wrench-adjustable-circle"></i> Run Repair Script</button>
                            </form>
                            <form method="POST" action="../core/adminHandle.php">
                                <button type="submit" name="rebuild_tfidf_vectors"><i class="bi bi-arrow-repeat"></i> Rebuild TF-IDF Vectors</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </section>

        <section class="dashboard-evaluation-section" id="evaluation">
                <h3>Evaluation Analytics</h3>
                <div class="stats-grid">
                    <article class="stat-card">
                        <h3>Total Logged Interactions</h3>
                        <p><?php echo (int)$evaluationStats['total_logged_interactions']; ?></p>
                    </article>
                    <article class="stat-card">
                        <h3>Average Precision Score</h3>
                        <p>
                            <?php if (!empty($evaluationStats['has_evaluation_data'])): ?>
                                <?php echo htmlspecialchars((string)$evaluationStats['average_precision_score']); ?>
                            <?php else: ?>
                                <span class="admin-eval-empty">No evaluation data yet</span>
                            <?php endif; ?>
                        </p>
                        <div class="admin-eval-meta">
                            Evaluated logs: <?php echo (int)($evaluationStats['evaluated_logs_count'] ?? 0); ?>
                            <span class="admin-eval-meta-sep">•</span>
                            Coverage: <?php echo htmlspecialchars((string)($evaluationStats['evaluated_logs_ratio'] ?? '0 / 0')); ?>
                        </div>
                    </article>
                </div>

                <button
                    type="button"
                    class="admin-eval-toggle"
                    data-admin-toggle-logs
                    data-target="admin-eval-logs"
                    data-open-label="Hide Detailed Logs"
                    data-closed-label="View Detailed Logs"
                >
                    <i class="bi bi-clipboard-data"></i>
                    View Detailed Logs
                </button>

                <div id="admin-eval-logs" class="admin-eval-logs" hidden>
                    <div class="table-wrap mt-3">
                        <table>
                            <thead>
                                <tr>
                                    <th>Log ID</th>
                                    <th>User</th>
                                    <th>Input Ingredients</th>
                                    <th>Top-K Count</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($evaluationLogs)): ?>
                                    <tr>
                                        <td colspan="5">No recommendation logs found yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($evaluationLogs as $log): ?>
                                        <?php
                                        $inputDecoded = json_decode((string)($log['input_ingredients'] ?? ''), true);
                                        $inputDisplay = is_array($inputDecoded)
                                            ? implode(', ', array_slice(array_map('strval', $inputDecoded), 0, 6))
                                            : trim((string)($log['input_ingredients'] ?? ''));
                                        if ($inputDisplay === '') {
                                            $inputDisplay = '—';
                                        }
                                        $topKCount = count(adminExtractRecipeIdsFromLog((string)($log['recommended_recipes'] ?? ''), 5));
                                        ?>
                                        <tr>
                                            <td><?php echo (int)$log['log_id']; ?></td>
                                            <td><?php echo htmlspecialchars((string)($log['username'] ?? ('User #' . (int)$log['user_id']))); ?></td>
                                            <td><?php echo htmlspecialchars($inputDisplay); ?></td>
                                            <td><?php echo (int)$topKCount; ?></td>
                                            <td><?php echo htmlspecialchars((string)($log['created_at'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
    </main>

    <script src="assets/admin.js?v=20260220-1"></script>
</body>
</html>
