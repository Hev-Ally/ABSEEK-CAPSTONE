<?php
/**
 * search_user.php
 * Handles AJAX autocomplete for selecting community users (admin only)
 */

header('Content-Type: application/json');
session_start();

// ✅ Adjust the path to your database connection
require_once '../../assets/db/db.php';

// ✅ Allow only admins (you can add 'staff' if needed)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  echo json_encode([]);
  exit;
}

// ✅ Get and sanitize search query
$q = trim($_GET['q'] ?? '');
if ($q === '') {
  echo json_encode([]);
  exit;
}

try {
  // ✅ Prepare query to find community users
  $stmt = $conn->prepare("
    SELECT 
      user_id, 
      CONCAT(first_name, ' ', last_name) AS fullname, 
      phone_number
    FROM users
    WHERE role = 'community_user' 
      AND (first_name LIKE ? OR last_name LIKE ? OR username LIKE ?)
    ORDER BY first_name ASC
    LIMIT 10
  ");

  $like = "%$q%";
  $stmt->bind_param("sss", $like, $like, $like);
  $stmt->execute();
  $result = $stmt->get_result();

  $users = [];
  while ($row = $result->fetch_assoc()) {
    $users[] = [
      'user_id' => $row['user_id'],
      'fullname' => $row['fullname'],
      'phone_number' => $row['phone_number']
    ];
  }

  // ✅ Output clean JSON
  echo json_encode($users);

} catch (Exception $e) {
  // ✅ Return an empty array on any failure
  error_log("Autocomplete error: " . $e->getMessage());
  echo json_encode([]);
}
