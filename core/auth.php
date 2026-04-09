<?php
/* =========================================================
   auth.php
   Authentication & Session Management
   DishCovery – Web-Based Recommender System
   ========================================================= */

require_once __DIR__ . '/models.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================================
   LOGIN
   ================================ */

function loginUser($email, $password) {
    $user = getUserByEmail($email);

    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Store session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'] ?? null;

    return true;
}

/* ================================
   LOGOUT
   ================================ */

function logoutUser() {
    $_SESSION = [];
    session_destroy();
}

/* ================================
   AUTH CHECK
   ================================ */

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/* ================================
   REQUIRE LOGIN (GUARD)
   ================================ */

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/* ================================
   GET CURRENT USER
   ================================ */

function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return getUserById($_SESSION['user_id']);
}

/* ================================
   ADMIN AUTHENTICATION
   ================================ */

function loginAdmin($email, $password) {
    $admin = getAdminByEmail($email);

    if (!$admin) {
        return false;
    }

    if (!password_verify($password, $admin['password_hash'])) {
        return false;
    }

    $_SESSION['admin_id'] = $admin['admin_id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];

    return true;
}

function isValidAdminEmailDomain(string $email): bool {
    $normalized = strtolower(trim($email));
    return (bool)preg_match('/^[a-z0-9._%+\-]+@eac\.edu\.ph$/i', $normalized);
}

function registerAdmin($username, $email, $password) {
    if (!isValidAdminEmailDomain($email)) {
        return false;
    }

    return createAdmin($username, $email, $password);
}

function logoutAdmin() {
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_email']);
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header("Location: ../admin/login.php");
        exit();
    }
}

function currentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }

    return getAdminById($_SESSION['admin_id']);
}

function hasAdminRole(): bool {
    if (isAdminLoggedIn()) {
        $admin = currentAdmin();
        $adminRole = strtolower(trim((string)($admin['role'] ?? 'admin')));
        return $adminRole === 'admin' || $adminRole === 'superadmin';
    }

    if (isLoggedIn()) {
        $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
        if ($role === '') {
            $user = currentUser();
            $role = strtolower(trim((string)($user['role'] ?? '')));
        }
        return $role === 'admin';
    }

    return false;
}

function requireAdminRoleAccess(string $loginPath = '../admin/login.php'): void {
    if (!hasAdminRole()) {
        header('Location: ' . $loginPath);
        exit();
    }
}
