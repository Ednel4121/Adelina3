<?php
// Pinapahintulutan ang resident na mag-request ng facility at ang staff na mag-approve o tumanggi.
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Reservations';
$role = $_SESSION['role'] ?? 'resident';
$isResident = $role === 'resident';
$isStaff = in_array($role, ['admin', 'officer'], true);
$userId = (int) $_SESSION['user_id'];
$error = '';
$message = $_SESSION['reservation_message'] ?? '';
unset($_SESSION['reservation_message']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$facilities = ['Clubhouse', 'Basketball Court', 'Park Area'];
$hours = range(1, 12);
$minutes = ['00', '15', '30', '45'];
$periods = ['AM', 'PM'];
$facility = '';
$reservationDate = '';
$reservationHour = '';
$reservationMinute = '';
$reservationPeriod = '';
$purpose = '';
$residentId = 0;

// Iniuugnay ang reservation sa resident profile ng kasalukuyang account.
if ($isResident) {
    $residentStmt = $conn->prepare('SELECT resident_id FROM residents WHERE user_id = ? LIMIT 1');
    $residentStmt->bind_param('i', $userId);
    $residentStmt->execute();
    $resident = $residentStmt->get_result()->fetch_assoc();
    if ($resident) {
        $residentId = (int) $resident['resident_id'];
    }
}

// Pinoproseso ang bagong reservation o staff decision pagkatapos beripikahin ang token at role.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(400);
        $error = 'Nag-expire o hindi wasto ang request. I-refresh ang page at subukan muli.';
    } elseif (isset($_POST['create_reservation']) && $isResident) {
        $facility = trim($_POST['facility'] ?? '');
        $reservationDate = trim($_POST['date_reserved'] ?? '');
        $reservationHour = trim($_POST['time_hour'] ?? '');
        $reservationMinute = trim($_POST['time_minute'] ?? '');
        $reservationPeriod = trim($_POST['time_period'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $reservationDate);
        $validDate = $parsedDate !== false && $parsedDate->format('Y-m-d') === $reservationDate;
        $validTime = in_array($reservationHour, array_map('strval', $hours), true)
            && in_array($reservationMinute, $minutes, true)
            && in_array($reservationPeriod, $periods, true);

        if ($residentId < 1) {
            $error = 'Wala pang resident profile na naka-link sa account mo.';
        } elseif (
            !in_array($facility, $facilities, true)
            || !$validDate
            || $reservationDate < date('Y-m-d')
            || !$validTime
            || $purpose === ''
            || mb_strlen($purpose) > 150
        ) {
            $error = 'Suriin ang facility, future date, time, at purpose (hanggang 150 character).';
        } else {
            // Kinokonberte ang napiling 12-oras na oras sa format na ginagamit ng database.
            $timeValue = DateTime::createFromFormat(
                '!g:i A',
                $reservationHour . ':' . $reservationMinute . ' ' . $reservationPeriod
            )->format('H:i:s');
            $stmt = $conn->prepare(
                "INSERT INTO reservations (resident_id, facility, date_reserved, time_reserved, purpose, status)
                 VALUES (?, ?, ?, ?, ?, 'Pending')"
            );
            $stmt->bind_param('issss', $residentId, $facility, $reservationDate, $timeValue, $purpose);
            $stmt->execute();

            $_SESSION['reservation_message'] = 'Matagumpay na naipasa ang reservation. Pending pa ito para sa approval.';
            header('Location: reservations.php');
            exit;
        }
    } elseif (
        (isset($_POST['approve_reservation']) || isset($_POST['reject_reservation']))
        && $isStaff
    ) {
        $reservationId = filter_var(
            $_POST['reservation_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($reservationId === false) {
            $error = 'Hindi wasto ang reservation na pinili.';
        } else {
            $reservationStatus = isset($_POST['approve_reservation']) ? 'Approved' : 'Not Approved';
            $decisionStmt = $conn->prepare(
                "UPDATE reservations SET status = ?
                 WHERE reservation_id = ? AND status = 'Pending'"
            );
            $decisionStmt->bind_param('si', $reservationStatus, $reservationId);
            $decisionStmt->execute();
            $updatedMessage = $reservationStatus === 'Approved'
                ? 'Matagumpay na na-approve ang reservation.'
                : 'Na-mark bilang Not Approved ang reservation.';
            $_SESSION['reservation_message'] = $decisionStmt->affected_rows > 0
                ? $updatedMessage
                : 'Hindi na pending ang reservation o hindi ito makita.';
            header('Location: reservations.php');
            exit;
        }
    } else {
        http_response_code(403);
        $error = 'Walang pahintulot para sa reservation action na ito.';
    }
}

// Staff ay nakakakita ng lahat; resident ay nakakakita lamang ng sariling reservation.
$reservationSelect = 'SELECT b.reservation_id, b.facility, b.date_reserved, b.time_reserved, b.purpose, b.status,
                             u.full_name, r.block_lot
                      FROM reservations b
                      JOIN residents r ON b.resident_id = r.resident_id
                      JOIN users u ON r.user_id = u.user_id';

if ($isStaff) {
    $reservations = $conn->query(
        $reservationSelect . ' ORDER BY b.date_reserved DESC, b.reservation_id DESC'
    );
} elseif ($isResident && $residentId > 0) {
    $reservationListStmt = $conn->prepare(
        $reservationSelect . ' WHERE b.resident_id = ? ORDER BY b.date_reserved DESC, b.reservation_id DESC'
    );
    $reservationListStmt->bind_param('i', $residentId);
    $reservationListStmt->execute();
    $reservations = $reservationListStmt->get_result();
} else {
    $reservations = null;
}

require_once 'includes/header.php';
?>
<div class="page-header"><h2>Reservations</h2></div>
<?php if ($error !== ''): ?>
  <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($message !== ''): ?>
  <p class="alert alert-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($isResident): ?>
  <?php if ($residentId < 1): ?>
    <section class="card"><p>Wala pang resident profile na naka-link sa account mo. Makipag-ugnayan sa HOA officer.</p></section>
  <?php else: ?>
    <section class="card">
      <h3>Reserve a Facility</h3>
      <form class="form-grid" action="reservations.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-field">
          <label for="facility">Facility</label>
          <select id="facility" name="facility" required>
            <option value="">-- Piliin ang facility --</option>
            <?php foreach ($facilities as $option): ?>
              <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"<?= $facility === $option ? ' selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="reservationDate">Date</label>
          <input id="reservationDate" type="date" name="date_reserved" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($reservationDate, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-field">
          <label for="reservationHour">Time</label>
          <div class="time-select-group">
            <select id="reservationHour" name="time_hour" aria-label="Hour" required>
              <option value="">Hour</option>
              <?php foreach ($hours as $hour): ?>
                <option value="<?= $hour ?>"<?= $reservationHour === (string) $hour ? ' selected' : '' ?>><?= $hour ?></option>
              <?php endforeach; ?>
            </select>
            <span aria-hidden="true">:</span>
            <select name="time_minute" aria-label="Minute" required>
              <?php foreach ($minutes as $minute): ?>
                <option value="<?= $minute ?>"<?= $reservationMinute === $minute ? ' selected' : '' ?>><?= $minute ?></option>
              <?php endforeach; ?>
            </select>
            <select name="time_period" aria-label="AM or PM" required>
              <option value="">AM/PM</option>
              <?php foreach ($periods as $period): ?>
                <option value="<?= $period ?>"<?= $reservationPeriod === $period ? ' selected' : '' ?>><?= $period ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-field form-field-wide">
          <label for="reservationPurpose">Purpose</label>
          <textarea id="reservationPurpose" name="purpose" rows="3" maxlength="150" placeholder="Ano ang gagamitin sa facility?" required><?= htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="form-actions"><button type="submit" name="create_reservation" value="1" class="btn btn-primary">Submit Reservation</button></div>
      </form>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php if ($isStaff || ($isResident && $residentId > 0)): ?>
  <section class="card">
    <div class="card-head"><h3><?= $isStaff ? 'All Reservations' : 'My Reservations' ?></h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><?php if ($isStaff): ?><th>Resident</th><th>Block/Lot</th><?php endif; ?><th>Facility</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><?php if ($isStaff): ?><th>Action</th><?php endif; ?></tr></thead>
        <tbody>
          <?php if ($reservations->num_rows === 0): ?>
            <tr><td colspan="<?= $isStaff ? '8' : '5' ?>">Wala pang reservations.</td></tr>
          <?php else: ?>
            <?php while ($reservation = $reservations->fetch_assoc()): ?>
              <tr>
                <?php if ($isStaff): ?>
                  <td><?= htmlspecialchars($reservation['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($reservation['block_lot'], ENT_QUOTES, 'UTF-8') ?></td>
                <?php endif; ?>
                <td><?= htmlspecialchars($reservation['facility'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(date('M d, Y', strtotime($reservation['date_reserved'])), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= !empty($reservation['time_reserved']) ? htmlspecialchars(date('g:i A', strtotime($reservation['time_reserved'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><?= htmlspecialchars($reservation['purpose'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge <?= $reservation['status'] === 'Approved' ? 'badge-paid' : ($reservation['status'] === 'Not Approved' ? 'badge-unpaid' : 'badge-pending') ?>"><?= htmlspecialchars($reservation['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <?php if ($isStaff): ?>
                  <td>
                    <?php if ($reservation['status'] === 'Pending'): ?>
                      <div class="reservation-actions">
                        <form action="reservations.php" method="post" class="confirm-form" data-confirm="I-approve ang reservation na ito?">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="reservation_id" value="<?= (int) $reservation['reservation_id'] ?>">
                          <button type="submit" name="approve_reservation" value="1" class="btn btn-small">Approve</button>
                        </form>
                        <form action="reservations.php" method="post" class="confirm-form" data-confirm="Markahan bilang Not Approved ang reservation na ito?">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="reservation_id" value="<?= (int) $reservation['reservation_id'] ?>">
                          <button type="submit" name="reject_reservation" value="1" class="btn btn-small btn-danger">Not Approved</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
