<?php
/* =====================================
   Database Configuration File
   DishCovery Web-Based Recommender System
   ===================================== */

// $host = getenv('DISHCOVERY_DB_HOST') ?: "sql107.infinityfree.com";
// $username = getenv('DISHCOVERY_DB_USER') ?: "if0_41590269";
// $password = getenv('DISHCOVERY_DB_PASS') ?: "DmPkFPrnVInPvvX";
// $database = getenv('DISHCOVERY_DB_NAME') ?: "if0_41590269_dishcovery";
// $port = (int)(getenv('DISHCOVERY_DB_PORT') ?: 3306);

$host = getenv('DISHCOVERY_DB_HOST') ?: "localhost";
$username = getenv('DISHCOVERY_DB_USER') ?: "root";
$password = getenv('DISHCOVERY_DB_PASS') ?: "";
$database = getenv('DISHCOVERY_DB_NAME') ?: "dishcovery_db";
$port = (int)(getenv('DISHCOVERY_DB_PORT') ?: 3306);

/* Create database connection */
mysqli_report(MYSQLI_REPORT_ERROR);

$attempts = [
    ['host' => $host, 'port' => $port],
];

if ($host === 'localhost') {
    $attempts[] = ['host' => '127.0.0.1', 'port' => $port];
}

if ($port !== 3307) {
    $attempts[] = ['host' => $host, 'port' => 3307];
    if ($host === 'localhost') {
        $attempts[] = ['host' => '127.0.0.1', 'port' => 3307];
    }
}

$conn = null;
$lastError = '';

foreach ($attempts as $attempt) {
    $candidate = @new mysqli(
        $attempt['host'],
        $username,
        $password,
        $database,
        (int)$attempt['port']
    );

    if (!$candidate->connect_errno) {
        $conn = $candidate;
        break;
    }

    $lastError = $candidate->connect_error;
}

if (!$conn) {
    die(
        "Database connection failed: {$lastError}. " .
        "Check that MySQL is running in XAMPP and verify host/port/user in core/dbConfig.php " .
        "(or set DISHCOVERY_DB_HOST, DISHCOVERY_DB_PORT, DISHCOVERY_DB_USER, DISHCOVERY_DB_PASS, DISHCOVERY_DB_NAME)."
    );
}

/* Set character set to UTF-8 */
$conn->set_charset("utf8mb4");

/**
 * Gets the shared database connection.
 *
 * @return mysqli
 */
function getDbConnection(): mysqli {
    global $conn;
    return $conn;
}

/**
 * Execute a prepared query and return the statement object.
 *
 * @param string $sql
 * @param string|null $types
 * @param array|null $params
 * @return mysqli_stmt|false
 */
function dbPrepareAndExecute(string $sql, ?string $types = null, ?array $params = null) {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    return $stmt;
}

/**
 * Fetch all rows from a query with optional parameter binding.
 *
 * @param string $sql
 * @param string|null $types
 * @param array|null $params
 * @return array
 */
function dbFetchAll(string $sql, ?string $types = null, ?array $params = null): array {
    $stmt = dbPrepareAndExecute($sql, $types, $params);
    if (!$stmt) {
        return [];
    }

    $result = $stmt->get_result();
    if (!$result) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Fetch one row from a query with optional parameter binding.
 *
 * @param string $sql
 * @param string|null $types
 * @param array|null $params
 * @return array|null
 */
function dbFetchOne(string $sql, ?string $types = null, ?array $params = null): ?array {
    $rows = dbFetchAll($sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * Execute a query with no result set (insert/update/delete).
 *
 * @param string $sql
 * @param string|null $types
 * @param array|null $params
 * @return bool
 */
function dbExecute(string $sql, ?string $types = null, ?array $params = null): bool {
    $stmt = dbPrepareAndExecute($sql, $types, $params);
    return $stmt ? true : false;
}

?>
