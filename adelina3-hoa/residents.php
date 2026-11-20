<?php
// Staff page para magdagdag ng resident at makita ang kasalukuyang resident directory.
require_once 'includes/auth.php';

$pageTitle = 'Residents';
$role = $_SESSION['role'] ?? '';
$isStaff = in_array($role, ['admin', 'officer'], true);

// Tinatapos agad ang request ng ibang role bago magbukas o magpakita ng resident data.
if (!$isStaff) {
    http_response_code(403);
    require_once 'includes/header.php';
    ?>
    <div class="page-header"><h2>Residents</h2></div>
    <p class="alert" role="alert">Admin at Officer lang ang maaaring tumingin o mamahala ng resident records.</p>
    <?php
    require_once 'includes/footer.php';
    exit;
}

require_once 'includes/db.php';

$error = '';
$success = '';
$fullName = '';
$email = '';
$blockLot = '';
$householdMembers = '1';
$moveInDate = '';
$contactNo = '';

// Pinoprotektahan ang create form laban sa cross-site request forgery.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(400);
        $error = 'Nag-expire o hindi wasto ang request. I-refresh ang page at subukan muli.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $blockLot = trim($_POST['block_lot'] ?? '');
        $householdMembers = trim($_POST['household_members'] ?? '');
        $moveInDate = trim($_POST['move_in_date'] ?? '');
        $contactNo = trim($_POST['contact_no'] ?? '');
        $password = $_POST['password'] ?? '';
        $validMoveInDate = $moveInDate === '';

        if ($moveInDate !== '') {
            $parsedMoveInDate = DateTime::createFromFormat('!Y-m-d', $moveInDate);
            $validMoveInDate = $parsedMoveInDate !== false
                && $parsedMoveInDate->format('Y-m-d') === $moveInDate;
        }

        if (
            $fullName === ''
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $blockLot === ''
            || filter_var($householdMembers, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            || !$validMoveInDate
            || $contactNo === ''
            || $password === ''
        ) {
            $error = 'Punan nang tama ang lahat ng kinakailangang field.';
        } else {
            // Isinusulat ang user at resident profile bilang isang transaksyon.
            $householdMembers = (int) $householdMembers;
            $moveInDate = $moveInDate === '' ? null : $moveInDate;
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $conn->begin_transaction();

                $userStmt = $conn->prepare(
                    "INSERT INTO users (full_name, email, password, role, contact_no)
                     VALUES (?, ?, ?, 'resident', ?)"
                );
                $userStmt->bind_param('ssss', $fullName, $email, $passwordHash, $contactNo);
                $userStmt->execute();
                $userId = $conn->insert_id;

                $residentStmt = $conn->prepare(
                    'INSERT INTO residents (user_id, block_lot, household_members, move_in_date)
                     VALUES (?, ?, ?, ?)'
                );
                $residentStmt->bind_param('isis', $userId, $blockLot, $householdMembers, $moveInDate);
                $residentStmt->execute();

                $conn->commit();
                header('Location: residents.php?added=1');
                exit;
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                error_log('Resident creation failed: ' . $exception->getMessage());

                if ($exception->getCode() === 1062) {
                    $error = 'May account na gamit ang email na ito.';
                } else {
                    $error = 'Hindi naidagdag ang resident. Subukan muli.';
                }
            }
        }
    }
}

// Inihahanda ang kumpletong directory na ipapakita sa staff.
$result = $conn->query(
    'SELECT r.resident_id, r.block_lot, r.household_members, r.move_in_date, r.status,
            u.full_name, u.email, u.contact_no
     FROM residents r
     LEFT JOIN users u ON r.user_id = u.user_id
     ORDER BY r.block_lot'
);

if (isset($_GET['added'])) {
    $success = 'Matagumpay na naidagdag ang resident.';
}

require_once 'includes/header.php';
?>
<div class="page-header"><h2>Residents</h2></div>
<?php if ($error !== ''): ?>
  <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($success !== ''): ?>
  <p class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<section class="card">
  <h3>Add Resident</h3>
  <form class="form-grid" action="residents.php" method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-field">
      <label for="residentName">Full Name</label>
      <input id="residentName" type="text" name="full_name" value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" placeholder="Juan Dela Cruz" required>
    </div>
    <div class="form-field">
      <label for="residentEmail">Email</label>
      <input id="residentEmail" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
    </div>
    <div class="form-field">
      <label for="residentPassword">Temporary Password</label>
      <div class="password-field">
        <input id="residentPassword" type="password" name="password" autocomplete="new-password" required>
        <button type="button" class="password-toggle" data-password-toggle="residentPassword" aria-label="Ipakita ang password" aria-pressed="false">
          <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </button>
      </div>
    </div>
    <div class="form-field">
      <label for="residentBlockLot">Block/Lot</label>
      <input id="residentBlockLot" type="text" name="block_lot" value="<?= htmlspecialchars($blockLot, ENT_QUOTES, 'UTF-8') ?>" placeholder="Block 1 Lot 5" required>
    </div>
    <div class="form-field">
      <label for="householdMembers">Household Members</label>
      <input id="householdMembers" type="number" name="household_members" min="1" value="<?= htmlspecialchars($householdMembers, ENT_QUOTES, 'UTF-8') ?>" required>
    </div>
    <div class="form-field">
      <label for="moveInDate">Move-in Date</label>
      <input id="moveInDate" type="date" name="move_in_date" value="<?= htmlspecialchars($moveInDate, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="form-field">
      <label for="residentContact">Contact</label>
      <input id="residentContact" type="tel" name="contact_no" value="<?= htmlspecialchars($contactNo, ENT_QUOTES, 'UTF-8') ?>" placeholder="09XXXXXXXXX" required>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary">Add Resident</button></div>
  </form>
</section>
<section class="card">
  <div class="card-head">
    <h3>Residents List</h3>
    <input type="search" id="searchInput" placeholder="Mag-search ng pangalan..." aria-label="Search residents">
  </div>
  <div class="table-wrap">
    <table class="table" id="dataTable">
      <thead><tr><th>Pangalan</th><th>Email</th><th>Block/Lot</th><th>Household</th><th>Move-in Date</th><th>Contact</th><th>Status</th></tr></thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['block_lot'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['household_members'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['move_in_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['contact_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require_once 'includes/footer.php'; ?>
