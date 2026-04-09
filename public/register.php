<?php
require_once __DIR__ . '/../core/auth.php';

if (isLoggedIn()) {
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
    <title>Register | DishCovery</title>
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
                <h1 class="auth-brand-title">JOIN<br>DISHCOVERY</h1>
                <p class="auth-brand-subtitle">Create your account for personalized recipe recommendations.</p>
            </div>
        </div>

        <div class="auth-card-panel right">
            <div class="auth-form-wrap">
                <h2 class="auth-form-title">Register</h2>

                <form method="POST" action="../core/handleForms.php" id="registerForm">

                    <div class="mb-3">
                        <label class="auth-label">Username</label>
                        <input type="text" name="username" class="form-control auth-input" required>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label">Email</label>
                        <input type="email" name="email" class="form-control auth-input" required>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label">Password</label>
                        <input type="password"
                               name="password"
                               class="form-control auth-input"
                               required
                               minlength="6"
                               pattern="(?=.*[A-Z])(?=.*\d).{6,}"
                               title="Password must be at least 6 characters with at least 1 uppercase letter and 1 number.">
                        <div class="auth-rule">Must include at least 1 uppercase letter and 1 number.</div>
                    </div>

                    <div class="form-check auth-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="agreeTerms" name="agree_terms" required>
                        <label class="form-check-label" for="agreeTerms">
                            I agree to the <a href="terms.php" target="_blank" rel="noopener noreferrer">Terms and Conditions</a>
                        </label>
                    </div>

                    <button type="submit" name="register" id="registerBtn" class="btn auth-btn w-100 mt-2" disabled>
                        Register
                    </button>

                </form>

                <div class="text-center mt-3 auth-helper">
                    Already have an account?
                    <a href="login.php">Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const registerError = <?php echo json_encode($error); ?>;
    const registerForm = document.getElementById('registerForm');
    const agreeTermsCheckbox = document.getElementById('agreeTerms');
    const registerBtn = document.getElementById('registerBtn');

    const syncRegisterState = () => {
        registerBtn.disabled = !agreeTermsCheckbox.checked;
    };

    syncRegisterState();
    agreeTermsCheckbox.addEventListener('change', syncRegisterState);

    registerForm.addEventListener('submit', function (event) {
        if (!agreeTermsCheckbox.checked) {
            event.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Terms Required',
                text: 'Please agree to the Terms and Conditions to continue.',
                confirmButtonColor: '#6C63FF',
                background: '#23233B',
                color: '#FFFFFF'
            });
        }
    });

    if (registerError === 'registration_failed') {
        Swal.fire({
            icon: 'error',
            title: 'Registration Failed',
            text: 'Registration failed. Try another email.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }

    if (registerError === 'weak_password') {
        Swal.fire({
            icon: 'warning',
            title: 'Weak Password',
            text: 'Password must include at least 1 uppercase letter and 1 number.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }

    if (registerError === 'terms_required') {
        Swal.fire({
            icon: 'warning',
            title: 'Terms Required',
            text: 'Please agree to the Terms and Conditions to continue.',
            confirmButtonColor: '#6C63FF',
            background: '#23233B',
            color: '#FFFFFF'
        });
    }
</script>

</body>
</html>
