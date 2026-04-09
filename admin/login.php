<?php
require_once __DIR__ . '/../core/auth.php';

$success = $_GET['success'] ?? '';

if (isAdminLoggedIn() && $success !== 'login') {
    header('Location: dashboard.php');
    exit();
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | DishCovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/assets/css/auth.css">
</head>
<body class="auth-page">

<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-card-panel left">
            <div>
                <span class="auth-brand-logo-wrap">
                    <img src="../img/logo.png" alt="DishCovery Logo" class="auth-brand-logo">
                </span>
                <h1 class="auth-brand-title">ADMIN<br>PORTAL</h1>
                <p class="auth-brand-subtitle">Sign in to manage DishCovery data and operations.</p>
            </div>
        </div>

        <div class="auth-card-panel right">
            <div class="auth-form-wrap">
                <h2 class="auth-form-title">Admin Login</h2>

                <form method="POST" action="../core/adminHandle.php">
                    <input type="hidden" name="admin_action" value="login">

                    <div class="mb-3">
                        <label class="auth-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control auth-input" required>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control auth-input" required>
                    </div>

                    <button type="submit" name="admin_login" class="btn auth-btn w-100 mt-2">Login</button>
                </form>

                <div class="text-center mt-3 auth-helper">
                    No admin account yet?
                    <a href="register.php">Register</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const adminError = <?php echo json_encode($error); ?>;
    const adminSuccess = <?php echo json_encode($success); ?>;

    if (adminSuccess === 'registered') {
        Swal.fire({
            icon: 'success',
            title: 'Registered',
            text: 'Admin account created. You can now log in.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }

    if (adminSuccess === 'login') {
        Swal.fire({
            icon: 'success',
            title: 'Login Successful',
            text: 'Welcome to the Admin Dashboard!',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        }).then(() => {
            window.location.href = 'dashboard.php';
        });
    }

    if (adminError === 'invalid_credentials') {
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: 'Invalid admin credentials.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }

    if (adminError === 'missing_fields') {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Fields',
            text: 'Please complete all fields.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }
</script>
</body>
</html>
