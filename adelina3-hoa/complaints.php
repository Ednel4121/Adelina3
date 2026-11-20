<?php
// Pinamamahalaan ng page na ito ang pagsumite ng resident at pagproseso ng staff sa mga report.
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Complaints';
$role = $_SESSION['role'] ?? 'resident';
$isResident = $role === 'resident';
$isStaff = in_array($role, ['admin', 'officer'], true);
$userId = (int) $_SESSION['user_id'];
$error = '';
$message = $_SESSION['complaint_message'] ?? '';
unset($_SESSION['complaint_message']);

// Mga pinapayagang pagpipilian at limitasyon para ma-validate ang input at attachment.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$categories = ['Noise', 'Parking', 'Garbage', 'Water', 'Security', 'Others'];
$reportTypes = ['Complaint', 'Suggestion'];
$statuses = ['Pending', 'In Progress', 'Resolved'];
$attachmentTypes = [
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'gif' => ['image/gif'],
    'webp' => ['image/webp'],
    'pdf' => ['application/pdf'],
    'txt' => ['text/plain'],
    'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
];
$maxAttachmentBytes = 10 * 1024 * 1024;
$category = '';
$reportType = 'Complaint';
$subject = '';
$details = '';
$blockLot = '';

// Iniuugnay ang resident sa profile niya, kasama ang Block/Lot para sa form.
$residentId = 0;
if ($isResident) {
    $residentStmt = $conn->prepare('SELECT resident_id, block_lot FROM residents WHERE user_id = ? LIMIT 1');
    $residentStmt->bind_param('i', $userId);
    $residentStmt->execute();
    $resident = $residentStmt->get_result()->fetch_assoc();
    if ($resident) {
        $residentId = (int) $resident['resident_id'];
        $blockLot = $resident['block_lot'];
    }
}

