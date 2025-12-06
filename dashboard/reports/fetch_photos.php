<?php
// lfgabc/dashboard/reports/fetch_photos.php
header('Content-Type: application/json');
require_once '../../assets/db/db.php';
session_start();

$report_id = intval($_GET['report_id'] ?? 0);
if ($report_id <= 0) {
  echo json_encode([]);
  exit;
}

$stmt = $conn->prepare("SELECT photo_id, filename, uploaded_at FROM photos WHERE report_id = ? ORDER BY photo_id ASC");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$res = $stmt->get_result();

$photos = [];
while ($row = $res->fetch_assoc()) {
  $photos[] = $row;
}
echo json_encode($photos);
