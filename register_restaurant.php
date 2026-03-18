<?php
require_once 'config.php';
require_once 'includes/mail_helper.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';
$info_msg = '';
$just_submitted = false;

// Check if profile is complete
$stmt = $conn->prepare("SELECT u_firstname, u_lastname, u_dob, u_gender FROM tbl_users WHERE u_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_profile_complete = !empty($user_profile['u_firstname']) && !empty($user_profile['u_lastname']) && !empty($user_profile['u_dob']) && !empty($user_profile['u_gender']);

// check if user already applied or owns a restaurant (for info message)
$stmt = $conn->prepare("SELECT status FROM restaurants WHERE manager_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();



// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_profile_complete) {
    $name = $_POST['name'];
    $cuisine = $_POST['cuisine'];
    $location = $_POST['location'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $seating_type = $_POST['seating_type'];
    $avg_price = $_POST['avg_price'];
    $max_guests = $_POST['max_guests'];
    $description = $_POST['description'];

    // Handle Image Upload
    $target_dir = "uploads/restaurants/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $image_name = time() . "_" . basename($_FILES["primary_image"]["name"]);
    $target_file = $target_dir . $image_name;

    if (move_uploaded_file($_FILES["primary_image"]["tmp_name"], $target_file)) {
        // ensure a corresponding manager row exists so FK will not fail
        $chk = $conn->prepare("SELECT m_id FROM tbl_manager WHERE m_id = ?");
        $chk->bind_param("i", $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            // copy user details into manager table without deleting user
            $fetch = $conn->prepare("SELECT u_firstname,u_lastname,u_email,u_password,u_phone,u_gender,u_dob,u_image,u_bio,google_id FROM tbl_users WHERE u_id = ?");
            $fetch->bind_param("i", $user_id);
            $fetch->execute();
            $row = $fetch->get_result()->fetch_assoc();
            $fetch->close();

            $ins = $conn->prepare("INSERT INTO tbl_manager (m_id,m_firstname,m_lastname,m_email,m_password,m_phone,m_gender,m_dob,m_image,m_bio,google_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("issssssssss", $user_id, $row['u_firstname'], $row['u_lastname'], $row['u_email'], $row['u_password'], $row['u_phone'], $row['u_gender'], $row['u_dob'], $row['u_image'], $row['u_bio'], $row['google_id']);
            $ins->execute();
            $ins->close();
        }
        $chk->close();

        if ($existing) {
            // Update existing restaurant
            $stmt = $conn->prepare("UPDATE restaurants SET name=?, cuisine=?, location=?, phone=?, email=?, seating_type=?, avg_price=?, max_guests=?, description=?, primary_image=?, status='pending' WHERE manager_id=?");
            $stmt->bind_param("ssssssdissi", $name, $cuisine, $location, $phone, $email, $seating_type, $avg_price, $max_guests, $description, $target_file, $user_id);
        } else {
            // insert restaurant with pending status
            $stmt = $conn->prepare("INSERT INTO restaurants (manager_id, name, cuisine, location, phone, email, seating_type, avg_price, max_guests, description, primary_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("issssssdiss", $user_id, $name, $cuisine, $location, $phone, $email, $seating_type, $avg_price, $max_guests, $description, $target_file);
        }

        if ($stmt->execute()) {
            $success_msg = "Your application has been submitted successfully and is pending admin approval.";
            $just_submitted = true;

            // Send Pending Review Email to Manager
            $subject = "Restaurant Registration Received - Pending Approval";
            $body = "<h1>Application Received</h1><p>Your request to register the restaurant <strong>" . htmlspecialchars($name) . "</strong> has been received. Our admin team will review it shortly. You will be notified once it's approved.</p>";
            sendMail($_SESSION['email'], $subject, $body);
        } else {
            $error_msg = "Error submitting application: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error_msg = "Sorry, there was an error uploading your image.";
    }
}

require_once 'header.php';
?>

<div class="max-w-[800px] mx-auto pt-32 pb-24 px-5 animate-soft-rise">
    <?php if (!$is_profile_complete): ?>
        <div class="bg-slate-50 border border-black/5 rounded-[32px] p-12 text-center flex flex-col items-center shadow-sm">
            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-6 text-orange-500">
                <i class="fas fa-user-edit text-3xl"></i>
            </div>
            <h2 class="font-zodiak text-3xl text-ink font-bold mb-3">Complete Your Profile First</h2>
            <p class="text-ink/60 mb-8 max-w-md mx-auto leading-relaxed">To apply for restaurant registration, you must fill
                out your profile details (First Name, Last Name, Date of Birth, and Gender).</p>
            <a href="profile.php"
                class="bg-deepTeal text-white font-bold py-3 px-8 rounded-full hover:bg-tealAccent transition-colors">
                Go to Profile
            </a>
        </div>
    <?php elseif ($just_submitted): ?>
        <div
            class="bg-teal-50 border border-teal-100 rounded-[32px] p-12 text-center flex flex-col items-center shadow-sm mb-8">
            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-6 text-teal-600">
                <i class="fas fa-check text-3xl"></i>
            </div>
            <h2 class="font-zodiak text-3xl text-ink font-bold mb-3">Application Submitted!</h2>
            <p class="text-teal-800 mb-8 max-w-md mx-auto font-medium"><?php echo $success_msg; ?></p>
            <a href="index.php"
                class="text-deepTeal font-bold hover:text-tealAccent transition-colors underline decoration-2 decoration-deepTeal/30 underline-offset-4">Back
                to Home</a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-[40px] shadow-sm border border-black/5 p-8 md:p-12">
            <div class="mb-10 text-center">
                <h1 class="font-zodiak text-4xl text-ink font-bold mb-3">Register Your Restaurant</h1>
                <p class="text-ink/60 text-sm">Join the QuickTable premium network and start accepting bookings.</p>
            </div>

            <?php if ($info_msg): ?>
                <div
                    class="bg-blue-50 text-blue-600 p-4 rounded-2xl mb-6 border border-blue-100 flex items-center gap-3 shadow-sm text-sm">
                    <i class="fas fa-info-circle text-lg"></i>
                    <span class="font-medium"><?php echo htmlspecialchars($info_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div
                    class="bg-red-50 text-red-600 p-4 rounded-2xl mb-8 border border-red-100 flex items-center gap-3 shadow-sm text-sm">
                    <i class="fas fa-exclamation-circle text-lg"></i>
                    <span class="font-medium"><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-6 text-ink">
                <!-- Basic Info -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Restaurant Name
                        <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required
                        class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Cuisine <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="cuisine" required placeholder="e.g. Italian, Modern American"
                            class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none placeholder:text-ink/20">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Location /
                            Address <span class="text-red-500">*</span></label>
                        <input type="text" name="location" required
                            class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Phone Number
                            <span class="text-red-500">*</span></label>
                        <input type="tel" name="phone" required
                            class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Restaurant
                            Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required
                            class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Seating Type
                            <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="seating_type" required
                                class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none appearance-none cursor-pointer">
                                <option value="inside">Inside Only</option>
                                <option value="outside">Outside Only</option>
                                <option value="both">Both Available</option>
                            </select>
                            <i
                                class="fas fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-ink/30 pointer-events-none text-xs"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Average Price
                            (for 2) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 text-ink/50 font-medium">₹</span>
                            <input type="number" name="avg_price" step="0.01" required
                                class="w-full bg-slate-50 border border-black/5 rounded-2xl pl-10 pr-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Maximum Guests per
                        Booking <span class="text-red-500">*</span></label>
                    <input type="number" name="max_guests" min="1" required placeholder="e.g. 20"
                        class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none placeholder:text-ink/20">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Restaurant Cover
                        Photo <span class="text-red-500">*</span></label>
                    <label
                        class="relative flex flex-col items-center justify-center w-full min-h-[140px] px-4 py-8 bg-slate-50 border-2 border-dashed border-black/10 rounded-2xl cursor-pointer hover:bg-slate-100 hover:border-deepTeal/30 transition-all group">
                        <i
                            class="fas fa-cloud-upload-alt text-3xl text-ink/20 group-hover:text-deepTeal/60 transition-colors mb-3"></i>
                        <span class="text-sm font-medium text-ink/60 group-hover:text-ink transition-colors">Click to upload
                            cover photo</span>
                        <span class="text-xs text-ink/40 mt-1">High quality image recommended</span>
                        <input type="file" name="primary_image" accept="image/*" required class="hidden">
                    </label>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Description / Vibe
                        (Optional)</label>
                    <textarea name="description" rows="4" placeholder="Tell diners what makes your restaurant special..."
                        class="w-full bg-slate-50 border border-black/5 rounded-2xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all outline-none resize-none placeholder:text-ink/20"></textarea>
                </div>

                <div class="pt-6 mt-4 border-t border-black/5">
                    <button type="submit"
                        class="w-full md:w-auto bg-deepTeal text-white font-bold py-4 px-12 rounded-[20px] hover:bg-tealAccent transition-colors flex items-center justify-center gap-2 mx-auto group shadow-sm">
                        Submit Application <i
                            class="fas fa-arrow-right text-sm transition-transform group-hover:translate-x-1"></i>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>