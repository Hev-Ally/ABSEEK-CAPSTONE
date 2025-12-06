<?php
header('Content-Type: application/json');
require_once '../../assets/db/db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
  exit;
}

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !$status) {
  echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
  exit;
}

try {
  $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE report_id = ?");
  $stmt->bind_param('si', $status, $id);
  $stmt->execute();

  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
