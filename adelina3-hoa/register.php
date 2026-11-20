<?php
// Gumagawa ng bagong resident account at kaugnay na resident profile sa iisang transaction.
session_start();
require_once 'includes/db.php';

$error = '';
$fullName = '';
$email = '';
$contactNo = '';
$blockLot = '';

// Sinusuri muna ang mga kinakailangang field bago mag-hash ng password at magsulat sa database.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contactNo = trim($_POST['contact_no'] ?? '');
    $blockLot = trim($_POST['block_lot'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || $contactNo === '' || $blockLot === '') {
        $error = 'Punan nang tama ang lahat ng kinakailangang field.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $conn->begin_transaction();

            $userStmt = $conn->prepare(
                "INSERT INTO users (full_name, email, password, role, contact_no)
                 VALUES (?, ?, ?, 'resident', ?)"
            );
            $userStmt->bind_param('ssss', $fullName, $email, $hash, $contactNo);
            $userStmt->execute();
            $userId = $conn->insert_id;

            $residentStmt = $conn->prepare(
                'INSERT INTO residents (user_id, block_lot) VALUES (?, ?)'
            );
            $residentStmt->bind_param('is', $userId, $blockLot);
            $residentStmt->execute();

            $conn->commit();
            $registered = true;
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            if ($exception->getCode() === 1062) {
                $error = 'May account na gamit ang email na ito.';
            } else {
                error_log('Registration failed: ' . $exception->getMessage());
                $error = 'Hindi nakumpleto ang registration. Subukan muli.';
            }
            $registered = false;
        }

        if ($registered) {
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | Adelina 3 Homeowners Association</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="auth-page">
  <main class="login-wrap">
    <section class="login-card">
      <h1>Adelina 3 Homeowners Association</h1>
      <p class="muted">Gumawa ng account</p>
      <?php if ($error !== ''): ?>
        <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <form action="register.php" method="post">
        <label for="fullName">Full Name</label>
        <input id="fullName" type="text" name="full_name" value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" placeholder="Juan Dela Cruz" autocomplete="name" required>
        <label for="registerEmail">Email</label>
        <input id="registerEmail" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required>
        <label for="registerPassword">Password</label>
        <div class="password-field">
          <input id="registerPassword" type="password" name="password" placeholder="Password" autocomplete="new-password" required>
          <button type="button" class="password-toggle" data-password-toggle="registerPassword" aria-label="Ipakita ang password" aria-pressed="false">
            <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </button>
        </div>
        <label for="contactNo">Contact Number</label>
        <input id="contactNo" type="tel" name="contact_no" value="<?= htmlspecialchars($contactNo, ENT_QUOTES, 'UTF-8') ?>" placeholder="09XXXXXXXXX" autocomplete="tel" required>
        <label for="blockLot">Block &amp; Lot</label>
        <input id="blockLot" type="text" name="block_lot" value="<?= htmlspecialchars($blockLot, ENT_QUOTES, 'UTF-8') ?>" placeholder="Block 1, Lot 1" required>
        <button type="submit" class="btn btn-primary btn-block">Register</button>
      </form>
      <p class="muted">May account na? <a href="login.php">Mag-login</a></p>
    </section>
  </main>
  <script src="assets/js/script.js"></script>
</body>
</html>