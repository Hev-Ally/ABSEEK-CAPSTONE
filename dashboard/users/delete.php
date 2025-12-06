<?php
// dashboard/users/delete.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../assets/db/db.php';

// Only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location: index.php?error=1");
  exit;
}

// Prevent deleting yourself (optional safety)
if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === $id) {
  header("Location: index.php?error=you_cant_delete_self");
  exit;
}

$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
  $stmt->close();
  header("Location: index.php?deleted=1");
  exit;
} else {
  $stmt->close();
  header("Location: index.php?error=1");
  exit;
}
