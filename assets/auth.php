<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

// Helper: restrict by role
function allow_role($roles = []) {
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: ../dashboard/index.php?denied=true");
        exit();
    }
}
?>