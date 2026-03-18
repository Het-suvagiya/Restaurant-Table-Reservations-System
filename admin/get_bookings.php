<?php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode([]);
    exit;
}

$date = $_GET['date'] ?? null;

// If a specific date is requested, return bookings grouped by restaurant for that day
if ($date) {
    $stmt = $conn->prepare("
        SELECT r.name as restaurant_name, COUNT(b.id) as total_bookings, COALESCE(SUM(b.guests), 0) as total_guests 
        FROM bookings b 
        JOIN restaurants r ON b.restaurant_id = r.id 
        WHERE b.booking_date = ? AND b.status != 'cancelled' 
        GROUP BY r.id 
        ORDER BY total_bookings DESC
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings_by_restaurant = [];
    while ($row = $result->fetch_assoc()) {
        $bookings_by_restaurant[] = [
            'restaurant_name' => $row['restaurant_name'],
            'total_bookings' => $row['total_bookings'],
            'total_guests' => $row['total_guests']
        ];
    }
    $stmt->close();

    echo json_encode($bookings_by_restaurant);
    exit;
}

// If no date is requested, we shouldn't really hit this from the calendar, but return empty array just in case
echo json_encode([]);
exit;
?>