<?php
// Pinagsasama ang payment, resident at complaint summaries para sa admin at officer.
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Hindi inilalantad ang report data sa resident o ibang role na walang pahintulot.
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'officer'], true)) {
    http_response_code(403);
    $pageTitle = 'Reports';
    require_once 'includes/header.php';
    ?>
    <div class="page-header"><h2>Reports</h2></div>
    <p class="alert" role="alert">Admin at officer lang ang maaaring tumingin ng reports.</p>
    <?php
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = 'Reports';
// Kinukuha ang tatlong report dataset na ipi-print o susuriin ng awtorisadong staff.
$payments = $conn->query(
    'SELECT p.payment_id, p.amount, p.date_paid, p.status, p.description,
            u.full_name, r.block_lot
     FROM payments p
     JOIN residents r ON p.resident_id = r.resident_id
     JOIN users u ON r.user_id = u.user_id
     ORDER BY p.payment_id DESC'
);
$residents = $conn->query(
    'SELECT u.full_name, u.email, u.contact_no, r.block_lot, r.household_members, r.move_in_date, r.status
     FROM residents r
     LEFT JOIN users u ON r.user_id = u.user_id
     ORDER BY r.block_lot'
);
$complaints = $conn->query(
    "SELECT c.report_type, c.category, c.subject, c.status, c.date_filed, u.full_name, r.block_lot
     FROM complaints c
     JOIN residents r ON c.resident_id = r.resident_id
     JOIN users u ON r.user_id = u.user_id
     ORDER BY c.date_filed DESC, c.complaint_id DESC"
);

require_once 'includes/header.php';
?>
<div class="page-header page-header-actions">
  <h2>Reports</h2>
  <button type="button" class="btn btn-primary no-print" onclick="window.print()">Print</button>
</div>
<section class="card report-section">
  <h3>Payments Report</h3>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Resident</th><th>Block/Lot</th><th>Description</th><th>Amount</th><th>Date Paid</th><th>Status</th></tr></thead>
      <tbody>
        <?php if ($payments->num_rows === 0): ?>
          <tr><td colspan="6">Wala pang payment records.</td></tr>
        <?php else: ?>
          <?php while ($payment = $payments->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($payment['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($payment['block_lot'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($payment['description'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>₱<?= number_format((float) $payment['amount'], 2) ?></td>
              <td><?= $payment['date_paid'] ? htmlspecialchars(date('M d, Y', strtotime($payment['date_paid'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
              <td><span class="badge <?= $payment['status'] === 'Paid' ? 'badge-paid' : 'badge-unpaid' ?>"><?= htmlspecialchars($payment['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<section class="card report-section">
  <h3>Residents Report</h3>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Resident</th><th>Email</th><th>Block/Lot</th><th>Household</th><th>Move-in Date</th><th>Contact</th><th>Status</th></tr></thead>
      <tbody>
        <?php if ($residents->num_rows === 0): ?>
          <tr><td colspan="7">Wala pang resident records.</td></tr>
        <?php else: ?>
          <?php while ($resident = $residents->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($resident['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($resident['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($resident['block_lot'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) $resident['household_members'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($resident['move_in_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($resident['contact_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($resident['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<section class="card report-section">
  <h3>Complaints &amp; Suggestions Report</h3>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Resident</th><th>Block/Lot</th><th>Type</th><th>Category</th><th>Subject</th><th>Date Filed</th><th>Status</th></tr></thead>
      <tbody>
        <?php if ($complaints->num_rows === 0): ?>
          <tr><td colspan="7">Wala pang complaint o suggestion records.</td></tr>
        <?php else: ?>
          <?php while ($complaint = $complaints->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($complaint['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($complaint['block_lot'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($complaint['report_type'] ?? 'Complaint', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($complaint['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($complaint['subject'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= $complaint['date_filed'] ? htmlspecialchars(date('M d, Y', strtotime($complaint['date_filed'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
              <td><span class="badge <?= $complaint['status'] === 'Resolved' ? 'badge-paid' : ($complaint['status'] === 'Pending' ? 'badge-pending' : 'badge-progress') ?>"><?= htmlspecialchars($complaint['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require_once 'includes/footer.php'; ?>
