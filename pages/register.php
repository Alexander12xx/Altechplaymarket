<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    secureRedirect('/pages/index.php');
    exit();
}

$errors = [];
$formData = [
    'email' => '',
    'username' => '',
    'password' => '',
    'confirm_password' => '',
    'terms' => false
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'email' => $_POST['email'] ?? '',
        'username' => $_POST['username'] ?? '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'terms' => isset($_POST['terms'])
    ];

    // Validate form
    $validation = validateFormInput($formData, [
        'email' => ['required' => true, 'type' => 'email', 'label' => 'Email'],
        'username' => ['required' => false, 'type' => 'text', 'max_length' => MAX_USERNAME_LENGTH, 'label' => 'Username'],
        'password' => [
            'required' => true,
            'type' => 'password',
            'min_length' => 8,
            'confirm' => 'confirm_password',
            'label' => 'Password'
        ],
        'confirm_password' => ['required' => true, 'label' => 'Confirm Password'],
        'terms' => [
            'required' => true,
            'custom' => function($value) {
                return $value ? true : 'You must accept the terms of service';
            }
        ]
    ]);

    if (empty($validation['errors'])) {
        try {
            $user = new User();
            $result = $user->register(
                $validation['data']['email'],
                $validation['data']['password'],
                $validation['data']['username']
            );

            setFlashMessage('success', SUCCESS_REGISTRATION);
            secureRedirect('/pages/login.php');

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    } else {
        $errors = array_values($validation['errors']);
    }
}

$pageTitle = "Register - " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Join the betting and digital entertainment hub</p>
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
                <label for="email" class="form-label">Email Address *</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" class="form-input"
                           value="<?php echo e($formData['email']); ?>"
                           placeholder="Enter your email" required autocomplete="email">
                    <i class="input-icon icon-email"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">Username (optional)</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" class="form-input"
                           value="<?php echo e($formData['username']); ?>"
                           placeholder="Choose a username" maxlength="<?php echo MAX_USERNAME_LENGTH; ?>" autocomplete="username">
                    <i class="input-icon icon-user"></i>
                </div>
                <p class="form-hint">Public display name (leave empty to use email prefix)</p>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password *</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-input"
                           placeholder="Create a strong password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="password">
                        <i class="icon-eye"></i>
                    </button>
                </div>
                <div class="password-strength">
                    <div class="strength-bar"></div>
                    <span class="strength-text">Password strength</span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password *</label>
                <div class="input-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                           placeholder="Confirm your password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="confirm_password">
                        <i class="icon-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="terms" <?php echo $formData['terms'] ? 'checked' : ''; ?>>
                    <span class="checkbox-custom"></span>
                    I agree to the <a href="/pages/terms.php" target="_blank">Terms of Service</a> and <a href="/pages/privacy.php" target="_blank">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Create Account</button>

            <div class="auth-divider">
                <span>ALREADY HAVE AN ACCOUNT?</span>
            </div>

            <div class="auth-footer">
                <p>Sign in to access your account</p>
                <a href="/pages/login.php" class="btn btn-outline">Login</a>
            </div>
        </form>
    </div>

    <!-- Welcome Benefits -->
    <div class="auth-benefits">
        <h2 class="benefits-title">What You Get</h2>

        <div class="benefit-item">
            <div class="benefit-icon">
                <i class="icon-coins"></i>
            </div>
            <div class="benefit-content">
                <h3><?php echo STARTING_COINS; ?> Free Coins</h3>
                <p>Start with <?php echo STARTING_COINS; ?> virtual coins to begin betting</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon">
                <i class="icon-ad-reward"></i>
            </div>
            <div class="benefit-content">
                <h3>Earn More Coins</h3>
                <p>Watch ads and earn <?php echo AD_REWARD_COINS; ?> coins each time</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon">
                <i class="icon-chat"></i>
            </div>
            <div class="benefit-content">
                <h3>Join Community</h3>
                <p>Connect with other users in live chat</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon">
                <i class="icon-trophy"></i>
            </div>
            <div class="benefit-content">
                <h3>Multiple Betting Options</h3>
                <p>Bet on sports, crypto, and stock predictions</p>
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
/* Register Page Styles */
.auth-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    position: relative;
    z-index: 1;
}

.auth-benefits {
    display: none;
    max-width: 400px;
}

.benefits-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 2rem;
    text-align: center;
}

.benefit-item {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: rgba(74, 222, 128, 0.05);
    border-radius: 0.75rem;
    border: 1px solid rgba(74, 222, 128, 0.1);
}

.benefit-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.25rem;
    color: white;
}

.benefit-content h3 {
    margin-bottom: 0.25rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.benefit-content p {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.4;
}

.form-hint {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-style: italic;
}

.password-strength {
    margin-top: 0.5rem;
    height: 4px;
    background: var(--border-color);
    border-radius: 2px;
    overflow: hidden;
    position: relative;
}

.strength-bar {
    height: 100%;
    width: 0%;
    background: var(--danger-color);
    transition: width 0.3s, background-color 0.3s;
}

.strength-text {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

/* Password strength states */
.strength-bar.weak { width: 33%; background: var(--danger-color); }
.strength-bar.medium { width: 66%; background: var(--warning-color); }
.strength-bar.strong { width: 100%; background: var(--success-color); }

/* Responsive Design */
@media (min-width: 768px) {
    .auth-container {
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
    }

    .auth-benefits {
        display: block;
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

    .auth-subtitle {
        font-size: 0.875rem;
    }
}
</style>

<script>
// Password strength checker
document.getElementById('password').addEventListener('input', function(e) {
    const password = e.target.value;
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');

    let strength = 0;
    let feedback = '';

    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    strengthBar.className = 'strength-bar';

    switch(strength) {
        case 0:
        case 1:
            strengthBar.classList.add('weak');
            feedback = 'Weak password';
            break;
        case 2:
        case 3:
            strengthBar.classList.add('medium');
            feedback = 'Medium strength';
            break;
        case 4:
        case 5:
            strengthBar.classList.add('strong');
            feedback = 'Strong password';
            break;
    }

    strengthBar.style.width = (strength * 20) + '%';
    strengthText.textContent = feedback;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>