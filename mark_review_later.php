<?php
// mark_review_later.php
// AJAX endpoint for marking review as "Later"

require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? 0;

if (empty($booking_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
    exit;
}

// Verify booking belongs to user
$verify_stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ?");
$verify_stmt->bind_param("ii", $booking_id, $user_id);
$verify_stmt->execute();
$booking = $verify_stmt->get_result()->fetch_assoc();
$verify_stmt->close();

if (!$booking) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Booking not found or does not belong to you']);
    exit;
}

// Update booking review status to "later"
$stmt = $conn->prepare("UPDATE bookings SET review_status = 'later' WHERE id = ?");
$stmt->bind_param("i", $booking_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Review marked as later']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating booking: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