// Pinoproseso rito ang resident submission o staff status update pagkatapos suriin ang CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(400);
        $error = 'Nag-expire o hindi wasto ang request. I-refresh ang page at subukan muli.';
    } elseif (isset($_POST['file_complaint']) && $isResident) {
        $reportType = trim($_POST['report_type'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $blockLot = $residentId > 0 ? $blockLot : trim($_POST['block_lot'] ?? '');
        $attachment = $_FILES['attachment'] ?? null;
        $attachmentError = '';
        $attachmentExtension = '';
        $attachmentName = '';

        // Sinusuri ang laki, extension at aktuwal na MIME type bago tanggapin ang uploaded file.
        if ($attachment && $attachment['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($attachment['error'] !== UPLOAD_ERR_OK) {
                $attachmentError = $attachment['error'] === UPLOAD_ERR_INI_SIZE
                    || $attachment['error'] === UPLOAD_ERR_FORM_SIZE
                    ? 'Lumampas sa pinapayagang file size ang attachment.'
                    : 'Hindi na-upload ang attachment. Subukan muli.';
            } elseif ($attachment['size'] > $maxAttachmentBytes || !is_uploaded_file($attachment['tmp_name'])) {
                $attachmentError = 'Hanggang 10 MB lang ang attachment.';
            } else {
                $attachmentName = basename(str_replace('\\', '/', $attachment['name']));
                $attachmentName = preg_replace('/[\x00-\x1F\x7F]/u', '', $attachmentName) ?? '';
                $attachmentExtension = strtolower(pathinfo($attachmentName, PATHINFO_EXTENSION));
                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $fileInfo->file($attachment['tmp_name']);

                if (
                    $attachmentName === ''
                    || strlen($attachmentName) > 255
                    || !isset($attachmentTypes[$attachmentExtension])
                    || !in_array($detectedMime, $attachmentTypes[$attachmentExtension], true)
                ) {
                    $attachmentError = 'Hindi suportadong file. Mag-upload ng larawan, PDF, Word, Excel, o TXT file.';
                }
            }
        }

        if (
            !in_array($reportType, $reportTypes, true)
            || !in_array($category, $categories, true)
            || $subject === ''
            || mb_strlen($subject) > 150
            || $details === ''
            || ($residentId < 1 && $blockLot === '')
            || mb_strlen($blockLot) > 50
            || $attachmentError !== ''
        ) {
            $error = $attachmentError !== ''
                ? $attachmentError
                : 'Punan nang tama ang category, subject, details, at Block/Lot kung hinihingi.';
        } else {
            // Magkasamang sine-save ang report at attachment upang maiwasan ang hindi kumpletong record.
            $storedAttachmentFile = null;
            $conn->begin_transaction();

            try {
                if ($residentId < 1) {
                    $profileStmt = $conn->prepare('INSERT INTO residents (user_id, block_lot) VALUES (?, ?)');
                    $profileStmt->bind_param('is', $userId, $blockLot);
                    $profileStmt->execute();
                    $residentId = (int) $conn->insert_id;
                }

                if ($attachment && $attachment['error'] === UPLOAD_ERR_OK) {
                    $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'hoa-complaint-attachments';
                    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0700, true) && !is_dir($uploadDirectory)) {
                        throw new RuntimeException('Hindi mabuksan ang private upload folder.');
                    }

                    $storedAttachmentFile = bin2hex(random_bytes(16)) . '.' . $attachmentExtension;
                    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $storedAttachmentFile;
                    if (!move_uploaded_file($attachment['tmp_name'], $destination)) {
                        $storedAttachmentFile = null;
                        throw new RuntimeException('Hindi na-save ang attachment.');
                    }
                }

                $stmt = $conn->prepare(
                    "INSERT INTO complaints (resident_id, report_type, category, subject, details, status, attachment_file, attachment_name)
                     VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)"
                );
                $stmt->bind_param('issssss', $residentId, $reportType, $category, $subject, $details, $storedAttachmentFile, $attachmentName);
                $stmt->execute();
                $conn->commit();

                $_SESSION['complaint_message'] = $reportType === 'Suggestion'
                    ? 'Matagumpay na naipasa ang suggestion.'
                    : 'Matagumpay na naipasa ang complaint.';
                header('Location: complaints.php');
                exit;
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                if ($storedAttachmentFile !== null && isset($destination) && is_file($destination)) {
                    unlink($destination);
                }
                error_log('Complaint submission failed: ' . $exception->getMessage());
                $error = 'Hindi naipasa ang complaint. Subukan muli.';
            } catch (RuntimeException $exception) {
                $conn->rollback();
                if ($storedAttachmentFile !== null && isset($destination) && is_file($destination)) {
                    unlink($destination);
                }
                error_log('Complaint attachment failed: ' . $exception->getMessage());
                $error = 'Hindi na-save ang attachment. Suriin ang folder permission at subukan muli.';
            }
        }
    } elseif (isset($_POST['update_complaint']) && $isStaff) {
        $complaintId = filter_var(
            $_POST['complaint_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $status = trim($_POST['status'] ?? '');
        $officerResponse = trim($_POST['officer_response'] ?? '');

        if ($complaintId === false || !in_array($status, $statuses, true)) {
            $error = 'Hindi wasto ang complaint o status na pinili.';
        } else {
            $stmt = $conn->prepare(
                'UPDATE complaints SET status = ?, officer_response = ? WHERE complaint_id = ?'
            );
            $stmt->bind_param('ssi', $status, $officerResponse, $complaintId);
            $stmt->execute();

            $_SESSION['complaint_message'] = $stmt->affected_rows > 0
                ? 'Matagumpay na na-update ang complaint.'
                : 'Walang nabagong detalye o hindi makita ang complaint.';
            header('Location: complaints.php');
            exit;
        }
    } else {
        http_response_code(403);
        $error = 'Walang pahintulot para sa complaint action na ito.';
    }
}

// Staff lang ang nakakakita ng lahat; ang resident ay nakakakita lamang ng sariling report.
$complaintSelect = 'SELECT c.complaint_id, c.report_type, c.category, c.subject, c.details, c.status,
                           c.attachment_name, c.officer_response, c.date_filed, u.full_name, r.block_lot
                    FROM complaints c
                    JOIN residents r ON c.resident_id = r.resident_id
                    JOIN users u ON r.user_id = u.user_id';

if ($isStaff) {
    $complaints = $conn->query(
        $complaintSelect . ' ORDER BY c.date_filed DESC, c.complaint_id DESC'
    );
} elseif ($isResident) {
    if ($residentId > 0) {
        $complaintStmt = $conn->prepare(
            $complaintSelect . ' WHERE c.resident_id = ? ORDER BY c.date_filed DESC, c.complaint_id DESC'
        );
        $complaintStmt->bind_param('i', $residentId);
        $complaintStmt->execute();
        $complaints = $complaintStmt->get_result();
    } else {
        $complaints = $conn->query($complaintSelect . ' WHERE 1 = 0');
    }
} else {
    $complaints = null;
}

require_once 'includes/header.php';
?>
<div class="page-header"><h2>Complaints</h2></div>
<?php if ($error !== ''): ?>
  <p class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($message !== ''): ?>
  <p class="alert alert-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($isResident): ?>
  <section class="card">
    <h3>File Complaint / Report</h3>
    <?php if ($residentId < 1): ?>
      <p class="muted">Ilagay ang Block/Lot para ma-link ang unang complaint sa resident profile mo.</p>
    <?php endif; ?>
      <form class="form-grid" action="complaints.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($residentId < 1): ?>
          <div class="form-field form-field-wide">
            <label for="complaintBlockLot">Block/Lot</label>
            <input id="complaintBlockLot" type="text" name="block_lot" maxlength="50" value="<?= htmlspecialchars($blockLot, ENT_QUOTES, 'UTF-8') ?>" placeholder="Hal. Block 1 Lot 5" required>
          </div>
        <?php endif; ?>
        <div class="form-field">
          <label for="reportType">Type</label>
          <select id="reportType" name="report_type" required>
            <?php foreach ($reportTypes as $option): ?>
              <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"<?= $reportType === $option ? ' selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="complaintCategory">Category</label>
          <select id="complaintCategory" name="category" required>
            <option value="">-- Piliin ang category --</option>
            <?php foreach ($categories as $option): ?>
              <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"<?= $category === $option ? ' selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="complaintSubject">Subject</label>
          <input id="complaintSubject" type="text" name="subject" value="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>" maxlength="150" placeholder="Maikling paksa" required>
        </div>
        <div class="form-field form-field-wide">
          <label for="complaintDetails">Details</label>
          <textarea id="complaintDetails" name="details" rows="4" placeholder="Ilarawan ang concern..." required><?= htmlspecialchars($details, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="form-field form-field-wide">
          <label for="complaintAttachment">Attachment (optional)</label>
          <input id="complaintAttachment" type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
          <small class="muted">Larawan, PDF, Word, Excel, o TXT; hanggang 10 MB.</small>
        </div>
        <div class="form-actions"><button type="submit" name="file_complaint" value="1" class="btn btn-primary">Submit Complaint</button></div>
      </form>
  </section>
<?php endif; ?>

<?php if ($isStaff || $isResident): ?>
  <section class="card">
    <div class="card-head">
      <h3><?= $isStaff ? 'All Complaints' : 'My Complaints' ?></h3>
      <input type="search" id="searchInput" placeholder="Mag-search ng complaint..." aria-label="Search complaints">
    </div>
    <div class="table-wrap">
      <table class="table" id="dataTable">
        <thead><tr><th>Resident</th><th>Block/Lot</th><th>Type</th><th>Category</th><th>Subject / Details</th><th>Date Filed</th><th>Status</th><?php if ($isStaff): ?><th>Officer Response / Update</th><?php else: ?><th>Officer Response</th><?php endif; ?></tr></thead>
        <tbody>
          <?php if ($complaints->num_rows === 0): ?>
            <tr><td colspan="8">Wala pang complaints o suggestions.</td></tr>
          <?php else: ?>
            <?php while ($complaint = $complaints->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($complaint['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($complaint['block_lot'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($complaint['report_type'] ?? 'Complaint', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($complaint['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <strong><?= htmlspecialchars($complaint['subject'], ENT_QUOTES, 'UTF-8') ?></strong>
                  <div class="muted"><?= nl2br(htmlspecialchars($complaint['details'] ?? '', ENT_QUOTES, 'UTF-8')) ?></div>
                  <?php if (!empty($complaint['attachment_name'])): ?>
                    <a href="download_complaint_attachment.php?id=<?= (int) $complaint['complaint_id'] ?>">Attachment: <?= htmlspecialchars($complaint['attachment_name'], ENT_QUOTES, 'UTF-8') ?></a>
                  <?php endif; ?>
                </td>
                <td><?= $complaint['date_filed'] ? htmlspecialchars(date('M d, Y', strtotime($complaint['date_filed'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><span class="badge <?= $complaint['status'] === 'Pending' ? 'badge-pending' : ($complaint['status'] === 'In Progress' ? 'badge-progress' : 'badge-paid') ?>"><?= htmlspecialchars($complaint['status'] ?? 'Pending', ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                  <?php if ($isStaff): ?>
                    <form action="complaints.php" method="post" class="complaint-update-form">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="complaint_id" value="<?= (int) $complaint['complaint_id'] ?>">
                      <label class="visually-hidden" for="status-<?= (int) $complaint['complaint_id'] ?>">Status</label>
                      <select id="status-<?= (int) $complaint['complaint_id'] ?>" name="status" required>
                        <?php foreach ($statuses as $statusOption): ?>
                          <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>"<?= $complaint['status'] === $statusOption ? ' selected' : '' ?>><?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                      <label class="visually-hidden" for="response-<?= (int) $complaint['complaint_id'] ?>">Officer Response</label>
                      <textarea id="response-<?= (int) $complaint['complaint_id'] ?>" name="officer_response" rows="2" placeholder="Officer response..."><?= htmlspecialchars($complaint['officer_response'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                      <button type="submit" name="update_complaint" value="1" class="btn btn-small">Update</button>
                    </form>
                  <?php else: ?>
                    <?= nl2br(htmlspecialchars($complaint['officer_response'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
