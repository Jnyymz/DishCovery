<?php
require_once __DIR__ . '/../core/auth.php';

if (isAdminLoggedIn()) {
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
    <title>Admin Register | DishCovery</title>
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
                <h1 class="auth-brand-title">CREATE<br>ADMIN</h1>
                <p class="auth-brand-subtitle">Register an admin account for DishCovery management.</p>
            </div>
        </div>

        <div class="auth-card-panel right">
            <div class="auth-form-wrap">
                <h2 class="auth-form-title">Admin Register</h2>

                <form method="POST" action="../core/adminHandle.php">
                    <input type="hidden" name="admin_action" value="register">

                    <div class="mb-3">
                        <label class="auth-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control auth-input" required>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label" for="email">Email</label>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control auth-input"
                               placeholder="name@eac.edu.ph"
                               pattern="^[a-zA-Z0-9._%+-]+@eac\.edu\.ph$"
                               title="Use your @eac.edu.ph email"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label" for="password">Password</label>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control auth-input"
                               minlength="6"
                               pattern="(?=.*[A-Z])(?=.*\d).{6,}"
                               title="Password must be at least 6 characters with at least 1 uppercase letter and 1 number."
                               required>
                        <div class="auth-rule">Must include at least 1 uppercase letter and 1 number.</div>
                    </div>

                    <button type="submit" name="admin_register" class="btn auth-btn w-100 mt-2">Register</button>
                </form>

                <div class="text-center mt-3 auth-helper">
                    Already have an admin account?
                    <a href="login.php">Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const adminError = <?php echo json_encode($error); ?>;

    if (adminError === 'registration_failed') {
        Swal.fire({
            icon: 'error',
            title: 'Registration Failed',
            text: 'Check if email already exists.',
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

    if (adminError === 'invalid_domain') {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Email Domain',
            text: 'Only @eac.edu.ph admin emails are allowed.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }

    if (adminError === 'weak_password') {
        Swal.fire({
            icon: 'warning',
            title: 'Weak Password',
            text: 'Password must include at least 1 uppercase letter and 1 number.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }
</script>
</body>
</html>
