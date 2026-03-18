<?php
require_once 'header.php';

if (!isLoggedIn() || isAdmin() || isManager()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];

// Ensure reviews table exists (safe)
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

// Fetch user reviews
$stmt = $conn->prepare("
    SELECT rv.id as review_id, rv.rating, rv.comment, rv.created_at,
           r.id as restaurant_id, r.name as restaurant_name, r.primary_image
    FROM reviews rv
    JOIN restaurants r ON rv.restaurant_id = r.id
    WHERE rv.user_id = ?
    ORDER BY rv.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews = $stmt->get_result();
$stmt->close();
?>

<div class="max-w-[1000px] mx-auto px-5 pt-32 pb-24 min-h-[80vh] animate-soft-rise">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-10">
        <div>
            <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold mb-2">My Reviews</h1>
            <p class="text-ink/60 font-medium">View and manage your feedback</p>
        </div>
        <a href="my_bookings.php"
            class="bg-white border border-black/10 text-ink/70 font-bold py-3 px-6 rounded-full hover:bg-slate-50 transition-colors shadow-sm inline-flex items-center gap-2">
            <i class="fas fa-calendar-alt"></i> My Bookings
        </a>
    </div>

    <div class="flex flex-col gap-6">
        <?php if ($reviews && $reviews->num_rows > 0): ?>
            <?php while ($rv = $reviews->fetch_assoc()): ?>
                <div class="bg-white rounded-[24px] shadow-sm border border-black/5 overflow-hidden flex flex-col md:flex-row">
                    <div class="w-full md:w-48 h-48 md:h-auto shrink-0 relative">
                        <img src="<?php echo htmlspecialchars($rv['primary_image']); ?>"
                            alt="<?php echo htmlspecialchars($rv['restaurant_name']); ?>" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/30 to-transparent md:bg-none"></div>
                    </div>

                    <div class="p-6 md:p-8 flex-1">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
                            <div>
                                <h3 class="font-zodiak text-2xl text-ink font-bold mb-1">
                                    <?php echo htmlspecialchars($rv['restaurant_name']); ?>
                                </h3>
                                <div class="text-sm text-ink/60 font-medium">
                                    <i class="far fa-clock opacity-60"></i>
                                    <?php echo date('M d, Y • h:i A', strtotime($rv['created_at'])); ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center gap-1 bg-orange-50 text-orange-700 border border-orange-100 px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest">
                                    <?php echo (int) $rv['rating']; ?>/5
                                    <i class="fas fa-star"></i>
                                </div>
                                <form method="POST" action="delete_review.php"
                                    onsubmit="return openSiteConfirmForForm(this, 'Delete this review? This will allow you to review that booking again.');">
                                    <input type="hidden" name="review_id" value="<?php echo (int) $rv['review_id']; ?>">
                                    <button type="submit"
                                        class="inline-flex items-center justify-center text-[10px] sm:text-xs font-bold uppercase tracking-widest text-red-600 bg-red-50 border border-red-100 hover:bg-red-100 hover:text-red-800 px-4 py-1.5 rounded-full transition-colors">
                                        <i class="fas fa-trash mr-2"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <p class="text-ink/70 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($rv['comment'])); ?>
                        </p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-slate-50 border border-black/5 rounded-[32px] p-12 text-center flex flex-col items-center shadow-sm">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-6 text-warmWood">
                    <i class="fas fa-star text-3xl"></i>
                </div>
                <h2 class="font-zodiak text-2xl text-ink font-bold mb-3">No Reviews Yet</h2>
                <p class="text-ink/60 mb-8 max-w-md mx-auto">After your booking is completed, you’ll be able to leave feedback here.</p>
                <a href="my_bookings.php"
                    class="bg-deepTeal text-white font-bold py-3 px-8 rounded-full hover:bg-tealAccent transition-colors">
                    Go to My Bookings
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>

