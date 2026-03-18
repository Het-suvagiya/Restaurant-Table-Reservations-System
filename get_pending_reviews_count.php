<?php
// get_pending_reviews_count.php
// AJAX endpoint to get count of pending reviews

require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || isAdmin() || isManager()) {
    http_response_code(401);
    echo json_encode(['count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Count pending reviews - completed bookings with no review yet
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM bookings 
    WHERE user_id = ? 
    AND status = 'confirmed' 
    AND booking_date < CURDATE() 
    AND (review_status IS NULL OR review_status = 'pending')
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['count' => $result['count']]);
?>
