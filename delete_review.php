<?php
require_once 'config.php';

if (!isLoggedIn() || isAdmin() || isManager()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('my_reviews.php');
}

$user_id = $_SESSION['user_id'];
$review_id = (int) ($_POST['review_id'] ?? 0);

if ($review_id <= 0) {
    redirect('my_reviews.php');
}

// Ensure table exists (safe)
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
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
)");

// Fetch booking_id and ensure ownership
$stmt = $conn->prepare("SELECT booking_id FROM reviews WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    redirect('my_reviews.php');
}

$booking_id = (int) $row['booking_id'];

// Delete review
$del = $conn->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?");
$del->bind_param("ii", $review_id, $user_id);
$del->execute();
$del->close();

// Set booking back to pending review (so they can re-review)
$colcheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'review_status'");
if ($colcheck && $colcheck->num_rows > 0) {
    $up = $conn->prepare("UPDATE bookings SET review_status = 'pending' WHERE id = ? AND user_id = ?");
    $up->bind_param("ii", $booking_id, $user_id);
    $up->execute();
    $up->close();
}

redirect('my_reviews.php');

