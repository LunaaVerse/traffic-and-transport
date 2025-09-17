<?php
<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);
require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$errors = [];
$loginInput = '';
$forgotPasswordMessage = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process forgot password form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission";
    } else {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            // Check if email exists in users table
            $stmt = $pdo->prepare("SELECT user_id, username, email, full_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch();
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiration
                
                // Store token in database
                $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
                $updateStmt->execute([$token, $expires, $email]);
                
                // Send reset email using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    // SMTP configuration should be set in your environment or config file
                    $mail->isSMTP();
                    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = getenv('SMTP_USER') ?: 'your-email@domain.com';
                    $mail->Password = getenv('SMTP_PASS') ?: 'your-app-password';
                    $mail->SMTPSecure = defined('PHPMailer::ENCRYPTION_STARTTLS') ? PHPMailer::ENCRYPTION_STARTTLS : 'tls';
                    $mail->Port = getenv('SMTP_PORT') ?: 587;
                    
                    $mail->setFrom(getenv('SMTP_FROM_EMAIL') ?: 'noreply@yourdomain.com', getenv('SMTP_FROM_NAME') ?: 'TTM System');
                    $mail->addAddress($email, $user['full_name']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - TTM';
                    $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                    $mail->Body = "
                        <h2>Password Reset Request</h2>
                        <p>Hello {$user['full_name']},</p>
                        <p>You requested a password reset for your TTM account. Click the link below to reset your password:</p>
                        <p><a href='$resetLink'>Reset Password</a></p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you didn't request this, please ignore this email.</p>
                    ";
                    
                    $mail->send();
                    $forgotPasswordMessage = "Password reset link sent to your email";
                } catch (Exception $e) {
                    error_log("Email sending failed: " . $e->getMessage());
                    $forgotPasswordMessage = "Failed to send reset email. Please try again.";
                }
            } else {
                // Don't reveal if email exists for security
                $forgotPasswordMessage = "If an account with that email exists, a reset link has been sent";
            }
        }
    }
}

// Process login form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission";
    } else {
        // Sanitize and validate inputs
        $loginInput = trim($_POST['loginInput']);
        $password = $_POST['password'];
        $rememberMe = isset($_POST['remember']) ? true : false;
        
        // Validate inputs
        if (empty($loginInput)) {
            $errors[] = "Username or email is required";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        }
        
        // Rate limiting
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        
        if ($_SESSION['login_attempts'] > 5) {
            $errors[] = "Too many login attempts. Please try again later.";
        }
        
        // If no errors, proceed with login
        if (empty($errors)) {
            // Check in users table
            $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, full_name, role, is_verified, employee_id 
                                   FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch();
            
            // Check if we found a user
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check if email is verified
                    if (!$user['is_verified']) {
                        $errors[] = "Please verify your email first. Check your inbox.";
                        $_SESSION['verification_email'] = $user['email'];
                    } else {
                        // Regenerate session ID to prevent fixation
                        session_regenerate_id(true);
                        
                        // Clear any existing session data
                        $_SESSION = [];
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_username'] = $user['username'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['employee_id'] = $user['employee_id'];
                        $_SESSION['last_activity'] = time();
                        
                        // Set secure remember me cookie if checked
                        if ($rememberMe) {
                            $token = bin2hex(random_bytes(32));
                            $expiry = time() + (86400 * 30); // 30 days
                            
                            // Store hashed token in database
                            $hashedToken = password_hash($token, PASSWORD_DEFAULT);
                            $insertStmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, expires_at) 
                                         VALUES (?, ?, FROM_UNIXTIME(?))");
                            $insertStmt->execute([$user['user_id'], $hashedToken, $expiry]);
                            
                            setcookie('remember_me', $user['user_id'] . ':' . $token, $expiry, "/", "", true, true);
                        }
                        
                        // Reset login attempts
                        $_SESSION['login_attempts'] = 0;
                        
                        // Redirect based on user role
                        $_SESSION['user_logged_in'] = true;
                        
                        // Check user role and redirect accordingly
                        if (in_array($user['role'], ['admin', 'officer', 'operator'])) {
                            // Employee dashboard
                            header("Location: admin/dashboard.php");
                        } else {
                            // Citizen dashboard
                            header("Location: employee/index.php");
                        }
                        exit();
                    }
                } else {
                    // Increment failed login attempts
                    $_SESSION['login_attempts']++;
                    $errors[] = "Invalid credentials";
                }
            } else {
                $_SESSION['login_attempts']++;
                $errors[] = "Invalid credentials";
            }
        }
    }
}

