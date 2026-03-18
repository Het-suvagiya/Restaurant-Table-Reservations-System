<?php
require_once '../config.php';

if (!isLoggedIn() || !isManager()) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Fetch restaurant
$stmt = $conn->prepare("SELECT id, name, is_published FROM restaurants WHERE manager_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    die("Restaurant not found.");
}

// Redirect to setup if not published
if (!$restaurant['is_published'] && basename($_SERVER['PHP_SELF']) !== 'setup.php') {
    redirect('setup.php');
}

$rest_id = $restaurant['id'];

// --- Reviews summary for this restaurant ---
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

$reviews_count = 0;
$avg_rating = null;
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
    LIMIT 6
");
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$latest_reviews = $stmt->get_result();
$stmt->close();

// Today's bookings
$stmt = $conn->prepare("SELECT b.*, u.u_firstname as first_name, u.u_lastname as last_name, u.u_email as email, u.u_dob as dob FROM bookings b JOIN tbl_users u ON b.user_id = u.u_id WHERE b.restaurant_id = ? AND b.booking_date = ?");
$stmt->bind_param("is", $rest_id, $today);
$stmt->execute();
$today_bookings = $stmt->get_result();
$stmt->close();

// All bookings
$stmt = $conn->prepare("SELECT b.*, u.u_firstname as first_name, u.u_lastname as last_name, u.u_email as email, u.u_dob as dob FROM bookings b JOIN tbl_users u ON b.user_id = u.u_id WHERE b.restaurant_id = ? ORDER BY b.booking_date DESC, b.booking_time DESC");
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$all_bookings = $stmt->get_result();
$stmt->close();

// Fetch advance bookings for calendar (summary)
$stmt = $conn->prepare("SELECT booking_date, COUNT(*) as count FROM bookings WHERE restaurant_id = ? AND booking_date >= ? AND status != 'cancelled' GROUP BY booking_date");
$stmt->bind_param("is", $rest_id, $today);
$stmt->execute();
$advance_summary = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $advance_summary[$row['booking_date']] = $row['count'];
}
$stmt->close();

// Fetch counts for the three new buttons
// Confirmed Bookings (Today and future)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE restaurant_id = ? AND status = 'confirmed' AND booking_date >= ?");
$stmt->bind_param("is", $rest_id, $today);
$stmt->execute();
$confirmed_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Cancelled Bookings
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE restaurant_id = ? AND status = 'cancelled'");
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$cancelled_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Completed Bookings (Confirmed but in the past)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE restaurant_id = ? AND status = 'confirmed' AND booking_date < ?");
$stmt->bind_param("is", $rest_id, $today);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$total_status = $confirmed_count + $cancelled_count + $completed_count;
$confirmed_pct = ($total_status > 0) ? round(($confirmed_count / $total_status) * 100, 1) : 0;
$cancelled_pct = ($total_status > 0) ? round(($cancelled_count / $total_status) * 100, 1) : 0;
$completed_pct = ($total_status > 0) ? round(($completed_count / $total_status) * 100, 1) : 0;

require_once '../header.php';
?>

