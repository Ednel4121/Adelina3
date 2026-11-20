<?php
// Ipinapakita sa resident ang sariling balanse at pinamamahalaan ng staff ang payment records.
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Payments';
$role = $_SESSION['role'] ?? 'resident';
$userId = (int) $_SESSION['user_id'];
$error = '';
$message = $_SESSION['payment_message'] ?? '';
unset($_SESSION['payment_message']);

// Kinukuha ang resident ID ng kasalukuyang user at inihahanda ang CSRF protection ng mga form.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$residentId = 0;
if ($role === 'resident') {
    $residentStmt = $conn->prepare('SELECT resident_id FROM residents WHERE user_id = ? LIMIT 1');
    $residentStmt->bind_param('i', $userId);
    $residentStmt->execute();
    $resident = $residentStmt->get_result()->fetch_assoc();
    if ($resident) {
        $residentId = (int) $resident['resident_id'];
    }
}

$isStaff = in_array($role, ['admin', 'officer'], true);

// Bawat payment action ay sinusuri muna ang token at awtorisadong role bago baguhin ang data.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(400);
        $error = 'Nag-expire o hindi wasto ang request. I-refresh ang page at subukan muli.';
    } elseif (isset($_POST['pay_now']) && $role === 'resident') {
        if ($residentId < 1) {
            $error = 'Wala pang resident profile na naka-link sa account mo.';
        } else {
            $paymentOption = trim($_POST['payment_option'] ?? '');
            $paymentReference = trim($_POST['payment_reference'] ?? '');
            $validOptions = ['Cash', 'GCash', 'Online Bank'];

            if (!in_array($paymentOption, $validOptions, true)) {
                $error = 'Piliin ang payment option.';
            } else {
                $paymentMethod = $paymentOption === 'Cash' ? 'Cash' : 'Online Payment';
                $paymentProvider = $paymentOption === 'Cash' ? null : $paymentOption;
                $proofFile = $_FILES['payment_proof'] ?? null;
                $proofName = '';

                if ($paymentOption !== 'Cash') {
                    if ($paymentReference === '') {
                        $error = 'Maglagay ng transaction/reference number para sa online payment.';
                    } elseif (!$proofFile || $proofFile['error'] === UPLOAD_ERR_NO_FILE) {
                        $error = 'Mag-upload ng proof of payment para sa online payment.';
                    } elseif ($proofFile['error'] !== UPLOAD_ERR_OK
                        || $proofFile['size'] > 5 * 1024 * 1024
                        || !is_uploaded_file($proofFile['tmp_name'])) {
                        $error = 'Invalid o lumampas sa 5 MB ang payment proof.';
                    } else {
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                        $detectedMime = $fileInfo->file($proofFile['tmp_name']);
                        if (!in_array($detectedMime, $allowedTypes, true)) {
                            $error = 'Mag-upload ng JPG, PNG, WEBP, o PDF payment proof.';
                        } else {
                            $proofDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'hoa-payment-proofs';
                            if (!is_dir($proofDirectory) && !mkdir($proofDirectory, 0700, true) && !is_dir($proofDirectory)) {
                                throw new RuntimeException('Hindi mabuksan ang payment proof folder.');
                            }
                            $extension = strtolower(pathinfo(basename($proofFile['name']), PATHINFO_EXTENSION));
                            $proofName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
                            if (!move_uploaded_file($proofFile['tmp_name'], $proofDirectory . DIRECTORY_SEPARATOR . $proofName)) {
                                $error = 'Hindi na-save ang payment proof. Subukan muli.';
                            }
                        }
                    }
                }

                if ($error === '') {
                    $payStmt = $conn->prepare(
                        "UPDATE payments
                         SET approval_status = 'Pending',
                             date_paid = NULL,
                             payment_method = ?, payment_provider = ?,
                             payment_reference = ?, payment_proof = ?
                         WHERE resident_id = ? AND status = 'Unpaid'"
                    );
                    $payStmt->bind_param(
                        'ssssi',
                        $paymentMethod,
                        $paymentProvider,
                        $paymentReference,
                        $proofName,
                        $residentId
                    );
                    $payStmt->execute();

                    $_SESSION['payment_message'] = $payStmt->affected_rows > 0
                        ? 'Na-submit ang payment at naghihintay ng admin validation.'
                        : 'Wala kang unpaid dues na kailangang bayaran.';
                    header('Location: payments.php');
                    exit;
                }
            }
        }
    } elseif (isset($_POST['record_payment']) && $isStaff) {
        $selectedResidentId = filter_var(
            $_POST['resident_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $description = trim($_POST['description'] ?? '');
        $amountInput = $_POST['amount'] ?? '';
        $dueDate = trim($_POST['due_date'] ?? '');
        $validDescriptions = ['HOA Dues', 'Other Fees'];
        $amount = filter_var($amountInput, FILTER_VALIDATE_FLOAT);
        $validDate = $dueDate === '';

        if ($dueDate !== '') {
            $parsedDate = DateTime::createFromFormat('!Y-m-d', $dueDate);
            $validDate = $parsedDate !== false && $parsedDate->format('Y-m-d') === $dueDate;
        }

        if (
            $selectedResidentId === false
            || !in_array($description, $validDescriptions, true)
            || $amount === false
            || !is_finite((float) $amount)
            || $amount <= 0
            || $amount > 99999999.99
            || !$validDate
            || $dueDate === ''
        ) {
            $error = 'Suriin ang resident, description, amount, at due date.';
        } else {
            $selectedResidentId = (int) $selectedResidentId;
            $amount = (float) $amount;
            $status = 'Unpaid';

            $checkResident = $conn->prepare('SELECT resident_id FROM residents WHERE resident_id = ?');
            $checkResident->bind_param('i', $selectedResidentId);
            $checkResident->execute();

            if (!$checkResident->get_result()->fetch_assoc()) {
                $error = 'Hindi makita ang napiling resident.';
            } else {
                $insertStmt = $conn->prepare(
                    'INSERT INTO payments (resident_id, description, amount, due_date, status)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insertStmt->bind_param(
                    'isdss',
                    $selectedResidentId,
                    $description,
                    $amount,
                    $dueDate,
                    $status
                );
                $insertStmt->execute();

                $_SESSION['payment_message'] = 'Matagumpay na na-record ang payment.';
                header('Location: payments.php');
                exit;
            }
        }
    } elseif (isset($_POST['mark_paid']) && $isStaff) {
        $paymentId = filter_var(
            $_POST['payment_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($paymentId === false) {
            $error = 'Hindi wasto ang payment na pinili.';
        } else {
            $markStmt = $conn->prepare(
                "UPDATE payments SET status = 'Paid', date_paid = CURDATE()
                 WHERE payment_id = ? AND status = 'Unpaid'"
            );
            $markStmt->bind_param('i', $paymentId);
            $markStmt->execute();
            $_SESSION['payment_message'] = $markStmt->affected_rows > 0
                ? 'Na-update na sa Paid ang payment (demo lamang).'
                : 'Hindi makita ang unpaid payment o na-update na ito.';
            header('Location: payments.php');
            exit;
        }
    } elseif (isset($_POST['delete_payment']) && $isStaff) {
        $paymentId = filter_var(
            $_POST['payment_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($paymentId === false) {
            $error = 'Hindi wasto ang payment history na pinili.';
        } else {
            $deleteStmt = $conn->prepare('DELETE FROM payments WHERE payment_id = ?');
            $deleteStmt->bind_param('i', $paymentId);
            $deleteStmt->execute();

            $_SESSION['payment_message'] = $deleteStmt->affected_rows > 0
                ? 'Natanggal na ang payment history.'
                : 'Hindi natagpuan ang payment history na tatanggalin.';
            header('Location: payments.php');
            exit;
        }
    } elseif (isset($_POST['validate_payment']) && $isStaff) {
        $paymentId = filter_var(
            $_POST['payment_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $decision = $_POST['decision'] ?? '';

        if ($paymentId === false || !in_array($decision, ['Approved', 'Rejected'], true)) {
            $error = 'Hindi wasto ang payment validation.';
        } else {
            if ($decision === 'Approved') {
                $validateStmt = $conn->prepare(
                    "UPDATE payments
                     SET approval_status = 'Approved', status = 'Paid', date_paid = CURDATE()
                     WHERE payment_id = ?"
                );
            } else {
                $validateStmt = $conn->prepare(
                    "UPDATE payments
                     SET approval_status = 'Rejected'
                     WHERE payment_id = ?"
                );
            }
            $validateStmt->bind_param('i', $paymentId);
            $validateStmt->execute();
            $_SESSION['payment_message'] = $decision === 'Approved'
                ? 'Na-approve at na-mark ang payment bilang Paid.'
                : 'Na-reject ang payment submission.';
            header('Location: payments.php');
            exit;
        }
    } else {
        http_response_code(403);
        $error = 'Walang pahintulot para sa payment action na ito.';
    }
}

// Kinukuha lamang ang sariling payment history ng resident o ang buong listahan para sa staff.
$balance = 0.0;
$paymentHistory = null;
$allPayments = null;
$residents = null;

if ($role === 'resident' && $residentId > 0) {
    $balanceStmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM payments WHERE resident_id = ? AND status = 'Unpaid'"
    );
    $balanceStmt->bind_param('i', $residentId);
    $balanceStmt->execute();
    $balance = (float) $balanceStmt->get_result()->fetch_assoc()['total'];

    $historyStmt = $conn->prepare(
        'SELECT payment_id, description, amount, date_paid, due_date, status, approval_status,
                payment_method, payment_provider, payment_reference, payment_proof
         FROM payments WHERE resident_id = ? ORDER BY payment_id DESC'
    );
    $historyStmt->bind_param('i', $residentId);
    $historyStmt->execute();
    $paymentHistory = $historyStmt->get_result();
} elseif ($isStaff) {
    $residents = $conn->query(
        "SELECT r.resident_id, u.full_name, r.block_lot
         FROM residents r
         JOIN users u ON r.user_id = u.user_id
         WHERE r.status = 'active'
         ORDER BY u.full_name"
    );
    $allPayments = $conn->query(
        'SELECT p.payment_id, p.description, p.amount, p.date_paid, p.due_date, p.status,
                p.approval_status, p.payment_method, p.payment_provider,
                p.payment_reference, p.payment_proof,
                u.full_name, r.block_lot
         FROM payments p
         JOIN residents r ON p.resident_id = r.resident_id
         JOIN users u ON r.user_id = u.user_id
         ORDER BY p.payment_id DESC'
    );
}

require_once 'includes/header.php';
?>
<div class="page-header"><h2>Payments</h2></div>
<?php if ($error !== ''): ?>
  <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($message !== ''): ?>
  <p class="alert alert-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($role === 'resident'): ?>
  <?php if ($residentId < 1): ?>
    <section class="card"><p>Wala pang resident profile na naka-link sa account mo. Makipag-ugnayan sa HOA officer.</p></section>
  <?php else: ?>
    <section class="card balance-card">
      <div><span class="muted">Outstanding Balance</span><strong>₱<?= number_format($balance, 2) ?></strong></div>
      <?php if ($balance > 0): ?>
        <form action="payments.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-field">
            <label for="residentPaymentOption">Payment Option</label>
            <select id="residentPaymentOption" name="payment_option" required>
              <option value="">-- Piliin ang payment option --</option>
              <option value="Cash">Cash</option>
              <option value="GCash">GCash</option>
              <option value="Online Bank">Online Bank</option>
            </select>
          </div>
          <div class="form-field" id="onlineReferenceField" hidden>
            <label for="paymentReference">Transaction / Reference Number</label>
            <input id="paymentReference" type="text" name="payment_reference" maxlength="100" placeholder="GCash or bank reference number">
          </div>
          <div class="form-field" id="onlineProofField" hidden>
            <label for="paymentProof">Proof of Payment</label>
            <input id="paymentProof" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
          </div>
          <button type="submit" name="pay_now" value="1" class="btn btn-primary">Pay Now</button>
        </form>
      <?php else: ?>
        <span>Wala kang outstanding balance.</span>
      <?php endif; ?>
    </section>
    <section class="card">
      <div class="card-head">
        <h3>Payment History</h3>
        <input type="search" id="searchInput" placeholder="Mag-search sa history..." aria-label="Search payment history">
      </div>
      <div class="table-wrap">
        <table class="table" id="dataTable">
          <thead><tr><th>Date Need to Pay</th><th>Date Paid</th><th>Description</th><th>Amount</th><th>Payment Option</th><th>Status</th></tr></thead>
          <tbody>
            <?php while ($payment = $paymentHistory->fetch_assoc()): ?>
              <tr>
                <td><?= $payment['due_date'] ? htmlspecialchars(date('M d, Y', strtotime($payment['due_date'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><?= $payment['date_paid'] ? htmlspecialchars(date('M d, Y', strtotime($payment['date_paid'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><?= htmlspecialchars($payment['description'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>₱<?= number_format((float) $payment['amount'], 2) ?></td>
                <td>
                  <?= htmlspecialchars(!empty($payment['payment_provider']) ? $payment['payment_provider'] : ($payment['payment_method'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td>
                  <?php if (!empty($payment['approval_status'])): ?>
                    <span class="badge"><?= htmlspecialchars($payment['approval_status'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php else: ?>
                    <span class="badge badge-<?= strtolower(htmlspecialchars($payment['status'], ENT_QUOTES, 'UTF-8')) ?>"><?= htmlspecialchars($payment['status'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
<?php elseif ($isStaff): ?>
  <section class="card">
    <h3>Record Payment</h3>
    <form class="form-grid" action="payments.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <div class="form-field">
        <label for="paymentResident">Resident</label>
        <select id="paymentResident" name="resident_id" required>
          <option value="">-- Piliin ang resident --</option>
          <?php while ($resident = $residents->fetch_assoc()): ?>
            <option value="<?= (int) $resident['resident_id'] ?>">
              <?= htmlspecialchars($resident['full_name'] . ' - ' . $resident['block_lot'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-field">
        <label for="paymentDescription">Description</label>
        <select id="paymentDescription" name="description" required>
          <option value="HOA Dues">HOA Dues</option>
          <option value="Other Fees">Other Fees</option>
        </select>
      </div>
      <div class="form-field">
        <label for="paymentAmount">Amount</label>
        <input id="paymentAmount" type="number" step="0.01" min="0.01" max="99999999.99" name="amount" value="1000" required>
      </div>
      <div class="form-field">
        <label for="dueDate">Date Need to Pay</label>
        <input id="dueDate" type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
      </div>
      <div class="form-actions"><button type="submit" name="record_payment" value="1" class="btn btn-primary">Record Payment</button></div>
    </form>
  </section>
  <section class="card">
    <div class="card-head">
      <h3>All Payments</h3>
      <input type="search" id="searchInput" placeholder="Mag-search ng pangalan..." aria-label="Search all payments">
    </div>
    <div class="table-wrap">
      <table class="table" id="dataTable">
        <thead><tr><th>Date Need to Pay</th><th>Member</th><th>Block/Lot</th><th>Description</th><th>Amount</th><th>Validation</th><th>Action</th></tr></thead>
        <tbody>
          <?php while ($payment = $allPayments->fetch_assoc()): ?>
            <tr>
              <td><?= $payment['due_date'] ? htmlspecialchars(date('M d, Y', strtotime($payment['due_date'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
              <td><?= htmlspecialchars($payment['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($payment['block_lot'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($payment['description'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>₱<?= number_format((float) $payment['amount'], 2) ?></td>
              <td>
                <?php if (!empty($payment['approval_status'])): ?>
                  <?= htmlspecialchars($payment['approval_status'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
                <?php if (!empty($payment['payment_reference'])): ?>
                  <div>Ref: <?= htmlspecialchars($payment['payment_reference'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($payment['payment_proof'])): ?>
                  <div><a href="download_payment_proof.php?payment_id=<?= (int) $payment['payment_id'] ?>" target="_blank">View Proof</a></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if (($payment['approval_status'] ?? '') === 'Pending'): ?>
                  <form action="payments.php" method="post" class="confirm-form" data-confirm="Approve at markahan bilang Paid ang payment na ito?" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="payment_id" value="<?= (int) $payment['payment_id'] ?>">
                    <input type="hidden" name="decision" value="Approved">
                    <button type="submit" name="validate_payment" value="1" class="btn btn-small" onclick="this.form.elements.decision.value='Approved'">Approve</button>
                  </form>
                  <form action="payments.php" method="post" class="confirm-form" data-confirm="Reject ang payment submission na ito?" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="payment_id" value="<?= (int) $payment['payment_id'] ?>">
                    <input type="hidden" name="decision" value="Rejected">
                    <button type="submit" name="validate_payment" value="1" class="btn btn-small">Reject</button>
                  </form>
                <?php endif; ?>
                <form action="payments.php" method="post" class="confirm-form" data-confirm="Tatanggalin ang payment history na ito. Magpatuloy?">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="payment_id" value="<?= (int) $payment['payment_id'] ?>">
                  <button type="submit" name="delete_payment" value="1" class="btn btn-small">Delete History</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php else: ?>
  <section class="card"><p>Walang pahintulot na makita ang payments page.</p></section>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
