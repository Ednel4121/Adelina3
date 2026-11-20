<?php
// Naghahatid ng pribadong attachment matapos patunayan ang role at pagmamay-ari ng complaint.
require_once 'includes/auth.php';
require_once 'includes/db.php';

$role = $_SESSION['role'] ?? '';
$complaintId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($complaintId === false) {
    http_response_code(400);
    exit('Invalid complaint ID.');
}

// Magkaiba ang saklaw ng access: staff sa lahat ng report, resident sa sarili lamang.
if (in_array($role, ['admin', 'officer'], true)) {
    $stmt = $conn->prepare(
        'SELECT attachment_file, attachment_name
         FROM complaints
         WHERE complaint_id = ? AND attachment_file IS NOT NULL'
    );
    $stmt->bind_param('i', $complaintId);
} elseif ($role === 'resident') {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare(
        'SELECT c.attachment_file, c.attachment_name
         FROM complaints c
         JOIN residents r ON r.resident_id = c.resident_id
         WHERE c.complaint_id = ? AND r.user_id = ? AND c.attachment_file IS NOT NULL'
    );
    $stmt->bind_param('ii', $complaintId, $userId);
} else {
    http_response_code(403);
    exit('Access denied.');
}

$stmt->execute();
$attachment = $stmt->get_result()->fetch_assoc();
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx'];

if (
    !$attachment
    || !preg_match('/^[a-f0-9]{32}\.(?:' . implode('|', $allowedExtensions) . ')$/', $attachment['attachment_file'])
) {
    http_response_code(404);
    exit('Attachment not found.');
}

// Tinatanggap lamang ang server-generated na pangalan ng file at umiiral na mababasang file.
$filePath = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR . 'hoa-complaint-attachments'
    . DIRECTORY_SEPARATOR . $attachment['attachment_file'];

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Attachment not found.');
}

// Pinipilit ang download at pinipigilan ang browser na hulaan o patakbuhin ang file type.
$downloadName = $attachment['attachment_name'] ?: 'complaint-attachment';
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($filePath));
header(
    'Content-Disposition: attachment; filename="attachment"; filename*=UTF-8\'\''
    . rawurlencode($downloadName)
);
readfile($filePath);
exit;
