<?php
require_once '../config.php';
require_once '../includes/mail_helper.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success_msg = '';
$error_msg = '';


// Handle Block
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'block') {
    $id = $_POST['id'];
    $message = trim($_POST['block_message']);

    $stmt = $conn->prepare("UPDATE restaurants SET is_published = 0, is_blocked = 1, block_message = ? WHERE id = ?");
    $stmt->bind_param("si", $message, $id);
    if ($stmt->execute()) {
        $success_msg = "Restaurant has been blocked and unpublished.";

        // Send Block Email
        $getMgr = $conn->prepare("SELECT m_email, name FROM restaurants r JOIN tbl_manager m ON r.manager_id = m.m_id WHERE r.id = ?");
        $getMgr->bind_param("i", $id);
        $getMgr->execute();
        $mgrData = $getMgr->get_result()->fetch_assoc();
        $getMgr->close();

        if ($mgrData) {
            $subject = "Important Notice: Your Restaurant has been Blocked";
            $body = "<h1>Restaurant Account Blocked</h1>
                     <p>We are writing to inform you that your restaurant <strong>" . htmlspecialchars($mgrData['name']) . "</strong> has been blocked by the administrator.</p>
                     <p><strong>Reason:</strong> " . htmlspecialchars($message) . "</p>
                     <p>Your restaurant is currently unpublished and will not be visible to diners. You can still use your account for personal bookings.</p>";
            sendMail($mgrData['m_email'], $subject, $body);
        }
    } else {
        $error_msg = "Error blocking restaurant: " . $conn->error;
    }
    $stmt->close();
}


// Fetch all restaurants
$stmt = $conn->prepare("SELECT r.*, m.m_email as manager_email FROM restaurants r JOIN tbl_manager m ON r.manager_id = m.m_id ORDER BY r.created_at DESC");
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
                style="font-size: 2.25rem; line-height: 2.5rem; color: #0f172a;">Manage Restaurants</h1>
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

    <div class="dash-section">
        <div class="dash-table-wrapper">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Restaurant</th>
                        <th>Manager</th>
                        <th>Status</th>
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
                                        <?php echo htmlspecialchars($row['location']); ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem; color: #34495e;"><i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($row['manager_email']); ?></div>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <?php if ($row['status'] == 'approved'): ?>
                                            <div class="status-badge status-approved">Approved</div>
                                            <?php if ($row['is_blocked'] == 1): ?>
                                                <div class="status-badge status-blocked">Blocked</div>
                                            <?php elseif ($row['is_published'] == 1): ?>
                                                <div class="status-badge status-published">Published</div>
                                            <?php else: ?>
                                                <div class="status-badge status-unpublished">Unpublished</div>
                                            <?php endif; ?>

                                        <?php elseif ($row['status'] == 'rejected'): ?>
                                            <div class="status-badge status-rejected">Rejected</div>
                                        <?php else: ?>
                                            <div class="status-badge status-pending">Pending</div>
                                        <?php endif; ?>
                                    </div>


                                </td>
                                <td style="text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <a href="view_restaurant.php?id=<?php echo $row['id']; ?>" target="_blank"
                                        class="action-btn dash-btn-view action-btn-fixed">
                                        <i class="fas fa-eye"></i> View Profile
                                    </a>
                                    <?php if ($row['status'] == 'approved' && $row['is_blocked'] == 0): ?>
                                        <button onclick="openBlockModal(<?php echo $row['id']; ?>)" class="action-btn dash-btn-reject action-btn-fixed" style="background:#fef2f2; color:#ef4444; border-color:#fca5a5; cursor: pointer;">
                                            <i class="fas fa-ban"></i> Block
                                        </button>
                                    <?php else: ?>
                                        <div class="action-btn-placeholder"></div>
                                    <?php endif; ?>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="padding: 4rem; text-align: center; color: #95a5a6;">
                                <i class="fas fa-utensils"
                                    style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                                No restaurants registered yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Block Restaurant Modal -->
<div id="blockModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white text-ink rounded-3xl shadow-2xl max-w-md w-[90%] p-6 md:p-7 relative">
        <h2 class="font-zodiak text-xl md:text-2xl font-bold mb-2" style="color:#dc2626;">Block restaurant</h2>
        <p class="text-sm text-ink/70 mb-4">
            Enter a reason for blocking this restaurant. The manager will see this message and their restaurant will be
            unpublished.
        </p>
        <label class="block text-xs font-semibold uppercase tracking-wide text-ink/60 mb-1">
            Block reason
        </label>
        <textarea id="blockModalMessage"
            class="w-full rounded-2xl bg-white border border-slate-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/30 outline-none text-sm text-ink px-3 py-2.5 resize-none"
            rows="3" placeholder="Write a short explanation for the manager..."></textarea>
        <p id="blockModalError" class="text-xs text-red-400 mt-2 hidden">A block message is required.</p>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button"
                class="px-4 py-2.5 text-sm font-semibold rounded-full bg-slate-100 text-ink hover:bg-slate-200 transition-colors"
                onclick="closeBlockModal()">
                Cancel
            </button>
            <button type="button"
                class="px-5 py-2.5 text-sm font-semibold rounded-full bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                onclick="submitBlockModal()">
                Block restaurant
            </button>
        </div>
    </div>
</div>

<form id="blockForm" method="POST" action="restaurants.php" style="display:none;">
    <input type="hidden" name="action" value="block">
    <input type="hidden" name="id" id="blockAppId" value="">
    <input type="hidden" name="block_message" id="blockMsgInput" value="">
</form>

<script>
    let currentBlockRestaurantId = null;

    function openBlockModal(id) {
        currentBlockRestaurantId = id;
        const modal = document.getElementById('blockModal');
        const messageInput = document.getElementById('blockModalMessage');
        const errorText = document.getElementById('blockModalError');

        // Reset state
        messageInput.value = '';
        errorText.classList.add('hidden');

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Small timeout so focus works reliably
        setTimeout(() => messageInput.focus(), 10);
    }

    function closeBlockModal() {
        const modal = document.getElementById('blockModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function submitBlockModal() {
        const messageInput = document.getElementById('blockModalMessage');
        const errorText = document.getElementById('blockModalError');
        const msg = messageInput.value.trim();

        if (!msg) {
            errorText.classList.remove('hidden');
            messageInput.focus();
            return;
        }

        document.getElementById('blockAppId').value = currentBlockRestaurantId;
        document.getElementById('blockMsgInput').value = msg;
        document.getElementById('blockForm').submit();
    }

    // Close modal when clicking on the dimmed background
    document.getElementById('blockModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeBlockModal();
        }
    });

    // Allow Escape key to close the modal
    document.addEventListener('keydown', function (e) {
        const modal = document.getElementById('blockModal');
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeBlockModal();
        }
    });
</script>

<?php require_once '../footer.php'; ?>