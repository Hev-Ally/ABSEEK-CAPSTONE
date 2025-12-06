<?php
// update_phone.php
// Endpoint for AJAX phone updates from report-incident.php
header('Content-Type: application/json');

// Start session (if layouts/head.php also starts session, ensure this file is only used for AJAX)
if (session_status() === PHP_SESSION_NONE) session_start();

// Adjust path to your DB file as needed
require_once __DIR__ . '../../../../assets/db/db.php';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
  exit;
}

$user_id = (int) $_SESSION['user_id'];
$new_phone = trim($_POST['new_phone'] ?? '');

if ($new_phone === '') {
  echo json_encode(['success' => false, 'message' => 'Phone number cannot be empty.']);
  exit;
}

// (Optional) basic validation: length/digits
$clean = preg_replace('/[^0-9\+]/', '', $new_phone);
if (strlen($clean) < 7) {
  // still allow but warn
  // echo json_encode(['success' => false, 'message' => 'Phone number seems too short.']);
  // exit;
}

try {
  $stmt = $conn->prepare("UPDATE users SET phone_number = ? WHERE user_id = ?");
  $stmt->bind_param('si', $new_phone, $user_id);
  $stmt->execute();
  $affected = $stmt->affected_rows;
  $stmt->close();

  // Return success (affected_rows may be 0 if same value)
  echo json_encode(['success' => true, 'phone' => $new_phone, 'affected' => $affected]);
  exit;
} catch (Exception $e) {
  error_log('update_phone error: ' . $e->getMessage());
  echo json_encode(['success' => false, 'message' => 'Database error.']);
  exit;
}
