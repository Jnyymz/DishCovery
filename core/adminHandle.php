<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/adminService.php';

/* =========================================================
   INPUT VALIDATION FUNCTIONS
   ========================================================= */

/**
 * Validates admin username: not empty, alphanumeric + spaces, length 3-50
 */
function validateAdminUsername($username) {
    $username = trim($username);
    if (empty($username)) return false;
    if (strlen($username) < 3 || strlen($username) > 50) return false;
    if (!preg_match('/^[a-zA-Z0-9\s]+$/', $username)) return false;
    return true;
}

/**
 * Validates admin password: at least 8 chars, uppercase, number
 */
function validateAdminPassword($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/\d/', $password)) return false;
    return true;
}

/**
 * Validates fetch limit: positive integer, max 500
 */
function validateFetchLimit($limit) {
    return is_numeric($limit) && $limit > 0 && $limit <= 500;
}

/**
 * Validates ingredient name: not empty, reasonable length
 */
function validateIngredientName($ingredient) {
    $ingredient = trim($ingredient);
    return !empty($ingredient) && strlen($ingredient) <= 100;
}

/* =========================================================
   BUSINESS LOGIC FUNCTIONS
   ========================================================= */

/**
 * Handles admin registration logic
 * @param string $username
 * @param string $email
 * @param string $password
 * @return array ['success' => true] or ['error' => 'message']
 */
function handleAdminRegistration($username, $email, $password) {
    if (empty(trim($username)) || empty(trim($email)) || empty($password)) {
        return ['error' => 'missing_fields'];
    }

    if (!validateAdminUsername($username)) {
        return ['error' => 'invalid_username'];
    }

    if (!isValidAdminEmailDomain($email)) {
        return ['error' => 'invalid_domain'];
    }

    if (!validateAdminPassword($password)) {
        return ['error' => 'weak_password'];
    }

    if (registerAdmin($username, $email, $password)) {
        return ['success' => true];
    } else {
        return ['error' => 'registration_failed'];
    }
}

/**
 * Handles admin login logic
 * @param string $email
 * @param string $password
 * @return array ['success' => true] or ['error' => 'message']
 */
function handleAdminLogin($email, $password) {
    if (empty(trim($email)) || empty($password)) {
        return ['error' => 'missing_fields'];
    }

    if (loginAdmin($email, $password)) {
        return ['success' => true];
    } else {
        return ['error' => 'invalid_credentials'];
    }
}

/**
 * Handles fetching more recipes from API
 * @param int $limit
 * @param string $query
 * @return array result from fetchMoreRecipesFromApi
 */
function handleFetchMoreRecipes($limit, $query) {
    if (!validateFetchLimit($limit)) {
        return ['ok' => false, 'message' => 'Invalid fetch limit. Must be 1-500.'];
    }

    return fetchMoreRecipesFromApi($limit, $query);
}

/**
 * Handles fetching ingredient substitutions
 * @param int $limit
 * @param string $ingredient
 * @return array result from fetchIngredientSubstitutionsBackfill
 */
function handleFetchSubstitutions($limit, $ingredient) {
    if (!validateFetchLimit($limit)) {
        return ['ok' => false, 'message' => 'Invalid substitution limit. Must be 1-500.'];
    }

    if (!empty($ingredient) && !validateIngredientName($ingredient)) {
        return ['ok' => false, 'message' => 'Invalid ingredient name.'];
    }

    return fetchIngredientSubstitutionsBackfill($limit, $ingredient);
}

/**
 * Handles bulk user deletion
 * @param array $selectedIds
 * @return array result from adminDeleteUsersByIds
 */
function handleBulkDeleteUsers($selectedIds) {
    if (!is_array($selectedIds)) {
        return ['ok' => false, 'message' => 'Invalid user selection.'];
    }

    // Validate each ID is numeric
    foreach ($selectedIds as $id) {
        if (!is_numeric($id) || $id <= 0) {
            return ['ok' => false, 'message' => 'Invalid user ID in selection.'];
        }
    }

    return adminDeleteUsersByIds($selectedIds);
}

$adminAction = trim((string)($_POST['admin_action'] ?? ''));

if ($adminAction === '' && isset($_POST['admin_register'])) {
    $adminAction = 'register';
}
if ($adminAction === '' && isset($_POST['admin_login'])) {
    $adminAction = 'login';
}

