<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Fetch current user details
$role = getUserRole();
if ($role == ROLE_ADMIN) {
    $stmt = $conn->prepare("SELECT a_email as email, a_firstname as first_name, a_lastname as last_name, a_gender as gender, a_dob as dob, a_bio as bio, a_image as profile_image FROM tbl_admin WHERE a_id = ?");
} elseif ($role == ROLE_MANAGER) {
    $stmt = $conn->prepare("SELECT m_email as email, m_firstname as first_name, m_lastname as last_name, m_gender as gender, m_dob as dob, m_bio as bio, m_image as profile_image FROM tbl_manager WHERE m_id = ?");
} else {
    $stmt = $conn->prepare("SELECT u_email as email, u_firstname as first_name, u_lastname as last_name, u_gender as gender, u_dob as dob, u_bio as bio, u_image as profile_image FROM tbl_users WHERE u_id = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $profile_image = $user['profile_image']; // Default to existing

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($gender) || empty($dob)) {
        $error_msg = "Please fill in all required fields (First Name, Last Name, Gender, and Date of Birth).";
    }

    // Handle Image Upload
    if (empty($error_msg) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $target_dir = "uploads/users/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $image_name = time() . "_" . basename($_FILES["profile_image"]["name"]);
        $target_file = $target_dir . $image_name;

        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            $profile_image = $target_file;
        } else {
            $error_msg = "Sorry, there was an error uploading your profile image.";
        }
    }

    if (empty($error_msg)) {
        if ($role == ROLE_ADMIN) {
            $stmt = $conn->prepare("UPDATE tbl_admin SET a_firstname = ?, a_lastname = ?, a_gender = ?, a_dob = ?, a_bio = ?, a_image = ? WHERE a_id = ?");
        } elseif ($role == ROLE_MANAGER) {
            $stmt = $conn->prepare("UPDATE tbl_manager SET m_firstname = ?, m_lastname = ?, m_gender = ?, m_dob = ?, m_bio = ?, m_image = ? WHERE m_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE tbl_users SET u_firstname = ?, u_lastname = ?, u_gender = ?, u_dob = ?, u_bio = ?, u_image = ? WHERE u_id = ?");
        }
        $stmt->bind_param("ssssssi", $first_name, $last_name, $gender, $dob, $bio, $profile_image, $user_id);

        if ($stmt->execute()) {
            $success_msg = "profile updated";
            // Update local user data
            $user['first_name'] = $first_name;
            $user['last_name'] = $last_name;
            $user['gender'] = $gender;
            $user['dob'] = $dob;
            $user['bio'] = $bio;
            $user['profile_image'] = $profile_image;
        } else {
            $error_msg = "Error updating profile: " . $conn->error;
        }
        $stmt->close();
    }
}

require_once 'header.php';
?>

<div class="max-w-[800px] mx-auto pt-32 pb-24 px-5 animate-soft-rise">
    <?php if ($success_msg): ?>
        <div class="bg-teal-50 text-teal-800 p-4 rounded-2xl mb-8 border border-teal-100 flex items-center gap-3 shadow-sm">
            <i class="fas fa-check-circle text-lg"></i>
            <span class="font-medium"><?php echo $success_msg; ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-8 border border-red-100 flex items-center gap-3 shadow-sm">
            <i class="fas fa-exclamation-circle text-lg"></i>
            <span class="font-medium"><?php echo $error_msg; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-[40px] shadow-sm border border-black/5 p-8 md:p-12">
        <h1 class="font-zodiak text-4xl text-ink font-bold mb-8 pb-6 border-b border-black/5">My Profile</h1>

        <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-8 text-ink">

            <!-- Avatar Section -->
            <div class="flex flex-col items-center mb-4">
                <div class="relative group cursor-pointer">
                    <?php if (!empty($user['profile_image'])): ?>
                        <?php
                        if (strpos($user['profile_image'], 'http') === 0) {
                            $profile_img_src = $user['profile_image'];
                        } else {
                            $profile_img_src = BASE_URL . ltrim($user['profile_image'], '/');
                        }
                        ?>
                        <img src="<?php echo $profile_img_src; ?>" alt="Profile Image"
                            class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-premium"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="hidden w-32 h-32 rounded-full bg-slate-50 border border-black/5 shadow-inner items-center justify-center text-5xl text-ink/20">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php else: ?>
                        <div
                            class="w-32 h-32 rounded-full bg-slate-50 border border-black/5 shadow-inner flex items-center justify-center text-5xl text-ink/20">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <label
                        class="absolute inset-0 bg-ink/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-white">
                        <i class="fas fa-camera text-2xl"></i>
                        <input type="file" name="profile_image" accept="image/*" class="hidden">
                    </label>
                </div>
                <p class="text-xs font-bold uppercase tracking-widest text-ink/50 mt-4">Change Photo</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">First
                        Name</label>
                    <input type="text" name="first_name"
                        value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required
                        class="w-full bg-slate-50 border border-black/5 rounded-2xl px-4 py-3 text-sm text-ink font-medium focus:outline-none focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Last
                        Name</label>
                    <input type="text" name="last_name"
                        value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required
                        class="w-full bg-slate-50 border border-black/5 rounded-2xl px-4 py-3 text-sm text-ink font-medium focus:outline-none focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Email address
                    (Cannot be changed)</label>
                <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled
                    class="w-full bg-slate-100 border border-transparent rounded-2xl px-4 py-3 text-sm text-ink/50 font-medium cursor-not-allowed">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-3">Gender</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="cursor-pointer group flex items-center gap-2">
                            <input type="radio" name="gender" value="male" class="text-deepTeal focus:ring-deepTeal"
                                <?php echo (strtolower($user['gender'] ?? '') == 'male') ? 'checked' : ''; ?>>
                            <span class="text-sm font-medium group-hover:text-deepTeal transition-colors">Male</span>
                        </label>
                        <label class="cursor-pointer group flex items-center gap-2">
                            <input type="radio" name="gender" value="female" class="text-deepTeal focus:ring-deepTeal"
                                <?php echo (strtolower($user['gender'] ?? '') == 'female') ? 'checked' : ''; ?>>
                            <span class="text-sm font-medium group-hover:text-deepTeal transition-colors">Female</span>
                        </label>
                        <label class="cursor-pointer group flex items-center gap-2">
                            <input type="radio" name="gender" value="other" class="text-deepTeal focus:ring-deepTeal"
                                <?php echo (strtolower($user['gender'] ?? '') == 'other') ? 'checked' : ''; ?>>
                            <span class="text-sm font-medium group-hover:text-deepTeal transition-colors">Other</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Date of
                        Birth</label>
                    <input type="date" name="dob" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>" required
                        class="w-full bg-slate-50 border border-black/5 rounded-2xl px-4 py-3 text-sm text-ink font-medium focus:outline-none focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-ink/50 mb-2">Bio / Dining
                    Preferences</label>
                <textarea name="bio" rows="4"
                    class="w-full bg-slate-50 border border-black/5 rounded-2xl px-4 py-3 text-sm text-ink font-medium focus:outline-none focus:ring-2 focus:ring-deepTeal/20 focus:border-deepTeal transition-all resize-none"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>

            <div class="pt-6 border-t border-black/5">
                <button type="submit"
                    class="w-full md:w-auto bg-deepTeal text-white font-bold py-4 px-10 rounded-[20px] hover:bg-tealAccent transition-colors flex items-center justify-center gap-2 mx-auto">
                    Save Changes <i class="fas fa-check text-sm"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>