<?php
require_once 'config.php';
require_once 'includes/mail_helper.php';

if (isLoggedIn()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        exit;
    }
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all fields.";
    } else {
        // Check if email already exists in any table
        $query = "SELECT u_email as email FROM tbl_users WHERE u_email = ? 
                  UNION SELECT m_email as email FROM tbl_manager WHERE m_email = ? 
                  UNION SELECT a_email as email FROM tbl_admin WHERE a_email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $email, $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already registered.";
            $stmt->close();
        } else {
            $stmt->close();
            // Secure password hash
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = ROLE_USER;

            $stmt = $conn->prepare("INSERT INTO tbl_users (u_firstname, u_lastname, u_email, u_password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);

            if ($stmt->execute()) {
                // Auto-login after registration
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['email'] = $email;
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $_SESSION['role'] = $role;

                // Send Welcome Email
                $subject = "Welcome to QuickTable!";
                $body = "<h1>Welcome to QuickTable</h1><p>Thank you for signing up. Start exploring the best restaurants and make your bookings easily.</p>";
                sendMail($email, $subject, $body);

                if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    echo json_encode(['success' => true, 'redirect' => 'index.php']);
                    exit;
                }
                redirect('index.php');
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
    }

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
}
?>
<?php require_once 'header.php'; ?>

<div class="min-h-screen flex items-center justify-center p-5 pt-32 pb-24 animate-soft-rise relative overflow-hidden">
    <!-- Decorative background elements -->
    <div
        class="absolute top-0 left-0 w-full h-1/2 bg-gradient-to-b from-teal-50/50 to-transparent -z-10 pointer-events-none">
    </div>

    <div class="bg-white rounded-[40px] shadow-premium border border-black/5 w-full max-w-[480px] p-8 md:p-12 relative">
        <a href="index.php"
            class="absolute top-6 right-6 w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-ink/40 hover:text-ink hover:bg-slate-100 transition-colors border border-black/5">
            <i class="fas fa-times"></i>
        </a>

        <div class="mb-10 mt-4 text-center">
            <h1 class="font-zodiak text-4xl text-ink font-bold mb-3">Create Account</h1>
            <p class="text-ink/60 text-sm">Join QuickTable to explore top dining spots.</p>
        </div>

        <?php if ($error): ?>
            <div
                class="bg-red-50 text-red-600 p-4 rounded-2xl mb-8 border border-red-100 flex items-center gap-3 shadow-sm text-sm">
                <i class="fas fa-exclamation-circle"></i>
                <span class="font-medium"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="flex flex-col gap-5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">First Name</label>
                    <input type="text" name="first_name" required placeholder="John"
                        class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3.5 text-sm text-ink font-medium focus:outline-none focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all placeholder:text-ink/10">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Last Name</label>
                    <input type="text" name="last_name" required placeholder="Doe"
                        class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3.5 text-sm text-ink font-medium focus:outline-none focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all placeholder:text-ink/10">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Email Address</label>
                <input type="email" name="email" required placeholder="example@email.com"
                    class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3.5 text-sm text-ink font-medium focus:outline-none focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all placeholder:text-ink/10">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Password</label>
                <input type="password" name="password" required placeholder="Min 6 characters"
                    class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3.5 text-sm text-ink font-medium focus:outline-none focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all placeholder:text-ink/10">
            </div>

            <button type="submit"
                class="w-full bg-deepTeal text-white font-bold py-4 rounded-2xl hover:bg-tealAccent transition-colors mt-4 flex items-center justify-center gap-2 group shadow-sm">
                Register <i class="fas fa-arrow-right text-sm transition-transform group-hover:translate-x-1"></i>
            </button>
        </form>

        <div class="relative flex items-center justify-center mt-8 mb-6">
            <div class="absolute inset-x-0 h-px bg-black/5"></div>
            <span class="relative bg-white px-4 text-[10px] font-bold uppercase tracking-widest text-ink/30">Or continue
                with</span>
        </div>

        <a href="google-login.php"
            class="w-full bg-white border border-black/10 text-ink font-bold py-4 rounded-2xl hover:bg-slate-50 transition-all flex items-center justify-center gap-3 shadow-[0_2px_10px_rgba(0,0,0,0.02)] hover:shadow-[0_4px_15px_rgba(0,0,0,0.05)] hover:-translate-y-0.5">
            <svg class="w-5 h-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
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
            Google
        </a>

        <div class="mt-8 text-center border-t border-black/5 pt-8">
            <p class="text-sm text-ink/60 font-medium">
                Already have an account?
                <a href="login.php"
                    class="text-deepTeal hover:text-tealAccent font-bold ml-1 transition-colors underline decoration-2 decoration-deepTeal/20 underline-offset-4">Log
                    in</a>
            </p>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>