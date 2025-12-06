<?php
// delete_photo.php
// Location: /dashboard/community/reports/delete_photo.php

// return JSON
header('Content-Type: application/json');

// show errors (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../../layouts/head.php';

// Read JSON payload
$input = json_decode(file_get_contents('php://input'), true);
$photo_id = isset($input['photo_id']) ? intval($input['photo_id']) : 0;
$report_id = isset($input['report_id']) ? intval($input['report_id']) : 0;

if ($photo_id <= 0 || $report_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

try {
    // validate photo belongs to report
    $stmt = $conn->prepare("SELECT filename, report_id FROM photos WHERE photo_id = ?");
    $stmt->bind_param('i', $photo_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) throw new Exception('Photo not found.');

    if ($row['report_id'] != $report_id) throw new Exception('Report mismatch.');

    // check permission: if community_user ensure they own the report
    if ($user_role === 'community_user') {
        $stmt = $conn->prepare("SELECT user_id FROM reports WHERE report_id = ?");
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        $rep = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$rep || $rep['user_id'] != $user_id) {
            throw new Exception('Not authorized to delete this photo.');
        }
    }

    // delete file
    $filePath = __DIR__ . '/../../../uploads/' . $row['filename'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    // delete db row
    $stmt = $conn->prepare("DELETE FROM photos WHERE photo_id = ?");
    $stmt->bind_param('i', $photo_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
