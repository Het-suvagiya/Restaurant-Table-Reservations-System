<?php
require_once 'header.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking_id'])) {
    $cancel_id = $_POST['cancel_booking_id'];

    // Safety check: Ensure the booking actually belongs to the user
    $cancel_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $cancel_stmt->bind_param("ii", $cancel_id, $user_id);

    if ($cancel_stmt->execute()) {
        $msg = "Booking cancelled successfully.";
    } else {
        $error = "Error cancelling booking.";
    }
    $cancel_stmt->close();
}

// Handle Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_booking_id'])) {
    $remove_id = $_POST['remove_booking_id'];

    // Safety check: Ensure the booking belongs to the user and is already cancelled
    $remove_stmt = $conn->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ? AND status = 'cancelled'");
    $remove_stmt->bind_param("ii", $remove_id, $user_id);

    if ($remove_stmt->execute()) {
        $msg = "Booking removed from your history.";
    } else {
        $error = "Error removing booking.";
    }
    $remove_stmt->close();
}

$stmt = $conn->prepare("SELECT b.*, r.name, r.primary_image, r.location FROM bookings b JOIN restaurants r ON b.restaurant_id = r.id WHERE b.user_id = ? ORDER BY b.booking_date DESC, b.booking_time DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result();
$stmt->close();
?>

<div class="max-w-[1000px] mx-auto px-5 pt-32 pb-24 min-h-[80vh] animate-soft-rise">
    <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold mb-10">My Itinerary</h1>

    <?php if (isset($msg)): ?>
        <div class="bg-teal-50 text-teal-800 p-4 rounded-2xl mb-8 border border-teal-100 flex items-center gap-3 shadow-sm">
            <i class="fas fa-check-circle text-lg"></i>
            <span class="font-medium"><?php echo $msg; ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-8 border border-red-100 flex items-center gap-3 shadow-sm">
            <i class="fas fa-exclamation-circle text-lg"></i>
            <span class="font-medium"><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <div class="flex flex-col gap-6">
        <?php if ($bookings->num_rows > 0): ?>
            <?php while ($b = $bookings->fetch_assoc()): ?>
                <div class="booking-review-group">
                <div
                    class="bg-white rounded-[24px] shadow-sm border border-black/5 overflow-hidden flex flex-col md:flex-row relative group hover:shadow-premium hover:border-black/10 transition-all duration-300">

                    <?php if ($b['status'] == 'cancelled'): ?>
                        <form method="POST" onsubmit="return openSiteConfirmForForm(this, 'Remove this booking from your history completely?');"
                            class="absolute top-4 right-4 z-20">
                            <input type="hidden" name="remove_booking_id" value="<?php echo $b['id']; ?>">
                            <button type="submit"
                                class="w-8 h-8 rounded-full bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors"
                                title="Remove Booking">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Image Section -->
                    <div class="w-full md:w-48 h-48 md:h-auto shrink-0 relative">
                        <img src="<?php echo htmlspecialchars($b['primary_image']); ?>"
                            alt="<?php echo htmlspecialchars($b['name']); ?>" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/30 to-transparent md:bg-none"></div>
                    </div>

                    <!-- Content Section -->
                    <div class="p-6 md:p-8 flex-1 flex flex-col justify-between">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4 mb-6">
                            <div>
                                <h3 class="font-zodiak text-2xl text-ink font-bold mb-2">
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </h3>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink/70 font-medium">
                                    <span class="flex items-center gap-1.5 whitespace-nowrap"><i
                                            class="fas fa-calendar-day opacity-50"></i>
                                        <?php echo date('D, M d, Y', strtotime($b['booking_date'])); ?></span>
                                    <span class="flex items-center gap-1.5 whitespace-nowrap"><i
                                            class="fas fa-clock opacity-50"></i>
                                        <?php echo date('h:i A', strtotime($b['booking_time'])); ?></span>
                                    <span class="flex items-center gap-1.5 whitespace-nowrap"><i
                                            class="fas fa-users opacity-50"></i> <?php echo $b['guests']; ?> Guest(s)</span>
                                </div>
                            </div>

                            <?php
                            $displayStatus = $b['status'];
                            $current_date = date('Y-m-d');

                            if ($displayStatus === 'confirmed' && $b['booking_date'] <= $current_date) {
                                $displayStatus = 'completed';
                            }

                            $statusBg = 'bg-teal-50';
                            $statusText = 'text-deepTeal';

                            if ($displayStatus == 'cancelled') {
                                $statusBg = 'bg-red-50';
                                $statusText = 'text-red-600';
                            } else if ($displayStatus == 'pending') {
                                $statusBg = 'bg-orange-50';
                                $statusText = 'text-orange-600';
                            } else if ($displayStatus == 'completed') {
                                $statusBg = 'bg-orange-50';
                                $statusText = 'text-orange-600';
                            }
                            ?>
                            <div
                                class="self-start inline-flex items-center justify-center px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest <?php echo $statusBg . ' ' . $statusText; ?>">
                                <?php echo htmlspecialchars($displayStatus); ?>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-black/5 pt-4 mt-auto">
                            <?php if ($displayStatus === 'confirmed' || $displayStatus === 'pending'): ?>
                                <form method="POST" onsubmit="return openSiteConfirmForForm(this, 'Are you sure you want to cancel this booking?');">
                                    <input type="hidden" name="cancel_booking_id" value="<?php echo $b['id']; ?>">
                                    <button type="submit"
                                        class="inline-flex items-center justify-center text-[10px] sm:text-xs font-bold uppercase tracking-widest text-red-600 bg-red-50 border border-red-100 hover:bg-red-100 hover:text-red-800 px-4 py-1.5 rounded-full transition-colors">
                                        Cancel Booking
                                    </button>
                                </form>
                            <?php elseif ($displayStatus === 'cancelled'): ?>
                                <span class="text-xs font-bold uppercase tracking-widest text-ink/30 px-4 py-2">Cancelled</span>
                            <?php elseif ($displayStatus === 'completed'): ?>
                                <span class="text-xs font-bold uppercase tracking-widest text-orange-400 px-4 py-2">Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Review & Feedback Section for Completed Bookings -->
                <?php if ($displayStatus === 'completed' && (empty($b['review_status']) || $b['review_status'] === 'pending')): ?>
                    <div class="bg-orange-50 rounded-[24px] border border-orange-100 shadow-sm p-6 md:p-8 mt-6" id="review-section-<?php echo $b['id']; ?>" style="position: relative; z-index: 0;">
                        <div id="review-error-<?php echo $b['id']; ?>" class="hidden mb-4 text-sm font-medium text-red-700 bg-red-50 border border-red-100 rounded-xl px-4 py-2"></div>
                        <div class="mb-4 text-sm text-ink/60">
                            Review for <?php echo htmlspecialchars($b['name']); ?> on <?php echo date('M d, Y', strtotime($b['booking_date'])); ?>
                        </div>
                        <div class="flex items-start gap-4 mb-6">
                            <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 flex-shrink-0">
                                <i class="fas fa-star text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-zodiak text-lg font-bold text-ink mb-1">Share Your Experience</h3>
                                <p class="text-sm text-ink/70">Help other diners learn about your visit to <?php echo htmlspecialchars($b['name']); ?></p>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <!-- Star Rating -->
                            <div>
                                <label class="block text-sm font-bold text-ink mb-3">Rate Your Experience</label>
                                <div class="flex gap-3 items-center" id="rating-container-<?php echo $b['id']; ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <button type="button" class="rating-star text-3xl transition-all hover:scale-110" data-rating="<?php echo $i; ?>" data-booking="<?php echo $b['id']; ?>" title="Rate <?php echo $i; ?> stars">
                                            <i class="far fa-star text-gray-300"></i>
                                        </button>
                                    <?php endfor; ?>
                                    <span class="text-sm text-ink/60 ml-2 rating-text-<?php echo $b['id']; ?>">Select a rating</span>
                                </div>
                            </div>

                            <!-- Comment Text Area -->
                            <div>
                                <label for="comment-<?php echo $b['id']; ?>" class="block text-sm font-bold text-ink mb-2">Your Feedback</label>
                                <textarea id="comment-<?php echo $b['id']; ?>" 
                                    class="w-full px-4 py-3 border border-black/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent resize-none"
                                    style="position: relative; z-index: 10; pointer-events: auto;"
                                    placeholder="Share details about your experience (e.g., food quality, service, ambiance)..."
                                    rows="4"
                                    minlength="5"></textarea>
                                <p class="text-xs text-ink/50 mt-1">Minimum 5 characters</p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-2">
                                <button type="button" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white font-bold py-3 px-6 rounded-xl transition-colors submit-review-btn" data-booking="<?php echo $b['id']; ?>">
                                    <i class="fas fa-check mr-2"></i> Submit Review
                                </button>
                                <button type="button" class="flex-1 bg-white hover:bg-slate-50 text-orange-600 font-bold py-3 px-6 rounded-xl border border-orange-200 transition-colors later-review-btn" data-booking="<?php echo $b['id']; ?>">
                                    <i class="fas fa-clock mr-2"></i> Later
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                </div> <!-- end booking-review-group -->
            <?php endwhile; ?>
        <?php else: ?>
            <div
                class="bg-slate-50 border border-black/5 rounded-[32px] p-12 text-center flex flex-col items-center shadow-sm">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-6 text-warmWood">
                    <i class="fas fa-utensils text-3xl"></i>
                </div>
                <h2 class="font-zodiak text-2xl text-ink font-bold mb-3">No Itineraries Yet</h2>
                <p class="text-ink/60 mb-8 max-w-md mx-auto">Your upcoming dining experiences will appear here. Ready to
                    discover extraordinary flavors?</p>
                <a href="index.php"
                    class="bg-deepTeal text-white font-bold py-3 px-8 rounded-full hover:bg-tealAccent transition-colors">
                    Explore Restaurants
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Star Rating System
    document.querySelectorAll('.rating-star').forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            const booking = this.getAttribute('data-booking');
            
            // Update all stars for this booking
            document.querySelectorAll(`#rating-container-${booking} .rating-star`).forEach(s => {
                const sRating = s.getAttribute('data-rating');
                const icon = s.querySelector('i');
                if (sRating <= rating) {
                    icon.className = 'fas fa-star text-orange-500';
                } else {
                    icon.className = 'far fa-star text-gray-300';
                }
            });
            
            // Update rating text
            document.querySelector(`.rating-text-${booking}`).textContent = `${rating} ${rating == 1 ? 'star' : 'stars'} selected`;
            
            // Store the selected rating in the button for later submission
            document.querySelector(`.submit-review-btn[data-booking="${booking}"]`).setAttribute('data-selected-rating', rating);
        });

        // Hover effect
        star.addEventListener('mouseover', function() {
            const rating = this.getAttribute('data-rating');
            const booking = this.getAttribute('data-booking');
            
            document.querySelectorAll(`#rating-container-${booking} .rating-star`).forEach(s => {
                const sRating = s.getAttribute('data-rating');
                const icon = s.querySelector('i');
                if (sRating <= rating) {
                    icon.className = 'fas fa-star text-orange-400';
                } else {
                    icon.className = 'far fa-star text-gray-300';
                }
            });
        });

        star.addEventListener('mouseout', function() {
            const booking = this.getAttribute('data-booking');
            const selectedRating = document.querySelector(`.submit-review-btn[data-booking="${booking}"]`).getAttribute('data-selected-rating');
            
            document.querySelectorAll(`#rating-container-${booking} .rating-star`).forEach(s => {
                const sRating = s.getAttribute('data-rating');
                const icon = s.querySelector('i');
                
                if (selectedRating && sRating <= selectedRating) {
                    icon.className = 'fas fa-star text-orange-500';
                } else {
                    icon.className = 'far fa-star text-gray-300';
                }
            });
        });
    });

    // Submit Review
    document.querySelectorAll('.submit-review-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const booking = this.getAttribute('data-booking');
            const rating = this.getAttribute('data-selected-rating');
            const comment = document.querySelector(`#comment-${booking}`).value.trim();

            const errorBox = document.getElementById(`review-error-${booking}`);

            // Clear previous error
            if (errorBox) {
                errorBox.classList.add('hidden');
                errorBox.textContent = '';
            }

            // Validation
            if (!rating) {
                if (errorBox) {
                    errorBox.textContent = 'Please select a rating (1–5 stars).';
                    errorBox.classList.remove('hidden');
                }
                return;
            }

            if (comment.length < 5) {
                if (errorBox) {
                    errorBox.textContent = 'Please write a comment (minimum 5 characters).';
                    errorBox.classList.remove('hidden');
                }
                return;
            }

            // Disable button to prevent double submission
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';

            // Submit via fetch
            fetch('submit_review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${booking}&rating=${rating}&comment=${encodeURIComponent(comment)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const reviewSection = document.getElementById(`review-section-${booking}`);
                    reviewSection.innerHTML = `
                        <div class="bg-teal-50 border border-teal-100 rounded-[24px] p-6 md:p-8 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-teal-100 flex items-center justify-center text-deepTeal flex-shrink-0">
                                <i class="fas fa-check text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-ink mb-1">Thank You for Your Review!</h3>
                                <p class="text-sm text-ink/70">Your feedback helps us and other diners discover great experiences.</p>
                            </div>
                        </div>
                    `;
                } else {
                    if (errorBox) {
                        errorBox.textContent = data.message || 'There was a problem submitting your review. Please try again.';
                        errorBox.classList.remove('hidden');
                    }
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-check mr-2"></i> Submit Review';
                }
            })
            .catch(err => {
                console.error('Error:', err);
                if (errorBox) {
                    errorBox.textContent = 'An error occurred while submitting your review. Please refresh the page and try again.';
                    errorBox.classList.remove('hidden');
                }
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-check mr-2"></i> Submit Review';
            });
        });
    });

    // Mark as Later
    document.querySelectorAll('.later-review-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const booking = this.getAttribute('data-booking');
            
            // Disable button
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Please wait...';

            // Submit via fetch
            fetch('mark_review_later.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${booking}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide the review section
                    const reviewSection = document.getElementById(`review-section-${booking}`);
                    reviewSection.style.display = 'none';
                } else {
                    openSiteAlert('Error: ' + (data.message || 'Failed to update'));
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-clock mr-2"></i> Later';
                }
            })
            .catch(err => {
                console.error('Error:', err);
                openSiteAlert('An error occurred');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-clock mr-2"></i> Later';
            });
        });
    });
</script>

<?php require_once 'footer.php'; ?>