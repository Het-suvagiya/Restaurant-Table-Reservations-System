<?php
require_once '../config.php';
require_once '../includes/mail_helper.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success_msg = '';
$error_msg = '';

// Handle Approval/Rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action === 'approve') {
        // Start transaction
        $conn->begin_transaction();

        try {
            // 1. Get manager_id
            $stmt = $conn->prepare("SELECT manager_id FROM restaurants WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $manager_id = $stmt->get_result()->fetch_assoc()['manager_id'];
            $stmt->close();

            // 2. Update Restaurant Status (DO NOT AUTO-PUBLISH)
            $stmt = $conn->prepare("UPDATE restaurants SET status = 'approved', is_published = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // 3. Ensure a manager record exists (registration side already creates one for FK)
            $chkMgr = $conn->prepare("SELECT COUNT(*) as cnt FROM tbl_manager WHERE m_id = ?");
            $chkMgr->bind_param("i", $manager_id);
            $chkMgr->execute();
            $cnt = $chkMgr->get_result()->fetch_assoc()['cnt'];
            $chkMgr->close();

            if ($cnt == 0) {
                // promote by copying from tbl_users and deleting the user row
                $chk = $conn->prepare("SELECT u_firstname,u_lastname,u_email,u_password,u_phone,u_gender,u_dob,u_image,u_bio,google_id FROM tbl_users WHERE u_id = ?");
                $chk->bind_param("i", $manager_id);
                $chk->execute();
                $userRow = $chk->get_result()->fetch_assoc();
                $chk->close();

                if ($userRow) {
                    $insert = $conn->prepare("INSERT INTO tbl_manager (m_id,m_firstname,m_lastname,m_email,m_password,m_phone,m_gender,m_dob,m_image,m_bio,google_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("issssssssss", $manager_id, $userRow['u_firstname'], $userRow['u_lastname'], $userRow['u_email'], $userRow['u_password'], $userRow['u_phone'], $userRow['u_gender'], $userRow['u_dob'], $userRow['u_image'], $userRow['u_bio'], $userRow['google_id']);
                    $insert->execute();
                    $insert->close();

                    // remove original user record if it still exists
                    $del = $conn->prepare("DELETE FROM tbl_users WHERE u_id = ?");
                    $del->bind_param("i", $manager_id);
                    $del->execute();
                    $del->close();
                }
            } else {
                // already present, just clean up any leftover user row
                $del = $conn->prepare("DELETE FROM tbl_users WHERE u_id = ?");
                $del->bind_param("i", $manager_id);
                $del->execute();
                $del->close();
            }

            $conn->commit();
            $success_msg = "Application approved! User promoted to Manager.";

            // Send Approval Email
            $subject = "Your Restaurant Application has been Approved!";
            $body = "<h1>Congratulations!</h1><p>Your restaurant has been approved by the admin. You can now login to your manager dashboard and start managing your restaurant.</p><p><a href='" . BASE_URL . "login.php'>Login Here</a></p>";
            // We need to fetch the email again or use the one we have if it's available
            // In the approval logic, we can fetch it during step 1
            $getUser = $conn->prepare("SELECT m_email FROM tbl_manager WHERE m_id = ?");
            $getUser->bind_param("i", $manager_id);
            $getUser->execute();
            $userEmail = $getUser->get_result()->fetch_assoc()['m_email'];
            $getUser->close();
            
            if ($userEmail) {
                sendMail($userEmail, $subject, $body);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE restaurants SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_msg = "Application rejected.";

            // Send Rejection Email
            // Fetch manager email
            $getMgr = $conn->prepare("SELECT m_email FROM tbl_manager WHERE m_id = (SELECT manager_id FROM restaurants WHERE id = ?)");
            $getMgr->bind_param("i", $id);
            $getMgr->execute();
            $mgrEmail = $getMgr->get_result()->fetch_assoc()['m_email'];
            $getMgr->close();

            if ($mgrEmail) {
                $subject = "Restaurant Application Update";
                $body = "<h1>Application Status Update</h1><p>We regret to inform you that your restaurant application has been rejected by the admin at this time.</p>";
                sendMail($mgrEmail, $subject, $body);
            }
        } else {
            $error_msg = "Error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch pending applications
$stmt = $conn->prepare("SELECT r.*, m.m_email as email, m.m_firstname as first_name, m.m_lastname as last_name, m.m_image as profile_image FROM restaurants r JOIN tbl_manager m ON r.manager_id = m.m_id WHERE r.status = 'pending' ORDER BY r.created_at DESC");
$stmt->execute();
$applications = $stmt->get_result();
$stmt->close();

require_once '../header.php';
?>

<div class="dash-container"
    style="padding-top: 120px; padding-bottom: 80px; animation: soft-rise 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-black/5 pb-6">
        <div>
            <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold m-0 p-0"
                style="font-size: 2.25rem; line-height: 2.5rem; color: #0f172a;">Restaurant Applications</h1>
            <p class="text-ink/60 mt-2 text-sm md:text-base">Pending Approval - Review and manage new restaurant applications</p>
        </div>
        <a href="dashboard.php"
            class="dash-btn-back font-bold text-sm bg-white border border-black/10 text-ink/70 px-4 py-2 rounded-xl hover:bg-slate-50 transition-colors shadow-sm inline-flex items-center gap-2 whitespace-nowrap"
            style="text-decoration: none;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if ($success_msg): ?>
        <p
            style="color: #27ae60; background: #e8f5e9; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600;">
            <?php echo $success_msg; ?>
        </p>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <p
            style="color: #c0392b; background: #fdecea; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600;">
            <?php echo $error_msg; ?>
        </p>
    <?php endif; ?>

    <div class="dash-section">
        <div class="dash-table-wrapper">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Restaurant</th>
                        <th>Applicant</th>
                        <th>Cuisine</th>
                        <th>Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($applications->num_rows > 0): ?>
                        <?php while ($row = $applications->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #7f8c8d;"><i class="fas fa-location-dot"></i>
                                        <?php echo htmlspecialchars($row['location']); ?></div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <?php if (!empty($row['profile_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($row['profile_image']); ?>" class="user-avatar"
                                                style="object-fit: cover; border: 2px solid #ddd;">
                                        <?php else: ?>
                                            <div class="user-avatar"><?php echo strtoupper(substr($row['first_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 600;">
                                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #7f8c8d;">
                                                <?php echo htmlspecialchars($row['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="tag-badge"
                                        style="background: #f1f2f6; color: #34495e;"><?php echo htmlspecialchars($row['cuisine']); ?></span>
                                </td>

                                <td style="color: #7f8c8d; font-size: 0.9rem;">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <a href="?action=approve&id=<?php echo $row['id']; ?>"
                                            onclick="return openSiteConfirmForLink(event, 'Approve this restaurant?')"
                                            class="action-btn dash-btn-approve">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="?action=reject&id=<?php echo $row['id']; ?>"
                                            onclick="return openSiteConfirmForLink(event, 'Reject this application?')"
                                            class="action-btn dash-btn-reject">
                                            <i class="fas fa-xmark"></i> Reject
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 4rem; text-align: center; color: #95a5a6;">
                                <i class="fas fa-inbox"
                                    style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                                No pending applications found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>