<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';
$success = '';
$username = '';
$full_name = '';
$role = 'cashier';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $confirm   = trim($_POST['confirm_password'] ?? '');
    $role      = in_array($_POST['role'] ?? '', ['admin', 'cashier']) ? $_POST['role'] : 'cashier';
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';

    // Check if using production or development reCAPTCHA keys
    $isProduction = strpos(RECAPTCHA_SECRET_KEY, 'YOUR_') === false;
    $recaptchaValid = true; // Default to true for development

    if ($isProduction) {
        // Production: Verify CAPTCHA with Google
        if (!$recaptchaToken) {
            $error = 'Security verification failed. Please try again.';
            $recaptchaValid = false;
        } else {
            $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
            
            $response = @file_get_contents($verifyUrl, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => http_build_query([
                        'secret' => RECAPTCHA_SECRET_KEY,
                        'response' => $recaptchaToken
                    ])
                ]
            ]));
            
            $recaptchaData = json_decode($response, true);
            $recaptchaValid = isset($recaptchaData['success']) && $recaptchaData['success'] && 
                             isset($recaptchaData['score']) && $recaptchaData['score'] > 0.5;

            if (!$recaptchaValid) {
                $error = 'Security verification failed. You appear to be a bot. Please try again.';
            }
        }
    }

    if ($recaptchaValid) {
        if (!$username || !$full_name || !$password || !$confirm) {
            $error = 'All fields are required.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $db = getDB();
            $stmt = $db->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists. Please choose another.';
            } else {
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare(
                        'INSERT INTO users (username, password, full_name, role, is_active)
                         VALUES (?, ?, ?, ?, 1)'
                    );
                    $stmt->execute([$username, $hashed_password, $full_name, $role]);
                    $success = 'Account created successfully! You can now log in.';
                    $username = $full_name = '';
                    $role = 'cashier';
                } catch (PDOException $e) {
                    $error = 'Error creating account. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Account — Pukawa Store POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>css/app.css"/>
  <!-- Google reCAPTCHA v3 -->
  <script src="https://www.google.com/recaptcha/api.js?render=<?= RECAPTCHA_SITE_KEY ?>"></script>
</head>
<body class="login-page">

  <div class="login-split">

    <!-- LEFT: Brand panel -->
    <div class="login-left">
      <img src="<?= BASE_URL ?>img/logo.png" alt="Pukawa Store Logo"/>
      <h2>Pukawa <span>Store</span></h2>
      <p>Join our team and start managing your store efficiently</p>
      <div style="margin-top:20px;background:rgba(255,255,255,.1);border-radius:16px;padding:20px 24px;">
        <div style="color:rgba(255,255,255,.5);font-size:11px;text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px;font-weight:700;">What you get</div>
        <?php foreach(['Fast POS checkout', 'Inventory tracking', 'Sales analytics', 'Team management'] as $feat): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;color:rgba(255,255,255,.85);font-size:13px;">
          <i class="bi bi-check-circle-fill" style="color:#f26b56;font-size:15px;"></i> <?= $feat ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- RIGHT: Signup form -->
    <div class="login-card">
      <h1 class="login-title">Create Account</h1>
      <p class="login-sub">Fill in your details to get started</p>

      <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3" style="border-radius:10px;font-size:13px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3" style="border-radius:10px;font-size:13px;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate id="signupForm">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <div class="input-group">
            <span class="input-group-text bg-white" style="border-radius:10px 0 0 10px;border:1.5px solid #e4ecef;border-right:0;">
              <i class="bi bi-person-fill" style="color:#3a8fa3;"></i>
            </span>
            <input type="text" name="full_name" class="form-control"
                   placeholder="Your full name"
                   value="<?= htmlspecialchars($full_name ?? '') ?>"
                   required autofocus
                   style="border-radius:0 10px 10px 0;border-left:0;"/>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="cashier" <?= ($role ?? 'cashier') === 'cashier' ? 'selected' : '' ?>>🛒 Cashier</option>
            <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>>⚙️ Admin</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Username</label>
          <div class="input-group">
            <span class="input-group-text bg-white" style="border-radius:10px 0 0 10px;border:1.5px solid #e4ecef;border-right:0;">
              <i class="bi bi-at" style="color:#3a8fa3;"></i>
            </span>
            <input type="text" name="username" class="form-control"
                   placeholder="Choose a username"
                   value="<?= htmlspecialchars($username ?? '') ?>"
                   autocomplete="username" required
                   style="border-radius:0 10px 10px 0;border-left:0;"/>
          </div>
          <small class="text-muted" style="font-size:11px;">At least 3 characters</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text bg-white" style="border-radius:10px 0 0 10px;border:1.5px solid #e4ecef;border-right:0;">
              <i class="bi bi-lock" style="color:#3a8fa3;"></i>
            </span>
            <input type="password" name="password" class="form-control"
                   placeholder="Create a password"
                   autocomplete="new-password" required
                   style="border-radius:0 10px 10px 0;border-left:0;"/>
          </div>
          <small class="text-muted" style="font-size:11px;">At least 6 characters</small>
        </div>

        <div class="mb-4">
          <label class="form-label">Confirm Password</label>
          <div class="input-group">
            <span class="input-group-text bg-white" style="border-radius:10px 0 0 10px;border:1.5px solid #e4ecef;border-right:0;">
              <i class="bi bi-lock-fill" style="color:#3a8fa3;"></i>
            </span>
            <input type="password" name="confirm_password" class="form-control"
                   placeholder="Confirm your password"
                   autocomplete="new-password" required
                   style="border-radius:0 10px 10px 0;border-left:0;"/>
          </div>
        </div>

        <!-- Hidden reCAPTCHA token -->
        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

        <button type="submit" class="btn btn-brand w-100 mb-3 py-2">
          <i class="bi bi-person-plus me-2"></i>Create Account
        </button>
      </form>

      <hr class="my-3" style="border-color:#edf1f4;"/>
      <p class="text-center text-muted mb-0" style="font-size:13px;">
        Already have an account? <a href="<?= BASE_URL ?>login.php" style="color:#3a8fa3;font-weight:700;">Sign in</a>
      </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Handle reCAPTCHA token on form submit
    document.getElementById('signupForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const siteKey = '<?= RECAPTCHA_SITE_KEY ?>';
      const isDevelopment = siteKey.includes('YOUR_');
      
      try {
        if (isDevelopment) {
          // Development mode: skip reCAPTCHA, submit directly
          document.getElementById('g-recaptcha-response').value = 'dev-mode';
          this.submit();
        } else {
          // Production mode: get reCAPTCHA token from Google
          const token = await grecaptcha.execute(siteKey, {action: 'signup'});
          document.getElementById('g-recaptcha-response').value = token;
          this.submit();
        }
      } catch (error) {
        console.error('reCAPTCHA error:', error);
        alert('Security verification failed. Please refresh and try again.');
      }
    });
  </script>
</body>
</html>
