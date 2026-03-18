<?php
require_once '../config.php';

if (!isLoggedIn() || !isManager()) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];

// Get restaurant id
$stmt = $conn->prepare("SELECT id, name FROM restaurants WHERE manager_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    die("Restaurant not found.");
}

$rest_id = $restaurant['id'];

// Fetch all bookings for this restaurant
$query = "
    SELECT b.*, 
           u.u_firstname as user_firstname, 
           u.u_lastname as user_lastname, 
           u.u_email as user_email
    FROM bookings b
    JOIN tbl_users u ON b.user_id = u.u_id
    WHERE b.restaurant_id = ?
    ORDER BY b.booking_date DESC, b.booking_time DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $rest_id);
$stmt->execute();
$bookings = $stmt->get_result();
$stmt->close();

require_once '../header.php';
?>

<div class="dash-container"
    style="padding-top: 120px; padding-bottom: 80px; animation: soft-rise 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;">
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-black/5 pb-6">
        <div>
            <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold m-0 p-0"
                style="font-size: 2.25rem; line-height: 2.5rem; color: #0f172a;">Manage Bookings</h1>
            <p class="text-ink/60 mt-2 font-medium">Bookings for <?php echo htmlspecialchars($restaurant['name']); ?></p>
        </div>
        <a href="dashboard.php"
            class="dash-btn-back font-bold text-sm bg-white border border-black/10 text-ink/70 px-4 py-2 rounded-xl hover:bg-slate-50 transition-colors shadow-sm inline-flex items-center gap-2"
            style="text-decoration: none;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="dash-section">
        <div
            style="display: flex; flex-direction: column; md:flex-row; justify-content: space-between; align-items: start; md:items-center; gap: 1rem; margin-bottom: 1.5rem;">
            <h2 style="margin: 0;"><i class="fas fa-calendar-check" style="color: var(--primary-color);"></i>
                Restaurant Bookings
            </h2>

            <div class="flex bg-slate-100 p-1 rounded-xl w-full md:w-auto overflow-x-auto">
                <button
                    class="filter-btn active px-4 py-2 text-sm font-bold rounded-lg transition-colors bg-white text-deepTeal shadow-sm whitespace-nowrap"
                    data-filter="all">All</button>
                <button
                    class="filter-btn px-4 py-2 text-sm font-bold rounded-lg transition-colors text-ink/60 hover:text-ink whitespace-nowrap"
                    data-filter="confirmed">Confirmed</button>
                <button
                    class="filter-btn px-4 py-2 text-sm font-bold rounded-lg transition-colors text-ink/60 hover:text-ink whitespace-nowrap"
                    data-filter="cancelled">Cancelled</button>
                <button
                    class="filter-btn px-4 py-2 text-sm font-bold rounded-lg transition-colors text-ink/60 hover:text-ink whitespace-nowrap"
                    data-filter="completed">Completed</button>
            </div>
        </div>

        <div class="dash-table-wrapper">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Diner</th>
                        <th>Party Size</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="bookings-tbody">
                    <?php if ($bookings->num_rows > 0): ?>
                        <?php while ($row = $bookings->fetch_assoc()): ?>
                            <?php
                            $displayStatus = $row['status'];
                            $current_date = date('Y-m-d');

                            if ($displayStatus === 'confirmed' && $row['booking_date'] < $current_date) {
                                $displayStatus = 'completed';
                            }
                            ?>
                            <tr class="booking-row" data-status="<?php echo strtolower($displayStatus); ?>">
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);">
                                        <?php echo date('M d, Y', strtotime($row['booking_date'])); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #7f8c8d;">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('h:i A', strtotime($row['booking_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500;">
                                        <?php echo htmlspecialchars($row['user_firstname'] . ' ' . $row['user_lastname']); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #7f8c8d;">
                                        <?php echo htmlspecialchars($row['user_email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        style="background: #f1f5f9; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: 600; color: #475569;">
                                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                                        <?php echo $row['guests']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch ($displayStatus) {
                                        case 'confirmed':
                                            $statusClass = 'status-approved';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'status-rejected';
                                            break;
                                        case 'completed':
                                            $statusClass = 'status-completed';
                                            break;
                                        default:
                                            $statusClass = 'status-pending';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($displayStatus); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="padding: 4rem; text-align: center; color: #95a5a6;">
                                <i class="fas fa-calendar-times"
                                    style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                                No bookings found for your restaurant yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const initialStatus = urlParams.get('status');

        const buttons = document.querySelectorAll('.filter-btn');
        const rows = document.querySelectorAll('.booking-row');

        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Update active button styling
                buttons.forEach(b => {
                    b.classList.remove('active', 'bg-white', 'text-deepTeal', 'shadow-sm');
                    b.classList.add('text-ink/60');
                    b.style.background = 'transparent';
                    b.style.color = '#64748b';
                    b.style.boxShadow = 'none';
                });

                btn.classList.add('active', 'bg-white', 'text-deepTeal', 'shadow-sm');
                btn.classList.remove('text-ink/60');
                btn.style.background = '#fff';
                btn.style.color = '#1e293b';
                btn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';

                const filter = btn.getAttribute('data-filter');

                // Filter rows
                rows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    if (filter === 'all' || status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        if (initialStatus) {
            const targetBtn = document.querySelector(`.filter-btn[data-filter="${initialStatus}"]`);
            if (targetBtn) {
                targetBtn.click();
            }
        }
    });
</script>

<?php require_once '../footer.php'; ?>