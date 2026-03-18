<?php
require_once 'config.php';

$is_logged_in = isLoggedIn();
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
$rest_id = $_GET['id'] ?? 0;

// Fetch restaurant details
$stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ? AND status = 'approved' AND is_published = 1");
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    die("Restaurant not found or not published.");
}

// Fetch user profile
if ($is_logged_in) {
    $role = getUserRole();
    if ($role == ROLE_ADMIN) {
        $stmt = $conn->prepare("SELECT a_firstname as first_name, a_lastname as last_name, 'Other' as gender, '1970-01-01' as dob FROM tbl_admin WHERE a_id = ?");
    } elseif ($role == ROLE_MANAGER) {
        $stmt = $conn->prepare("SELECT m_firstname as first_name, m_lastname as last_name, 'Other' as gender, '1970-01-01' as dob FROM tbl_manager WHERE m_id = ?");
    } else {
        $stmt = $conn->prepare("SELECT u_firstname as first_name, u_lastname as last_name, u_gender as gender, u_dob as dob FROM tbl_users WHERE u_id = ?");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $user = null;
}

// Fetch closed days for this restaurant
$stmt = $conn->prepare("SELECT day_of_week FROM restaurant_schedule WHERE restaurant_id = ? AND is_closed = 1");
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$closed_days_result = $stmt->get_result();
$closed_days = [];
while ($row = $closed_days_result->fetch_assoc()) {
    $closed_days[] = $row['day_of_week'];
}
$stmt->close();

// Fetch menu items
$stmt = $conn->prepare("SELECT * FROM restaurant_menu WHERE restaurant_id = ?");
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$menu_items = $stmt->get_result();
$stmt->close();

// --- Reviews (public feedback) ---
// Ensure reviews table exists (safe for first-run)
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

$avg_rating = null;
$reviews_count = 0;
$latest_reviews = null;

$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM reviews WHERE restaurant_id = ?");
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$agg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($agg) {
    $reviews_count = (int) ($agg['cnt'] ?? 0);
    $avg_rating = $agg['avg_rating'] !== null ? (float) $agg['avg_rating'] : null;
}

$stmt = $conn->prepare("
    SELECT rv.rating, rv.comment, rv.created_at,
           u.u_firstname, u.u_lastname, u.u_email, u.u_image
    FROM reviews rv
    JOIN tbl_users u ON rv.user_id = u.u_id
    WHERE rv.restaurant_id = ?
    ORDER BY rv.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$latest_reviews = $stmt->get_result();
$stmt->close();

// Check if basic profile is filled
$is_profile_complete = $is_logged_in && !empty($user['first_name'] ?? '') && !empty($user['last_name'] ?? '') && !empty($user['gender'] ?? '') && !empty($user['dob'] ?? '');

// Check if user is Admin or Manager
$is_staff = isAdmin() || isManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    if (!$is_logged_in) {
        die("You must be logged in to book a table.");
    }

    if ($is_staff) {
        die("Staff members (Admins/Managers) cannot book tables. Please use a customer account.");
    }
    
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $guests = (int) ($_POST['guests'] ?? 0);

    // Server-side validation for closed days
    $day_of_week = strtolower(date('D', strtotime($date))); // 'Mon', 'Tue' -> 'mon', 'tue'

    if (in_array($day_of_week, $closed_days)) {
        $error = "Sorry, this restaurant is closed on the selected day.";
    } elseif ($guests > $restaurant['max_guests']) {
        $error = "The number of guests exceeds the maximum capacity of " . $restaurant['max_guests'] . " for this restaurant.";
    } else {
        // Update profile if missing
        if (!$is_profile_complete) {
            $fname = $_POST['first_name'] ?? '';
            $lname = $_POST['last_name'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $dob = $_POST['dob'] ?? '';
            
            // Validate required fields
            if (empty($fname) || empty($lname) || empty($gender) || empty($dob)) {
                $error = "Please fill in all required personal details (First Name, Last Name, Gender, and Date of Birth).";
            } else {
                $stmt = $conn->prepare("UPDATE tbl_users SET u_firstname = ?, u_lastname = ?, u_gender = ?, u_dob = ? WHERE u_id = ?");
                $stmt->bind_param("ssssi", $fname, $lname, $gender, $dob, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE restaurant_id = ? AND booking_date = ? AND booking_time = ? AND status != 'cancelled'");
            $stmt->bind_param("iss", $rest_id, $date, $time);
            $stmt->execute();
            $existing = $stmt->get_result();
            $stmt->close();

            if ($existing->num_rows > 0) {
                $error = "Sorry, that time slot was just booked by someone else. Please select another time.";
            } else {
                $stmt = $conn->prepare("INSERT INTO bookings (user_id, restaurant_id, booking_date, booking_time, guests, status) VALUES (?, ?, ?, ?, ?, 'confirmed')");
                $stmt->bind_param("iissi", $user_id, $rest_id, $date, $time, $guests);

                if ($stmt->execute()) {
                    $booking_id = $conn->insert_id;
                    $success = true;
                }
            }
        }
    }
}

require_once 'header.php';
?>

<main
    class="relative w-full h-[65vh] min-h-[550px] flex flex-col justify-end pb-12 px-6 md:px-12 bg-black overflow-hidden mb-8 md:mb-12 mt-[-100px] pt-[100px]">
    <!-- Background Image -->
    <div class="absolute inset-0 w-full h-full">
        <img src="<?php echo htmlspecialchars($restaurant['primary_image']); ?>"
            class="w-full h-full object-cover opacity-80" alt="<?php echo htmlspecialchars($restaurant['name']); ?>">
        <!-- Gradient Overlay: lighter at top, dark at bottom -->
        <div class="absolute inset-0 bg-gradient-to-b from-black/20 via-black/30 to-[#07161a]/95"></div>
    </div>

    <!-- Hero Content -->
    <div class="relative z-10 max-w-[1200px] mx-auto w-full animate-soft-rise">
        <span
            class="inline-block px-3 py-1 bg-white/10 backdrop-blur-md border border-white/20 rounded-full text-xs font-bold uppercase tracking-[0.22em] text-white mb-4">
            <?php echo htmlspecialchars($restaurant['cuisine'] ?? 'Restaurant'); ?>
        </span>
        <h1 class="font-zodiak text-4xl md:text-5xl lg:text-7xl text-white leading-[1.02] mb-3">
            <?php echo htmlspecialchars($restaurant['name']); ?>
        </h1>
    </div>
</main>

<div class="max-w-[1200px] mx-auto px-5 pb-32 flex flex-col lg:flex-row gap-8 items-start relative">

    <!-- Left Side: Restaurant Info -->
    <div class="flex-1 w-full animate-soft-rise" style="animation-delay: 150ms;">

        <!-- Operating Info Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-slate-50 rounded-[32px] p-6 flex flex-col items-start border border-black/5">
                <div
                    class="w-12 h-12 bg-white rounded-full shadow-sm flex items-center justify-center text-warmWood mb-4 border border-black/5">
                    <i class="fas fa-phone-alt fa-flip-horizontal text-lg"></i>
                </div>
                <h5 class="text-sm font-bold text-ink/60 uppercase tracking-widest mb-1">Contact</h5>
                <?php if (!empty($restaurant['phone'])): ?>
                    <p class="font-medium text-ink"><?php echo htmlspecialchars($restaurant['phone']); ?></p>
                <?php else: ?>
                    <p class="text-ink/60">Not provided</p>
                <?php endif; ?>
                <?php if (!empty($restaurant['email'])): ?>
                    <p class="font-medium text-ink/80 text-sm mt-1 truncate w-full">
                        <?php echo htmlspecialchars($restaurant['email']); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="bg-slate-50 rounded-[32px] p-6 flex flex-col items-start border border-black/5">
                <div
                    class="w-12 h-12 bg-white rounded-full shadow-sm flex items-center justify-center text-[#2c3e50] mb-4 border border-black/5">
                    <i class="fas fa-map-marker-alt text-lg"></i>
                </div>
                <h5 class="text-sm font-bold text-ink/60 uppercase tracking-widest mb-1">Location</h5>
                <?php if (!empty($restaurant['location'])): ?>
                    <p class="font-medium text-ink">
                        <?php echo htmlspecialchars($restaurant['location']); ?>
                    </p>
                <?php else: ?>
                    <p class="text-ink/60">Not provided</p>
                <?php endif; ?>
            </div>

            <div class="bg-slate-50 rounded-[32px] p-6 flex flex-col items-start border border-black/5">
                <div
                    class="w-12 h-12 bg-white rounded-full shadow-sm flex items-center justify-center text-[#27ae60] mb-4 border border-black/5">
                    <i class="fas fa-coins text-lg"></i>
                </div>
                <h5 class="text-sm font-bold text-ink/60 uppercase tracking-widest mb-1">Avg Cost</h5>
                <p class="font-medium text-ink">
                    ₹<?php echo number_format($restaurant['avg_price'] ?? 0, 0); ?> for two
                </p>
            </div>

            <div class="bg-slate-50 rounded-[32px] p-6 flex flex-col items-start border border-black/5">
                <div
                    class="w-12 h-12 bg-white rounded-full shadow-sm flex items-center justify-center text-tealAccent mb-4 border border-black/5">
                    <i class="fas fa-chair text-lg"></i>
                </div>
                <h5 class="text-sm font-bold text-ink/60 uppercase tracking-widest mb-1">Seating</h5>
                <?php if (!empty($restaurant['seating_type'])): ?>
                    <p class="font-medium text-ink capitalize">
                        <?php echo htmlspecialchars($restaurant['seating_type'] === 'both' ? 'Indoor & Outdoor' : $restaurant['seating_type']); ?>
                    </p>
                <?php else: ?>
                    <p class="text-ink/60">Not provided</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($restaurant['description'])): ?>
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5 mb-6">
                <h4 class="font-zodiak text-2xl text-ink font-bold mb-4">About</h4>
                <p class="text-ink/70 leading-relaxed text-base">
                    <?php echo nl2br(htmlspecialchars($restaurant['description'])); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Menu Display -->
        <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h4 class="font-zodiak text-2xl text-ink font-bold">Menu Highlights</h4>
                <?php if (!empty($restaurant['menu_file'])): ?>
                    <a href="<?php echo htmlspecialchars($restaurant['menu_file']); ?>" target="_blank"
                        class="text-xs font-bold uppercase tracking-[0.1em] text-deepTeal hover:text-tealAccent transition-colors flex items-center gap-1.5 bg-teal-50 px-3 py-1.5 rounded-full">
                        <i class="fas fa-file-pdf"></i> Full Menu
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($menu_items->num_rows > 0): ?>
                <div class="flex flex-col gap-4">
                    <?php while ($item = $menu_items->fetch_assoc()): ?>
                        <div
                            class="flex items-center gap-4 p-4 bg-slate-50/50 rounded-2xl border border-black/[0.03] hover:border-black/10 transition-colors">
                            <?php if (!empty($item['item_image'])): ?>
                                <div class="flex-shrink-0">
                                    <img src="<?php echo htmlspecialchars($item['item_image']); ?>"
                                        class="w-16 h-16 rounded-2xl object-cover border border-black/5">
                                </div>
                            <?php endif; ?>
                            <div class="flex-1 flex justify-between items-center gap-4">
                                <div>
                                    <h5 class="font-bold text-ink mb-1"><?php echo htmlspecialchars($item['item_name']); ?>
                                    </h5>
                                    <?php if ($item['item_discount'] > 0): ?>
                                        <span
                                            class="text-xs bg-warmWood/10 text-warmWood px-2 py-0.5 rounded-md font-bold uppercase tracking-wider">
                                            <?php echo floatval($item['item_discount']); ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <?php if ($item['item_discount'] > 0): ?>
                                        <div class="text-xs text-ink/40 line-through">
                                            ₹<?php echo $item['item_price']; ?></div>
                                        <div class="font-bold text-lg text-deepTeal">
                                            ₹<?php echo number_format($item['item_price'] - ($item['item_price'] * ($item['item_discount'] / 100)), 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="font-bold text-lg text-ink">₹<?php echo $item['item_price']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-ink/50 italic text-sm">No specific menu items listed yet.</p>
            <?php endif; ?>
        </div>

        <!-- Reviews / Feedback -->
        <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <h4 class="font-zodiak text-2xl text-ink font-bold">Guest Feedback</h4>
                <div class="flex items-center gap-3">
                    <?php if ($avg_rating !== null): ?>
                        <div class="inline-flex items-center gap-2 bg-orange-50 border border-orange-100 text-orange-700 px-4 py-2 rounded-full font-bold">
                            <i class="fas fa-star"></i>
                            <span><?php echo number_format($avg_rating, 1); ?></span>
                            <span class="text-ink/40 font-medium text-sm">(<?php echo $reviews_count; ?>)</span>
                        </div>
                    <?php else: ?>
                        <div class="text-sm text-ink/50 font-medium">No reviews yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($latest_reviews && $latest_reviews->num_rows > 0): ?>
                <div class="flex flex-col gap-4">
                    <?php while ($rv = $latest_reviews->fetch_assoc()): ?>
                        <?php
                        $name = trim(($rv['u_firstname'] ?? '') . ' ' . ($rv['u_lastname'] ?? ''));
                        if ($name === '') {
                            $name = 'Guest';
                        }
                        $avatar = '';
                        if (!empty($rv['u_image'])) {
                            $avatar_src = (strpos($rv['u_image'], 'http') === 0) ? $rv['u_image'] : BASE_URL . $rv['u_image'];
                            $avatar = '<img src="' . htmlspecialchars($avatar_src) . '" class="w-10 h-10 rounded-full object-cover border border-black/5">';
                        } else {
                            $initial = strtoupper(substr($rv['u_email'] ?? 'G', 0, 1));
                            $avatar = '<div class="w-10 h-10 rounded-full bg-deepTeal text-white flex items-center justify-center font-bold">' . $initial . '</div>';
                        }
                        ?>
                        <div class="bg-slate-50/60 border border-black/5 rounded-2xl p-4">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0"><?php echo $avatar; ?></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-bold text-ink truncate"><?php echo htmlspecialchars($name); ?></div>
                                            <div class="text-xs text-ink/50 font-medium">
                                                <?php echo date('M d, Y • h:i A', strtotime($rv['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="inline-flex items-center gap-1 text-orange-600 font-bold text-sm shrink-0">
                                            <?php echo (int) $rv['rating']; ?>
                                            <i class="fas fa-star text-orange-500"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-ink/70 text-sm leading-relaxed">
                                        <?php echo nl2br(htmlspecialchars($rv['comment'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="py-10 text-center text-ink/50 font-medium">
                    Be the first to leave a review after your visit.
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- Right Side: Booking Wizard -->
    <div id="booking-wizard-container" class="w-full lg:w-[420px] lg:sticky lg:top-[120px] animate-soft-rise"
        style="animation-delay: 300ms;">

        <?php if (isset($error)): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 border border-red-100 flex items-start gap-3">
                <i class="fas fa-exclamation-circle mt-1"></i>
                <p class="font-medium text-sm"><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-white rounded-[32px] p-10 text-center shadow-premium border border-black/5">
                <div class="w-20 h-20 bg-teal-50 text-deepTeal rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-check text-3xl"></i>
                </div>
                <h2 class="font-zodiak text-3xl text-ink font-bold mb-3">Reservation Confirmed</h2>
                <p class="text-ink/60 mb-8 leading-relaxed">Your table at <strong
                        class="text-ink"><?php echo htmlspecialchars($restaurant['name']); ?></strong> is secured. We look
                    forward to seeing you.</p>
                <a href="my_bookings.php"
                    class="w-full inline-block bg-deepTeal text-white font-bold py-4 rounded-2xl hover:bg-tealAccent transition-colors">
                    View Itinerary
                </a>
            </div>
        <?php else: ?>
            <div id="booking-wizard"
                class="bg-white rounded-[32px] p-6 md:px-8 md:py-6 shadow-premium border border-black/5 relative overflow-hidden h-[500px] flex flex-col">

                <!-- Inner Image Header (Removed to match clean white screenshot background) -->

                <form method="POST" id="main-booking-form" class="flex-1 flex flex-col relative w-full h-full">

                    <!-- Custom Progress Dots -->
                    <div class="flex items-center justify-between mb-5 relative w-full max-w-[85%] mx-auto px-2">
                        <!-- Background track line -->
                        <div class="absolute top-1/2 left-2 right-2 h-0.5 bg-black/5 -z-10 -translate-y-1/2 rounded-full">
                        </div>

                        <!-- Colored track line (progress) -->
                        <div class="absolute top-1/2 left-2 h-0.5 bg-[#27ae60] -z-10 -translate-y-1/2 transition-all duration-500 rounded-full"
                            id="wizard-progress-line" style="width: 0%;"></div>

                        <!-- Dots (using #27ae60 for active style to match image) -->
                        <div class="relative flex items-center justify-center bg-white scale-125 px-1 wizard-dot"
                            id="dot-1">
                            <div
                                class="w-4 h-4 rounded-full bg-[#27ae60] ring-2 ring-[#27ae60] ring-offset-[3px] transition-all dot-inner">
                            </div>
                        </div>
                        <div class="relative flex items-center justify-center bg-white px-1 wizard-dot" id="dot-2">
                            <div class="w-4 h-4 rounded-full bg-black/10 transition-all dot-inner"></div>
                        </div>
                        <div class="relative flex items-center justify-center bg-white px-1 wizard-dot" id="dot-3">
                            <div class="w-4 h-4 rounded-full bg-black/10 transition-all dot-inner"></div>
                        </div>
                        <div class="relative flex items-center justify-center bg-white px-1 wizard-dot" id="dot-4">
                            <div class="w-4 h-4 rounded-full bg-black/10 transition-all dot-inner"></div>
                        </div>
                    </div>

                    <!-- Steps Container -->
                    <div class="relative w-full flex-1 overflow-hidden">

                        <!-- Step 1: Date -->
                        <div class="wizard-step absolute inset-0 transition-transform duration-500 transform translate-x-0 overflow-y-auto no-scrollbar pb-16"
                            id="step-1">
                            <h4 class="font-zodiak text-2xl text-ink font-bold mb-3">1. Select Date</h4>

                            <!-- Custom Calendar -->
                            <div class="max-w-[320px] mx-auto">
                                <div class="flex items-center justify-between mb-1 px-2">
                                    <button type="button" id="cal-prev"
                                        class="text-ink/40 hover:text-ink transition-colors p-2"><i
                                            class="fas fa-chevron-left"></i></button>
                                    <h5 class="font-bold text-lg text-ink" id="cal-month-year">...</h5>
                                    <button type="button" id="cal-next"
                                        class="text-ink/40 hover:text-ink transition-colors p-2"><i
                                            class="fas fa-chevron-right"></i></button>
                                </div>
                                <div class="grid grid-cols-7 gap-1 mb-1">
                                    <div class="text-center text-[10px] font-bold text-ink/70">Mo</div>
                                    <div class="text-center text-[10px] font-bold text-ink/70">Tu</div>
                                    <div class="text-center text-[10px] font-bold text-ink/70">We</div>
                                    <div class="text-center text-[10px] font-bold text-ink/70">Th</div>
                                    <div class="text-center text-[10px] font-bold text-ink/70">Fr</div>
                                    <div class="text-center text-[10px] font-bold text-ink/70">Sa</div>
                                    <div class="text-center text-[10px] font-bold text-ink/70">Su</div>
                                </div>
                                <div id="calendar-days" class="grid grid-cols-7 gap-1 md:gap-1.5">
                                    <!-- Populated by JS -->
                                </div>
                            </div>

                            <input type="hidden" name="date" id="book-date" required>
                            <p id="no-date-error" class="hidden text-red-500 text-sm mt-3 font-bold text-center">Please
                                select a date.</p>
                        </div>

                        <!-- Step 2: Time -->
                        <div class="wizard-step absolute inset-0 transition-transform duration-500 transform translate-x-full overflow-y-auto no-scrollbar pb-16"
                            id="step-2">
                            <h4 class="font-zodiak text-2xl text-ink font-bold mb-3">2. Pick a Time</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="time-grid">
                                <?php
                                $times = ['12:00', '13:00', '14:00', '18:00', '19:00', '20:00', '21:00', '22:00'];
                                foreach ($times as $t): ?>
                                    <label class="cursor-pointer group relative">
                                        <input type="radio" name="time" value="<?php echo $t; ?>"
                                            id="time_<?php echo str_replace(':', '', $t); ?>" class="peer hidden time-radio">
                                        <div
                                            class="text-center py-3 bg-white border border-black/10 rounded-xl text-base font-medium text-ink transition-all peer-checked:bg-[#27ae60] peer-checked:text-white peer-checked:border-[#27ae60] time-box shadow-sm group-hover:border-black/30">
                                            <?php echo $t; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p id="no-times-error" class="hidden text-red-500 text-sm mt-4 font-bold text-center">Please
                                select a time.</p>
                        </div>

                        <!-- Step 3: Guests -->
                        <div class="wizard-step absolute inset-0 transition-transform duration-500 transform translate-x-full overflow-y-auto no-scrollbar pb-16"
                            id="step-3">
                            <h4 class="font-zodiak text-2xl text-ink font-bold mb-3">3. Number of guests</h4>
                            <div class="grid grid-cols-3 md:grid-cols-4 gap-3 mb-5" id="guests-grid">
                                <?php
                                $max_display = min(13, $restaurant['max_guests']);
                                for ($i = 2; $i <= $max_display; $i++):
                                    ?>
                                    <label class="cursor-pointer group relative">
                                        <input type="radio" name="guests_radio" value="<?php echo $i; ?>"
                                            class="peer hidden guest-radio">
                                        <div
                                            class="text-center py-3 bg-white border border-black/10 rounded-xl text-lg font-bold text-ink transition-all peer-checked:bg-[var(--primary-color)] peer-checked:text-white peer-checked:border-[var(--primary-color)] shadow-sm group-hover:border-black/30">
                                            <?php echo $i; ?>
                                        </div>
                                    </label>
                                <?php endfor; ?>
                            </div>

                            <?php if ($restaurant['max_guests'] > 13): ?>
                                <div class="mt-4 text-center pb-4">
                                    <label class="block text-ink font-medium text-base mb-3.5">Enter number of guests:</label>
                                    <input type="number" id="custom-guests" min="14"
                                        max="<?php echo $restaurant['max_guests']; ?>"
                                        class="w-full max-w-[200px] mx-auto text-center border border-black/10 rounded-xl py-3 px-4 text-lg font-bold text-ink focus:outline-none focus:border-[var(--primary-color)] focus:ring-1 focus:ring-[var(--primary-color)] transition-colors shadow-sm">
                                    <p id="max-guests-error"
                                        class="hidden text-red-500 text-sm mt-2 font-bold break-words px-4">Sorry, our current
                                        limit is <?php echo $restaurant['max_guests']; ?>.</p>
                                </div>
                            <?php endif; ?>
                            <input type="hidden" id="guests-input" name="guests" required>
                            <p id="no-guests-error" class="hidden text-red-500 text-sm mt-4 font-bold text-center">Please
                                select party size.</p>
                        </div>

                        <!-- Step 4: Final Confirmation -->
                        <div class="wizard-step absolute inset-0 transition-transform duration-500 transform translate-x-full overflow-y-auto no-scrollbar pb-16"
                            id="step-4">
                            <h4 class="font-zodiak text-2xl text-ink font-bold mb-3">4. Final Confirmation</h4>

                            <!-- Summary Box -->
                            <div class="bg-slate-50 border border-black/5 rounded-2xl p-3 mb-4 relative">
                                <div class="flex flex-col gap-1.5 text-sm">
                                    <div><span class="font-bold text-ink">Date:</span> <span class="text-ink/80"
                                            id="summary-date">...</span></div>
                                    <div><span class="font-bold text-ink">Time:</span> <span class="text-ink/80"
                                            id="summary-time">...</span></div>
                                    <div><span class="font-bold text-ink">Guests:</span> <span class="text-ink/80"
                                            id="summary-guests">...</span></div>
                                </div>
                            </div>

                            <?php if ($is_staff): ?>
                                <div class="flex flex-col items-center justify-center py-4 text-center gap-3">
                                    <div class="w-14 h-14 bg-amber-50 text-orange-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user-shield text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="text-orange-600 font-bold text-base mb-1">Access Restricted</h5>
                                        <p class="text-ink/60 text-xs">Admins and Managers cannot book tables. Please use a customer account to make a reservation.</p>
                                    </div>
                                </div>
                            <?php elseif (!$is_profile_complete): ?>
                                <div class="flex flex-col items-center justify-center py-4 text-center gap-3">
                                    <div
                                        class="w-14 h-14 bg-red-50 text-red-500 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user-circle text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="text-red-500 font-bold text-base mb-1">Complete Your Profile</h5>
                                        <p class="text-ink/60 text-xs">Please complete your profile to proceed.</p>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>profile.php"
                                        class="bg-deepTeal text-white font-bold py-2.5 px-6 rounded-xl hover:bg-tealAccent transition-colors inline-block shadow-sm text-sm w-full text-center">
                                        <i class="fas fa-edit mr-2"></i> Go to Profile
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="flex flex-col items-center justify-center py-4 text-center">
                                    <div
                                        class="w-12 h-12 bg-[#27ae60]/10 text-[#27ae60] rounded-full flex items-center justify-center mb-3">
                                        <i class="fas fa-check text-xl"></i>
                                    </div>
                                    <h5 class="text-ink font-bold text-base">Profile Complete</h5>
                                    <p class="text-ink/60 text-xs mt-1">Review your details above and confirm.</p>
                                </div>
                            <?php endif; ?>
                            <input type="hidden" name="confirm_booking" value="1">
                        </div>
                    </div>

                    <!-- Bottom Action Buttons (Absolute to bottom of wizard) -->
                    <div class="absolute bottom-0 left-0 w-full pt-3 pb-0 bg-white flex items-center justify-between gap-3">
                        <button type="button" id="btn-prev"
                            class="invisible bg-slate-100 text-ink/70 font-bold py-3 px-6 rounded-xl hover:bg-slate-200 transition-colors flex-1 text-center shadow-sm">
                            Back
                        </button>
                        <button type="button" id="btn-next"
                            class="bg-[#2c3e50] text-white font-bold py-3 px-6 rounded-xl hover:bg-[#1a252f] transition-colors flex-[1.5] text-center shadow-sm">
                            Next Step
                        </button>
                        <button type="submit" id="btn-submit"
                            class="hidden <?php echo (!$is_profile_complete || $is_staff) ? '!hidden' : ''; ?> bg-[#27ae60] text-white font-bold py-3 px-6 rounded-xl hover:bg-[#219653] transition-colors flex-[1.5] text-center shadow-sm">
                            Book Table Now
                        </button>
                    </div>

                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const isLoggedInUser = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

    // --- State ---
    let currentStep = 1;
    const totalSteps = 4;
    const closedDays = <?php echo json_encode($closed_days); ?>;

    // --- Elements ---
    const dateInput = document.getElementById('book-date');
    const guestsInput = document.getElementById('guests-input');

    const wizardContainer = document.getElementById('booking-wizard-container');
    const noDateError = document.getElementById('no-date-error');
    const noTimesError = document.getElementById('no-times-error');
    const noGuestsError = document.getElementById('no-guests-error');

    const btnNext = document.getElementById('btn-next');
    const btnPrev = document.getElementById('btn-prev');
    const btnSubmit = document.getElementById('btn-submit');

    // Summary Els
    const sumDate = document.getElementById('summary-date');
    const sumTime = document.getElementById('summary-time');
    const sumGuests = document.getElementById('summary-guests');

    // --- Calendar Logic ---
    let currentDate = new Date();
    currentDate.setHours(0, 0, 0, 0);
    let viewingMonth = currentDate.getMonth();
    let viewingYear = currentDate.getFullYear();

    function renderCalendar() {
        const firstDay = new Date(viewingYear, viewingMonth, 1);
        const lastDay = new Date(viewingYear, viewingMonth + 1, 0);

        let startDay = firstDay.getDay() || 7; // 1 (Mon) to 7 (Sun)
        startDay--; // 0 (Mon) to 6 (Sun) for grid offset

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        document.getElementById('cal-month-year').innerText = `${monthNames[viewingMonth]} ${viewingYear}`;

        const daysContainer = document.getElementById('calendar-days');
        daysContainer.innerHTML = '';

        // Prev month days
        const prevMonthLastDay = new Date(viewingYear, viewingMonth, 0).getDate();
        for (let i = startDay - 1; i >= 0; i--) {
            const d = prevMonthLastDay - i;
            daysContainer.innerHTML += `<div class="aspect-square flex items-center justify-center rounded-xl text-ink/20 font-medium text-sm border border-transparent">${d}</div>`;
        }

        // Curr month days
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const limitDate = new Date();
        limitDate.setMonth(limitDate.getMonth() + 3);
        limitDate.setHours(0, 0, 0, 0);

        const dayNamesMap = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

        for (let i = 1; i <= lastDay.getDate(); i++) {
            const thisDate = new Date(viewingYear, viewingMonth, i);
            const dateStr = `${viewingYear}-${String(viewingMonth + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const isPast = thisDate < today;
            const isBeyond = thisDate > limitDate;
            const isClosed = closedDays.includes(dayNamesMap[thisDate.getDay()]);

            const isSelected = dateInput.value === dateStr;

            if (isPast || isClosed || isBeyond) {
                // Disabled
                let content = i;
                if (isPast || isBeyond) {
                    content = `<span class="line-through relative z-10">${i}</span>`;
                }
                daysContainer.innerHTML += `<div class="aspect-square flex items-center justify-center rounded-xl bg-slate-50 text-ink/40 font-medium text-sm border border-transparent cursor-not-allowed">${content}</div>`;
            } else {
                // Selectable
                let classes = `cal-day aspect-square flex items-center justify-center rounded-xl font-bold text-sm cursor-pointer transition-all border shadow-sm `;
                if (isSelected) {
                    classes += `bg-[#2c3e50] text-white border-[#2c3e50] scale-105`;
                } else {
                    classes += `bg-white text-ink border-black/5 hover:border-black/20 hover:bg-slate-50`;
                }
                daysContainer.innerHTML += `<div class="${classes}" data-date="${dateStr}">${i}</div>`;
            }
        }

        // Bind day clicks
        document.querySelectorAll('.cal-day').forEach(el => {
            el.addEventListener('click', (e) => {
                const dateSelected = e.currentTarget.getAttribute('data-date');
                dateInput.value = dateSelected;
                noDateError.classList.add('hidden');
                renderCalendar(); // Re-render to show selection
                fetchTimes(dateSelected);
            });
        });
    }

    document.getElementById('cal-prev')?.addEventListener('click', () => {
        viewingMonth--;
        if (viewingMonth < 0) { viewingMonth = 11; viewingYear--; }
        renderCalendar();
    });
    document.getElementById('cal-next')?.addEventListener('click', () => {
        viewingMonth++;
        if (viewingMonth > 11) { viewingMonth = 0; viewingYear++; }
        renderCalendar();
    });

    if (document.getElementById('cal-month-year')) {
        renderCalendar();
    }

    // --- Time Selection Logic ---
    function fetchTimes(dateStr) {
        if (!dateStr) return;
        fetch(`get_booked_times.php?restaurant_id=<?php echo $rest_id; ?>&date=${dateStr}`)
            .then(res => res.json())
            .then(bookedTimes => {
                document.querySelectorAll('.time-radio').forEach(radio => {
                    radio.disabled = false;
                    radio.checked = false;
                    const box = radio.nextElementSibling;
                    box.classList.remove('bg-slate-100', 'text-ink/30', 'line-through', 'cursor-not-allowed', 'border-transparent', 'hover:bg-white', 'hover:shadow-md');
                    box.classList.add('bg-white', 'text-ink', 'border-black/10', 'hover:bg-white', 'hover:shadow-md');

                    if (bookedTimes.includes(radio.value)) {
                        radio.disabled = true;
                        box.classList.remove('bg-white', 'text-ink', 'group-hover:border-black/30', 'border-black/10', 'hover:shadow-md');
                        box.classList.add('bg-slate-100', 'text-ink/30', 'line-through', 'cursor-not-allowed', 'border-transparent');
                    }
                });
            })
            .catch(console.error);
    }

    document.querySelectorAll('.time-radio').forEach(r => r.addEventListener('change', () => {
        noTimesError.classList.add('hidden');
    }));

    // --- Guest Selection Logic ---
    const customGuestsInput = document.getElementById('custom-guests');
    const maxGuestsError = document.getElementById('max-guests-error');

    document.querySelectorAll('.guest-radio').forEach(r => {
        r.addEventListener('change', (e) => {
            guestsInput.value = e.target.value;
            noGuestsError.classList.add('hidden');
            if (customGuestsInput) {
                customGuestsInput.value = '';
                maxGuestsError.classList.add('hidden');
            }
        });
    });

    if (customGuestsInput) {
        customGuestsInput.addEventListener('input', (e) => {
            const val = parseInt(e.target.value);
            const max = parseInt(e.target.max);

            // clear radios
            document.querySelectorAll('.guest-radio').forEach(r => r.checked = false);

            if (val > max) {
                maxGuestsError.classList.remove('hidden');
                guestsInput.value = ''; // invalidate
            } else {
                maxGuestsError.classList.add('hidden');
                if (val > 0) {
                    guestsInput.value = val;
                    noGuestsError.classList.add('hidden');
                } else {
                    guestsInput.value = '';
                }
            }
        });
    }

    // --- Wizard UI Logic ---
    function updateWizardUI() {
        // Layout shifts
        for (let i = 1; i <= totalSteps; i++) {
            const stepEl = document.getElementById(`step-${i}`);
            if (!stepEl) continue;
            if (i < currentStep) {
                stepEl.classList.remove('translate-x-0', 'translate-x-full');
                stepEl.classList.add('-translate-x-full');
            } else if (i === currentStep) {
                stepEl.classList.remove('-translate-x-full', 'translate-x-full');
                stepEl.classList.add('translate-x-0');
            } else {
                stepEl.classList.remove('-translate-x-full', 'translate-x-0');
                stepEl.classList.add('translate-x-full');
            }
        }

        // Progress Line & Dots
        const progressLine = document.getElementById('wizard-progress-line');
        const percentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
        progressLine.style.width = `calc(${percentage}% - 14px)`; // accounting for dot padding

        for (let i = 1; i <= totalSteps; i++) {
            const dotContainer = document.getElementById(`dot-${i}`);
            const dotInner = dotContainer.querySelector('.dot-inner');
            if (i === currentStep) {
                dotContainer.classList.add('scale-125');
                dotInner.className = "w-4 h-4 rounded-full bg-[#27ae60] ring-[3px] ring-[#27ae60] ring-offset-2 transition-all dot-inner";
            } else if (i < currentStep) {
                dotContainer.classList.remove('scale-125');
                dotInner.className = "w-4 h-4 rounded-full bg-[#27ae60] transition-all dot-inner";
            } else {
                dotContainer.classList.remove('scale-125');
                dotInner.className = "w-4 h-4 rounded-full bg-black/10 transition-all dot-inner";
            }
        }

        // Buttons
        if (currentStep === 1) {
            btnPrev.classList.add('invisible');
            btnNext.classList.remove('hidden');
            btnSubmit.classList.add('hidden');
            btnNext.innerText = "Next Step";
        } else if (currentStep === totalSteps) {
            btnPrev.classList.remove('invisible');
            btnNext.classList.add('hidden');
            btnSubmit.classList.remove('hidden');

            // Populate summary
            sumDate.innerText = dateInput.value;
            const t = document.querySelector('input[name="time"]:checked');
            sumTime.innerText = t ? t.value : '';
            sumGuests.innerText = guestsInput.value ? `${guestsInput.value} Person(s)` : '';

        } else {
            btnPrev.classList.remove('invisible');
            btnNext.classList.remove('hidden');
            btnSubmit.classList.add('hidden');
            if (currentStep === 3) {
                btnNext.innerText = "Review & Confirm";
            } else {
                btnNext.innerText = "Next Step";
            }
        }
    }

    if (btnNext && btnPrev) {
        btnNext.addEventListener('click', () => {
            if (!isLoggedInUser) {
                openModal('login-modal');
                return;
            }

            // Validation
            if (currentStep === 1) {
                if (!dateInput.value) {
                    noDateError.classList.remove('hidden');
                    return;
                }
            } else if (currentStep === 2) {
                const timeSelected = document.querySelector('input[name="time"]:checked');
                if (!timeSelected) {
                    noTimesError.classList.remove('hidden');
                    return;
                }
            } else if (currentStep === 3) {
                if (!guestsInput.value) {
                    noGuestsError.classList.remove('hidden');
                    return;
                }
            }

            if (currentStep < totalSteps) {
                currentStep++;
                updateWizardUI();
            }
        });

        btnPrev.addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                updateWizardUI();
            }
        });

        document.getElementById('main-booking-form').addEventListener('submit', (e) => {
            if (<?php echo ($is_profile_complete && !$is_staff) ? 'false' : 'true'; ?>) {
                e.preventDefault();
                if (<?php echo $is_staff ? 'true' : 'false'; ?>) {
                    openSiteAlert("Admins and Managers cannot book tables. Please use a customer account.");
                } else {
                    openSiteAlert("Please complete your profile to book a table.");
                }
            }
        });

        updateWizardUI();
    }
</script>

<?php require_once 'footer.php'; ?>