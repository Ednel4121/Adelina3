<?php
// Pinapatotohanan ang account at inililipat ang matagumpay na naka-login sa dashboard.
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

// Tinitingnan ang password hash mula sa database; hindi iniimbak ang password bilang plain text.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT user_id, full_name, email, password, role FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Mali ang email o password.';
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Adelina 3 Homeowners Association</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="auth-page">
  <main class="login-wrap">
    <section class="login-card">
      <h1>Adelina 3 Homeowners Association</h1>
      <p class="muted">Mag-login para magpatuloy</p>
      <?php if (isset($_GET['registered'])): ?>
        <p class="alert alert-success" role="status">Matagumpay ang registration. Mag-login para magpatuloy.</p>
      <?php endif; ?>
      <?php if (isset($_GET['password_reset'])): ?>
        <p class="alert alert-success" role="status">Napalitan na ang password. Maaari ka nang mag-login.</p>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <form action="login.php" method="post">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" placeholder="name@example.com" autocomplete="email" required>
        <label for="password">Password</label>
        <div class="password-field">
          <input id="password" type="password" name="password" placeholder="Password" autocomplete="current-password" required>
          <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Ipakita ang password" aria-pressed="false">
            <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Login</button>
      </form>
      <p class="muted"><a href="forgot_password.php">Nakalimutan ang password?</a></p>
      <p class="muted">Wala pang account? <a href="register.php">Mag-register</a></p>
    </section>
  </main>
  <script src="assets/js/script.js"></script>
</body>
</html>