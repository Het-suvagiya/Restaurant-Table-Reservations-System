<?php
require_once '../config.php';

if (!isLoggedIn() || !isManager()) {
    echo json_encode([]);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');
$rest_id = $_GET['restaurant_id'] ?? 0;

$stmt = $conn->prepare("SELECT b.*, u.u_firstname as first_name, u.u_lastname as last_name, u.u_email as email FROM bookings b JOIN tbl_users u ON b.user_id = u.u_id WHERE b.restaurant_id = ? AND b.booking_date = ? ORDER BY b.booking_time ASC");
$stmt->bind_param("is", $rest_id, $date);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = [
        'time' => date('H:i', strtotime($row['booking_time'])),
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'email' => $row['email'],
        'guests' => $row['guests'],
        'status' => $row['status']
    ];
}

header('Content-Type: application/json');
echo json_encode($bookings);
