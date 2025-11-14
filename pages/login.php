<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    handleLoginRedirect();
    exit();
}

$errors = [];
$formData = [
    'email' => '',
    'password' => '',
    'remember' => false
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'remember' => isset($_POST['remember'])
    ];

    // Validate form
    $validation = validateFormInput($formData, [
        'email' => ['required' => true, 'type' => 'email', 'label' => 'Email'],
        'password' => ['required' => true, 'type' => 'password', 'label' => 'Password']
    ]);

    if (empty($validation['errors'])) {
        try {
            $user = new User();
            $result = $user->login($validation['data']['email'], $validation['data']['password'], $formData['remember']);

            // Handle remember me
            if ($formData['remember']) {
                generateRememberToken($result['user_id']);
            }

            setFlashMessage('success', SUCCESS_LOGIN);
            handleLoginRedirect();

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    } else {
        $errors = array_values($validation['errors']);
    }
}

$pageTitle = "Login - " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Login to your account to continue</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo e($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <?php echo csrfField(); ?>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" class="form-input"
                           value="<?php echo e($formData['email']); ?>"
                           placeholder="Enter your email" required autocomplete="email">
                    <i class="input-icon icon-email"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-input"
                           placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-target="password">
                        <i class="icon-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember" <?php echo $formData['remember'] ? 'checked' : ''; ?>>
                    <span class="checkbox-custom"></span>
                    Remember me for 30 days
                </label>

                <a href="/pages/forgot-password.php" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Login</button>

            <div class="auth-divider">
                <span>OR</span>
            </div>

            <div class="auth-footer">
                <p>Don't have an account?</p>
                <a href="/pages/register.php" class="btn btn-outline">Create Account</a>
            </div>
        </form>
    </div>

    <!-- Features Section -->
    <div class="auth-features">
        <div class="feature-item">
            <div class="feature-icon">
                <i class="icon-coins"></i>
            </div>
            <div class="feature-content">
                <h3>Virtual Coins</h3>
                <p>Start with 10 free coins and earn more by watching ads</p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="icon-betting"></i>
            </div>
            <div class="feature-content">
                <h3>Betting Platform</h3>
                <p>Bet on sports, crypto, and stock predictions</p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="icon-chat"></i>
            </div>
            <div class="feature-content">
                <h3>Live Chat</h3>
                <p>Connect with other users in real-time</p>
            </div>
        </div>
    </div>
</div>

<div class="auth-bg">
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>
</div>

<style>
/* Login Page Styles */
.auth-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    position: relative;
    z-index: 1;
}

.auth-card {
    background: var(--card-bg);
    border-radius: 1rem;
    padding: 2.5rem;
    width: 100%;
    max-width: 450px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 2;
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.auth-subtitle {
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.auth-form {
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.input-wrapper {
    position: relative;
}

.form-input {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 3rem;
    border: 2px solid var(--border-color);
    border-radius: 0.5rem;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: var(--input-bg);
    color: var(--text-primary);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
}

.input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.password-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.25rem;
}

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 1.25rem;
    height: 1.25rem;
    border: 2px solid var(--border-color);
    border-radius: 0.25rem;
    margin-right: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s, border-color 0.2s;
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom::after {
    content: '✓';
    color: white;
    font-size: 0.75rem;
    font-weight: bold;
}

.forgot-link {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.875rem;
}

.forgot-link:hover {
    text-decoration: underline;
}

.auth-divider {
    text-align: center;
    margin: 2rem 0;
    position: relative;
}

.auth-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: var(--border-color);
}

.auth-divider span {
    background: var(--card-bg);
    padding: 0 1rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.auth-footer {
    text-align: center;
}

.auth-footer p {
    color: var(--text-secondary);
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

.auth-features {
    display: none;
}

.auth-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 0;
}

.bg-shapes {
    position: relative;
    width: 100%;
    height: 100%;
}

.shape {
    position: absolute;
    border-radius: 50%;
    opacity: 0.1;
}

.shape-1 {
    width: 300px;
    height: 300px;
    background: var(--primary-color);
    top: 10%;
    left: 10%;
    animation: float 6s ease-in-out infinite;
}

.shape-2 {
    width: 200px;
    height: 200px;
    background: var(--secondary-color);
    top: 60%;
    right: 10%;
    animation: float 8s ease-in-out infinite reverse;
}

.shape-3 {
    width: 150px;
    height: 150px;
    background: var(--accent-color);
    bottom: 10%;
    left: 50%;
    transform: translateX(-50%);
    animation: float 10s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

/* Responsive Design */
@media (min-width: 768px) {
    .auth-container {
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
    }

    .auth-features {
        display: block;
        max-width: 400px;
    }

    .feature-item {
        display: flex;
        align-items: center;
        margin-bottom: 2rem;
    }

    .feature-icon {
        width: 60px;
        height: 60px;
        background: rgba(74, 222, 128, 0.1);
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        font-size: 1.5rem;
        color: var(--primary-color);
    }

    .feature-content h3 {
        margin-bottom: 0.25rem;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .feature-content p {
        color: var(--text-secondary);
        font-size: 0.875rem;
    }
}

@media (max-width: 767px) {
    .auth-card {
        padding: 2rem 1.5rem;
        margin: 1rem;
    }

    .auth-title {
        font-size: 1.5rem;
    }

    .form-options {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>