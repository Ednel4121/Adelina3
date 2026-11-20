<?php
// Ibinabahagi ng lahat ng authenticated page ang title, navigation at pambungad na HTML layout.
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? 'Adelina 3 Homeowners Association';
$currentPage = basename($_SERVER['PHP_SELF']);
$userName = trim((string) ($_SESSION['full_name'] ?? ''));
$userRole = ucfirst((string) ($_SESSION['role'] ?? ''));
$showUserRole = $userName === '' || strcasecmp($userName, $userRole) !== 0;
?>
<!DOCTYPE html>
<html lang="tl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Adelina 3 Homeowners Association</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
  <div class="layout">
    <!-- Ipinapakita ang navigation ayon sa role at minamarkahan ang kasalukuyang page. -->
    <aside class="sidebar" id="sidebar">
      <div class="brand">Adelina 3 Homeowners Association</div>
      <nav aria-label="Main navigation">
        <a href="dashboard.php"<?= $currentPage === 'dashboard.php' ? ' class="active" aria-current="page"' : '' ?>>Dashboard</a>
        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'officer'], true)): ?>
          <a href="residents.php"<?= $currentPage === 'residents.php' ? ' class="active" aria-current="page"' : '' ?>>Residents</a>
        <?php endif; ?>
        <a href="payments.php"<?= $currentPage === 'payments.php' ? ' class="active" aria-current="page"' : '' ?>>Payments</a>
        <a href="announcements.php"<?= $currentPage === 'announcements.php' ? ' class="active" aria-current="page"' : '' ?>>Announcements</a>
        <a href="complaints.php"<?= $currentPage === 'complaints.php' ? ' class="active" aria-current="page"' : '' ?>>Complaints / Report</a>
        <a href="reservations.php"<?= $currentPage === 'reservations.php' ? ' class="active" aria-current="page"' : '' ?>>Reservations</a>
        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'officer'], true)): ?>
          <a href="reports.php"<?= $currentPage === 'reports.php' ? ' class="active" aria-current="page"' : '' ?>>Reports</a>
        <?php endif; ?>
        <a href="profile.php"<?= $currentPage === 'profile.php' ? ' class="active" aria-current="page"' : '' ?>>Profile</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>
    <div class="main">
      <!-- Ipinapakita ang kasalukuyang user; ang menu button ay para sa maliit na screen. -->
      <header class="topbar">
        <button class="menu-btn" id="menuBtn" type="button" aria-controls="sidebar" aria-expanded="false">☰ Menu</button>
        <span>Kumusta, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?><?= $showUserRole ? ' (' . htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') . ')' : '' ?></span>
      </header>
      <main class="content">
