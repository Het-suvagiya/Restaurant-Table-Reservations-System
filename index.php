<?php
require_once 'header.php';

// Handle randomization per session
if (!isset($_SESSION['seed'])) {
    $_SESSION['seed'] = rand(1, 10000);
}
$seed = $_SESSION['seed'];

// Fetch user favorites if logged in
$user_favs = [];
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT restaurant_id FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_favs[] = $row['restaurant_id'];
    }
    $stmt->close();
}

// Function to fetch restaurants for rows
function getRestaurantsForRow($conn, $seed, $limit = 10, $offset = 0) {
    $sql = "SELECT r.*, 
                   COALESCE(AVG(rev.rating), 0) as avg_rating, 
                   COUNT(rev.id) as review_count 
            FROM restaurants r 
            LEFT JOIN reviews rev ON r.id = rev.restaurant_id 
            WHERE r.status = 'approved' AND r.is_published = 1 AND r.is_blocked = 0
            GROUP BY r.id
            ORDER BY RAND(?)
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $seed, $limit, $offset);
    $stmt->execute();
    return $stmt->get_result();
}

$row1_restaurants = getRestaurantsForRow($conn, $seed, 10, 0);
$row2_restaurants = getRestaurantsForRow($conn, $seed, 10, 10);
$row3_restaurants = getRestaurantsForRow($conn, $seed, 10, 20);

// Search parameters (for search results if q is present)
$search_query = isset($_GET['q']) ? '%' . $_GET['q'] . '%' : null;
$search_results = null;
if ($search_query) {
    $sql = "SELECT r.*, 
                   COALESCE(AVG(rev.rating), 0) as avg_rating, 
                   COUNT(rev.id) as review_count 
            FROM restaurants r 
            LEFT JOIN reviews rev ON r.id = rev.restaurant_id 
            WHERE r.status = 'approved' AND r.is_published = 1 AND r.is_blocked = 0
              AND (r.name LIKE ? OR r.cuisine LIKE ? OR r.location LIKE ?)
            GROUP BY r.id
            ORDER BY RAND(?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $search_query, $search_query, $search_query, $seed);
    $stmt->execute();
    $search_results = $stmt->get_result();
    $stmt->close();
}
?>

