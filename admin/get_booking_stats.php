<?php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

header('Content-Type: application/json');

if ($type === 'daily') {
    // Day-wise total booking for a particular month and year
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));

    $stmt = $conn->prepare("
        SELECT DAY(booking_date) as day, COUNT(*) as count 
        FROM bookings 
        WHERE booking_date >= ? AND booking_date <= ? AND status != 'cancelled' 
        GROUP BY DAY(booking_date)
        ORDER BY day ASC
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $days_in_month = date('t', strtotime($start_date));
    $data = array_fill(1, $days_in_month, 0);
    
    while ($row = $result->fetch_assoc()) {
        $data[(int)$row['day']] = (int)$row['count'];
    }
    
    echo json_encode([
        'labels' => array_keys($data),
        'data' => array_values($data)
    ]);
    $stmt->close();
} elseif ($type === 'monthly') {
    // Month-wise total booking for a particular year
    $start_date = sprintf('%04d-01-01', $year);
    $end_date = sprintf('%04d-12-31', $year);

    $stmt = $conn->prepare("
        SELECT MONTH(booking_date) as month, COUNT(*) as count 
        FROM bookings 
        WHERE booking_date >= ? AND booking_date <= ? AND status != 'cancelled' 
        GROUP BY MONTH(booking_date)
        ORDER BY month ASC
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = array_fill(1, 12, 0);
    $month_names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    
    while ($row = $result->fetch_assoc()) {
        $data[(int)$row['month']] = (int)$row['count'];
    }
    
    echo json_encode([
        'labels' => $month_names,
        'data' => array_values($data)
    ]);
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid type']);
}
?>