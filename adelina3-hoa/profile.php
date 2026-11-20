<?php
// Ipinapakita ang profile ng naka-login na user at hinahayaang baguhin ang contact details.
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Profile';
$error = '';
$success = '';
$userId = (int) $_SESSION['user_id'];

// Ina-update lamang ang email at contact number matapos tiyakin ang pangunahing format.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $contactNo = trim($_POST['contact_no'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $contactNo === '') {
        $error = 'Maglagay ng wastong email at contact number.';
    } else {
        $updateStmt = $conn->prepare('UPDATE users SET email = ?, contact_no = ? WHERE user_id = ?');
        $updateStmt->bind_param('ssi', $email, $contactNo, $userId);

        try {
            $updateStmt->execute();
            $success = 'Matagumpay na na-update ang contact information.';
        } catch (mysqli_sql_exception $exception) {
            if ($exception->getCode() !== 1062) {
                throw $exception;
            }

            $error = 'May account na gumagamit ng email na ito.';
        }
    }
}

// Pinagsasama ang user at resident details; maaaring walang resident record ang staff account.
$profileStmt = $conn->prepare(
    'SELECT u.full_name, u.email, u.contact_no, u.role,
            r.block_lot, r.household_members, r.move_in_date, r.status
     FROM users u
     LEFT JOIN residents r ON r.user_id = u.user_id
     WHERE u.user_id = ?'
);
$profileStmt->bind_param('i', $userId);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc();

if (!$profile) {
    http_response_code(404);
    $error = 'Hindi makita ang profile ng account na ito.';
}

require_once 'includes/header.php';
?>
<div class="page-header"><h2>Profile</h2></div>
<?php if ($error !== ''): ?>
  <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($success !== ''): ?>
  <p class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($profile): ?>
  <section class="card profile-summary">
    <h3>Resident Profile</h3>
    <dl class="profile-details">
      <div><dt>Full Name</dt><dd><?= htmlspecialchars($profile['full_name'], ENT_QUOTES, 'UTF-8') ?></dd></div>
      <div><dt>Email</dt><dd><?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?></dd></div>
      <div><dt>Contact</dt><dd><?= htmlspecialchars($profile['contact_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
      <div><dt>Block/Lot</dt><dd><?= htmlspecialchars($profile['block_lot'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
      <div><dt>Household Members</dt><dd><?= htmlspecialchars((string) ($profile['household_members'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd></div>
      <div><dt>Move-in Date</dt><dd><?= htmlspecialchars($profile['move_in_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
      <div><dt>Status</dt><dd><?= htmlspecialchars($profile['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
      <div><dt>Role</dt><dd><?= htmlspecialchars(ucfirst($profile['role']), ENT_QUOTES, 'UTF-8') ?></dd></div>
    </dl>
  </section>
  <section class="card">
    <h3>Update Contact Information</h3>
    <form class="form-grid" action="profile.php" method="post">
      <div class="form-field">
        <label for="profileContact">Contact</label>
        <input id="profileContact" type="tel" name="contact_no" value="<?= htmlspecialchars($profile['contact_no'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="form-field">
        <label for="profileEmail">Email</label>
        <input id="profileEmail" type="email" name="email" value="<?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn-primary">Save Changes</button></div>
    </form>
  </section>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
