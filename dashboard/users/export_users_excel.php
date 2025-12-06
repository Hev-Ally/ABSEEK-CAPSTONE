<?php
// dashboard/users/export_users_excel.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../assets/db/db.php';

// Allow admin or staff (optional: restrict to admin only)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

// Fetch all users
$result = $conn->query("
    SELECT 
        user_id,
        first_name,
        last_name,
        username,
        email,
        phone_number,
        age,
        gender,
        address,
        role,
        date_registered
    FROM users
    ORDER BY user_id DESC
");

// Prepare Excel output (XLS format readable by all Excel versions)
$filename = "users_list_" . date("Y-m-d") . ".xls";

// Set headers for download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Start table
echo "<table border='1'>";
echo "<tr>
        <th>User ID</th>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Age</th>
        <th>Gender</th>
        <th>Address</th>
        <th>Role</th>
        <th>Date Registered</th>
      </tr>";

// Fill table rows
while ($row = $result->fetch_assoc()) {

    echo "<tr>";
    echo "<td>{$row['user_id']}</td>";
    echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
    echo "<td>{$row['age']}</td>";
    echo "<td>{$row['gender']}</td>";
    echo "<td>" . htmlspecialchars($row['address']) . "</td>";
    echo "<td>{$row['role']}</td>";
    echo "<td>" . date("F j, Y", strtotime($row['date_registered'])) . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;
?>
