<?php
require_once '../config.php';
require_once '../includes/mail_helper.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success_msg = '';
$error_msg = '';

// Handle Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action === 'unblock_rejected') {
        $stmt = $conn->prepare("UPDATE restaurants SET status = 'pending' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_msg = "Restaurant moved back to Pending applications.";

            // Send Unblock Email
            $getMgr = $conn->prepare("SELECT m_email, name FROM restaurants r JOIN tbl_manager m ON r.manager_id = m.m_id WHERE r.id = ?");
            $getMgr->bind_param("i", $id);
            $getMgr->execute();
            $mgrData = $getMgr->get_result()->fetch_assoc();
            $getMgr->close();

            if ($mgrData) {
                $subject = "Your Restaurant Application Status Update";
                $body = "<h1>Application Status Update</h1>
                         <p>Your restaurant <strong>" . htmlspecialchars($mgrData['name']) . "</strong> application has been moved back to 'Pending'.</p>
                         <p>The admin team will review it again shortly.</p>";
                sendMail($mgrData['m_email'], $subject, $body);
            }
        } else {
            $error_msg = "Error: " . $conn->error;
        }
        $stmt->close();
    } elseif ($action === 'unblock_published') {
        $stmt = $conn->prepare("UPDATE restaurants SET is_published = 1, is_blocked = 0, block_message = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_msg = "Restaurant successfully unblocked and published.";

            // Send Unblock Email
            $getMgr = $conn->prepare("SELECT m_email, name FROM restaurants r JOIN tbl_manager m ON r.manager_id = m.m_id WHERE r.id = ?");
            $getMgr->bind_param("i", $id);
            $getMgr->execute();
            $mgrData = $getMgr->get_result()->fetch_assoc();
            $getMgr->close();

            if ($mgrData) {
                $subject = "Your Restaurant has been Unblocked";
                $body = "<h1>Good News!</h1>
                         <p>Your restaurant <strong>" . htmlspecialchars($mgrData['name']) . "</strong> has been unblocked and published by the administrator.</p>
                         <p>It is now visible to diners, and you can start receiving bookings again.</p>
                         <p><a href='" . BASE_URL . "login.php'>Login to Dashboard</a></p>";
                sendMail($mgrData['m_email'], $subject, $body);
            }
        } else {
            $error_msg = "Error: " . $conn->error;
        }
        $stmt->close();
    }
}


// Fetch blocked or rejected restaurants
$stmt = $conn->prepare("SELECT r.*, m.m_email as manager_email FROM restaurants r JOIN tbl_manager m ON r.manager_id = m.m_id WHERE r.status = 'rejected' OR r.is_blocked = 1 ORDER BY r.created_at DESC");
$stmt->execute();
$restaurants = $stmt->get_result();
$stmt->close();

require_once '../header.php';
?>

<div class="dash-container"
    style="padding-top: 120px; padding-bottom: 80px; animation: soft-rise 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;">
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-black/5 pb-6">
        <div>
            <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold m-0 p-0"
                style="font-size: 2.25rem; line-height: 2.5rem; color: #0f172a;">Restaurant Status Management</h1>
            <p class="text-ink/60 mt-2 font-medium">Review and manage rejected or blocked restaurant applications.</p>

        </div>
        <a href="dashboard.php"
            class="dash-btn-back font-bold text-sm bg-white border border-black/10 text-ink/70 px-4 py-2 rounded-xl hover:bg-slate-50 transition-colors shadow-sm inline-flex items-center gap-2"
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
            style="color: #e74c3c; background: #fdf2f2; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600;">
            <?php echo $error_msg; ?>
        </p>
    <?php endif; ?>

    <div class="dash-section">
        <div class="dash-table-wrapper">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Restaurant</th>
                        <th>Status</th>
                        <th>Manager</th>
                        <th>Reason / Block Message</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($restaurants->num_rows > 0): ?>
                        <?php while ($row = $restaurants->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #7f8c8d;"><i class="fas fa-location-dot"></i>
                                        <?php echo htmlspecialchars($row['location']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['is_blocked'] == 1): ?>
                                        <div class="status-badge status-blocked">Blocked</div>
                                    <?php elseif ($row['status'] == 'rejected'): ?>
                                        <div class="status-badge status-rejected">Rejected</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem; color: #34495e;"><i class="fas fa-envelope-open-text" style="color: #64748b; margin-right: 0.5rem;"></i>
                                        <?php echo htmlspecialchars($row['manager_email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; color: #1e293b; max-width: 300px;">
                                        <?php if ($row['is_blocked'] == 1): ?>
                                            <i class="fas fa-comment-slash text-red-500 mr-1"></i>
                                            <i>
                                                <?php echo htmlspecialchars($row['block_message'] ? $row['block_message'] : 'No message provided'); ?>
                                            </i>
                                        <?php else: ?>
                                            <i class="fas fa-ban text-gray-400 mr-1"></i> Application was rejected
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <?php if ($row['status'] == 'rejected' && $row['is_blocked'] == 0): ?>
                                        <a href="?action=unblock_rejected&id=<?php echo $row['id']; ?>"
                                            onclick="return openSiteConfirmForLink(event, 'Send this restaurant back to pending applications?')"
                                            class="action-btn dash-btn-view action-btn-fixed"
                                            style="background: #f1f5f9; color: #475569; border-color: #cbd5e1;">
                                            <i class="fas fa-rotate-left"></i> Unblock App
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($row['is_blocked'] == 1): ?>
                                        <a href="?action=unblock_published&id=<?php echo $row['id']; ?>"
                                            onclick="return openSiteConfirmForLink(event, 'This will unblock and immediately publish this restaurant. Are you sure?')"
                                            class="action-btn dash-btn-approve action-btn-fixed">
                                            <i class="fas fa-unlock"></i> Unblock & Publish
                                        </a>
                                    <?php endif; ?>

                                    <a href="view_restaurant.php?id=<?php echo $row['id']; ?>" target="_blank"
                                        class="action-btn dash-btn-view action-btn-fixed">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 4rem; text-align: center; color: #95a5a6;">
                                <i class="fas fa-shield-halved"
                                    style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                                No blocked or rejected restaurants.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>