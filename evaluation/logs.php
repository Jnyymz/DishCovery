<?php
/* =========================================================
   logs.php
   Evaluation & Analytics Summary
   DishCovery – Research Data Analysis
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/precision.php';
require_once __DIR__ . '/../core/auth.php';

/**
 * Total recommendations generated
 */
function getTotalRecommendations() {
    global $conn;

    $result = $conn->query(
        "SELECT COUNT(*) as total FROM recommendation_logs"
    );

    return $result->fetch_assoc()['total'];
}

/**
 * Get evaluation summary
 */
function getEvaluationSummary($k = 5) {
    $precisionStats = computeOverallPrecisionStats((int)$k);
    $totalLoggedInteractions = (int)getTotalRecommendations();
    $evaluatedLogs = (int)($precisionStats['evaluated_logs'] ?? 0);
    $evaluatedLogs = min($evaluatedLogs, $totalLoggedInteractions);
    $coverage = $totalLoggedInteractions > 0
        ? ($evaluatedLogs / $totalLoggedInteractions)
        : 0.0;

    return [
        'total_logged_interactions' => $totalLoggedInteractions,
        'evaluated_logs' => $evaluatedLogs,
        'coverage' => round($coverage, 3),
        'precision_at_k' => round((float)($precisionStats['precision'] ?? 0), 3),
    ];
}

$isDirectRequest = realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($isDirectRequest) {
    requireAdminRoleAccess('../admin/login.php');
    $summary = getEvaluationSummary(5);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Evaluation Logs | DishCovery Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="../admin/assets/admin.css?v=20260220-1">
    </head>
    <body class="admin-scroll-under-navbar">
        <?php include __DIR__ . '/../admin/components/navbar.php'; ?>
        <main class="admin-container">
            <section class="admin-card">
                <h2>Evaluation Logs</h2>
                <p class="admin-inline-label">System-level recommendation session evaluation summary.</p>

                <div class="stats-grid">
                    <article class="stat-card">
                        <h3>Total Logged Interactions</h3>
                        <p><?php echo (int)$summary['total_logged_interactions']; ?></p>
                    </article>
                    <article class="stat-card">
                        <h3>Evaluated Logs</h3>
                        <p><?php echo (int)$summary['evaluated_logs']; ?></p>
                    </article>
                    <article class="stat-card">
                        <h3>Coverage</h3>
                        <p><?php echo htmlspecialchars((string)$summary['coverage']); ?></p>
                    </article>
                    <article class="stat-card">
                        <h3>Precision@5</h3>
                        <p><?php echo htmlspecialchars((string)$summary['precision_at_k']); ?></p>
                    </article>
                </div>

                <a class="btn btn-light btn-sm" href="../admin/dashboard.php"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            </section>
        </main>
        <script src="../admin/assets/admin.js?v=20260220-1"></script>
    </body>
    </html>
    <?php
}
