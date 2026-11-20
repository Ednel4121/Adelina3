<?php
// Inaalis ang session data at ibinabalik ang user sa login page.
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
?>