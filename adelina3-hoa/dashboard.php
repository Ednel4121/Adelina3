<?php
// Ipinapakita rito ang magkakaibang buod para sa staff at sa naka-login na resident.
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Dashboard';
$role = strtolower($_SESSION['role'] ?? 'resident');
$isStaff = in_array($role, ['admin', 'officer'], true);
$userId = (int) $_SESSION['user_id'];
$residentId = 0;

// Hinahanap ang resident record upang ma-filter sa sariling account ang mga bilang.
if (!$isStaff) {
    $residentStmt = $conn->prepare('SELECT resident_id FROM residents WHERE user_id = ? LIMIT 1');
    $residentStmt->bind_param('i', $userId);
    $residentStmt->execute();
    $resident = $residentStmt->get_result()->fetch_assoc();
    if ($resident) {
        $residentId = (int) $resident['resident_id'];
    }
}

// Kinukuha ang angkop na mga bilang depende sa role.
if ($isStaff) {
    $stats = [
        ['Total Residents', (int) $conn->query("SELECT COUNT(*) AS c FROM residents WHERE status = 'active'")->fetch_assoc()['c']],
        ['Unpaid Dues', (int) $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status = 'Unpaid'")->fetch_assoc()['c']],
        ['Open Complaints', (int) $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE status != 'Resolved'")->fetch_assoc()['c']],
        ['Reservations', (int) $conn->query('SELECT COUNT(*) AS c FROM reservations')->fetch_assoc()['c']],
    ];
} else {
    $stats = [
        ['My Unpaid Dues', 0],
        ['My Open Complaints', 0],
        ['My Reservations', 0],
        ['Announcements', (int) $conn->query('SELECT COUNT(*) AS c FROM announcements')->fetch_assoc()['c']],
    ];

    if ($residentId > 0) {
        $unpaidStmt = $conn->prepare("SELECT COUNT(*) AS c FROM payments WHERE resident_id = ? AND status = 'Unpaid'");
        $unpaidStmt->bind_param('i', $residentId);
        $unpaidStmt->execute();
        $stats[0][1] = (int) $unpaidStmt->get_result()->fetch_assoc()['c'];

        $complaintsStmt = $conn->prepare("SELECT COUNT(*) AS c FROM complaints WHERE resident_id = ? AND status != 'Resolved'");
        $complaintsStmt->bind_param('i', $residentId);
        $complaintsStmt->execute();
        $stats[1][1] = (int) $complaintsStmt->get_result()->fetch_assoc()['c'];

        $reservationsStmt = $conn->prepare('SELECT COUNT(*) AS c FROM reservations WHERE resident_id = ?');
        $reservationsStmt->bind_param('i', $residentId);
        $reservationsStmt->execute();
        $stats[2][1] = (int) $reservationsStmt->get_result()->fetch_assoc()['c'];
    }
}

// Pareho ang announcement list na ipinapakita sa staff at resident dashboard.
$latestAnnouncements = $conn->query(
    'SELECT title, body FROM announcements ORDER BY date_posted DESC, announcement_id DESC LIMIT 3'
);

// Ipinapasa ang nakalkulang datos sa iisang shared page layout.
require_once 'includes/header.php';
?>
<div class="page-header"><h2>Dashboard</h2></div>
<div class="cards">
  <?php foreach ($stats as [$label, $value]): ?>
    <div class="card stat">
      <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
      <strong><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
  <?php endforeach; ?>
</div>
<section class="card announcements-card">
  <h3>Pinakabagong Announcements</h3>
  <?php if ($latestAnnouncements->num_rows === 0): ?>
    <p class="muted">Wala pang announcements.</p>
  <?php else: ?>
    <?php while ($announcement = $latestAnnouncements->fetch_assoc()): ?>
      <p><b><?= htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8') ?></b> — <?= nl2br(htmlspecialchars($announcement['body'], ENT_QUOTES, 'UTF-8')) ?></p>
    <?php endwhile; ?>
  <?php endif; ?>
</section>
<?php require_once 'includes/footer.php'; ?>