if ($adminAction === 'register') {
    $result = handleAdminRegistration(
        trim((string)($_POST['username'] ?? '')),
        trim((string)($_POST['email'] ?? '')),
        (string)($_POST['password'] ?? '')
    );

    if (isset($result['error'])) {
        header('Location: ../admin/register.php?error=' . $result['error']);
    } else {
        header('Location: ../admin/login.php?success=registered');
    }
    exit();
}

if ($adminAction === 'login') {
    $result = handleAdminLogin(
        trim((string)($_POST['email'] ?? '')),
        (string)($_POST['password'] ?? '')
    );

    if (isset($result['error'])) {
        header('Location: ../admin/login.php?error=' . $result['error']);
    } else {
        header('Location: ../admin/login.php?success=login');
    }
    exit();
}

if (isset($_GET['admin_logout'])) {
    logoutAdmin();
    header('Location: ../admin/login.php');
    exit();
}

if (isset($_POST['run_repair_script'])) {
    requireAdminLogin();
    $result = runRepairIncompleteRecipesScript();
    $_SESSION['admin_flash'] = $result['message'];
    $_SESSION['admin_flash_type'] = $result['ok'] ? 'success' : 'error';
    header('Location: ../admin/dashboard.php');
    exit();
}

if (isset($_POST['rebuild_tfidf_vectors'])) {
    requireAdminLogin();
    $result = rebuildAllTfidfVectors();
    $_SESSION['admin_flash'] = $result['message'];
    $_SESSION['admin_flash_type'] = $result['ok'] ? 'success' : 'error';
    header('Location: ../admin/dashboard.php');
    exit();
}

if ($adminAction === 'fetch_more_recipes_api' || isset($_POST['fetch_more_recipes_api'])) {
    requireAdminLogin();
    $limit = (int)($_POST['fetch_limit'] ?? 20);
    $query = trim((string)($_POST['fetch_query'] ?? ''));

    $result = handleFetchMoreRecipes($limit, $query);
    $_SESSION['admin_flash'] = $result['message'];
    $_SESSION['admin_flash_type'] = $result['ok'] ? 'success' : 'error';

    $redirect = '../admin/dashboard.php';
    if (isset($_SERVER['HTTP_REFERER']) && strpos((string)$_SERVER['HTTP_REFERER'], 'manage_recipes.php') !== false) {
        $redirect = '../admin/manage_recipes.php';
    }

    header('Location: ' . $redirect);
    exit();
}

if ($adminAction === 'fetch_substitutions_api' || isset($_POST['fetch_substitutions_api'])) {
    requireAdminLogin();
    $limit = (int)($_POST['substitution_limit'] ?? 30);
    $ingredient = trim((string)($_POST['substitution_ingredient'] ?? ''));

    $result = handleFetchSubstitutions($limit, $ingredient);
    $_SESSION['admin_flash'] = $result['message'];
    $_SESSION['admin_flash_type'] = $result['ok'] ? 'success' : 'error';

    $redirect = '../admin/dashboard.php';
    if (isset($_SERVER['HTTP_REFERER']) && strpos((string)$_SERVER['HTTP_REFERER'], 'manage_substitutions.php') !== false) {
        $redirect = '../admin/manage_substitutions.php';
    }

    header('Location: ' . $redirect);
    exit();
}

if ($adminAction === 'add_recipe' || isset($_POST['admin_add_recipe'])) {
    requireAdminLogin();
    $result = adminCreateRecipe($_POST);
    $_SESSION['admin_flash'] = $result['message'];
    $_SESSION['admin_flash_type'] = $result['ok'] ? 'success' : 'error';
    header('Location: ../admin/manage_recipes.php');
    exit();
}

if ($adminAction === 'delete_users_bulk' || isset($_POST['delete_users_bulk']) || isset($_POST['user_ids'])) {
    requireAdminLogin();
    $selectedIds = $_POST['user_ids'] ?? [];

    $result = handleBulkDeleteUsers($selectedIds);
    $_SESSION['admin_flash'] = $result['message'];
    $_SESSION['admin_flash_type'] = $result['ok'] ? 'success' : 'error';
    header('Location: ../admin/manage_users.php');
    exit();
}

if (isAdminLoggedIn()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

header('Location: ../admin/login.php');
exit();
