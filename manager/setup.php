<?php
require_once '../config.php';

if (!isLoggedIn() || !isManager()) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Fetch restaurant details
$stmt = $conn->prepare("SELECT * FROM restaurants WHERE manager_id = ?");
$stmt->bind_param("i", $user_id);
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

// Ensure menu items table has image column
$colcheck_menu_img = $conn->query("SHOW COLUMNS FROM restaurant_menu LIKE 'item_image'");
if ($colcheck_menu_img && $colcheck_menu_img->num_rows == 0) {
    $conn->query("ALTER TABLE restaurant_menu ADD COLUMN item_image VARCHAR(255) DEFAULT NULL AFTER item_discount");
}

// Fetch menu items
$stmt = $conn->prepare("SELECT * FROM restaurant_menu WHERE restaurant_id = ?");
$stmt->bind_param("i", $restaurant['id']);
$stmt->execute();
$menu_items = $stmt->get_result();
$stmt->close();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $seating = $_POST['seating_type'];
    $price = $_POST['avg_price'];
    $max_guests = $_POST['max_guests'];
    $desc = $_POST['description'];
    $published = isset($_POST['is_published']) ? 1 : 0;

    // Force unpublish if blocked by admin
    if ($restaurant['is_blocked'] == 1) {
        $published = 0;
    }

    // Update restaurant
    $stmt = $conn->prepare("UPDATE restaurants SET phone = ?, email = ?, seating_type = ?, avg_price = ?, max_guests = ?, description = ?, is_published = ? WHERE id = ?");
    $stmt->bind_param("sssdissi", $phone, $email, $seating, $price, $max_guests, $desc, $published, $restaurant['id']);

    if ($stmt->execute()) {
        // Handle schedule (closed days)
        $conn->query("DELETE FROM restaurant_schedule WHERE restaurant_id = " . $restaurant['id']);
        if (isset($_POST['closed_days'])) {
            $stmt_sched = $conn->prepare("INSERT INTO restaurant_schedule (restaurant_id, day_of_week, is_closed) VALUES (?, ?, 1)");
            if (!$stmt_sched) {
                error_log("Prepare failed for schedule: " . $conn->error);
            } else {
                foreach ($_POST['closed_days'] as $day) {
                    $stmt_sched->bind_param("is", $restaurant['id'], $day);
                    if (!$stmt_sched->execute()) {
                        error_log("Execute failed for schedule insert: " . $stmt_sched->error);
                    }
                }
                $stmt_sched->close();
            }
        }



        // Handle Primary Image Upload
        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] == 0) {
            $target_dir = "../uploads/restaurants/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $image_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["primary_image"]["name"]));
            $target_file = $target_dir . $image_name;
            $db_path = "uploads/restaurants/" . $image_name;

            if (move_uploaded_file($_FILES["primary_image"]["tmp_name"], $target_file)) {
                $stmt_img = $conn->prepare("UPDATE restaurants SET primary_image = ? WHERE id = ?");
                $stmt_img->bind_param("si", $db_path, $restaurant['id']);
                $stmt_img->execute();
                $stmt_img->close();
            }
        }

        // Handle Menu File Upload
        if (isset($_FILES['menu_file']) && $_FILES['menu_file']['error'] == 0) {
            $target_dir = "../uploads/menus/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $menu_name = time() . "_menu_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["menu_file"]["name"]));
            $target_file = $target_dir . $menu_name;
            $db_path = "uploads/menus/" . $menu_name;

            if (move_uploaded_file($_FILES["menu_file"]["tmp_name"], $target_file)) {
                $stmt_menu_file = $conn->prepare("UPDATE restaurants SET menu_file = ? WHERE id = ?");
                $stmt_menu_file->bind_param("si", $db_path, $restaurant['id']);
                $stmt_menu_file->execute();
                $stmt_menu_file->close();
            }
        }

        // Handle Manual Menu Items (with optional images)
        if (isset($_POST['menu_item_name'])) {
            // First clear old items
            $conn->query("DELETE FROM restaurant_menu WHERE restaurant_id = " . $restaurant['id']);

            $stmt_item = $conn->prepare("INSERT INTO restaurant_menu (restaurant_id, item_name, item_price, item_discount, item_image) VALUES (?, ?, ?, ?, ?)");
            $names = $_POST['menu_item_name'];
            $prices = $_POST['menu_item_price'];
            $discounts = $_POST['menu_item_discount'];
            $existingImages = $_POST['menu_item_existing_image'] ?? [];
            $uploadedImages = $_FILES['menu_item_image'] ?? null;

            // Ensure upload directory exists
            $item_upload_dir = "../uploads/menu_items/";
            if (!file_exists($item_upload_dir)) {
                mkdir($item_upload_dir, 0777, true);
            }

            for ($i = 0; $i < count($names); $i++) {
                if (!empty(trim($names[$i])) && !empty(trim($prices[$i]))) {
                    $n = trim($names[$i]);
                    $p = (float) $prices[$i];
                    $d = empty($discounts[$i]) ? 0.00 : (float) $discounts[$i];

                    // Start with existing image path if present
                    $imgPath = $existingImages[$i] ?? '';

                    // If a new file is uploaded for this row, override
                    if ($uploadedImages && isset($uploadedImages['error'][$i]) && $uploadedImages['error'][$i] === 0) {
                        $safeName = time() . '_' . $i . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($uploadedImages['name'][$i]));
                        $targetFile = $item_upload_dir . $safeName;
                        $dbPath = "uploads/menu_items/" . $safeName;

                        if (move_uploaded_file($uploadedImages['tmp_name'][$i], $targetFile)) {
                            $imgPath = $dbPath;
                        }
                    }

                    $stmt_item->bind_param("isdds", $restaurant['id'], $n, $p, $d, $imgPath);
                    $stmt_item->execute();
                }
            }
            $stmt_item->close();
        }

        // Refresh data
        header("Location: setup.php?success=1");
        exit;
    } else {
        $error_msg = "Error updating details.";
    }
}



