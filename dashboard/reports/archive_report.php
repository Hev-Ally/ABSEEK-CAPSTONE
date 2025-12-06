<?php
// lfgabc/dashboard/reports/archive_report.php
header('Content-Type: application/json');
require_once '../../assets/db/db.php';
session_start();

// admin only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header("Location: ../../pages/login.php");
    exit;
}


$report_id = intval($_POST['id'] ?? 0);
if ($report_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid id']);
  exit;
}

try {
  $stmt = $conn->prepare("UPDATE reports SET status = 'Archived' WHERE report_id = ?");
  $stmt->bind_param('i', $report_id);
  $stmt->execute();

  echo json_encode(['success' => $stmt->affected_rows > 0]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
