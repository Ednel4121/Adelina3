<?php
// Tinitiyak na may aktibong session bago payagang bumukas ang mga pribadong page.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Ibinabalik sa login page ang sinumang walang authenticated user ID.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
