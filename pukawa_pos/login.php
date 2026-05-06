<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';

// Check for deactivation message
if (isset($_GET['msg']) && $_GET['msg'] === 'account_deactivated') {
    $error = 'Your account has been deactivated. Please contact an administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
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
        if ($username && $password) {
            $db   = getDB();
            $stmt = $db->prepare(
                'SELECT user_id, username, password, full_name, role
                 FROM users WHERE username = ? AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                header('Location: ' . BASE_URL . 'dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Please enter your credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In — Pukawa Store POS</title>
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
      <p>Your friendly neighborhood store management system</p>
      <div style="margin-top:24px; display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
        <div style="background:rgba(255,255,255,.12);border-radius:12px;padding:10px 18px;text-align:center;">
          <div style="font-size:22px;font-weight:900;color:#fff;font-family:'Nunito',sans-serif;">Fast</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.08em;">Checkout</div>
        </div>
        <div style="background:rgba(255,255,255,.12);border-radius:12px;padding:10px 18px;text-align:center;">
          <div style="font-size:22px;font-weight:900;color:#fff;font-family:'Nunito',sans-serif;">Smart</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.08em;">Inventory</div>
        </div>
        <div style="background:rgba(255,255,255,.12);border-radius:12px;padding:10px 18px;text-align:center;">
          <div style="font-size:22px;font-weight:900;color:#fff;font-family:'Nunito',sans-serif;">Live</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.08em;">Reports</div>
        </div>
      </div>
    </div>

    <!-- RIGHT: Login form -->
    <div class="login-card">
      <h1 class="login-title">Welcome back! 👋</h1>
      <p class="login-sub">Sign in to your Pukawa Store account</p>

      <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3" style="border-radius:10px;font-size:13px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate id="loginForm">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <div class="input-group">
            <span class="input-group-text bg-white" style="border-radius:10px 0 0 10px;border:1.5px solid #e4ecef;border-right:0;">
              <i class="bi bi-person" style="color:#3a8fa3;"></i>
            </span>
            <input type="text" name="username" class="form-control"
                   placeholder="Enter your username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   autocomplete="username" required autofocus
                   style="border-radius:0 10px 10px 0;border-left:0;"/>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text bg-white" style="border-radius:10px 0 0 10px;border:1.5px solid #e4ecef;border-right:0;">
              <i class="bi bi-lock" style="color:#3a8fa3;"></i>
            </span>
            <input type="password" name="password" class="form-control"
                   placeholder="Enter your password"
                   autocomplete="current-password" required
                   style="border-left:0;border-right:0;"/>
            <button type="button" class="input-group-text bg-white border-start-0"
                    style="border-radius:0 10px 10px 0;border:1.5px solid #e4ecef;border-left:0;cursor:pointer;"
                    onclick="togglePwd(this)">
              <i class="bi bi-eye" style="color:#5f7e89;"></i>
            </button>
          </div>
        </div>
        <!-- Hidden reCAPTCHA token -->
        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
        <button type="submit" class="btn btn-brand w-100 py-2 fs-6">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>

      <hr class="my-3" style="border-color:#edf1f4;"/>
      <p class="text-center text-muted mb-0" style="font-size:13px;">
        Don't have an account? <a href="<?= BASE_URL ?>signup.php" style="color:#3a8fa3;font-weight:700;">Create one</a>
      </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePwd(btn) {
      const inp = btn.closest('.input-group').querySelector('input');
      const icon = btn.querySelector('i');
      if (inp.type === 'password') {
        inp.type = 'text'; icon.className = 'bi bi-eye-slash'; icon.style.color='#3a8fa3';
      } else {
        inp.type = 'password'; icon.className = 'bi bi-eye'; icon.style.color='#5f7e89';
      }
    }

    // Handle reCAPTCHA token on form submit
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
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
          const token = await grecaptcha.execute(siteKey, {action: 'login'});
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
