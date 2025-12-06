<?php
include '../assets/db/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();

    if ($success) {
        echo 'success';
    } else {
        echo 'error';
    }

    exit;
}
?>