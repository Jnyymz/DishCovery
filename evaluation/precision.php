<?php
/* =========================================================
   precision.php
   Precision@K Evaluation
   DishCovery – Algorithm Accuracy Metric
   ========================================================= */

require_once __DIR__ . '/../core/dbConfig.php';
require_once __DIR__ . '/../core/auth.php';

/**
 * Extract top-K recipe IDs from recommendation_logs.recommended_recipes JSON.
 */
function extractTopKRecipeIdsFromLog(string $recommendedRecipesJson, int $k): array {
    $decoded = json_decode($recommendedRecipesJson, true);
    if (!is_array($decoded) || empty($decoded)) {
        return [];
    }

    $ids = [];
    foreach ($decoded as $item) {
        $recipeId = 0;

        if (is_array($item)) {
            $recipeId = (int)($item['recipe_id'] ?? 0);
            if ($recipeId <= 0 && isset($item['recipe']) && is_array($item['recipe'])) {
                $recipeId = (int)($item['recipe']['recipe_id'] ?? 0);
            }
        } else {
            $recipeId = (int)$item;
        }

        if ($recipeId > 0) {
            $ids[] = $recipeId;
        }
    }

    if (empty($ids)) {
        return [];
    }

    $uniqueIds = array_values(array_unique($ids));
    return array_slice($uniqueIds, 0, max(1, (int)$k));
}

/**
 * Compute precision for one recommendation log.
 * A recipe is relevant if rating >= 4 in user_feedback.
 */
function computePrecisionForLog(int $userId, string $recommendedRecipesJson, int $k, mysqli_stmt $feedbackStmt, int &$ratedTopKCount = 0): float {
    $topK = extractTopKRecipeIdsFromLog($recommendedRecipesJson, $k);
    $kInt = max(1, (int)$k);

    if (empty($topK)) {
        $ratedTopKCount = 0;
        return 0.0;
    }

    $relevantCount = 0;
    $ratedCount = 0;

    foreach ($topK as $recipeId) {
        $safeRecipeId = (int)$recipeId;
        if ($safeRecipeId <= 0) {
            continue;
        }

        $feedbackStmt->bind_param("ii", $userId, $safeRecipeId);
        $feedbackStmt->execute();
        $feedback = $feedbackStmt->get_result()->fetch_assoc();

        if ($feedback) {
            $ratedCount++;
            if ((int)$feedback['rating'] >= 4) {
                $relevantCount++;
            }
        }
    }

    $ratedTopKCount = $ratedCount;
    $boundedRelevantCount = min($relevantCount, $kInt);
    $logPrecision = $boundedRelevantCount / $kInt;

    if ($logPrecision < 0.0) {
        return 0.0;
    }
    if ($logPrecision > 1.0) {
        return 1.0;
    }

    return $logPrecision;
}

/**
 * Compute overall system Precision@K based on recommendation logs (sessions).
 */
function computeOverallPrecisionStats($k = 5): array {
    global $conn;

    $result = $conn->query(
        "SELECT log_id, user_id, recommended_recipes
         FROM recommendation_logs
         ORDER BY log_id ASC"
    );

    $feedbackStmt = $conn->prepare(
        "SELECT rating
         FROM user_feedback
         WHERE user_id = ? AND recipe_id = ?
         LIMIT 1"
    );

    $totalPrecision = 0;
    $totalLogs = 0;
    $evaluatedLogs = 0;

    while ($row = $result->fetch_assoc()) {
        $totalLogs++;

        $ratedTopKCount = 0;
        $precision = computePrecisionForLog(
            (int)$row['user_id'],
            (string)($row['recommended_recipes'] ?? ''),
            (int)$k,
            $feedbackStmt,
            $ratedTopKCount
        );

        if ($ratedTopKCount > 0) {
            $evaluatedLogs++;
        }

        $totalPrecision += $precision;
    }

    $overallPrecision = $totalLogs > 0 ? ($totalPrecision / $totalLogs) : 0.0;
    $overallPrecision = max(0.0, min(1.0, (float)$overallPrecision));

    return [
        'precision' => $overallPrecision,
        'evaluated_logs' => $evaluatedLogs,
        'total_logs' => $totalLogs,
    ];
}

/**
 * Backward-compatible accessor for callers expecting a float precision value.
 */
function computeOverallPrecision($k = 5) {
    $stats = computeOverallPrecisionStats($k);
    return (float)$stats['precision'];
}

function getPrecisionSummaryForKValues(array $kValues): array {
    $summary = [];
    foreach ($kValues as $k) {
        $kInt = max(1, (int)$k);
        $stats = computeOverallPrecisionStats($kInt);
        $summary[] = [
            'k' => $kInt,
            'precision' => round((float)$stats['precision'], 3),
            'evaluated_logs' => (int)$stats['evaluated_logs'],
            'total_logs' => (int)$stats['total_logs'],
        ];
    }
    return $summary;
}

$isDirectRequest = realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($isDirectRequest) {
    requireAdminRoleAccess('../admin/login.php');
    $rows = getPrecisionSummaryForKValues([3, 5, 10]);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Precision Metrics | DishCovery Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="../admin/assets/admin.css?v=20260220-1">
    </head>
    <body class="admin-scroll-under-navbar">
        <?php include __DIR__ . '/../admin/components/navbar.php'; ?>
        <main class="admin-container">
            <section class="admin-card">
                <h2>Precision Metrics</h2>
                <p class="admin-inline-label">Overall system Precision@K scores.</p>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>K</th>
                                <th>Precision@K</th>
                                <th>Evaluated Logs</th>
                                <th>Total Logs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?php echo (int)$row['k']; ?></td>
                                    <td><?php echo htmlspecialchars((string)$row['precision']); ?></td>
                                    <td><?php echo (int)$row['evaluated_logs']; ?></td>
                                    <td><?php echo (int)$row['total_logs']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a class="btn btn-light btn-sm" href="../admin/dashboard.php"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
                </div>
            </section>
        </main>
        <script src="../admin/assets/admin.js?v=20260220-1"></script>
    </body>
    </html>
    <?php
}