if (isset($_GET['success'])) {
    if ($_GET['success'] == '1')
        $success_msg = "Restaurant details updated successfully!";
}



require_once '../header.php';
?>

<div class="dash-container" style="padding-top: 120px;">
    <div class="dash-header" style="margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div>
                <h1>Restaurant Profile</h1>
                <p style="color: #7f8c8d;">View and configure your public restaurant page</p>
            </div>
            <div>
                <a href="dashboard.php" class="dash-btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>
    </div>

    <?php if ($restaurant['is_blocked'] == 1): ?>
        <div
            style="background: #ffecec; border-left: 5px solid #e74c3c; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-ban" style="color: #e74c3c; font-size: 2rem;"></i>
                <div>
                    <h3 style="margin: 0; color: #c0392b; font-size: 1.2rem;">Account Notice</h3>
                    <p style="margin: 0.3rem 0 0 0; color: #8a0000; font-weight: 500;">
                        Your restaurant has been blocked by an administrator. You can still use QuickTable to make
                        bookings as a diner using the booking button in the navigation bar.
                    </p>
                    <?php if (!empty($restaurant['block_message'])): ?>
                        <p style="margin: 0.4rem 0 0 0; color: #8a0000; font-size: 0.9rem;">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($restaurant['block_message']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php elseif (!$restaurant['is_published']): ?>
        <div
            style="background: #fff8e1; border-left: 5px solid #ffb300; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-exclamation-triangle" style="color: #ffb300; font-size: 2rem;"></i>
                <div>
                    <h3 style="margin: 0; color: #b78100; font-size: 1.2rem;">Action Required</h3>
                    <p style="margin: 0.3rem 0 0 0; color: #8a6300; font-weight: 500;">Publish your restaurant to view the
                        Dashboard and start receiving bookings.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <p
            style="color: #27ae60; background: #e8f5e9; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 600; text-align: center;">
            <i class="fas fa-circle-check"></i> <?php echo $success_msg; ?>
        </p>
    <?php endif; ?>

    <!-- VIEW MODE -->
    <div id="view-mode" class="dash-section"
        style="max-width: 900px; margin: 0 auto; <?php echo isset($_GET['edit']) ? 'display: none;' : 'display: block;'; ?>">

        <!-- Premium Image Display -->
        <div
            style="position: relative; width: 100%; height: 350px; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 2rem; background: #f8f9fb; display: flex; align-items: center; justify-content: center;">
            <?php if (!empty($restaurant['primary_image'])): ?>
                <img src="../<?php echo htmlspecialchars($restaurant['primary_image']); ?>"
                    style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <i class="fas fa-image" style="font-size: 5rem; color: #bdc3c7;"></i>
            <?php endif; ?>
            <div style="position: absolute; top: 1.5rem; right: 1.5rem;">
                <span class="status-badge"
                    style="background: <?php echo $restaurant['is_published'] ? '#27ae60' : '#bdc3c7'; ?>; box-shadow: 0 4px 10px rgba(0,0,0,0.2); font-size: 1rem; padding: 0.5rem 1rem;">
                    <i class="fas <?php echo $restaurant['is_published'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                    <?php echo $restaurant['is_published'] ? 'Published' : 'Hidden'; ?>
                </span>
            </div>
        </div>

        <!-- Details Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h1
                    style="font-family: 'Outfit', sans-serif; color: #1e293b; font-size: 2.2rem; margin: 0 0 1rem 0; font-weight: 800;">
                    <?php echo htmlspecialchars($restaurant['name']); ?>
                </h1>

                <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <span
                        style="background: #f1f5f9; color: #475569; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 600; font-size: 0.9rem;">
                        <i class="fas fa-utensils" style="color: var(--primary-color);"></i>
                        <?php echo htmlspecialchars($restaurant['cuisine']); ?>
                    </span>
                    <span
                        style="background: #f1f5f9; color: #475569; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 600; font-size: 0.9rem;">
                        <i class="fas fa-wallet" style="color: #27ae60;"></i>
                        ₹<?php echo htmlspecialchars($restaurant['avg_price']); ?> avg
                    </span>
                    <span
                        style="background: #f1f5f9; color: #475569; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 600; font-size: 0.9rem;">
                        <i class="fas fa-users" style="color: #e67e22;"></i> Max
                        <?php echo htmlspecialchars($restaurant['max_guests'] ?? 20); ?>
                    </span>
                </div>

                <div style="color: #64748b; line-height: 1.7; font-size: 1.05rem; margin-bottom: 2rem;">
                    <?php echo nl2br(htmlspecialchars($restaurant['description'] ?? 'No description provided yet. Click edit to add one.')); ?>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 12px;">
                    <h3 style="margin: 0 0 1rem 0; color: #1e293b; font-size: 1.1rem;"><i class="fas fa-address-card"
                            style="color: #3b82f6;"></i> Contact Info</h3>
                    <div style="display: grid; gap: 0.8rem; color: #475569;">
                        <div><i class="fas fa-location-dot" style="width: 25px; color: #94a3b8;"></i>
                            <?php echo htmlspecialchars($restaurant['location']); ?></div>
                        <div><i class="fas fa-phone" style="width: 25px; color: #94a3b8;"></i>
                            <?php echo htmlspecialchars($restaurant['phone']); ?></div>
                        <div><i class="fas fa-envelope" style="width: 25px; color: #94a3b8;"></i>
                            <?php echo htmlspecialchars($restaurant['email']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div
                    style="background: #fff; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <h3 style="margin: 0 0 1rem 0; color: #1e293b; font-size: 1.1rem;"><i class="fas fa-chair"
                            style="color: #8b5cf6;"></i> Seating</h3>
                    <div style="color: #475569; font-weight: 500; text-transform: capitalize;">
                        <?php echo htmlspecialchars($restaurant['seating_type'] === 'both' ? 'Inside and Outside' : ($restaurant['seating_type'] ?? 'Not set')); ?>
                    </div>
                </div>

                <div
                    style="background: #fff; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <h3 style="margin: 0 0 1rem 0; color: #1e293b; font-size: 1.1rem;"><i class="fas fa-calendar-xmark"
                            style="color: #ef4444;"></i> Closed Days</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php
                        if (empty($closed_days)) {
                            echo "<span style='color: #94a3b8; font-size: 0.9rem;'>Open 7 days a week</span>";
                        } else {
                            foreach ($closed_days as $cd) {
                                echo "<span style='background: #fee2e2; color: #ef4444; padding: 0.3rem 0.8rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600; text-transform: capitalize;'>" . $cd . "</span>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Section View -->
        <div style="margin-top: 3rem; border-top: 2px solid #f1f5f9; padding-top: 2rem;">
            <h2
                style="font-family: 'Outfit', sans-serif; color: #1e293b; font-size: 1.8rem; margin: 0 0 1.5rem 0; display: flex; align-items: center; justify-content: space-between;">
                <span><i class="fas fa-book-open" style="color: #f59e0b; margin-right: 0.5rem;"></i> Restaurant
                    Menu</span>
                <?php if (!empty($restaurant['menu_file'])): ?>
                    <a href="../<?php echo htmlspecialchars($restaurant['menu_file']); ?>" target="_blank"
                        class="action-btn"
                        style="background: #f8fafc; color: #3b82f6; border: 1px solid #bfdbfe; font-size: 0.9rem; padding: 0.6rem 1.2rem;">
                        <i class="fas fa-file-pdf"></i> View Menu PDF/Image
                    </a>
                <?php endif; ?>
            </h2>

            <?php if ($menu_items->num_rows > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php while ($item = $menu_items->fetch_assoc()): ?>
                        <div
                            style="background: #fff; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; gap: 1rem; align-items: center;">
                            <?php if (!empty($item['item_image'])): ?>
                                <div style="flex: 0 0 72px;">
                                    <img src="../<?php echo htmlspecialchars($item['item_image']); ?>"
                                        style="width: 72px; height: 72px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0;">
                                </div>
                            <?php endif; ?>
                            <div style="flex: 1; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0 0 0.3rem 0; font-size: 1.1rem; color: #1e293b;">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </h4>
                                    <?php if ($item['item_discount'] > 0): ?>
                                        <span
                                            style="background: #dcfce7; color: #166534; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                                            <?php echo floatval($item['item_discount']); ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right; margin-left: 0.75rem;">
                                    <?php if ($item['item_discount'] > 0): ?>
                                        <div style="text-decoration: line-through; color: #94a3b8; font-size: 0.9rem;">
                                            ₹<?php echo $item['item_price']; ?></div>
                                        <div style="font-weight: 800; color: var(--primary-color); font-size: 1.2rem;">
                                            ₹<?php echo number_format($item['item_price'] - ($item['item_price'] * ($item['item_discount'] / 100)), 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-weight: 800; color: var(--primary-color); font-size: 1.2rem;">
                                            ₹<?php echo $item['item_price']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php $menu_items->data_seek(0); // Reset pointer for edit mode ?>
                </div>
            <?php else: ?>
                <div
                    style="text-align: center; padding: 3rem; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1;">
                    <i class="fas fa-utensils" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <p style="color: #64748b; margin: 0;">No menu items added yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 3rem; text-align: center;">
            <button
                onclick="document.getElementById('view-mode').style.display='none'; document.getElementById('edit-mode').style.display='block';"
                class="action-btn dash-btn-approve" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-edit"></i> Edit Profile & Menu
            </button>
        </div>
    </div>

    <!-- EDIT MODE -->
    <form method="POST" enctype="multipart/form-data" id="edit-mode" class="dash-section"
        style="max-width: 900px; margin: 0 auto; <?php echo isset($_GET['edit']) ? 'display: block;' : 'display: none;'; ?>">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="margin: 0; color: #1e293b;"><i class="fas fa-pen-to-square"></i> Edit Mode</h2>
            <button type="button"
                onclick="document.getElementById('edit-mode').style.display='none'; document.getElementById('view-mode').style.display='block';"
                class="action-btn" style="background: #f1f5f9; color: #475569;">
                Cancel Editing
            </button>
        </div>

        <!-- Publish Toggle -->
        <div style="margin-bottom: 2.5rem; background: #fff; padding: 1.5rem 2rem; border-radius: 12px; border: 2px solid <?php echo $restaurant['is_published'] ? '#27ae60' : '#bdc3c7'; ?>; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05);"
            id="publish-container">
            <div>
                <h3 style="margin: 0 0 0.4rem 0; color: #2c3e50; font-size: 1.3rem;"><i class="fas fa-globe"
                        style="color: #3498db;"></i> Restaurant Visibility</h3>
                <p style="margin: 0; color: #7f8c8d; font-size: 0.95rem;">Publish your restaurant to make it visible to
                    diners and accept bookings.</p>
            </div>
            <label class="publish-btn"
                style="cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 1rem 2.5rem; border-radius: 50px; background: <?php echo $restaurant['is_published'] ? '#27ae60' : '#95a5a6'; ?>; color: white; font-weight: bold; font-size: 1.1rem; transition: background 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"
                id="publish-label">
                <input type="checkbox" name="is_published" id="publish-checkbox" <?php echo $restaurant['is_published'] ? 'checked' : ''; ?> style="display: none;">
                <i class="fas <?php echo $restaurant['is_published'] ? 'fa-eye' : 'fa-eye-slash'; ?>" id="publish-icon"
                    style="margin-right: 0.8rem;"></i>
                <span
                    id="publish-text"><?php echo $restaurant['is_published'] ? 'Published (Visible)' : 'Unpublished (Hidden)'; ?></span>
            </label>
        </div>

        <script>
            document.getElementById('publish-checkbox').addEventListener('click', function (e) {
                // Check if blocked by admin
                const isBlocked = <?php echo $restaurant['is_blocked'] ? 'true' : 'false'; ?>;
                const blockMessage = <?php echo json_encode($restaurant['block_message'] ?: 'Your restaurant has been blocked by an administrator.'); ?>;

                if (isBlocked && this.checked) {
                    e.preventDefault();
                    openSiteAlert("⛔ Blocked by Admin\n\nReason: " + blockMessage + "\n\nYou cannot publish your restaurant at this time.");
                    return;
                }
            });

            document.getElementById('publish-checkbox').addEventListener('change', function () {
                const label = document.getElementById('publish-label');
                const icon = document.getElementById('publish-icon');
                const text = document.getElementById('publish-text');
                const container = document.getElementById('publish-container');

                if (this.checked) {
                    label.style.background = '#27ae60';
                    icon.className = 'fas fa-eye';
                    text.innerText = 'Published (Visible)';
                    container.style.borderColor = '#27ae60';
                } else {
                    label.style.background = '#95a5a6';
                    icon.className = 'fas fa-eye-slash';
                    text.innerText = 'Unpublished (Hidden)';
                    container.style.borderColor = '#bdc3c7';
                }
            });
        </script>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <!-- Basic Info -->
            <div class="setup-group">
                <h3
                    style="margin-bottom: 1.5rem; border-bottom: 2px solid #f1f2f6; padding-bottom: 0.5rem; color: var(--text-dark);">
                    <i class="fas fa-info-circle" style="color: var(--accent-color);"></i> Core Information
                </h3>

                <div class="form-group">
                    <label>Restaurant Name (Locked)</label>
                    <input type="text" value="<?php echo htmlspecialchars($restaurant['name']); ?>" disabled
                        style="background: #f8f9fb; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label>Location (Locked)</label>
                    <input type="text" value="<?php echo htmlspecialchars($restaurant['location']); ?>" disabled
                        style="background: #f8f9fb; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label>Cuisine Type</label>
                    <input type="text" value="<?php echo htmlspecialchars($restaurant['cuisine']); ?>" disabled
                        style="background: #f8f9fb; cursor: not-allowed;">
                </div>
            </div>

            <!-- Contact & Pricing -->
            <div class="setup-group">
                <h3
                    style="margin-bottom: 1.5rem; border-bottom: 2px solid #f1f2f6; padding-bottom: 0.5rem; color: var(--text-dark);">
                    <i class="fas fa-phone" style="color: var(--primary-color);"></i> Business Details
                </h3>

                <div class="form-group">
                    <label>Public Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($restaurant['phone']); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label>Public Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($restaurant['email']); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label>Average Price per Person (₹)</label>
                    <input type="number" name="avg_price"
                        value="<?php echo htmlspecialchars($restaurant['avg_price']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Maximum Guests per Booking</label>
                    <input type="number" name="max_guests"
                        value="<?php echo htmlspecialchars($restaurant['max_guests'] ?? 20); ?>" min="1" required>
                </div>
            </div>
        </div>

        <div
            style="margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <!-- Settings -->
            <div class="setup-group">
                <h3
                    style="margin-bottom: 1.5rem; border-bottom: 2px solid #f1f2f6; padding-bottom: 0.5rem; color: var(--text-dark);">
                    <i class="fas fa-sliders" style="color: #e67e22;"></i> Preferences
                </h3>

                <div class="form-group">
                    <label>Seating Options</label>
                    <select name="seating_type"
                        style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #ddd; outline: none;">
                        <option value="inside" <?php echo $restaurant['seating_type'] == 'inside' ? 'selected' : ''; ?>>
                            Inside Only</option>
                        <option value="outside" <?php echo $restaurant['seating_type'] == 'outside' ? 'selected' : ''; ?>>
                            Outside Only</option>
                        <option value="both" <?php echo $restaurant['seating_type'] == 'both' ? 'selected' : ''; ?>>Both
                            (Inside & Outside)</option>
                    </select>
                </div>

            </div>

            <!-- Operating Days -->
            <div class="setup-group">
                <h3
                    style="margin-bottom: 1.5rem; border-bottom: 2px solid #f1f2f6; padding-bottom: 0.5rem; color: var(--text-dark);">
                    <i class="fas fa-calendar-xmark" style="color: #e74c3c;"></i> Closed Days
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem;">
                    <?php
                    $days = [
                        'sun' => 'Sunday',
                        'mon' => 'Monday',
                        'tue' => 'Tuesday',
                        'wed' => 'Wednesday',
                        'thu' => 'Thursday',
                        'fri' => 'Friday',
                        'sat' => 'Saturday'
                    ];
                    foreach ($days as $val => $label) {
                        $is_checked = in_array($val, $closed_days) ? 'checked' : '';
                        echo "
                        <label style='display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer;'>
                            <input type='checkbox' name='closed_days[]' value='$val' $is_checked> $label
                        </label>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 2rem;">
            <label>Restaurant Description</label>
            <textarea name="description" rows="5"
                style="width: 100%; padding: 1rem; border-radius: 8px; border: 1px solid #ddd; outline: none; font-family: inherit; resize: vertical;"><?php echo htmlspecialchars($restaurant['description']); ?></textarea>
        </div>

        <div class="form-group" style="margin-top: 2rem;">
            <label>Update Primary Restaurant Image</label>
            <?php if (!empty($restaurant['primary_image'])): ?>
                <div style="margin-bottom: 1rem;">
                    <img src="../<?php echo htmlspecialchars($restaurant['primary_image']); ?>"
                        style="max-width: 300px; border-radius: 8px; border: 2px solid #ddd; display: block;">
                </div>
            <?php endif; ?>
            <input type="file" name="primary_image" accept="image/*"
                style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 6px; background: #fff;">
        </div>



        <!-- MENU EDITOR -->
        <div style="margin-top: 3rem; border-top: 2px solid #f1f5f9; padding-top: 2.5rem;">
            <h3 style="margin-bottom: 1.5rem; color: #1e293b; font-size: 1.4rem;">
                <i class="fas fa-book-open" style="color: #f59e0b;"></i> Menu Management
            </h3>

            <!-- File Upload -->
            <div class="setup-group" style="margin-bottom: 2rem;">
                <label style="font-weight: 600; color: #475569; display: block; margin-bottom: 0.5rem;">Menu File (PDF
                    or
                    Image)</label>
                <?php if (!empty($restaurant['menu_file'])): ?>
                    <div
                        style="margin-bottom: 1rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: inline-block;">
                        <i class="fas fa-file-check" style="color: #27ae60;"></i> Current file: <a
                            href="../<?php echo htmlspecialchars($restaurant['menu_file']); ?>" target="_blank"
                            style="color: #3b82f6; font-weight: bold;">View File</a>
                    </div>
                <?php endif; ?>
                <input type="file" name="menu_file" accept=".pdf,image/*"
                    style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 6px; background: #fff;">
                <small style="color: #94a3b8; display: block; margin-top: 0.5rem;">Upload a scanned image or PDF of your
                    menu for diners to view.</small>
            </div>

            <!-- Dynamic Menu Items -->
            <div class="setup-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <label style="font-weight: 600; color: #475569; margin: 0;">Individual Menu Items</label>
                    <button type="button" onclick="addMenuItem()" class="action-btn dash-btn-view"
                        style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                        <i class="fas fa-plus"></i> Add Item Row
                    </button>
                </div>

                <div id="menu-items-container">
                    <?php if ($menu_items->num_rows > 0): ?>
                        <?php while ($item = $menu_items->fetch_assoc()): ?>
                            <div class="menu-item-row"
                                style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <div style="flex: 2;">
                                    <input type="text" name="menu_item_name[]"
                                        value="<?php echo htmlspecialchars($item['item_name']); ?>"
                                        placeholder="Item Name (e.g. Margherita Pizza)" required
                                        style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div style="flex: 1;">
                                    <input type="number" name="menu_item_price[]"
                                        value="<?php echo htmlspecialchars($item['item_price']); ?>" placeholder="Price (₹)"
                                        required step="0.01"
                                        style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div style="flex: 1;">
                                    <input type="number" name="menu_item_discount[]"
                                        value="<?php echo htmlspecialchars($item['item_discount']); ?>"
                                        placeholder="Discount % (Optional)" step="0.01"
                                        style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div style="flex: 1;">
                                    <?php if (!empty($item['item_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($item['item_image']); ?>"
                                            style="max-width: 80px; max-height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; display: block; margin-bottom: 0.4rem;">
                                    <?php endif; ?>
                                    <input type="file" name="menu_item_image[]" accept="image/*"
                                        style="width: 100%; padding: 0.3rem; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                                    <input type="hidden" name="menu_item_existing_image[]"
                                        value="<?php echo htmlspecialchars($item['item_image'] ?? ''); ?>">
                                </div>
                                <button type="button" onclick="this.parentElement.remove()"
                                    style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0.5rem; font-size: 1.1rem;"><i
                                        class="fas fa-trash"></i></button>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <!-- Blank Initial Row -->
                        <div class="menu-item-row"
                            style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="flex: 2;">
                                <input type="text" name="menu_item_name[]" placeholder="Item Name (e.g. Margherita Pizza)"
                                    style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div style="flex: 1;">
                                <input type="number" name="menu_item_price[]" placeholder="Price (₹)" step="0.01"
                                    style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div style="flex: 1;">
                                <input type="number" name="menu_item_discount[]" placeholder="Discount %" step="0.01"
                                    style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div style="flex: 1;">
                                <input type="file" name="menu_item_image[]" accept="image/*"
                                    style="width: 100%; padding: 0.3rem; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                                <input type="hidden" name="menu_item_existing_image[]" value="">
                            </div>
                            <button type="button" onclick="this.parentElement.remove()"
                                style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0.5rem; font-size: 1.1rem;"><i
                                    class="fas fa-trash"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            function addMenuItem() {
                const container = document.getElementById('menu-items-container');
                const row = document.createElement('div');
                row.className = 'menu-item-row';
                row.style.cssText = 'display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; animation: fadeIn 0.3s ease;';
                row.innerHTML = `
                <div style="flex: 2;">
                    <input type="text" name="menu_item_name[]" placeholder="Item Name" required style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <input type="number" name="menu_item_price[]" placeholder="Price (₹)" required step="0.01" style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <input type="number" name="menu_item_discount[]" placeholder="Discount %" step="0.01" style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <input type="file" name="menu_item_image[]" accept="image/*" style="width: 100%; padding: 0.3rem; border: 1px solid #ddd; border-radius: 4px; background:#fff;">
                    <input type="hidden" name="menu_item_existing_image[]" value="">
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0.5rem; font-size: 1.1rem;"><i class="fas fa-trash"></i></button>
            `;
                container.appendChild(row);
            }
        </script>

        <button type="submit" class="action-btn dash-btn-approve"
            style="padding: 1.2rem 5rem; font-size: 1.1rem; box-shadow: 0 10px 25px rgba(39, 174, 96, 0.2);">
            <i class="fas fa-save"></i> Update Restaurant Profile
        </button>
</div>
</form>
</div>

<?php require_once '../footer.php'; ?>