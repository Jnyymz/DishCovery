<?php
require_once __DIR__ . '/../core/auth.php';

$success = $_GET['success'] ?? null;

if (isLoggedIn() && $success !== 'login') {
    header("Location: dashboard.php");
    exit();
}

$error = $_GET['error'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DishCovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">

<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-card-panel left">
            <div>
                <span class="auth-brand-logo-wrap">
                    <img src="../img/logo.png" alt="DishCovery Logo" class="auth-brand-logo">
                </span>
                <h1 class="auth-brand-title">WELCOME<br>BACK!</h1>
                <p class="auth-brand-subtitle">Sign in and continue discovering recipes.</p>
            </div>
        </div>

        <div class="auth-card-panel right">
            <div class="auth-form-wrap">
                <h2 class="auth-form-title">Login</h2>

                <form method="POST" action="../core/handleForms.php">

                    <div class="mb-3">
                        <label class="auth-label">Email</label>
                        <input type="email" name="email" class="form-control auth-input" required>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label">Password</label>
                        <input type="password" name="password" class="form-control auth-input" required>
                    </div>

                    <button type="submit" name="login" class="btn auth-btn w-100 mt-2">
                        Login
                    </button>

                </form>

                <div class="text-center mt-3 auth-helper">
                    Don't have an account?
                    <a href="register.php">Sign up</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const authError = <?php echo json_encode($error); ?>;
    const authSuccess = <?php echo json_encode($success); ?>;

    if (authError === 'invalid_credentials') {
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: 'Invalid email or password.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }

    if (authError === 'missing_fields') {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Fields',
            text: 'Please complete both email and password.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }

    if (authSuccess === 'registered') {
        Swal.fire({
            icon: 'success',
            title: 'Registered',
            text: 'Registration successful. Please login.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }

    if (authSuccess === 'login') {
        Swal.fire({
            icon: 'success',
            title: 'Login Successful',
            text: 'Welcome back to DishCovery!',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        }).then(() => {
            window.location.href = 'dashboard.php';
        });
    }
</script>

</body>
</html>
