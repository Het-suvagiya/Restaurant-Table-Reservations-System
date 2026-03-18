<?php
require_once 'config.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    echo json_encode([]);
    exit;
}

$rest_id = isset($_GET['restaurant_id']) ? (int) $_GET['restaurant_id'] : 0;
$date = $_GET['date'] ?? '';

if (!$rest_id || empty($date)) {
    echo json_encode([]);
    exit;
}

// Fetch all booked times for this restaurant on this date
// We exclude cancelled bookings
$stmt = $conn->prepare("SELECT booking_time FROM bookings WHERE restaurant_id = ? AND booking_date = ? AND status != 'cancelled'");
$stmt->bind_param("is", $rest_id, $date);
$stmt->execute();
$result = $stmt->get_result();

$booked_times = [];
while ($row = $result->fetch_assoc()) {
    // Format to H:i to match the 24-hour time slots in book.php (e.g., "13:00")
    $booked_times[] = date('H:i', strtotime($row['booking_time']));
}
$stmt->close();

echo json_encode($booked_times);
?>