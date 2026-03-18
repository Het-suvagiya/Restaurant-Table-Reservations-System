<?php
// submit_review.php
// AJAX endpoint for submitting reviews

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
$rating = $_POST['rating'] ?? 0;
$comment = $_POST['comment'] ?? '';

// Validation
if (empty($booking_id) || empty($rating) || empty($comment)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    exit;
}

if (strlen($comment) < 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Comment must be at least 5 characters']);
    exit;
}

// Verify booking belongs to user
$verify_stmt = $conn->prepare("SELECT id, restaurant_id FROM bookings WHERE id = ? AND user_id = ?");
$verify_stmt->bind_param("ii", $booking_id, $user_id);
$verify_stmt->execute();
$booking = $verify_stmt->get_result()->fetch_assoc();
$verify_stmt->close();

if (!$booking) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Booking not found or does not belong to you']);
    exit;
}

// Ensure reviews table exists
$create_table = "CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES tbl_users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_restaurant_id (restaurant_id)
)";
$conn->query($create_table);

// Add review_status column to bookings if it doesn't exist (avoid fatal errors on duplicate column)
$colcheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'review_status'");
if ($colcheck && $colcheck->num_rows == 0) {
    $conn->query("ALTER TABLE bookings ADD COLUMN review_status VARCHAR(20) DEFAULT 'pending' AFTER status");
}

// Insert review
$stmt = $conn->prepare("INSERT INTO reviews (booking_id, user_id, restaurant_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiis", $booking_id, $user_id, $booking['restaurant_id'], $rating, $comment);

try {
    if ($stmt->execute()) {
        // Update booking review status
        $update_stmt = $conn->prepare("UPDATE bookings SET review_status = 'submitted' WHERE id = ?");
        $update_stmt->bind_param("i", $booking_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
    } else {
        if (strpos($stmt->error, 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'message' => 'You have already reviewed this booking']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error submitting review: ' . $stmt->error]);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>