<div class="max-w-[1200px] mx-auto pt-32 pb-24 px-5 animate-soft-rise">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-black/5 pb-6">
        <div>
            <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold mb-2">Manager Dashboard</h1>
            <p class="text-ink/60 font-medium">Welcome back, Manager of <strong
                    class="text-ink"><?php echo htmlspecialchars($restaurant['name']); ?></strong></p>
        </div>
        <div class="flex gap-3">
            <a href="setup.php"
                class="bg-deepTeal text-white font-bold py-3 px-6 rounded-full hover:bg-tealAccent transition-colors shadow-sm flex items-center gap-2">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

        <!-- Left Column: Today's Bookings, Charts, and Reviews -->
        <div class="lg:col-span-2 flex flex-col gap-8">

            <!-- Status Rate Buttons -->
            <div class="flex flex-wrap gap-4">
                <a href="bookings.php?status=confirmed" class="flex-1 min-w-[150px] flex items-center gap-3 bg-white border border-black/5 rounded-2xl px-5 py-4 shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-ink/40 uppercase tracking-wider">Confirmed</div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-zodiak font-bold text-ink"><?php echo $confirmed_count; ?></span>
                            <span class="text-[10px] font-bold text-emerald-600"><?php echo $confirmed_pct; ?>%</span>
                        </div>
                    </div>
                </a>
                <a href="bookings.php?status=cancelled" class="flex-1 min-w-[150px] flex items-center gap-3 bg-white border border-black/5 rounded-2xl px-5 py-4 shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-ink/40 uppercase tracking-wider">Cancelled</div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-zodiak font-bold text-ink"><?php echo $cancelled_count; ?></span>
                            <span class="text-[10px] font-bold text-rose-600"><?php echo $cancelled_pct; ?>%</span>
                        </div>
                    </div>
                </a>
                <a href="bookings.php?status=completed" class="flex-1 min-w-[150px] flex items-center gap-3 bg-white border border-black/5 rounded-2xl px-5 py-4 shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-ink/40 uppercase tracking-wider">Completed</div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-zodiak font-bold text-ink"><?php echo $completed_count; ?></span>
                            <span class="text-[10px] font-bold text-blue-600"><?php echo $completed_pct; ?>%</span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Bookings List -->
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5">
                <div
                    class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 border-b border-black/5 pb-4">
                    <h2 class="font-zodiak text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-clock text-orange-500"></i> <span id="bookings-heading-text">Today's
                            Bookings</span>
                    </h2>
                    <div class="flex bg-slate-100 p-1 rounded-xl">
                        <button id="view-today-btn"
                            class="px-3 py-1.5 text-sm font-bold rounded-lg transition-colors bg-white text-deepTeal shadow-sm">Today's</button>
                        <button id="view-all-btn"
                            class="px-3 py-1.5 text-sm font-bold rounded-lg transition-colors text-ink/60 hover:text-ink">All
                            Bookings</button>
                    </div>
                </div>

                <!-- Today's Bookings Container -->
                <div id="today-bookings-container" class="overflow-x-auto">
                    <?php if ($today_bookings->num_rows > 0): ?>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-ink/50 text-xs font-bold uppercase tracking-wider border-b border-black/5">
                                    <th class="pb-3 px-2">Time</th>
                                    <th class="pb-3 px-2">Guest</th>
                                    <th class="pb-3 px-2">Covers</th>
                                    <th class="pb-3 px-2 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/5">
                                <?php while ($b = $today_bookings->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="py-4 px-2 font-bold text-ink whitespace-nowrap">
                                            <?php echo date('H:i', strtotime($b['booking_time'])); ?>
                                        </td>
                                        <td class="py-4 px-2">
                                            <div class="font-bold text-ink">
                                                <?php echo htmlspecialchars($b['first_name'] . ' ' . $b['last_name']); ?>
                                            </div>
                                            <div class="text-xs text-ink/50 mt-0.5"><?php echo htmlspecialchars($b['email']); ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-2 text-ink/70 font-medium"><i
                                                class="fas fa-users text-ink/30 mr-1 text-xs"></i> <?php echo $b['guests']; ?>
                                        </td>
                                        <td class="py-4 px-2 text-right">
                                            <?php
                                            $displayStatus = $b['status'];
                                            if ($displayStatus === 'confirmed' && $today > $b['booking_date']) {
                                                $displayStatus = 'completed';
                                            }

                                            $statusBg = 'bg-teal-50';
                                            $statusText = 'text-teal-700';
                                            $statusBorder = 'border-teal-100';

                                            if ($displayStatus === 'cancelled') {
                                                $statusBg = 'bg-red-50';
                                                $statusText = 'text-red-700';
                                                $statusBorder = 'border-red-100';
                                            } else if ($displayStatus === 'pending') {
                                                $statusBg = 'bg-orange-50';
                                                $statusText = 'text-orange-700';
                                                $statusBorder = 'border-orange-100';
                                            } else if ($displayStatus === 'completed') {
                                                $statusBg = 'bg-orange-50';
                                                $statusText = 'text-orange-600';
                                                $statusBorder = 'border-orange-100';
                                            }
                                            ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $statusBg . ' ' . $statusText . ' ' . $statusBorder; ?>">
                                                <?php echo ucfirst($displayStatus); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="py-12 text-center flex flex-col items-center">
                            <div
                                class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-ink/20 mb-4 shadow-sm">
                                <i class="fas fa-calendar-xmark text-2xl"></i>
                            </div>
                            <p class="font-medium text-ink/60">No bookings for today.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- All Bookings Container -->
                <div id="all-bookings-container" class="overflow-x-auto" style="display: none;">
                    <?php if ($all_bookings->num_rows > 0): ?>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-ink/50 text-xs font-bold uppercase tracking-wider border-b border-black/5">
                                    <th class="pb-3 px-2">Date & Time</th>
                                    <th class="pb-3 px-2">Guest</th>
                                    <th class="pb-3 px-2">Covers</th>
                                    <th class="pb-3 px-2 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/5">
                                <?php while ($b = $all_bookings->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="py-4 px-2 font-bold text-ink whitespace-nowrap">
                                            <?php echo date('M d, Y', strtotime($b['booking_date'])); ?>
                                            <div class="text-xs text-ink/50 mt-0.5">
                                                <?php echo date('h:i A', strtotime($b['booking_time'])); ?></div>
                                        </td>
                                        <td class="py-4 px-2">
                                            <div class="font-bold text-ink">
                                                <?php echo htmlspecialchars($b['first_name'] . ' ' . $b['last_name']); ?>
                                            </div>
                                            <div class="text-xs text-ink/50 mt-0.5"><?php echo htmlspecialchars($b['email']); ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-2 text-ink/70 font-medium"><i
                                                class="fas fa-users text-ink/30 mr-1 text-xs"></i> <?php echo $b['guests']; ?>
                                        </td>
                                        <td class="py-4 px-2 text-right">
                                            <?php
                                            $displayStatus = $b['status'];
                                            if ($displayStatus === 'confirmed' && $today > $b['booking_date']) {
                                                $displayStatus = 'completed';
                                            }

                                            $statusBg = 'bg-teal-50';
                                            $statusText = 'text-teal-700';
                                            $statusBorder = 'border-teal-100';

                                            if ($displayStatus === 'cancelled') {
                                                $statusBg = 'bg-red-50';
                                                $statusText = 'text-red-700';
                                                $statusBorder = 'border-red-100';
                                            } else if ($displayStatus === 'pending') {
                                                $statusBg = 'bg-orange-50';
                                                $statusText = 'text-orange-700';
                                                $statusBorder = 'border-orange-100';
                                            } else if ($displayStatus === 'completed') {
                                                $statusBg = 'bg-orange-50';
                                                $statusText = 'text-orange-600';
                                                $statusBorder = 'border-orange-100';
                                            }
                                            ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $statusBg . ' ' . $statusText . ' ' . $statusBorder; ?>">
                                                <?php echo ucfirst($displayStatus); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="py-12 text-center flex flex-col items-center">
                            <div
                                class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-ink/20 mb-4 shadow-sm">
                                <i class="fas fa-history text-2xl"></i>
                            </div>
                            <p class="font-medium text-ink/60">No bookings found for your restaurant.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Graph 1: Day-wise bookings for selected month/year -->
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 border-b border-black/5 pb-4">
                    <h2 class="font-zodiak text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-calendar-day text-deepTeal"></i> Daily Bookings
                    </h2>
                    <div class="flex gap-2">
                        <select id="dailyMonth" class="bg-slate-100 p-2 rounded-xl text-sm font-bold outline-none border-none">
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $selected = ($m == date('n')) ? 'selected' : '';
                                echo "<option value='$m' $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
                            }
                            ?>
                        </select>
                        <select id="dailyYear" class="bg-slate-100 p-2 rounded-xl text-sm font-bold outline-none border-none">
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
                                $selected = ($y == $currentYear) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="w-full relative h-[300px]">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <!-- Graph 2: Month-wise bookings for selected year -->
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 border-b border-black/5 pb-4">
                    <h2 class="font-zodiak text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-chart-bar text-warmWood"></i> Monthly Bookings
                    </h2>
                    <div class="flex gap-2">
                        <select id="monthlyYear" class="bg-slate-100 p-2 rounded-xl text-sm font-bold outline-none border-none">
                            <?php
                            for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
                                $selected = ($y == $currentYear) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="w-full relative h-[300px]">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Day Breakdown (matches Admin layout) -->
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5">
                <div class="mb-6 border-b border-black/5 pb-4">
                    <h2 class="font-zodiak text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-store text-warmWood"></i> Day Breakdown
                    </h2>
                    <p class="text-sm text-ink/60 mt-1">
                        Select a date on the calendar to view this restaurant's reservations.
                    </p>
                </div>

                <div id="date-bookings-container"
                    class="bg-slate-50/50 rounded-[24px] border border-black/5 overflow-hidden"
                    style="height: 260px; overflow-y: auto;">
                    <div class="p-12 text-center flex flex-col items-center justify-center">
                        <div
                            class="w-16 h-16 bg-white rounded-full flex items-center justify-center text-ink/20 mb-4 shadow-sm">
                            <i class="fas fa-hand-pointer text-2xl"></i>
                        </div>
                        <p class="font-medium text-ink/60">Select a date to view reservations</p>
                    </div>
                </div>
            </div>

            <!-- Reviews -->
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 class="font-zodiak text-2xl font-bold mb-1 flex items-center gap-3">
                            <i class="fas fa-star text-orange-500"></i> Reviews
                        </h2>
                        <p class="text-sm text-ink/60 font-medium">Latest guest feedback</p>
                    </div>
                    <div class="text-right">
                        <?php if ($avg_rating !== null): ?>
                            <div class="inline-flex items-center gap-2 bg-orange-50 border border-orange-100 text-orange-700 px-4 py-2 rounded-full font-bold">
                                <i class="fas fa-star"></i>
                                <span><?php echo number_format($avg_rating, 1); ?></span>
                                <span class="text-ink/40 font-medium text-sm">(<?php echo $reviews_count; ?>)</span>
                            </div>
                        <?php else: ?>
                            <div class="text-sm text-ink/50 font-medium">No reviews</div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($latest_reviews && $latest_reviews->num_rows > 0): ?>
                    <div class="flex flex-col gap-3">
                        <?php while ($rv = $latest_reviews->fetch_assoc()): ?>
                            <?php
                            $name = trim(($rv['u_firstname'] ?? '') . ' ' . ($rv['u_lastname'] ?? ''));
                            if ($name === '') {
                                $name = 'Guest';
                            }
                            $avatar = '';
                            if (!empty($rv['u_image'])) {
                                $avatar_src = (strpos($rv['u_image'], 'http') === 0) ? $rv['u_image'] : BASE_URL . $rv['u_image'];
                                $avatar = '<img src="' . htmlspecialchars($avatar_src) . '" class="w-9 h-9 rounded-full object-cover border border-black/5">';
                            } else {
                                $initial = strtoupper(substr($rv['u_email'] ?? 'G', 0, 1));
                                $avatar = '<div class="w-9 h-9 rounded-full bg-deepTeal text-white flex items-center justify-center font-bold text-sm">' . $initial . '</div>';
                            }
                            ?>
                            <div class="bg-slate-50/60 border border-black/5 rounded-2xl p-4">
                                <div class="flex items-start gap-3">
                                    <div class="shrink-0"><?php echo $avatar; ?></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="font-bold text-ink truncate"><?php echo htmlspecialchars($name); ?></div>
                                                <div class="text-[11px] text-ink/50 font-medium">
                                                    <?php echo date('M d • h:i A', strtotime($rv['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="inline-flex items-center gap-1 text-orange-600 font-bold text-sm shrink-0">
                                                <?php echo (int) $rv['rating']; ?>
                                                <i class="fas fa-star text-orange-500"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2 text-ink/70 text-sm leading-relaxed">
                                            <?php echo nl2br(htmlspecialchars($rv['comment'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="py-10 text-center text-ink/50 font-medium">
                        No guest feedback yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Context/Calendar -->
        <div class="lg:col-span-1 lg:sticky lg:top-[120px]">
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5 mb-8">
                <h2 class="font-zodiak text-2xl font-bold mb-1 flex items-center gap-3"><i
                        class="fas fa-calendar-days text-tealAccent"></i> Advance Search</h2>
                <p class="text-sm text-ink/60 mb-6 font-medium">Select a date to view reservations</p>

                <!-- Custom Calendar UI -->
                <div class="bg-slate-50 rounded-[24px] p-5 border border-black/5 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <button type="button" id="prev-month"
                            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-black/5 transition-colors text-ink/60">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </button>
                        <h4 id="calendar-month-year" class="m-0 font-bold text-ink"></h4>
                        <button type="button" id="next-month"
                            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-black/5 transition-colors text-ink">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </button>
                    </div>

                    <div
                        class="grid grid-cols-7 gap-1 text-center text-xs font-bold text-ink/50 mb-2 uppercase tracking-wider">
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                        <div>Sun</div>
                    </div>

                    <div id="calendar-grid" class="grid grid-cols-7 gap-1">
                        <!-- JS will populate days here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const restId = <?php echo $rest_id; ?>;
    const summaryData = <?php echo json_encode($advance_summary); ?>;
    const todayStr = "<?php echo $today; ?>";

    // --- Bookings Fetch Logic (feeds Day Breakdown box) ---
    function fetchBookings(date) {
        fetch(`get_bookings.php?date=${date}&restaurant_id=${restId}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('date-bookings-container');

                const displayDate = new Date(date).toLocaleDateString('default', {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                });

                if (data.length > 0) {
                    let html = `
                        <div style="padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fafafa; border-radius: 8px 8px 0 0;">
                            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">${displayDate}</h3>
                            <span style="font-size: 0.85rem; background: #1e293b; color: #fff; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: 700;">${data.length} Bookings</span>
                        </div>
                        <div style="max-height: 400px; overflow-y: auto; padding: 0.5rem 0;">
                    `;
                    data.forEach(b => {
                        let displayStatus = b.status;
                        if (displayStatus === 'confirmed' && date < todayStr) {
                            displayStatus = 'completed';
                        }

                        let badgeClass = 'status-approved';
                        if (displayStatus === 'cancelled') badgeClass = 'status-rejected';
                        else if (displayStatus === 'pending') badgeClass = 'status-pending';
                        else if (displayStatus === 'completed') badgeClass = 'status-completed';

                        let displayStatusText = displayStatus.charAt(0).toUpperCase() + displayStatus.slice(1);

                        html += `
                            <div style="padding: 1.1rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                                <div style="flex: 1 1 auto; min-width: 0;">
                                    <div style="font-weight: 700; color: #111827; margin-bottom: 0.25rem;">${b.name}</div>
                                    <div style="font-size: 0.8rem; color: #6b7280; display:flex; align-items:center; gap:0.5rem;">
                                        <span><i class="fas fa-clock"></i> ${b.time}</span>
                                        <span><i class="fas fa-users"></i> ${b.guests} Guests</span>
                                    </div>
                                </div>
                                <span class="status-badge ${badgeClass}" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.06em;">${displayStatusText}</span>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `
                        <div style="padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">${displayDate}</h3>
                        </div>
                        <div style="padding: 3rem; text-align: center; color: #95a5a6;">
                            <i class="fas fa-calendar-xmark" style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.2;"></i>
                            No bookings for this date.
                        </div>`;
                }
            });
    }

    // --- Custom Calendar Logic ---
    const calendarGrid = document.getElementById('calendar-grid');
    const monthYearDisplay = document.getElementById('calendar-month-year');
    const prevBtn = document.getElementById('prev-month');
    const nextBtn = document.getElementById('next-month');

    let currentDate = new Date();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const limitDate = new Date();
    limitDate.setMonth(limitDate.getMonth() + 3);
    limitDate.setHours(0, 0, 0, 0);

    // Start by selecting today
    let selectedDateStr = todayStr;

    function renderCalendar(date) {
        calendarGrid.innerHTML = '';
        const year = date.getFullYear();
        const month = date.getMonth();

        const options = { month: 'short', year: 'numeric' };
        monthYearDisplay.textContent = date.toLocaleDateString('en-US', options);

        let firstDay = new Date(year, month, 1).getDay();
        firstDay = firstDay === 0 ? 7 : firstDay;

        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        for (let i = firstDay - 1; i > 0; i--) {
            addDayCell(daysInPrevMonth - i + 1, true, null);
        }

        for (let i = 1; i <= daysInMonth; i++) {
            const cellDate = new Date(year, month, i);
            const isBeyond = cellDate > limitDate;
            addDayCell(i, false, cellDate, isBeyond);
        }

        const currentMonth = date.getFullYear() * 12 + date.getMonth();
        const maxMonth = limitDate.getFullYear() * 12 + limitDate.getMonth();

        // Manager can go back to see past bookings
        prevBtn.style.pointerEvents = 'auto';
        prevBtn.style.color = '#1e293b';

        nextBtn.style.pointerEvents = currentMonth >= maxMonth ? 'none' : 'auto';
        nextBtn.style.color = currentMonth >= maxMonth ? '#e2e8f0' : '#1e293b';

        const totalRendered = (firstDay - 1) + daysInMonth;
        const remainder = totalRendered % 7;
        if (remainder !== 0) {
            for (let i = 1; i <= (7 - remainder); i++) {
                addDayCell(i, true, null);
            }
        }
    }

    function addDayCell(day, isFiller, cellDate = null, isDisabled = false) {
        const cell = document.createElement('div');
        cell.style.aspectRatio = '1';
        cell.style.borderRadius = '6px';
        cell.style.display = 'flex';
        cell.style.flexDirection = 'column';
        cell.style.alignItems = 'center';
        cell.style.justifyContent = 'center';
        cell.style.border = '1px solid #f1f5f9';
        cell.style.position = 'relative';
        cell.style.cursor = (isFiller || isDisabled) ? 'default' : 'pointer';
        cell.style.userSelect = 'none';
        cell.style.fontSize = '0.9rem';

        if (isFiller) {
            cell.style.color = '#cbd5e1';
            cell.innerText = day;
        } else if (isDisabled) {
            cell.style.color = '#cbd5e1';
            cell.style.background = '#f8fafc';
            cell.innerText = day;
        } else {
            cell.style.color = '#1e293b';
            cell.style.fontWeight = '600';
            cell.classList.add('valid-day');

            const yyyy = cellDate.getFullYear();
            const mm = String(cellDate.getMonth() + 1).padStart(2, '0');
            const dd = String(cellDate.getDate()).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;
            cell.dataset.date = dateStr;

            const numSpan = document.createElement('span');
            numSpan.innerText = day;
            numSpan.style.zIndex = '2';
            cell.appendChild(numSpan);

            // Add Booking Count Badge if exists
            if (summaryData[dateStr] && summaryData[dateStr] > 0) {
                const badge = document.createElement('span');
                badge.innerText = `${summaryData[dateStr]} Bkg`;
                badge.style.fontSize = '0.5rem';
                badge.style.background = '#e0f2fe'; // Light blue
                badge.style.color = '#0284c7'; // Dark blue
                badge.style.padding = '1px 3px';
                badge.style.borderRadius = '4px';
                badge.style.marginTop = '2px';
                badge.style.zIndex = '2';
                cell.appendChild(badge);
            }

            cell.addEventListener('click', function () {
                document.querySelectorAll('.valid-day').forEach(d => {
                    d.style.borderColor = '#f1f5f9';
                    d.style.background = 'transparent';
                    d.style.boxShadow = 'none';
                });

                this.style.borderColor = 'var(--primary-color)';
                this.style.background = '#eef2ff';
                this.style.boxShadow = '0 0 0 1px var(--primary-color)';
                selectedDateStr = this.dataset.date;
                fetchBookings(selectedDateStr);
            });

            if (selectedDateStr === dateStr) {
                cell.style.borderColor = 'var(--primary-color)';
                cell.style.background = '#eef2ff';
                cell.style.boxShadow = '0 0 0 1px var(--primary-color)';
            }
        }

        calendarGrid.appendChild(cell);
    }

    prevBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar(currentDate);
    });

    nextBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar(currentDate);
    });

    // Init Layout
    renderCalendar(currentDate);
    fetchBookings(selectedDateStr);

    // --- New Chart.js Implementation ---
    let dailyChartInstance = null;
    let monthlyChartInstance = null;

    async function fetchAndRenderDaily() {
        const month = document.getElementById('dailyMonth').value;
        const year = document.getElementById('dailyYear').value;
        
        try {
            const response = await fetch(`get_booking_stats.php?type=daily&month=${month}&year=${year}`);
            const data = await response.json();
            
            const ctx = document.getElementById('dailyChart').getContext('2d');
            
            if (dailyChartInstance) {
                dailyChartInstance.destroy();
            }
            
            dailyChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Daily Bookings',
                        data: data.data,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#27ae60',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' Bookings';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#94a3b8' },
                            grid: { color: '#f1f5f9', drawBorder: false }
                        },
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { display: false, drawBorder: false }
                        }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
        } catch (error) {
            console.error('Error fetching daily stats:', error);
        }
    }

    async function fetchAndRenderMonthly() {
        const year = document.getElementById('monthlyYear').value;
        
        try {
            const response = await fetch(`get_booking_stats.php?type=monthly&year=${year}`);
            const data = await response.json();
            
            const ctx = document.getElementById('monthlyChart').getContext('2d');
            
            if (monthlyChartInstance) {
                monthlyChartInstance.destroy();
            }
            
            monthlyChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Monthly Bookings',
                        data: data.data,
                        backgroundColor: '#e67e22',
                        borderRadius: 8,
                        hoverBackgroundColor: '#d35400'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' Bookings';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#94a3b8' },
                            grid: { color: '#f1f5f9', drawBorder: false }
                        },
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { display: false, drawBorder: false }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error fetching monthly stats:', error);
        }
    }

    // Initial renders
    fetchAndRenderDaily();
    fetchAndRenderMonthly();

    // Event listeners for selectors
    document.getElementById('dailyMonth').addEventListener('change', fetchAndRenderDaily);
    document.getElementById('dailyYear').addEventListener('change', fetchAndRenderDaily);
    document.getElementById('monthlyYear').addEventListener('change', fetchAndRenderMonthly);

    // Toggle Bookings Tab
    const viewTodayBookingsBtn = document.getElementById('view-today-btn');
    const viewAllBookingsBtn = document.getElementById('view-all-btn');
    const todayBookingsContainer = document.getElementById('today-bookings-container');
    const allBookingsContainer = document.getElementById('all-bookings-container');
    const bookingsHeadingText = document.getElementById('bookings-heading-text');

    if (viewTodayBookingsBtn && viewAllBookingsBtn) {
        const setActiveBtn = (activeBtn, inactiveBtn) => {
            activeBtn.style.background = '#fff';
            activeBtn.style.borderColor = '#e2e8f0';
            activeBtn.style.color = '#1e293b';
            activeBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';

            inactiveBtn.style.background = 'transparent';
            inactiveBtn.style.borderColor = 'transparent';
            inactiveBtn.style.color = '#64748b';
            inactiveBtn.style.boxShadow = 'none';
        };

        viewTodayBookingsBtn.addEventListener('click', () => {
            setActiveBtn(viewTodayBookingsBtn, viewAllBookingsBtn);
            todayBookingsContainer.style.display = 'block';
            allBookingsContainer.style.display = 'none';
            bookingsHeadingText.innerText = "Today's Bookings";
        });

        viewAllBookingsBtn.addEventListener('click', () => {
            setActiveBtn(viewAllBookingsBtn, viewTodayBookingsBtn);
            todayBookingsContainer.style.display = 'none';
            allBookingsContainer.style.display = 'block';
            bookingsHeadingText.innerText = "All Bookings";
        });
    }
</script>

<?php require_once '../footer.php'; ?>