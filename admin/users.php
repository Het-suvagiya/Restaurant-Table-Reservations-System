<?php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success_msg = '';
$error_msg = '';

// Handle Delete
if (isset($_GET['delete']) && isset($_GET['role'])) {
    $id = $_GET['delete'];
    $role = $_GET['role'];
    if ($id == $_SESSION['user_id'] && $role == ROLE_ADMIN) { // Cannot delete self
        $error_msg = "Cannot delete your own admin account.";
    } else {
        if ($role == ROLE_ADMIN) {
            $stmt = $conn->prepare("DELETE FROM tbl_admin WHERE a_id = ?");
        } elseif ($role == ROLE_MANAGER) {
            $stmt = $conn->prepare("DELETE FROM tbl_manager WHERE m_id = ?");
        } else {
            $stmt = $conn->prepare("DELETE FROM tbl_users WHERE u_id = ?");
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_msg = "User deleted successfully.";
        } else {
            $error_msg = "Error deleting user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all users
$query = "
    SELECT u_id as id, u_email as email, u_firstname as first_name, u_lastname as last_name, " . ROLE_USER . " as role, u_image as profile_image, u_created_at as created_at FROM tbl_users
    UNION ALL
    SELECT m_id as id, m_email as email, m_firstname as first_name, m_lastname as last_name, " . ROLE_MANAGER . " as role, m_image as profile_image, m_created_at as created_at FROM tbl_manager
    UNION ALL
    SELECT a_id as id, a_email as email, a_firstname as first_name, a_lastname as last_name, " . ROLE_ADMIN . " as role, a_image as profile_image, a_created_at as created_at FROM tbl_admin
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();

require_once '../header.php';
?>

<div class="dash-container"
    style="padding-top: 120px; padding-bottom: 80px; animation: soft-rise 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;">
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-black/5 pb-6">
        <div>
            <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold m-0 p-0"
                style="font-size: 2.25rem; line-height: 2.5rem; color: #0f172a;">Manage Users</h1>
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
                        <th>User</th>
                        <th>Role</th>
                        <th>Joined Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>"
                                            class="user-avatar" style="object-fit: cover; border: 2px solid #ddd;">
                                    <?php else: ?>
                                        <div class="user-avatar"
                                            style="background: <?php echo $user['role'] == ROLE_ADMIN ? '#e74c3c' : ($user['role'] == ROLE_MANAGER ? '#e67e22' : 'var(--primary-color)'); ?>; color: white;">
                                            <?php echo strtoupper(substr($user['email'] ?? 'U', 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-dark);">
                                            <?php echo htmlspecialchars(($user['first_name'] ?? 'Incomplete') . ' ' . ($user['last_name'] ?? 'Profile')); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                if ($user['role'] == ROLE_ADMIN)
                                    echo '<div class="status-badge role-admin">Admin</div>';
                                elseif ($user['role'] == ROLE_MANAGER)
                                    echo '<div class="status-badge role-manager">Manager</div>';
                                else
                                    echo '<div class="status-badge role-user">Diner</div>';
                                ?>

                            </td>
                            <td style="color: #7f8c8d; font-size: 0.9rem;">
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($user['id'] != $_SESSION['user_id'] || $user['role'] != ROLE_ADMIN): ?>
                                    <a href="?delete=<?php echo $user['id']; ?>&role=<?php echo $user['role']; ?>"
                                        onclick="return openSiteConfirmForLink(event, 'Are you sure you want to delete this user?')"
                                        class="action-btn dash-btn-reject">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php else: ?>
                                    <span style="color: #bdc3c7; font-weight: 600; font-size: 0.85rem;">(Current User)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>