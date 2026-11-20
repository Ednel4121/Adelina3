<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/send_mail.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';
$otpSent = false;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(400);
        $error = 'Nag-expire o hindi wasto ang request. I-refresh ang page at subukan muli.';
    } elseif (($_POST['action'] ?? '') === 'verify') {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Maglagay ng wastong email.';
        } else {
            $stmt = $conn->prepare(
                "SELECT user_id FROM users
                 WHERE email = ? AND role = 'resident'
                 LIMIT 1"
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();

            if (!$account) {
                $error = 'Hindi makita ang resident account gamit ang email na ito.';
            } else {
                $_SESSION['reset_user_id'] = (int) $account['user_id'];
                $_SESSION['reset_email'] = $email;
                $otp = (string) random_int(100000, 999999);
                $_SESSION['reset_otp_hash'] = hash('sha256', $otp);
                $_SESSION['reset_otp_expires'] = time() + 600;
                $_SESSION['reset_otp_attempts'] = 0;

                try {
                    sendGmailOtp($email, $otp);
                    $otpSent = true;
                    $_SESSION['reset_stage'] = 'otp';
                } catch (Throwable $exception) {
                    error_log('Gmail OTP send failed: ' . $exception->getMessage());
                    $error = 'Hindi maipadala ang OTP sa email ngayon. Subukan muli.';
                    unset(
                        $_SESSION['reset_user_id'],
                        $_SESSION['reset_email'],
                        $_SESSION['reset_otp_hash'],
                        $_SESSION['reset_otp_expires'],
                        $_SESSION['reset_otp_attempts'],
                        $_SESSION['reset_stage']
                    );
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'otp') {
        $enteredOtp = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        $otpHash = (string) ($_SESSION['reset_otp_hash'] ?? '');
        $expires = (int) ($_SESSION['reset_otp_expires'] ?? 0);
        $attempts = (int) ($_SESSION['reset_otp_attempts'] ?? 0);

        if (($_SESSION['reset_stage'] ?? '') !== 'otp' || $otpHash === '') {
            $error = 'Kailangan munang mag-request ng bagong OTP.';
        } elseif (time() > $expires) {
            $error = 'Nag-expire ang OTP. Mag-request ng bagong code.';
            unset($_SESSION['reset_otp_hash'], $_SESSION['reset_otp_expires'], $_SESSION['reset_stage']);
        } elseif ($attempts >= 5) {
            $error = 'Masyadong maraming maling attempt. Mag-request ng bagong OTP.';
        } elseif (!hash_equals($otpHash, hash('sha256', $enteredOtp))) {
            $_SESSION['reset_otp_attempts'] = $attempts + 1;
            $error = 'Mali ang OTP code.';
        } else {
            $_SESSION['reset_stage'] = 'reset';
            unset($_SESSION['reset_otp_hash'], $_SESSION['reset_otp_expires'], $_SESSION['reset_otp_attempts']);
        }
    } elseif (($_POST['action'] ?? '') === 'resend') {
        $resetEmail = (string) ($_SESSION['reset_email'] ?? '');
        $resetUserId = (int) ($_SESSION['reset_user_id'] ?? 0);
        if ($resetEmail === '' || $resetUserId <= 0) {
            $error = 'Mag-request muna ng password reset.';
        } else {
            $otp = (string) random_int(100000, 999999);
            $_SESSION['reset_otp_hash'] = hash('sha256', $otp);
            $_SESSION['reset_otp_expires'] = time() + 600;
            $_SESSION['reset_otp_attempts'] = 0;
            try {
                sendGmailOtp($resetEmail, $otp);
                $otpSent = true;
                $_SESSION['reset_stage'] = 'otp';
            } catch (Throwable $exception) {
                error_log('Gmail OTP resend failed: ' . $exception->getMessage());
                $error = 'Hindi maipadala ang bagong OTP.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'reset') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $resetUserId = (int) ($_SESSION['reset_user_id'] ?? 0);

        if ($resetUserId <= 0 || ($_SESSION['reset_stage'] ?? '') !== 'reset') {
            $error = 'Nag-expire ang reset session. I-verify muli ang account.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Ang password ay dapat may hindi bababa sa 8 character.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Hindi magkatugma ang dalawang password.';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE users SET password = ? WHERE user_id = ? AND role = 'resident'"
            );
            $stmt->bind_param('si', $passwordHash, $resetUserId);
            $stmt->execute();

            unset(
                $_SESSION['reset_user_id'],
                $_SESSION['reset_email'],
                $_SESSION['reset_stage']
            );
            header('Location: login.php?password_reset=1');
            exit;
        }
    } else {
        $error = 'Hindi wasto ang reset request.';
    }
}

$resetStage = $_SESSION['reset_stage'] ?? '';
$showOtpForm = $resetStage === 'otp';
$showResetForm = $resetStage === 'reset';
?>
<!DOCTYPE html>
<html lang="tl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | Adelina 3 Homeowners Association</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="auth-page">
  <main class="login-wrap">
    <section class="login-card">
      <h1>Forgot Password</h1>
      <p class="muted">I-reset ang password ng resident account.</p>
      <?php if ($error !== ''): ?>
        <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <?php if ($otpSent): ?>
        <p class="alert alert-success" role="status">Naipadala ang 6-digit OTP sa Gmail account mo.</p>
      <?php endif; ?>

      <?php if ($showResetForm): ?>
        <form action="forgot_password.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="reset">
          <label for="newPassword">Bagong Password</label>
          <div class="password-field">
            <input id="newPassword" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
            <button type="button" class="password-toggle" data-password-toggle="newPassword" onclick="togglePasswordField('newPassword', this)" onmousedown="showPasswordField('newPassword', this)" onmouseup="hidePasswordFieldAfterClick('newPassword', this)" onmouseleave="hidePasswordFieldAfterClick('newPassword', this)" aria-controls="newPassword" aria-label="Ipakita ang password" aria-pressed="false">
              <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
          <label for="confirmPassword">Kumpirmahin ang Password</label>
          <div class="password-field">
            <input id="confirmPassword" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            <button type="button" class="password-toggle" data-password-toggle="confirmPassword" onclick="togglePasswordField('confirmPassword', this)" onmousedown="showPasswordField('confirmPassword', this)" onmouseup="hidePasswordFieldAfterClick('confirmPassword', this)" onmouseleave="hidePasswordFieldAfterClick('confirmPassword', this)" aria-controls="confirmPassword" aria-label="Ipakita ang password" aria-pressed="false">
              <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
          <button type="submit" class="btn btn-primary btn-block">I-reset ang Password</button>
        </form>
      <?php elseif ($showOtpForm): ?>
        <form action="forgot_password.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="otp">
          <label for="otpCode">6-digit OTP Code</label>
          <input id="otpCode" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
          <button type="submit" class="btn btn-primary btn-block">I-verify ang OTP</button>
        </form>
        <form action="forgot_password.php" method="post" style="margin-top:10px;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="resend">
          <button type="submit" class="btn btn-block">Magpadala muli ng OTP</button>
        </form>
      <?php else: ?>
        <form action="forgot_password.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="verify">
          <label for="resetEmail">Email</label>
          <input id="resetEmail" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required>
          <button type="submit" class="btn btn-primary btn-block">Magpatuloy</button>
        </form>
      <?php endif; ?>

      <p class="muted"><a href="login.php">Bumalik sa Login</a></p>
    </section>
  </main>
  <script>
    function togglePasswordField(inputId, button) {
      var input = document.getElementById(inputId);
      if (!input) {
        return;
      }

      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      button.setAttribute('aria-pressed', String(!showing));
      button.setAttribute('aria-label', showing ? 'Ipakita ang password' : 'Itago ang password');
    }

    function showPasswordField(inputId, button) {
      var input = document.getElementById(inputId);
      if (!input || button.getAttribute('aria-pressed') === 'true') {
        return;
      }
      input.type = 'text';
    }

    function hidePasswordFieldAfterClick(inputId, button) {
      var input = document.getElementById(inputId);
      if (!input || button.getAttribute('aria-pressed') === 'true') {
        return;
      }
      input.type = 'password';
    }
  </script>
  <script src="assets/js/script.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/script.js') ?>"></script>
</body>
</html>
