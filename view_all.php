<?php
require_once 'header.php';

// Handle randomization per session
if (!isset($_SESSION['seed'])) {
    $_SESSION['seed'] = rand(1, 10000);
}
$seed = $_SESSION['seed'];

// Filters
$location = $_GET['location'] ?? '';
$price_range = $_GET['price_range'] ?? '';
$cuisine = $_GET['cuisine'] ?? '';
$rating = $_GET['rating'] ?? '';
$seating = $_GET['seating'] ?? '';
$offer = isset($_GET['offer']) ? 1 : 0;
$q = $_GET['q'] ?? '';

// Build query
$sql = "SELECT r.*, 
               COALESCE(AVG(rev.rating), 0) as avg_rating, 
               COUNT(rev.id) as review_count 
        FROM restaurants r 
        LEFT JOIN reviews rev ON r.id = rev.restaurant_id 
        WHERE r.status = 'approved' AND r.is_published = 1 AND r.is_blocked = 0";

$params = [];
$types = "";

if ($q) {
    $sql .= " AND (r.name LIKE ? OR r.cuisine LIKE ? OR r.location LIKE ?)";
    $q_param = "%$q%";
    $params[] = $q_param; $params[] = $q_param; $params[] = $q_param;
    $types .= "sss";
}

if ($location) {
    $sql .= " AND r.location LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}

if ($cuisine) {
    $sql .= " AND r.cuisine = ?";
    $params[] = $cuisine;
    $types .= "s";
}

if ($seating) {
    $sql .= " AND r.seating_type = ?";
    $params[] = $seating;
    $types .= "s";
}

if ($price_range) {
    if ($price_range == 'low') $sql .= " AND r.avg_price < 500";
    elseif ($price_range == 'mid') $sql .= " AND r.avg_price BETWEEN 500 AND 1500";
    elseif ($price_range == 'high') $sql .= " AND r.avg_price > 1500";
}

$sql .= " GROUP BY r.id";

if ($rating) {
    $sql .= " HAVING avg_rating >= ?";
    $params[] = $rating;
    $types .= "d";
}

$sql .= " ORDER BY RAND(?)";
$params[] = $seed;
$types .= "i";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$restaurants = $stmt->get_result();

// Fetch distinct locations and cuisines for filters
$locations_res = $conn->query("SELECT DISTINCT location FROM restaurants WHERE is_published = 1 AND status = 'approved'");
$cuisines_res = $conn->query("SELECT DISTINCT cuisine FROM restaurants WHERE is_published = 1 AND status = 'approved'");

// User favorites
$user_favs = [];
if (isLoggedIn()) {
    $fav_stmt = $conn->prepare("SELECT restaurant_id FROM favorites WHERE user_id = ?");
    $fav_stmt->bind_param("i", $_SESSION['user_id']);
    $fav_stmt->execute();
    $fav_result = $fav_stmt->get_result();
    while ($row = $fav_result->fetch_assoc()) {
        $user_favs[] = $row['restaurant_id'];
    }
}
?>

<div class="max-w-[1400px] mx-auto px-5 py-24 md:py-32">
    <div class="flex flex-col lg:flex-row gap-10">
        <!-- Sidebar Filter -->
        <aside class="w-full lg:w-72 flex-shrink-0">
            <div class="sticky top-24 bg-white p-6 rounded-[32px] border border-black/5 shadow-premium max-h-[calc(100vh-120px)] overflow-y-auto scrollbar-hide">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-zodiak text-xl font-bold text-ink">Filters</h3>
                    <a href="view_all.php" class="text-xs font-bold uppercase tracking-widest text-deepTeal hover:underline">Reset</a>
                </div>

                <form action="view_all.php" method="GET" class="space-y-6">
                    <?php if($q): ?>
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                    <?php endif; ?>

                    <!-- Location -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-ink/40 mb-3">Location</label>
                        <select name="location" onchange="this.form.submit()" class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-deepTeal/20 transition-all">
                            <option value="">All Locations</option>
                            <?php while($loc = $locations_res->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location == $loc['location'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['location']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Cuisine -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-ink/40 mb-3">Cuisine</label>
                        <select name="cuisine" onchange="this.form.submit()" class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-deepTeal/20 transition-all">
                            <option value="">All Cuisines</option>
                            <?php while($c = $cuisines_res->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($c['cuisine']); ?>" <?php echo $cuisine == $c['cuisine'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['cuisine']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Price Range -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-ink/40 mb-3">Price Range</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="radio" name="price_range" value="" onchange="this.form.submit()" <?php echo $price_range == '' ? 'checked' : ''; ?> class="w-4 h-4 text-deepTeal focus:ring-deepTeal border-slate-300">
                                <span class="text-sm text-ink/70 group-hover:text-ink transition-colors">Any Price</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="radio" name="price_range" value="low" onchange="this.form.submit()" <?php echo $price_range == 'low' ? 'checked' : ''; ?> class="w-4 h-4 text-deepTeal focus:ring-deepTeal border-slate-300">
                                <span class="text-sm text-ink/70 group-hover:text-ink transition-colors">Under ₹500</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="radio" name="price_range" value="mid" onchange="this.form.submit()" <?php echo $price_range == 'mid' ? 'checked' : ''; ?> class="w-4 h-4 text-deepTeal focus:ring-deepTeal border-slate-300">
                                <span class="text-sm text-ink/70 group-hover:text-ink transition-colors">₹500 - ₹1500</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="radio" name="price_range" value="high" onchange="this.form.submit()" <?php echo $price_range == 'high' ? 'checked' : ''; ?> class="w-4 h-4 text-deepTeal focus:ring-deepTeal border-slate-300">
                                <span class="text-sm text-ink/70 group-hover:text-ink transition-colors">Above ₹1500</span>
                            </label>
                        </div>
                    </div>

                    <!-- Rating -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-ink/40 mb-3">Minimum Rating</label>
                        <div class="flex gap-2">
                            <?php for($i=3; $i<=5; $i++): ?>
                                <label class="flex-1">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" onchange="this.form.submit()" <?php echo $rating == $i ? 'checked' : ''; ?> class="hidden peer">
                                    <div class="text-center py-2 rounded-xl border border-slate-200 peer-checked:border-deepTeal peer-checked:bg-deepTeal peer-checked:text-white text-sm font-bold cursor-pointer transition-all">
                                        <?php echo $i; ?>+ <i class="fas fa-star text-[10px]"></i>
                                    </div>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Seating -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-ink/40 mb-3">Seating Type</label>
                        <select name="seating" onchange="this.form.submit()" class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-deepTeal/20 transition-all">
                            <option value="">Any Seating</option>
                            <option value="inside" <?php echo $seating == 'inside' ? 'selected' : ''; ?>>Inside</option>
                            <option value="outside" <?php echo $seating == 'outside' ? 'selected' : ''; ?>>Outside</option>
                            <option value="both" <?php echo $seating == 'both' ? 'selected' : ''; ?>>Both</option>
                        </select>
                    </div>

                    <!-- Offers -->
                    <div class="pt-4 border-t border-black/5">
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm font-bold text-ink">Special Offers</span>
                            <div class="relative inline-block w-10 h-6">
                                <input type="checkbox" name="offer" onchange="this.form.submit()" <?php echo $offer ? 'checked' : ''; ?> class="hidden peer">
                                <div class="absolute inset-0 bg-slate-200 rounded-full peer-checked:bg-deepTeal transition-colors"></div>
                                <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform peer-checked:translate-x-4"></div>
                            </div>
                        </label>
                    </div>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="mb-8">
                <h1 class="font-zodiak text-4xl font-bold text-ink mb-2">
                    <?php 
                    if($q) echo 'Results for "' . htmlspecialchars($q) . '"';
                    else echo 'Explore All Restaurants';
                    ?>
                </h1>
                <p class="text-ink/60"><?php echo $restaurants->num_rows; ?> establishments found</p>
            </div>

            <?php if ($restaurants->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php while ($rest = $restaurants->fetch_assoc()): ?>
                        <?php include 'restaurant_card.php'; ?>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-32 bg-white rounded-[40px] border border-black/5">
                    <div class="w-20 h-20 mx-auto bg-slate-50 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-search text-3xl text-ink/20"></i>
                    </div>
                    <h3 class="font-zodiak text-2xl text-ink font-bold mb-3">No results found</h3>
                    <p class="text-ink/60 max-w-sm mx-auto mb-8">Try adjusting your filters or search query to find what you're looking for.</p>
                    <a href="view_all.php" class="inline-flex py-4 px-8 bg-deepTeal text-white rounded-full font-bold hover:bg-tealAccent transition-all shadow-premium">
                        Clear All Filters
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
function toggleFavorite(restId, btn) {
    if (!<?php echo isLoggedIn() ? 'true' : 'false'; ?>) {
        openModal('login-modal');
        return;
    }

    fetch(`favorites.php?toggle=1&id=${restId}`)
        .then(response => response.json())
        .then(data => {
            const icon = btn.querySelector('i');
            if (data.status === 'added') {
                icon.className = 'fas fa-heart text-red-400';
            } else {
                icon.className = 'far fa-heart';
            }
        });
}
</script>

<?php require_once 'footer.php'; ?>