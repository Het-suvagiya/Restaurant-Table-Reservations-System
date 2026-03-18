<div class="group relative h-[360px] bg-ink rounded-[24px] overflow-hidden shadow-premium transition-transform hover:-translate-y-1">

    <!-- Full Bleed Image -->
    <img src="<?php echo htmlspecialchars($rest['primary_image'] ?? 'assets/images/default-restaurant.jpg'); ?>"
        class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity duration-500"
        alt="<?php echo htmlspecialchars($rest['name']); ?>">

    <!-- Gradient Overlay from bottom -->
    <div class="absolute inset-0 bg-gradient-to-t from-ink/95 via-ink/50 to-transparent"></div>

    <!-- Favorite Toggle -->
    <button onclick="toggleFavorite(<?php echo $rest['id']; ?>, this)"
        class="absolute top-4 right-4 w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border border-white/30 text-white transition-all hover:bg-white/40 z-10">
        <i class="<?php echo (isset($user_favs) && in_array($rest['id'], $user_favs)) ? 'fas text-red-400' : 'far'; ?> fa-heart"></i>
    </button>

    <!-- Content (Inside Image Area) -->
    <div class="absolute inset-x-0 bottom-0 p-5 flex flex-col justify-end">
        <div class="mb-3">
            <span
                class="inline-block px-3 py-1 bg-white/20 backdrop-blur-md border border-white/20 rounded-full text-[10px] font-bold uppercase tracking-[0.18em] text-white mb-2">
                <?php echo htmlspecialchars($rest['cuisine']); ?>
            </span>
            <h3 class="font-zodiak text-2xl text-white font-bold leading-tight mb-1">
                <?php echo htmlspecialchars($rest['name']); ?>
            </h3>
            <p class="text-white/70 text-sm flex items-center gap-1.5 line-clamp-1">
                <i class="fas fa-map-marker-alt text-[10px]"></i>
                <?php echo htmlspecialchars($rest['location']); ?>
            </p>
            <p class="text-white/90 text-sm font-medium mt-1 flex justify-between items-center">
                <span>₹<?php echo number_format($rest['avg_price'], 0); ?> for two</span>
                <?php if ($rest['review_count'] > 0): ?>
                    <span class="inline-flex items-center gap-1 text-orange-400 font-bold">
                        <i class="fas fa-star text-xs"></i>
                        <?php echo number_format($rest['avg_rating'], 1); ?>
                        <span class="text-white/40 font-normal text-[10px]">(<?php echo $rest['review_count']; ?>)</span>
                    </span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Solid Teal Add/Book Button -->
        <a href="book.php?id=<?php echo $rest['id']; ?>"
            class="w-full bg-deepTeal text-white font-bold py-3.5 rounded-2xl text-center hover:bg-tealAccent transition-colors shadow-sm flex justify-center items-center gap-2">
            Reserve <i class="fas fa-arrow-right text-sm"></i>
        </a>
    </div>
</div>