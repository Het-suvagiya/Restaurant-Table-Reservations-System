<?php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$restaurant_id = $_GET['id'] ?? 0;

// Fetch restaurant details
$stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    die("Restaurant not found.");
}

// Fetch current schedule
$stmt = $conn->prepare("SELECT day_of_week FROM restaurant_schedule WHERE restaurant_id = ?");
$stmt->bind_param("i", $restaurant['id']);
$stmt->execute();
$res = $stmt->get_result();
$closed_days = [];
while ($row = $res->fetch_assoc()) {
    $closed_days[] = $row['day_of_week'];
}
$stmt->close();

// Fetch menu items
$stmt = $conn->prepare("SELECT * FROM restaurant_menu WHERE restaurant_id = ?");
$stmt->bind_param("i", $restaurant['id']);
$stmt->execute();
$menu_items = $stmt->get_result();
$stmt->close();

require_once '../header.php';
?>

<div class="max-w-[1200px] mx-auto pt-32 pb-24 px-5 animate-soft-rise">
    <div class="mb-8 flex justify-between items-center border-b border-black/5 pb-6">
        <div>
            <h1 class="font-zodiak text-4xl text-ink font-bold">Restaurant Profile</h1>
            <p class="text-ink/60 font-medium mt-1">Viewing restaurant details</p>
        </div>
        <a href="restaurants.php"
            class="px-6 py-2.5 rounded-full border border-black/10 text-ink font-bold hover:bg-slate-50 transition-colors shadow-sm flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Back to Manage
        </a>
    </div>

    <!-- VIEW MODE -->
    <div id="view-mode" class="max-w-[1000px] mx-auto">

        <!-- Premium Image Display -->
        <div
            class="relative w-full h-[400px] rounded-[32px] overflow-hidden shadow-premium mb-8 bg-slate-50 flex items-center justify-center border border-black/5 group">
            <?php if (!empty($restaurant['primary_image'])): ?>
                <img src="../<?php echo htmlspecialchars($restaurant['primary_image']); ?>"
                    class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
            <?php else: ?>
                <i class="fas fa-image text-8xl text-ink/10"></i>
            <?php endif; ?>
            <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent pointer-events-none"></div>

            <div class="absolute top-6 right-6">
                <span
                    class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold uppercase tracking-wider backdrop-blur-md shadow-sm <?php echo $restaurant['is_published'] ? 'bg-teal-500/90 text-white' : 'bg-slate-800/80 text-white'; ?>">
                    <i class="fas <?php echo $restaurant['is_published'] ? 'fa-eye' : 'fa-eye-slash'; ?> mr-2"></i>
                    <?php echo $restaurant['is_published'] ? 'Published' : 'Hidden'; ?>
                </span>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            <div class="lg:col-span-2">
                <h1 class="font-zodiak text-4xl md:text-5xl font-bold text-ink mb-6">
                    <?php echo htmlspecialchars($restaurant['name']); ?>
                </h1>

                <!-- Tags -->
                <div class="flex flex-wrap gap-3 mb-8">
                    <span
                        class="bg-slate-100 text-ink/70 px-4 py-2 rounded-full font-bold text-sm flex items-center gap-2 border border-black/5">
                        <i class="fas fa-utensils text-deepTeal"></i>
                        <?php echo htmlspecialchars($restaurant['cuisine']); ?>
                    </span>
                    <span
                        class="bg-slate-100 text-ink/70 px-4 py-2 rounded-full font-bold text-sm flex items-center gap-2 border border-black/5">
                        <i class="fas fa-wallet text-green-600"></i>
                        ₹<?php echo htmlspecialchars($restaurant['avg_price']); ?> avg
                    </span>
                    <span
                        class="bg-slate-100 text-ink/70 px-4 py-2 rounded-full font-bold text-sm flex items-center gap-2 border border-black/5">
                        <i class="fas fa-users text-orange-500"></i> Max
                        <?php echo htmlspecialchars($restaurant['max_guests'] ?? 20); ?>
                    </span>
                </div>

                <div class="text-ink/80 leading-relaxed text-lg mb-8 font-serif">
                    <?php echo nl2br(htmlspecialchars($restaurant['description'] ?? 'No description provided.')); ?>
                </div>

                <div class="bg-white border border-black/5 p-6 rounded-[24px] shadow-sm">
                    <h3 class="font-zodiak text-xl font-bold text-ink mb-4 flex items-center gap-3">
                        <i class="fas fa-address-card text-deepTeal"></i> Contact Info
                    </h3>
                    <div class="flex flex-col gap-4 text-ink/70 font-medium">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center border border-black/5 text-deepTeal">
                                <i class="fas fa-location-dot"></i></div>
                            <?php echo htmlspecialchars($restaurant['location']); ?>
                        </div>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center border border-black/5 text-deepTeal">
                                <i class="fas fa-phone"></i></div> <?php echo htmlspecialchars($restaurant['phone']); ?>
                        </div>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center border border-black/5 text-deepTeal">
                                <i class="fas fa-envelope"></i></div>
                            <?php echo htmlspecialchars($restaurant['email']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="flex flex-col gap-6">
                <div class="bg-white border border-black/5 p-6 rounded-[24px] shadow-sm">
                    <h3 class="font-zodiak text-xl font-bold text-ink mb-4 flex items-center gap-3">
                        <i class="fas fa-chair text-warmWood"></i> Seating
                    </h3>
                    <div class="text-ink/70 font-bold text-lg capitalize">
                        <?php echo htmlspecialchars($restaurant['seating_type'] === 'both' ? 'Inside and Outside' : ($restaurant['seating_type'] ?? 'Not set')); ?>
                    </div>
                </div>

                <div class="bg-white border border-black/5 p-6 rounded-[24px] shadow-sm">
                    <h3 class="font-zodiak text-xl font-bold text-ink mb-4 flex items-center gap-3">
                        <i class="fas fa-calendar-xmark text-red-500"></i> Closed Days
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        if (empty($closed_days)) {
                            echo '<span class="text-ink/50 text-sm font-bold bg-slate-50 px-3 py-1.5 rounded-full border border-black/5">Open 7 days a week</span>';
                        } else {
                            foreach ($closed_days as $cd) {
                                echo '<span class="bg-red-50 text-red-600 px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider border border-red-100">' . $cd . '</span>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Section View -->
        <div class="mt-12 pt-12 border-t border-black/5">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <h2 class="font-zodiak text-3xl font-bold text-ink flex items-center gap-3">
                    <i class="fas fa-book-open text-tealAccent"></i> Restaurant Menu
                </h2>
                <?php if (!empty($restaurant['menu_file'])): ?>
                    <a href="../<?php echo htmlspecialchars($restaurant['menu_file']); ?>" target="_blank"
                        class="bg-white border border-black/10 text-ink px-6 py-2.5 rounded-full font-bold hover:bg-slate-50 transition-colors shadow-sm flex items-center gap-2">
                        <i class="fas fa-file-pdf text-red-500"></i> View Menu PDF
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($menu_items->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php while ($item = $menu_items->fetch_assoc()): ?>
                        <div
                            class="bg-white p-6 rounded-[24px] border border-black/5 shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all flex items-center gap-4 group">
                            <?php if (!empty($item['item_image'])): ?>
                                <div class="flex-shrink-0">
                                    <img src="../<?php echo htmlspecialchars($item['item_image']); ?>"
                                        class="w-16 h-16 rounded-2xl object-cover border border-black/5">
                                </div>
                            <?php endif; ?>
                            <div class="flex-1 flex justify-between items-center gap-3">
                                <div>
                                    <h4 class="font-bold text-ink text-lg mb-1 group-hover:text-deepTeal transition-colors">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </h4>
                                    <?php if ($item['item_discount'] > 0): ?>
                                        <span
                                            class="inline-flex bg-green-50 text-green-700 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider border border-green-100">
                                            <?php echo floatval($item['item_discount']); ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <?php if ($item['item_discount'] > 0): ?>
                                        <div class="line-through text-ink/40 text-sm font-bold">₹<?php echo $item['item_price']; ?>
                                        </div>
                                        <div class="font-bold text-deepTeal text-xl">
                                            ₹<?php echo number_format($item['item_price'] - ($item['item_price'] * ($item['item_discount'] / 100)), 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="font-bold text-deepTeal text-xl">₹<?php echo $item['item_price']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-16 bg-slate-50 rounded-[32px] border border-black/5 flex flex-col items-center">
                    <div
                        class="w-20 h-20 bg-white rounded-full flex items-center justify-center text-ink/10 mb-4 shadow-sm">
                        <i class="fas fa-utensils text-3xl"></i>
                    </div>
                    <p class="text-ink/50 font-medium">No menu items added yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>