<?php
// Pinapahintulutan ang staff na mag-post at inililista ang mga announcement para sa lahat.
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Announcements';
$role = $_SESSION['role'] ?? 'resident';
$canPost = in_array($role, ['admin', 'officer'], true);
$error = '';
$message = $_SESSION['announcement_message'] ?? '';
unset($_SESSION['announcement_message']);

// Pinoprotektahan ng CSRF token ang form laban sa request na hindi galing sa page na ito.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$title = '';
$body = '';

// Tinatanggap lamang ang valid na post request mula sa awtorisadong staff.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(400);
        $error = 'Nag-expire o hindi wasto ang request. I-refresh ang page at subukan muli.';
    } elseif (!$canPost) {
        http_response_code(403);
        $error = 'Admin o officer lang ang maaaring mag-post ng announcement.';
    } elseif (isset($_POST['delete_announcement'])) {
        $announcementId = filter_var(
            $_POST['announcement_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($announcementId === false) {
            $error = 'Hindi wasto ang napiling announcement.';
        } else {
            $deleteStmt = $conn->prepare('DELETE FROM announcements WHERE announcement_id = ?');
            $deleteStmt->bind_param('i', $announcementId);
            $deleteStmt->execute();

            $_SESSION['announcement_message'] = $deleteStmt->affected_rows > 0
                ? 'Matagumpay na natanggal ang announcement.'
                : 'Hindi natagpuan ang announcement na tatanggalin.';
            header('Location: announcements.php');
            exit;
        }
    } else {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($title === '' || $body === '') {
            $error = 'Kailangan ang title at body ng announcement.';
        } else {
            $postedBy = (int) $_SESSION['user_id'];
            $stmt = $conn->prepare('INSERT INTO announcements (title, body, posted_by) VALUES (?, ?, ?)');
            $stmt->bind_param('ssi', $title, $body, $postedBy);
            $stmt->execute();

            $_SESSION['announcement_message'] = 'Matagumpay na naipost ang announcement.';
            header('Location: announcements.php');
            exit;
        }
    }
}

// Ipinapakita ang pinakabago muna upang madaling makita ang bagong impormasyon.
$announcements = $conn->query(
    'SELECT a.announcement_id, a.title, a.body, a.date_posted, u.full_name AS author
     FROM announcements a
     LEFT JOIN users u ON a.posted_by = u.user_id
     ORDER BY a.date_posted DESC, a.announcement_id DESC'
);

require_once 'includes/header.php';
?>
<div class="page-header"><h2>Announcements</h2></div>
<?php if ($error !== ''): ?>
  <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($message !== ''): ?>
  <p class="alert alert-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($canPost): ?>
  <section class="card">
    <h3>Post Announcement</h3>
    <form class="form-grid" action="announcements.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <div class="form-field form-field-wide">
        <label for="announcementTitle">Title</label>
        <input id="announcementTitle" type="text" name="title" value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" maxlength="150" placeholder="Announcement title" required>
      </div>
      <div class="form-field form-field-wide">
        <label for="announcementBody">Body</label>
        <textarea id="announcementBody" name="body" rows="4" placeholder="Ilagay ang detalye ng announcement..." required><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn-primary">Post Announcement</button></div>
    </form>
  </section>
<?php endif; ?>
<section class="card">
  <h3>Latest Announcements</h3>
  <div class="announcement-list">
    <?php if ($announcements->num_rows === 0): ?>
      <p class="muted">Wala pang announcements.</p>
    <?php else: ?>
      <?php while ($announcement = $announcements->fetch_assoc()): ?>
        <article class="announcement-item">
          <div class="announcement-content">
            <h4><?= htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8') ?></h4>
            <p><?= nl2br(htmlspecialchars($announcement['body'], ENT_QUOTES, 'UTF-8')) ?></p>
            <span class="muted">
              <?= $announcement['date_posted'] ? htmlspecialchars(date('M d, Y g:i A', strtotime($announcement['date_posted'])), ENT_QUOTES, 'UTF-8') : '' ?>
              <?php if ($announcement['author']): ?> · <?= htmlspecialchars($announcement['author'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
            </span>
          </div>
          <?php if ($canPost): ?>
            <form action="announcements.php" method="post" class="announcement-remove confirm-form" data-confirm="Tatanggalin ang announcement na ito. Magpatuloy?">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="announcement_id" value="<?= (int) $announcement['announcement_id'] ?>">
              <button type="submit" name="delete_announcement" value="1" class="btn btn-small">Remove Announcement</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>
</section>
<?php require_once 'includes/footer.php'; ?>