// Check for remember me cookie
if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($userId, $token) = explode(':', $_COOKIE['remember_me']);
    
    // Validate the token
    $stmt = $pdo->prepare("SELECT users.user_id, users.username, users.email, users.full_name, users.role, users.employee_id 
                           FROM users 
                           JOIN auth_tokens ON users.user_id = auth_tokens.user_id 
                           WHERE users.user_id = ? AND auth_tokens.expires_at > NOW()");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch();
        
        // Verify token against hashed version in DB
        $tokenCheck = $pdo->prepare("SELECT id FROM auth_tokens 
                                     WHERE user_id = ? AND token_hash = ?");
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $tokenCheck->execute([$userId, $hashedToken]);
        
        if ($tokenCheck->rowCount() === 1) {
            // Regenerate session ID
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['last_activity'] = time();
            
            $_SESSION['user_logged_in'] = true;
            
            // Check user role and redirect accordingly
            if (in_array($user['role'], ['admin', 'officer', 'operator'])) {
                // Employee dashboard
                header("Location: admin/dashboard.php");
            } else {
                // Citizen dashboard
                header("Location: employee/index.php");
            }
            exit();
        }
    }
    
    // Invalid cookie - delete it
    setcookie('remember_me', '', time() - 3600, "/", "", true, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Traffic and Transport Management</title>
    <link rel="icon" type="image/png" href="img/ttm-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ...existing CSS code... */
        /* (CSS unchanged for brevity) */
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-wrapper">
            <!-- Branding Section -->
            <div class="auth-brand">
                <div class="brand-logo">
                    <img src="img/ttm.png" alt="TTM Logo" style="width: 175px; height: 175px;">
                </div>
                <h1 class="brand-title">TTM</h1>
                <p class="brand-subtitle">Traffic and Transport Management System</p>
                
                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Authentication</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-route"></i>
                        <span>Route Optimization</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-car-side"></i>
                        <span>Real-time Traffic Updates</span>
                    </div>
                </div>
            </div>
            
            <!-- Login Form Section -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h2 class="form-title">Welcome Back</h2>
                    <p class="form-subtitle">Sign in to your TTM account</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($forgotPasswordMessage)): ?>
                    <div class="alert <?php echo strpos($forgotPasswordMessage, 'sent') !== false ? 'alert-success' : 'alert-error'; ?>">
                        <p><?php echo htmlspecialchars($forgotPasswordMessage); ?></p>
                    </div>
                <?php endif; ?>
                
                <form class="auth-form" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="loginInput" class="form-label">Username or Email</label>
                        <input type="text" id="loginInput" name="loginInput" class="form-input" 
                               placeholder="Enter your username or email" 
                               value="<?php echo htmlspecialchars($loginInput); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="loginPassword" class="form-label">Password</label>
                        <input type="password" id="loginPassword" name="password" class="form-input" 
                               placeholder="Enter your password" required>
                    </div>
                    
                    <div class="form-actions">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="rememberMe" name="remember">
                            <label for="rememberMe">Remember me</label>
                        </div>
                        <a href="#" id="forgotPasswordLink" class="forgot-link">Forgot password?</a>
                    </div>
                    
                    <button type="submit" name="login" class="btn-primary">
                        Sign In
                    </button>
                </form>
                
                <div class="auth-switch">
                    <span class="auth-switch-text">Don't have an account?</span>
                    <a href="register.php" class="auth-switch-link">Sign up</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-key" style="margin-right: 8px;"></i>Forgot Password</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: var(--gray-600); line-height: 1.5;">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
                <form id="forgotPasswordForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter your email" required>
                        <small style="display: block; margin-top: 4px; color: var(--gray-500); font-size: 11px;">
                            We'll never share your email with anyone else.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelForgotPassword">
                    Cancel
                </button>
                <button type="submit" form="forgotPasswordForm" name="forgot_password" class="btn-primary" 
                        style="width: auto; padding: 10px 16px; font-size: 13px;">
                    <i class="fas fa-paper-plane" style="margin-right: 6px;"></i>Send Reset Link
                </button>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const loginInput = document.getElementById('loginInput').value;
            const password = document.getElementById('loginPassword').value;
            
            if (!loginInput || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            return true;
        });

        // Modal functionality
        const modal = document.getElementById("forgotPasswordModal");
        const btn = document.getElementById("forgotPasswordLink");
        const closeBtn = document.querySelector(".modal-close");
        const cancelBtn = document.getElementById("cancelForgotPassword");

        btn.onclick = function(e) {
            e.preventDefault();
            modal.style.display = "block";
            document.body.style.overflow = "hidden";
        }

        function closeModal() {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        }

        closeBtn.onclick = closeModal;
        cancelBtn.onclick = closeModal;

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Enhanced form interactions
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>