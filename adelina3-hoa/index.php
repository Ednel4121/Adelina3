<?php
// Ipinapadala ang bisita sa dashboard kung naka-login na, kung hindi ay sa login page.
session_start();
header(isset($_SESSION['user_id']) ? 'Location: dashboard.php' : 'Location: login.php');
exit;
?>