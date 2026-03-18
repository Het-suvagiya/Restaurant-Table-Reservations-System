<?php
require_once 'config.php';

// Handle AJAX toggle request BEFORE including header.php
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $rest_id = (int) $_GET['id'];

    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND restaurant_id = ?");
    $stmt->bind_param("ii", $user_id, $rest_id);
    $stmt->execute();
    $fav = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fav) {
        $stmt = $conn->prepare("DELETE FROM favorites WHERE id = ?");
        $stmt->bind_param("i", $fav['id']);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'removed']);
    } else {
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, restaurant_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $rest_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'added']);
    }
    exit;
}

require_once 'header.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Fetch favorites
$stmt = $conn->prepare("SELECT r.* FROM restaurants r JOIN favorites f ON r.id = f.restaurant_id WHERE f.user_id = ? AND r.is_published = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$favorites = $stmt->get_result();
$stmt->close();
?>

<div class="max-w-[1200px] mx-auto px-5 pt-32 pb-24 min-h-[80vh] animate-soft-rise">
    <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold mb-10 pb-6 border-b border-black/5">Saved Curations
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if ($favorites->num_rows > 0): ?>
            <?php while ($rest = $favorites->fetch_assoc()): ?>
                <div class="rest-card group relative h-[380px] rounded-[24px] overflow-hidden shadow-premium hover:shadow-premium-hover transition-all duration-500 cursor-pointer border border-black/5"
                    onclick="window.location.href='book.php?id=<?php echo $rest['id']; ?>'">

                    <!-- Favorite Toggle Button -->
                    <button onclick="event.stopPropagation(); toggleFavorite(<?php echo $rest['id']; ?>, this)"
                        class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-white/90 backdrop-blur-md flex items-center justify-center text-red-500 hover:scale-110 transition-transform shadow-sm">
                        <i class="fas fa-heart text-lg"></i>
                    </button>

                    <!-- Background Image & Gradient -->
                    <img src="<?php echo htmlspecialchars($rest['primary_image']); ?>" alt="Restaurant Image"
                        class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 opacity-90">
                    <div class="absolute inset-0 bg-gradient-to-t from-ink/95 via-ink/50 to-transparent"></div>

                    <!-- Card Content -->
                    <div class="absolute bottom-0 left-0 w-full p-6 flex flex-col justify-end">
                        <span
                            class="inline-block px-3 py-1 bg-white/20 backdrop-blur-md border border-white/30 rounded-full text-[10px] font-bold uppercase tracking-[0.2em] text-white w-fit mb-3">
                            Saved
                        </span>

                        <h3
                            class="font-zodiak text-2xl text-white font-bold mb-1 leading-tight group-hover:text-cream transition-colors">
                            <?php echo htmlspecialchars($rest['name']); ?>
                        </h3>

                        <div class="flex items-center gap-4 text-cream/80 text-xs font-medium mb-4">
                            <span class="flex items-center gap-1.5"><i class="fas fa-map-marker-alt opacity-70"></i>
                                <?php echo htmlspecialchars($rest['location'] ?? 'Location N/A'); ?></span>
                        </div>

                        <!-- Book Now Button (appears on hover for desktop, static for mobile) -->
                        <div
                            class="w-full relative h-[44px] overflow-hidden rounded-xl bg-white/10 backdrop-blur-md border border-white/20 group-hover:bg-deepTeal group-hover:border-deepTeal transition-colors duration-300">
                            <div
                                class="absolute inset-0 flex items-center justify-center text-sm font-bold text-white tracking-wide">
                                Reserve Table <i
                                    class="fas fa-arrow-right ml-2 text-[10px] opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all duration-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div
                class="col-span-full py-16 text-center bg-slate-50 border border-black/5 rounded-[32px] shadow-sm flex flex-col items-center">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center text-warmWood mb-6 shadow-sm">
                    <i class="far fa-heart text-3xl"></i>
                </div>
                <h2 class="font-zodiak text-2xl text-ink font-bold mb-3">No Saved Curations</h2>
                <p class="text-ink/60 mb-8 max-w-sm mx-auto text-sm leading-relaxed">Save your favorite dining destinations
                    so you can easily access and book them later.</p>
                <a href="index.php"
                    class="bg-deepTeal text-white font-bold py-3 px-8 rounded-full hover:bg-tealAccent transition-colors">
                    Explore Restaurants
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleFavorite(restId, btn) {
        fetch(`favorites.php?toggle=1&id=${restId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'removed') {
                    const card = btn.closest('.rest-card');
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => card.remove(), 300);
                }
            });
    }
</script>

<?php require_once 'footer.php'; ?>