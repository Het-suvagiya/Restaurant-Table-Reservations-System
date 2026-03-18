<footer class="bg-ink text-white pt-20 pb-10 px-5 md:px-12 border-t border-black/10">
    <div class="max-w-[1200px] mx-auto grid grid-cols-1 md:grid-cols-12 gap-12 md:gap-8">

        <!-- Brand Section -->
        <div class="md:col-span-4 flex flex-col items-start">
            <h3 class="font-zodiak text-3xl font-bold mb-4 tracking-wide">QuickTable<span
                    class="text-deepTeal text-4xl leading-none">.</span></h3>
            <p class="text-white/60 text-sm leading-relaxed mb-8 max-w-sm">Elevating your dining experiences. Discover
                and reserve the best tables at premium restaurants with effortless elegance.</p>
            <div class="flex gap-4">
                <a href="#" target="_blank"
                    class="w-10 h-10 rounded-full border border-white/20 flex items-center justify-center hover:bg-white hover:text-ink transition-all duration-300">
                    <i class="fab fa-instagram text-sm"></i>
                </a>
                <a href="#" target="_blank"
                    class="w-10 h-10 rounded-full border border-white/20 flex items-center justify-center hover:bg-white hover:text-ink transition-all duration-300">
                    <i class="fab fa-twitter text-sm"></i>
                </a>
                <a href="#" target="_blank"
                    class="w-10 h-10 rounded-full border border-white/20 flex items-center justify-center hover:bg-white hover:text-ink transition-all duration-300">
                    <i class="fab fa-facebook text-sm"></i>
                </a>
            </div>
        </div>

        <!-- Spacer for desktop -->
        <div class="hidden md:block md:col-span-2"></div>

        <!-- Quick Links -->
        <div class="md:col-span-3">
            <h4 class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/40 mb-6">Explore</h4>
            <div class="flex flex-col gap-4">
                <a href="index.php"
                    class="text-white/80 hover:text-white transition-colors text-sm font-medium w-fit relative after:content-[''] after:absolute after:w-full after:scale-x-0 after:h-0.5 after:bottom-0 after:left-0 after:bg-deepTeal after:origin-bottom-right after:transition-transform after:duration-300 hover:after:scale-x-100 hover:after:origin-bottom-left">Home</a>
                <a href="#"
                    class="text-white/80 hover:text-white transition-colors text-sm font-medium w-fit relative after:content-[''] after:absolute after:w-full after:scale-x-0 after:h-0.5 after:bottom-0 after:left-0 after:bg-deepTeal after:origin-bottom-right after:transition-transform after:duration-300 hover:after:scale-x-100 hover:after:origin-bottom-left">About
                    Us</a>
                <a href="#"
                    class="text-white/80 hover:text-white transition-colors text-sm font-medium w-fit relative after:content-[''] after:absolute after:w-full after:scale-x-0 after:h-0.5 after:bottom-0 after:left-0 after:bg-deepTeal after:origin-bottom-right after:transition-transform after:duration-300 hover:after:scale-x-100 hover:after:origin-bottom-left">Contact</a>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="md:col-span-3">
            <h4 class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/40 mb-6">Contact</h4>
            <div class="flex flex-col gap-4">
                <a href="mailto:support@quicktable.com"
                    class="flex items-center gap-3 text-white/80 hover:text-white transition-colors text-sm font-medium group">
                    <div
                        class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center group-hover:bg-deepTeal transition-colors border border-white/10 group-hover:border-deepTeal">
                        <i class="fas fa-envelope text-xs"></i>
                    </div>
                    support@quicktable.com
                </a>
                <a href="tel:+1234567890"
                    class="flex items-center gap-3 text-white/80 hover:text-white transition-colors text-sm font-medium group">
                    <div
                        class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center group-hover:bg-deepTeal transition-colors border border-white/10 group-hover:border-deepTeal">
                        <i class="fas fa-phone text-xs"></i>
                    </div>
                    +1 234 567 890
                </a>
            </div>
        </div>
    </div>

    <!-- Copyright -->
    <div
        class="max-w-[1200px] mx-auto mt-16 pt-8 border-t border-white/10 text-center flex flex-col md:flex-row justify-between items-center gap-4">
        <p class="text-white/40 text-xs font-medium tracking-wide">
            &copy; <?php echo date('Y'); ?> QuickTable. All rights reserved.
        </p>
        <div class="flex gap-6">
            <a href="#" class="text-white/40 hover:text-white text-xs font-medium transition-colors">Privacy</a>
            <a href="#" class="text-white/40 hover:text-white text-xs font-medium transition-colors">Terms</a>
        </div>
    </div>
</footer>
</body>

</html>