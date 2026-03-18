<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickTable - Find Your Next Culinary Adventure</title>
    <!-- Import Fontshare Fonts: Zodiak & Satoshi -->
    <link href="https://api.fontshare.com/v2/css?f[]=zodiak@400,700&f[]=satoshi@400,500,700,900&display=swap"
        rel="stylesheet">
    <!-- Google Fonts: Outfit (display) & Plus Jakarta Sans (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=1.1">


    <!-- Tailwind CSS with Custom Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        deepTeal: '#1B4F5C',
                        tealAccent: '#2D7A8E',
                        warmWood: '#8B6F47',
                        cream: '#F5F3F0',
                        ink: '#0F172A',
                    },
                    fontFamily: {
                        zodiak: ['Zodiak', 'serif'],
                        satoshi: ['Satoshi', 'sans-serif'],
                    },
                    boxShadow: {
                        'premium': '0 10px 24px -16px rgba(15,23,42,0.35)',
                        'premium-hover': '0 18px 40px -24px rgba(15,23,42,0.55)',
                    },
                    keyframes: {
                        'soft-rise': {
                            '0%': { opacity: '0', transform: 'translateY(8px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    },
                    animation: {
                        'soft-rise': 'soft-rise 520ms cubic-bezier(0.4, 0, 0.2, 1) forwards',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-cream text-ink font-satoshi antialiased selection:bg-tealAccent selection:text-white">

    <body>
        <!-- Floating Navigation Bar -->
        <nav class="fixed top-4 left-4 right-4 z-50 rounded-[32px] bg-white/80 backdrop-blur-md border border-white/40 shadow-premium px-6 py-3 flex justify-between items-center transition-all duration-300 mx-auto max-w-[1200px]"
            style="height: 64px;">
            <!-- Left: Logo & Brand -->
            <a href="<?php echo BASE_URL; ?>index.php" class="flex items-center gap-3 no-underline group" title="Home">
                <div
                    class="w-10 h-10 rounded-full bg-deepTeal flex items-center justify-center text-white font-zodiak text-xl font-bold transition-transform group-hover:scale-105 shadow-sm">
                    Q</div>
                <span class="font-zodiak text-xl font-bold text-ink tracking-tight hidden sm:block">QuickTable</span>
            </a>

            <!-- Center/Right: Icon Navigation -->
            <div class="flex items-center gap-2 md:gap-4">

                <a href="<?php echo BASE_URL; ?>index.php"
                    class="text-ink/60 hover:text-deepTeal transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-black/5"
                    title="Home">
                    <i class="fas fa-home text-lg"></i>
                </a>

                <?php if (isLoggedIn()): ?>
                    <?php if (!isAdmin()): ?>
                        <a href="<?php echo BASE_URL; ?>favorites.php"
                            class="text-ink/60 hover:text-deepTeal transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-black/5"
                            title="Saved Restaurants">
                            <i class="far fa-heart text-lg"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Check Roles -->
                    <?php
                    // Fetch user data for avatar
                    if (!isset($header_user)) {
                        $role_header = getUserRole();

                        // Track block status for current session
                        $is_manager_blocked = false;
                        $is_user_blocked = false;

                        // If current user is a manager, check if their restaurant is blocked by admin
                        if ($role_header === ROLE_MANAGER) {
                            $stmt_block = $conn->prepare("SELECT is_blocked FROM restaurants WHERE manager_id = ? LIMIT 1");
                            if ($stmt_block) {
                                $stmt_block->bind_param("i", $_SESSION['user_id']);
                                $stmt_block->execute();
                                $res_block = $stmt_block->get_result();
                                if ($res_block && ($row_block = $res_block->fetch_assoc())) {
                                    $is_manager_blocked = !empty($row_block['is_blocked']);
                                }
                                $stmt_block->close();
                            }
                        }

                        // If current user is a normal diner, optionally check if they are blocked (if column exists)
                        if ($role_header === ROLE_USER) {
                            $colcheck_user_block = $conn->query("SHOW COLUMNS FROM tbl_users LIKE 'is_blocked'");
                            if ($colcheck_user_block && $colcheck_user_block->num_rows > 0) {
                                $stmt_user_block = $conn->prepare("SELECT is_blocked FROM tbl_users WHERE u_id = ? LIMIT 1");
                                if ($stmt_user_block) {
                                    $stmt_user_block->bind_param("i", $_SESSION['user_id']);
                                    $stmt_user_block->execute();
                                    $res_user_block = $stmt_user_block->get_result();
                                    if ($res_user_block && ($row_user_block = $res_user_block->fetch_assoc())) {
                                        $is_user_blocked = !empty($row_user_block['is_blocked']);
                                    }
                                    $stmt_user_block->close();
                                }
                            }
                        }

                        if ($role_header == ROLE_ADMIN) {
                            $stmt_header = $conn->prepare("SELECT a_email as email, a_firstname as first_name, a_image as profile_image FROM tbl_admin WHERE a_id = ?");
                        } elseif ($role_header == ROLE_MANAGER) {
                            $stmt_header = $conn->prepare("SELECT m_email as email, m_firstname as first_name, m_image as profile_image FROM tbl_manager WHERE m_id = ?");
                        } else {
                            $stmt_header = $conn->prepare("SELECT u_email as email, u_firstname as first_name, u_image as profile_image FROM tbl_users WHERE u_id = ?");
                        }
                        $stmt_header->bind_param("i", $_SESSION['user_id']);
                        $stmt_header->execute();
                        $header_user = $stmt_header->get_result()->fetch_assoc();
                        $stmt_header->close();
                    }

                    $avatar_html = '';
                    if (!empty($header_user['profile_image'])) {
                        // Check if it's an external URL (like Google)
                        if (strpos($header_user['profile_image'], 'http') === 0) {
                            $img_src = $header_user['profile_image'];
                        } else {
                            // Ensure we don't double up BASE_URL if the path already starts with it or a slash
                            $clean_path = ltrim($header_user['profile_image'], '/');
                            $img_src = BASE_URL . $clean_path;
                        }
                        $avatar_html = '<img src="' . $img_src . '" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
                        $initial = strtoupper(substr($header_user['email'] ?? 'U', 0, 1));
                        $avatar_html .= '<div class="hidden w-10 h-10 rounded-full bg-deepTeal text-white items-center justify-center font-bold text-lg shadow-sm">' . $initial . '</div>';
                    } else {
                        $initial = strtoupper(substr($header_user['email'] ?? 'U', 0, 1));
                        $avatar_html = '<div class="w-10 h-10 rounded-full bg-deepTeal text-white flex items-center justify-center font-bold text-lg shadow-sm">' . $initial . '</div>';
                    }

                    $profile_link = isAdmin() ? BASE_URL . 'admin/profile.php' : BASE_URL . 'profile.php';
                    ?>

                    <?php if (isAdmin() || isManager()): ?>
                        <?php if (isManager()): ?>
                            <!-- My Restaurant Icon for Manager -->
                            <a href="<?php echo BASE_URL . 'manager/setup.php'; ?>"
                                class="text-ink/60 hover:text-deepTeal transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-black/5"
                                title="My Restaurant">
                                <i class="fas fa-store text-lg"></i>
                            </a>

                            <?php if (!empty($is_manager_blocked)): ?>
                                <!-- Booking button for blocked managers (diner-style access) -->
                                <a href="<?php echo BASE_URL; ?>my_bookings.php"
                                    class="text-ink/60 hover:text-deepTeal transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-black/5"
                                    title="My Bookings">
                                    <i class="fas fa-calendar-alt text-lg"></i>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a href="<?php echo isAdmin() ? BASE_URL . 'admin/dashboard.php' : BASE_URL . 'manager/dashboard.php'; ?>"
                            class="text-ink/60 hover:text-deepTeal transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-black/5"
                            title="Dashboard">
                            <i class="fas fa-chart-pie text-lg"></i>
                        </a>
                    <?php else: ?>
                        <?php
                        // Check for pending reviews (safely checks if column exists)
                        $pending_reviews = 0;
                        if (isLoggedIn() && !isAdmin() && !isManager()) {
                            // ensure the review_status column exists so query won't fail
                            $colcheck = $conn->query("SHOW COLUMNS FROM bookings LIKE 'review_status'");
                            if ($colcheck && $colcheck->num_rows == 0) {
                                $conn->query("ALTER TABLE bookings ADD COLUMN review_status VARCHAR(20) DEFAULT 'pending' AFTER status");
                            }

                            $review_check = $conn->prepare("
                                SELECT COUNT(*) as count FROM bookings 
                                WHERE user_id = ? AND status = 'confirmed' AND booking_date <= CURDATE() 
                                AND (review_status IS NULL OR review_status = 'pending')
                            ");
                            if ($review_check) {
                                $review_check->bind_param("i", $_SESSION['user_id']);
                                $review_check->execute();
                                $result = $review_check->get_result();
                                if ($result) {
                                    $row = $result->fetch_assoc();
                                    $pending_reviews = $row['count'] ?? 0;
                                }
                                $review_check->close();
                            }
                        }
                        ?>
                        <a href="<?php echo BASE_URL; ?>my_bookings.php"
                            class="text-ink/60 hover:text-deepTeal transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-black/5 relative"
                            title="My Bookings">
                            <i class="fas fa-calendar-alt text-lg"></i>
                            <?php if ($pending_reviews > 0): ?>
                                <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full shadow-sm animate-pulse"></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <!-- Profile Dropdown -->
                    <div class="relative group ml-2">
                        <button
                            class="relative w-10 h-10 flex items-center justify-center rounded-full transition-transform hover:scale-105 focus:outline-none">
                            <?php echo $avatar_html; ?>
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            class="absolute right-0 top-full mt-2 w-48 bg-white rounded-2xl shadow-premium border border-black/5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 flex flex-col p-2 z-50 transform origin-top-right scale-95 group-hover:scale-100">
                            <div class="px-4 py-3 border-b border-black/5 mb-2">
                                <p class="text-sm font-bold text-ink truncate">
                                    <?php echo htmlspecialchars($header_user['first_name'] ?? 'User'); ?>
                                </p>
                                <p class="text-xs text-ink/50 truncate">
                                    <?php echo htmlspecialchars($header_user['email']); ?>
                                </p>
                            </div>
                            <a href="<?php echo $profile_link; ?>"
                                class="px-4 py-2 text-sm text-ink hover:bg-slate-50 hover:text-deepTeal rounded-xl transition-colors font-medium flex items-center gap-3">
                                <i class="fas fa-user text-ink/50 text-center w-4"></i> Profile
                            </a>

                            <?php if (!isAdmin() && !isManager()): ?>
                                <a href="<?php echo BASE_URL; ?>my_reviews.php"
                                    class="px-4 py-2 text-sm text-ink hover:bg-slate-50 hover:text-deepTeal rounded-xl transition-colors font-medium flex items-center gap-3">
                                    <i class="fas fa-star text-ink/50 text-center w-4"></i> My Reviews
                                </a>
                            <?php endif; ?>

                            <!-- Add Logout directly in profile dropdown as requested -->
                            <a href="<?php echo BASE_URL; ?>logout.php"
                                class="px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-xl transition-colors font-medium mt-1 flex items-center gap-3">
                                <i class="fas fa-sign-out-alt text-red-500/70 text-center w-4"></i> Logout
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Not Logged In -->
                    <button onclick="openModal('login-modal')"
                        class="text-ink/70 hover:text-deepTeal transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-black/5"
                        title="Log In">
                        <i class="fas fa-sign-in-alt text-lg"></i>
                    </button>
                    <button onclick="openModal('register-modal')"
                        class="bg-deepTeal text-white px-5 py-2 rounded-full font-bold text-sm hover:bg-tealAccent transition-colors shadow-sm ml-2"
                        title="Sign Up">
                        Get Started
                    </button>
                <?php endif; ?>
            </div>
        </nav>

        <!-- Login Modal -->
        <div id="login-modal" class="modal-overlay">
            <div class="modal-card">
                <span class="modal-close" onclick="closeModal('login-modal')">&times;</span>
                <h2>Welcome Back</h2>
                <p>Login to your QuickTable account</p>
                <div id="login-error"
                    style="color: #e74c3c; margin-bottom: 1rem; display: none; font-size: 0.9rem; background: #fdf2f2; padding: 0.8rem; border-radius: 8px;">
                </div>
                <form onsubmit="handleAuth(event, '<?php echo BASE_URL; ?>login.php', 'login-modal', 'login-error')">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="email@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="auth-btn mb-4">Login Now</button>

                    <div class="flex items-center justify-center my-4">
                        <div class="flex-grow h-px bg-black/10"></div>
                        <span class="px-3 text-[10px] font-bold uppercase tracking-widest text-ink/40">Or</span>
                        <div class="flex-grow h-px bg-black/10"></div>
                    </div>

                    <a href="<?php echo BASE_URL; ?>google-login.php"
                        class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-black/10 rounded-xl hover:bg-slate-50 transition-colors font-bold text-ink text-sm shadow-sm relative group overflow-hidden">
                        <svg class="w-5 h-5 relative z-10" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                fill="#4285F4" />
                            <path
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                fill="#34A853" />
                            <path
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                fill="#FBBC05" />
                            <path
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                fill="#EA4335" />
                        </svg>
                        <span class="relative z-10">Sign in with Google</span>
                        <div
                            class="absolute inset-0 bg-black/5 transform translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                        </div>
                    </a>
                </form>
                <div class="switch-auth mt-6">
                    Don't have an account? <a href="javascript:void(0)"
                        onclick="closeModal('login-modal'); openModal('register-modal')">Register here</a>
                </div>
            </div>
        </div>

        <!-- Global Alert Modal -->
        <div id="site-alert-modal" class="modal-overlay">
            <div class="modal-card">
                <span class="modal-close" onclick="closeSiteAlert()">&times;</span>
                <h2>Notice</h2>
                <p id="site-alert-message"></p>
                <div class="mt-6 flex justify-end">
                    <button type="button" class="auth-btn" style="min-width: 120px;" onclick="closeSiteAlert()">OK</button>
                </div>
            </div>
        </div>

        <!-- Global Confirm Modal -->
        <div id="site-confirm-modal" class="modal-overlay">
            <div class="modal-card">
                <span class="modal-close" onclick="closeSiteConfirm()">&times;</span>
                <h2>Are you sure?</h2>
                <p id="site-confirm-message"></p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="auth-btn"
                        style="min-width: 110px; background:#e5e7eb; color:#111827; border:none;"
                        onclick="closeSiteConfirm()">Cancel</button>
                    <button type="button" class="auth-btn" style="min-width: 140px;" onclick="handleSiteConfirmYes()">Yes,
                        continue</button>
                </div>
            </div>
        </div>

        <!-- Register Modal -->
        <div id="register-modal" class="modal-overlay">
            <div class="modal-card">
                <span class="modal-close" onclick="closeModal('register-modal')">&times;</span>
                <h2>Join QuickTable</h2>
                <p>Create your account in seconds</p>
                <div id="register-error"
                    style="color: #e74c3c; margin-bottom: 1rem; display: none; font-size: 0.9rem; background: #fdf2f2; padding: 0.8rem; border-radius: 8px;">
                </div>
                <form
                    onsubmit="handleAuth(event, '<?php echo BASE_URL; ?>register.php', 'register-modal', 'register-error')">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.8rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>First Name</label>
                            <input type="text" name="first_name" placeholder="John" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Last Name</label>
                            <input type="text" name="last_name" placeholder="Doe" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="email@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Min 6 characters" required>
                    </div>
                    <button type="submit" class="auth-btn mb-4">Create Account</button>

                    <div class="flex items-center justify-center my-4">
                        <div class="flex-grow h-px bg-black/10"></div>
                        <span class="px-3 text-[10px] font-bold uppercase tracking-widest text-ink/40">Or</span>
                        <div class="flex-grow h-px bg-black/10"></div>
                    </div>

                    <a href="<?php echo BASE_URL; ?>google-login.php"
                        class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-black/10 rounded-xl hover:bg-slate-50 transition-colors font-bold text-ink text-sm shadow-sm relative group overflow-hidden">
                        <svg class="w-5 h-5 relative z-10" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                fill="#4285F4" />
                            <path
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                fill="#34A853" />
                            <path
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                fill="#FBBC05" />
                            <path
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                fill="#EA4335" />
                        </svg>
                        <span class="relative z-10">Sign up with Google</span>
                        <div
                            class="absolute inset-0 bg-black/5 transform translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                        </div>
                    </a>
                </form>
                <div class="switch-auth mt-6">
                    Already have an account? <a href="javascript:void(0)"
                        onclick="closeModal('register-modal'); openModal('login-modal')">Login here</a>
                </div>
            </div>
        </div>

        <script>
            let siteConfirmCallback = null;

            function openModal(id) {
                document.getElementById(id).classList.add('active');
                document.body.classList.add('modal-open');
            }

            function closeModal(id) {
                document.getElementById(id).classList.remove('active');
                document.body.classList.remove('modal-open');
            }

            function openSiteAlert(message) {
                const modal = document.getElementById('site-alert-modal');
                const msgEl = document.getElementById('site-alert-message');
                if (msgEl) msgEl.textContent = message || '';
                modal.classList.add('active');
                document.body.classList.add('modal-open');
            }

            function closeSiteAlert() {
                const modal = document.getElementById('site-alert-modal');
                modal.classList.remove('active');
                document.body.classList.remove('modal-open');
            }

            function openSiteConfirm(message, onConfirm) {
                const modal = document.getElementById('site-confirm-modal');
                const msgEl = document.getElementById('site-confirm-message');
                if (msgEl) msgEl.textContent = message || '';
                siteConfirmCallback = typeof onConfirm === 'function' ? onConfirm : null;
                modal.classList.add('active');
                document.body.classList.add('modal-open');
            }

            function closeSiteConfirm() {
                const modal = document.getElementById('site-confirm-modal');
                modal.classList.remove('active');
                document.body.classList.remove('modal-open');
                siteConfirmCallback = null;
            }

            function handleSiteConfirmYes() {
                const cb = siteConfirmCallback;
                closeSiteConfirm();
                if (typeof cb === 'function') {
                    cb();
                }
            }

            function openSiteConfirmForForm(form, message) {
                openSiteConfirm(message, function () {
                    form.submit();
                });
                return false;
            }

            function openSiteConfirmForLink(event, message) {
                event.preventDefault();
                const href = event.currentTarget.getAttribute('href');
                openSiteConfirm(message, function () {
                    window.location.href = href;
                });
                return false;
            }

            async function handleAuth(event, url, modalId, errorId) {
                event.preventDefault();
                const form = event.target;
                const formData = new FormData(form);
                const errorDiv = document.getElementById(errorId);
                const submitBtn = form.querySelector('button');

                errorDiv.style.display = 'none';
                submitBtn.disabled = true;
                submitBtn.innerText = 'Processing...';

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        window.location.href = data.redirect.startsWith('http') ? data.redirect : '<?php echo BASE_URL; ?>' + data.redirect;
                    } else {
                        errorDiv.innerText = data.error || 'Authentication failed.';
                        errorDiv.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerText = modalId.includes('login') ? 'Login Now' : 'Create Account';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    errorDiv.innerText = 'An unexpected error occurred.';
                    errorDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Try Again';
                }
            }

            // Close modal on outside click
            window.onclick = function (event) {
                if (event.target.classList.contains('modal-overlay')) {
                    const overlay = event.target;
                    overlay.classList.remove('active');
                    document.body.classList.remove('modal-open');
                    // Also clear any pending confirm callback if confirm modal closed by background click
                    if (overlay.id === 'site-confirm-modal') {
                        siteConfirmCallback = null;
                    }
                }
            }

            // Auto-open modal if URL contains ?auth=login or ?auth=register
            window.addEventListener('DOMContentLoaded', () => {
                const params = new URLSearchParams(window.location.search);
                if (params.get('auth') === 'login') openModal('login-modal');
                if (params.get('auth') === 'register') openModal('register-modal');
            });
        </script>