<main
    class="relative w-full h-[50vh] min-h-[400px] flex flex-col justify-center items-center pb-12 px-6 md:px-12 bg-black overflow-hidden mt-20">
    <!-- Background Image -->
    <div class="absolute inset-0 w-full h-full">
        <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&q=80&w=2070"
            class="w-full h-full object-cover opacity-80" alt="Restaurant Interior">
        <!-- Gradient Overlay: Darker for better text visibility -->
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[#07161a] via-transparent to-transparent"></div>
    </div>

    <!-- Hero Content -->
    <div
        class="relative z-10 max-w-[800px] mx-auto w-full flex flex-col items-center text-center animate-soft-rise mt-10">
        <span class="block text-cream text-xs font-bold uppercase tracking-[0.22em] mb-4 drop-shadow-md">The Finest
            Selection</span>
        <h1 class="font-zodiak text-4xl md:text-5xl lg:text-7xl text-white leading-[1.05] mb-6 drop-shadow-lg">
            Find Your Next Culinary Adventure.
        </h1>
        <p
            class="text-white/90 text-base md:text-xl max-w-[600px] mx-auto mb-10 font-medium leading-relaxed drop-shadow-md">
            Real-time booking for the best restaurants in your city. Experience dining curated for the exceptional.
        </p>

        <!-- Search Bar -->
        <form action="index.php" method="GET"
            class="bg-white/20 backdrop-blur-lg border border-white/30 p-2 rounded-[32px] flex flex-col md:flex-row items-center gap-2 w-full max-w-[700px] shadow-premium">

            <div class="flex-1 flex items-center px-4 py-2 w-full">
                <i class="fas fa-search text-white mr-3 drop-shadow-sm"></i>
                <input type="text" name="q" placeholder="Restaurant name, cuisine, or location..."
                    value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                    class="w-full bg-transparent border-none text-white placeholder:text-white/70 focus:ring-0 outline-none text-base font-medium drop-shadow-sm">
            </div>

            <button type="submit"
                class="w-full md:w-auto bg-white text-ink font-bold px-8 py-3 rounded-full hover:bg-cream transition-colors shadow-sm text-base">
                Find Table
            </button>
        </form>
    </div>
</main>

<section class="max-w-[1200px] mx-auto px-5 py-12 md:py-20 animate-soft-rise" style="animation-delay: 150ms;">

    <?php if ($search_results): ?>
        <!-- Search Results Section -->
        <div class="flex justify-between items-end mb-8 border-b border-black/5 pb-4">
            <div>
                <h2 class="font-zodiak text-3xl md:text-4xl font-bold text-ink">Search Results</h2>
                <p class="text-ink/60 mt-2">
                    Showing results for
                    "<strong class="text-ink"><?php echo htmlspecialchars($_GET['q']); ?></strong>"
                </p>
            </div>
            <a href="view_all.php?q=<?php echo urlencode($_GET['q']); ?>"
                class="hidden md:inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.18em] text-ink hover:text-deepTeal transition-colors">
                View all <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <?php if ($search_results->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 pb-8">
                <?php while ($rest = $search_results->fetch_assoc()): ?>
                    <?php include 'restaurant_card.php'; ?>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20 px-4 bg-white rounded-[32px] border border-black/5 shadow-sm">
                <div class="w-16 h-16 mx-auto bg-slate-50 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-search text-2xl text-ink/30"></i>
                </div>
                <h3 class="font-zodiak text-2xl text-ink font-bold mb-2">No establishments found</h3>
                <p class="text-ink/60 max-w-md mx-auto mb-6">We couldn't find any dining options matching your criteria. Try adjusting your search or location preferences.</p>
                <a href="index.php" class="inline-flex py-3 px-6 bg-deepTeal text-white rounded-full font-bold hover:bg-tealAccent transition-colors">
                    Clear Filters
                </a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Three Scrollable Rows -->
        
        <!-- Row 1: Restaurants chosen for you -->
        <div class="mb-16">
            <div class="flex justify-between items-end mb-8 border-b border-black/5 pb-4">
                <div>
                    <h2 class="font-zodiak text-3xl md:text-4xl font-bold text-ink">Restaurants chosen for you</h2>
                    <p class="text-ink/60 mt-1">Hand-picked selections for premium dining</p>
                </div>
                <a href="view_all.php?sort=featured"
                    class="hidden md:inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.18em] text-ink hover:text-deepTeal transition-colors">
                    View all <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="group relative">
                <!-- Left Scroll Button -->
                <button onclick="scrollRow(this, 'left')" 
                    class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 z-20 w-12 h-12 bg-white rounded-full shadow-xl border border-black/5 flex items-center justify-center text-ink opacity-0 group-hover:opacity-100 transition-all hover:bg-slate-50">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="flex overflow-x-auto gap-5 pb-8 snap-x snap-mandatory scrollbar-hide -mx-5 px-5 scroll-smooth">
                    <?php while ($rest = $row1_restaurants->fetch_assoc()): ?>
                        <div class="min-w-[280px] md:min-w-[300px] snap-center">
                            <?php include 'restaurant_card.php'; ?>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Right Scroll Button -->
                <button onclick="scrollRow(this, 'right')" 
                    class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 z-20 w-12 h-12 bg-white rounded-full shadow-xl border border-black/5 flex items-center justify-center text-ink opacity-0 group-hover:opacity-100 transition-all hover:bg-slate-50">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Row 2: Most booked of the month -->
        <div class="mb-16">
            <div class="flex justify-between items-end mb-8 border-b border-black/5 pb-4">
                <div>
                    <h2 class="font-zodiak text-3xl md:text-4xl font-bold text-ink">Most booked of the month</h2>
                    <p class="text-ink/60 mt-1">The most popular spots in the city right now</p>
                </div>
                <a href="view_all.php?sort=trending"
                    class="hidden md:inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.18em] text-ink hover:text-deepTeal transition-colors">
                    View all <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="group relative">
                <!-- Left Scroll Button -->
                <button onclick="scrollRow(this, 'left')" 
                    class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 z-20 w-12 h-12 bg-white rounded-full shadow-xl border border-black/5 flex items-center justify-center text-ink opacity-0 group-hover:opacity-100 transition-all hover:bg-slate-50">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="flex overflow-x-auto gap-5 pb-8 snap-x snap-mandatory scrollbar-hide -mx-5 px-5 scroll-smooth">
                    <?php while ($rest = $row2_restaurants->fetch_assoc()): ?>
                        <div class="min-w-[280px] md:min-w-[300px] snap-center">
                            <?php include 'restaurant_card.php'; ?>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Right Scroll Button -->
                <button onclick="scrollRow(this, 'right')" 
                    class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 z-20 w-12 h-12 bg-white rounded-full shadow-xl border border-black/5 flex items-center justify-center text-ink opacity-0 group-hover:opacity-100 transition-all hover:bg-slate-50">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Row 3: New & Noteworthy -->
        <div class="mb-16">
            <div class="flex justify-between items-end mb-8 border-b border-black/5 pb-4">
                <div>
                    <h2 class="font-zodiak text-3xl md:text-4xl font-bold text-ink">New & Noteworthy</h2>
                    <p class="text-ink/60 mt-1">Discover fresh flavors and new experiences</p>
                </div>
                <a href="view_all.php?sort=new"
                    class="hidden md:inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.18em] text-ink hover:text-deepTeal transition-colors">
                    View all <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="group relative">
                <!-- Left Scroll Button -->
                <button onclick="scrollRow(this, 'left')" 
                    class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 z-20 w-12 h-12 bg-white rounded-full shadow-xl border border-black/5 flex items-center justify-center text-ink opacity-0 group-hover:opacity-100 transition-all hover:bg-slate-50">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="flex overflow-x-auto gap-5 pb-8 snap-x snap-mandatory scrollbar-hide -mx-5 px-5 scroll-smooth">
                    <?php while ($rest = $row3_restaurants->fetch_assoc()): ?>
                        <div class="min-w-[280px] md:min-w-[300px] snap-center">
                            <?php include 'restaurant_card.php'; ?>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Right Scroll Button -->
                <button onclick="scrollRow(this, 'right')" 
                    class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 z-20 w-12 h-12 bg-white rounded-full shadow-xl border border-black/5 flex items-center justify-center text-ink opacity-0 group-hover:opacity-100 transition-all hover:bg-slate-50">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Restaurant Owner Section -->
    <div class="mt-20 mb-10 bg-white rounded-[40px] border border-black/5 p-8 md:p-12 shadow-premium overflow-hidden">
        <h2 class="font-zodiak text-3xl md:text-4xl font-bold text-ink mb-10">Are you a restaurant owner?</h2>
        
        <div class="flex flex-col lg:flex-row gap-12 items-center">
            <!-- Image Side -->
            <div class="w-full lg:w-1/2">
                <img src="https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&q=80&w=1000" 
                     alt="Chef in kitchen" 
                     class="w-full h-[400px] object-cover rounded-3xl shadow-lg">
            </div>
            
            <!-- Content Side -->
            <div class="w-full lg:w-1/2 flex flex-col gap-10">
                <!-- Register Section -->
                <div>
                    <h3 class="text-xl font-bold text-ink mb-2">Register your Restaurant</h3>
                    <p class="text-ink/60 mb-6">Tell us more about you and we will contact you as soon as possible</p>
                    <a href="<?php echo BASE_URL; ?>register_restaurant.php" 
                       class="inline-block px-8 py-3 border-2 border-deepTeal text-deepTeal font-bold rounded-lg hover:bg-deepTeal hover:text-white transition-all uppercase tracking-wider text-sm">
                        Register Restaurant
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    function scrollRow(btn, direction) {
        const row = btn.parentElement.querySelector('.overflow-x-auto');
        const scrollAmount = row.clientWidth * 0.8;
        if (direction === 'left') {
            row.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            row.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    }

    // Auto-scroll to search results if a search was performed
    document.addEventListener('DOMContentLoaded', function() {
        const searchQuery = new URLSearchParams(window.location.search).get('q');
        if (searchQuery) {
            // Find the search results section and scroll to it
            const resultsSection = document.querySelector('section');
            if (resultsSection) {
                resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });

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
                    btn.style.color = ''; // Clear inline color
                }
            })
            .catch(error => {
                console.error('Error toggling favorite:', error);
            });
    }
</script>

<?php require_once 'footer.php'; ?>