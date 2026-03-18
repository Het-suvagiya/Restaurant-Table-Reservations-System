<?php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Fetch stats
$today = date('Y-m-d');

// Total bookings (Confirmed only)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed' AND booking_date >= ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$total_bookings = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Total restaurants
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM restaurants");
$stmt->execute();
$total_apps = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Pending applications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM restaurants WHERE status = 'pending'");
$stmt->execute();
$pending_apps = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Blocked / Rejected applications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM restaurants WHERE status = 'rejected' OR is_blocked = 1");
$stmt->execute();
$blocked_apps = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Total users
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_users");
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Fetch advance bookings for global calendar (summary)
$stmt = $conn->prepare("SELECT booking_date, COUNT(*) as count FROM bookings WHERE booking_date >= ? AND status != 'cancelled' GROUP BY booking_date");
$stmt->bind_param("s", $today);
$stmt->execute();
$advance_summary = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $advance_summary[$row['booking_date']] = $row['count'];
}
$stmt->close();

// Fetch counts for the three new buttons
$today = date('Y-m-d');

// Confirmed Bookings (Today and future)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed' AND booking_date >= ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$confirmed_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Cancelled Bookings
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'cancelled'");
$stmt->execute();
$cancelled_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Completed Bookings (Confirmed but in the past)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed' AND booking_date < ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$total_status = $confirmed_count + $cancelled_count + $completed_count;
$confirmed_pct = ($total_status > 0) ? round(($confirmed_count / $total_status) * 100, 1) : 0;
$cancelled_pct = ($total_status > 0) ? round(($cancelled_count / $total_status) * 100, 1) : 0;
$completed_pct = ($total_status > 0) ? round(($completed_count / $total_status) * 100, 1) : 0;

require_once '../header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="max-w-[1200px] mx-auto pt-32 pb-24 px-5 animate-soft-rise">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-black/5 pb-6">
        <div>
            <h1 class="font-zodiak text-4xl md:text-5xl text-ink font-bold">Admin Dashboard</h1>
            <p class="text-ink/60 mt-2 font-medium">Platform overview and management</p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-12">
        <a href="bookings.php" class="block group">
            <div
                class="bg-white rounded-[24px] p-6 shadow-sm border border-black/5 group-hover:shadow-premium group-hover:-translate-y-1 transition-all duration-300 h-full flex flex-col justify-center relative overflow-hidden">
                <div
                    class="absolute -right-4 -top-4 w-24 h-24 bg-deepTeal/5 rounded-full blur-xl group-hover:bg-deepTeal/10 transition-colors">
                </div>
                <div
                    class="w-12 h-12 rounded-full bg-deepTeal/10 text-deepTeal flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3 class="text-ink/60 text-sm font-bold uppercase tracking-wider mb-1">Table Bookings</h3>
                <div class="text-3xl font-zodiak font-bold text-ink"><?php echo $total_bookings; ?></div>
            </div>
        </a>
        <a href="restaurants.php" class="block group">
            <div
                class="bg-white rounded-[24px] p-6 shadow-sm border border-black/5 group-hover:shadow-premium group-hover:-translate-y-1 transition-all duration-300 h-full flex flex-col justify-center relative overflow-hidden">
                <div
                    class="absolute -right-4 -top-4 w-24 h-24 bg-tealAccent/5 rounded-full blur-xl group-hover:bg-tealAccent/10 transition-colors">
                </div>
                <div
                    class="w-12 h-12 rounded-full bg-tealAccent/10 text-teal-600 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3 class="text-ink/60 text-sm font-bold uppercase tracking-wider mb-1">Total Restaurants</h3>
                <div class="text-3xl font-zodiak font-bold text-ink"><?php echo $total_apps; ?></div>
            </div>
        </a>
        <a href="applications.php" class="block group">
            <div
                class="bg-white rounded-[24px] p-6 shadow-sm border border-black/5 group-hover:shadow-premium group-hover:-translate-y-1 transition-all duration-300 h-full flex flex-col justify-center relative overflow-hidden">
                <div
                    class="absolute -right-4 -top-4 w-24 h-24 bg-orange-500/5 rounded-full blur-xl group-hover:bg-orange-500/10 transition-colors">
                </div>
                <div
                    class="w-12 h-12 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="text-ink/60 text-sm font-bold uppercase tracking-wider mb-1">Pending Approval</h3>
                <div class="text-3xl font-zodiak font-bold text-ink"><?php echo $pending_apps; ?></div>
            </div>
        </a>
        <a href="blocked_restaurants.php" class="block group">
            <div
                class="bg-white rounded-[24px] p-6 shadow-sm border border-black/5 group-hover:shadow-premium group-hover:-translate-y-1 transition-all duration-300 h-full flex flex-col justify-center relative overflow-hidden">
                <div
                    class="absolute -right-4 -top-4 w-24 h-24 bg-red-500/5 rounded-full blur-xl group-hover:bg-red-500/10 transition-colors">
                </div>
                <div
                    class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-ban"></i>
                </div>
                <h3 class="text-ink/60 text-sm font-bold uppercase tracking-wider mb-1">Blocked / Rejected</h3>
                <div class="text-3xl font-zodiak font-bold text-ink"><?php echo $blocked_apps; ?></div>
            </div>
        </a>
        <a href="users.php" class="block group">
            <div
                class="bg-white rounded-[24px] p-6 shadow-sm border border-black/5 group-hover:shadow-premium group-hover:-translate-y-1 transition-all duration-300 h-full flex flex-col justify-center relative overflow-hidden">
                <div
                    class="absolute -right-4 -top-4 w-24 h-24 bg-warmWood/5 rounded-full blur-xl group-hover:bg-warmWood/10 transition-colors">
                </div>
                <div
                    class="w-12 h-12 rounded-full bg-orange-50 text-warmWood flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-ink/60 text-sm font-bold uppercase tracking-wider mb-1">Manage Users</h3>
                <div class="text-3xl font-zodiak font-bold text-ink"><?php echo $total_users; ?></div>
            </div>
        </a>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

        <!-- Left Column: Charts and Breakdowns -->
        <div class="lg:col-span-2 flex flex-col gap-8">
            <!-- Status Rate Buttons -->
            <div class="flex flex-wrap gap-4">
                <a href="bookings.php?status=confirmed" class="flex-1 min-w-[150px] flex items-center gap-3 bg-white border border-black/5 rounded-2xl px-5 py-4 shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-ink/40 uppercase tracking-wider">Confirmed</div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-zodiak font-bold text-ink"><?php echo $confirmed_count; ?></span>
                            <span class="text-[10px] font-bold text-emerald-600"><?php echo $confirmed_pct; ?>%</span>
                        </div>
                    </div>
                </a>
                <a href="bookings.php?status=cancelled" class="flex-1 min-w-[150px] flex items-center gap-3 bg-white border border-black/5 rounded-2xl px-5 py-4 shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-ink/40 uppercase tracking-wider">Cancelled</div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-zodiak font-bold text-ink"><?php echo $cancelled_count; ?></span>
                            <span class="text-[10px] font-bold text-rose-600"><?php echo $cancelled_pct; ?>%</span>
                        </div>
                    </div>
                </a>
                <a href="bookings.php?status=completed" class="flex-1 min-w-[150px] flex items-center gap-3 bg-white border border-black/5 rounded-2xl px-5 py-4 shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-ink/40 uppercase tracking-wider">Completed</div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-zodiak font-bold text-ink"><?php echo $completed_count; ?></span>
                            <span class="text-[10px] font-bold text-blue-600"><?php echo $completed_pct; ?>%</span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Graph 1: Day-wise bookings for selected month/year -->
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 border-b border-black/5 pb-4">
                    <h2 class="font-zodiak text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-calendar-day text-deepTeal"></i> Daily Bookings
                    </h2>
                    <div class="flex gap-2">
                        <select id="dailyMonth" class="bg-slate-100 p-2 rounded-xl text-sm font-bold outline-none border-none">
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $selected = ($m == date('n')) ? 'selected' : '';
                                echo "<option value='$m' $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
                            }
                            ?>
                        </select>
                        <select id="dailyYear" class="bg-slate-100 p-2 rounded-xl text-sm font-bold outline-none border-none">
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
                                $selected = ($y == $currentYear) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="w-full relative h-[300px]">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <!-- Graph 2: Month-wise bookings for selected year -->
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 border-b border-black/5 pb-4">
                    <h2 class="font-zodiak text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-chart-bar text-warmWood"></i> Monthly Bookings
                    </h2>
                    <div class="flex gap-2">
                        <select id="monthlyYear" class="bg-slate-100 p-2 rounded-xl text-sm font-bold outline-none border-none">
                            <?php
                            for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
                                $selected = ($y == $currentYear) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="w-full relative h-[300px]">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5">
                <div class="mb-6 border-b border-black/5 pb-4">
                    <h2 class="font-zodiak text-2xl font-bold flex items-center gap-3"><i
                            class="fas fa-store text-warmWood"></i> Day Breakdown</h2>
                    <p class="text-sm text-ink/60 mt-1">Select a date on the calendar to view the restaurant breakdown.
                    </p>
                </div>

                <div id="date-bookings-container"
                    class="bg-slate-50/50 rounded-[24px] border border-black/5 overflow-hidden"
                    style="height: 260px; overflow-y: auto;">
                    <div class="p-12 text-center flex flex-col items-center justify-center">
                        <div
                            class="w-16 h-16 bg-white rounded-full flex items-center justify-center text-ink/20 mb-4 shadow-sm">
                            <i class="fas fa-hand-pointer text-2xl"></i>
                        </div>
                        <p class="font-medium text-ink/60">Select a date to view reservations</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Context/Calendar -->
        <div class="lg:col-span-1 lg:sticky lg:top-[120px]">
            <div class="bg-white rounded-[32px] p-6 md:p-8 shadow-sm border border-black/5 mb-8">
                <h2 class="font-zodiak text-2xl font-bold mb-1 flex items-center gap-3"><i
                        class="fas fa-calendar-days text-tealAccent"></i> Advance Search</h2>
                <p class="text-sm text-ink/60 mb-6 font-medium">Platform Wide Bookings</p>

                <!-- Custom Calendar UI -->
                <div class="bg-slate-50 rounded-[24px] p-5 border border-black/5">
                    <div class="flex justify-between items-center mb-6">
                        <button type="button" id="prev-month"
                            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-black/5 transition-colors text-ink/60">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </button>
                        <h4 id="calendar-month-year" class="m-0 font-bold text-ink"></h4>
                        <button type="button" id="next-month"
                            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-black/5 transition-colors text-ink">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </button>
                    </div>

                    <div
                        class="grid grid-cols-7 gap-1 text-center text-xs font-bold text-ink/50 mb-2 uppercase tracking-wider">
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                        <div>Sun</div>
                    </div>

                    <div id="calendar-grid" class="grid grid-cols-7 gap-1">
                        <!-- JS will populate days here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const rawAdvanceData = <?php echo json_encode($advance_summary); ?>;
    // --- New Chart.js Implementation ---
    let dailyChartInstance = null;
    let monthlyChartInstance = null;

    async function fetchAndRenderDaily() {
        const month = document.getElementById('dailyMonth').value;
        const year = document.getElementById('dailyYear').value;
        
        try {
            const response = await fetch(`get_booking_stats.php?type=daily&month=${month}&year=${year}`);
            const data = await response.json();
            
            const ctx = document.getElementById('dailyChart').getContext('2d');
            
            if (dailyChartInstance) {
                dailyChartInstance.destroy();
            }
            
            dailyChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Daily Bookings',
                        data: data.data,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#27ae60',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' Bookings';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#94a3b8' },
                            grid: { color: '#f1f5f9', drawBorder: false }
                        },
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { display: false, drawBorder: false }
                        }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
        } catch (error) {
            console.error('Error fetching daily stats:', error);
        }
    }

    async function fetchAndRenderMonthly() {
        const year = document.getElementById('monthlyYear').value;
        
        try {
            const response = await fetch(`get_booking_stats.php?type=monthly&year=${year}`);
            const data = await response.json();
            
            const ctx = document.getElementById('monthlyChart').getContext('2d');
            
            if (monthlyChartInstance) {
                monthlyChartInstance.destroy();
            }
            
            monthlyChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Monthly Bookings',
                        data: data.data,
                        backgroundColor: '#e67e22',
                        borderRadius: 8,
                        hoverBackgroundColor: '#d35400'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' Bookings';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#94a3b8' },
                            grid: { color: '#f1f5f9', drawBorder: false }
                        },
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { display: false, drawBorder: false }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error fetching monthly stats:', error);
        }
    }

    // Initial renders
    fetchAndRenderDaily();
    fetchAndRenderMonthly();

    // Event listeners for selectors
    document.getElementById('dailyMonth').addEventListener('change', fetchAndRenderDaily);
    document.getElementById('dailyYear').addEventListener('change', fetchAndRenderDaily);
    document.getElementById('monthlyYear').addEventListener('change', fetchAndRenderMonthly);



    // --- Calendar System ---
    const advanceSummary = rawAdvanceData;

    let currentDate = new Date();
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Enforce 3 months future limit logic for Admin
    const limitDate = new Date();
    limitDate.setMonth(limitDate.getMonth() + 3);
    limitDate.setHours(0, 0, 0, 0);

    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');

    function renderCalendar(date) {
        const year = date.getFullYear();
        const month = date.getMonth();
        const grid = document.getElementById('calendar-grid');

        document.getElementById('calendar-month-year').innerText = date.toLocaleString('default', { month: 'long', year: 'numeric' });
        grid.innerHTML = '';

        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDay = firstDayOfMonth === 0 ? 7 : firstDayOfMonth;

        // Filler days
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        for (let i = firstDay - 1; i > 0; i--) {
            grid.appendChild(createAdminDayCell(daysInPrevMonth - i + 1, true));
        }

        for (let i = 1; i <= daysInMonth; i++) {
            const cellDate = new Date(year, month, i);
            const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const count = rawAdvanceData[dateString] || 0;
            // Admin can see past bookings but only up to 3 months in future
            const beyondLimit = cellDate > limitDate;

            grid.appendChild(createAdminDayCell(i, false, beyondLimit, dateString, count));
        }

        const currentMonth = date.getFullYear() * 12 + date.getMonth();
        const maxMonth = limitDate.getFullYear() * 12 + limitDate.getMonth();

        // Admin can go back as much as they want
        prevMonthBtn.style.pointerEvents = 'auto';
        prevMonthBtn.style.color = '#1e293b';

        nextMonthBtn.style.pointerEvents = currentMonth >= maxMonth ? 'none' : 'auto';
        nextMonthBtn.style.color = currentMonth >= maxMonth ? '#e2e8f0' : '#1e293b';
    }

    function createAdminDayCell(day, isFiller, isDisabled = false, dateString = null, count = 0) {
        const cell = document.createElement('div');
        cell.style.aspectRatio = '1';
        cell.style.display = 'flex';
        cell.style.flexDirection = 'column';
        cell.style.alignItems = 'center';
        cell.style.justifyContent = 'center';
        cell.style.borderRadius = '6px';
        cell.style.fontSize = '0.9rem';
        cell.style.position = 'relative';

        if (isFiller) {
            cell.innerText = day;
            cell.style.color = '#cbd5e1';
        } else {
            cell.innerText = day;
            if (isDisabled) {
                cell.style.color = '#cbd5e1';
                cell.style.background = '#f8fafc';
                cell.style.cursor = 'not-allowed';
            } else {
                cell.style.cursor = 'pointer';
                cell.style.transition = 'all 0.2s';
                cell.onmouseover = () => { if (!cell.classList.contains('selected')) cell.style.background = '#f1f5f9'; };
                cell.onmouseout = () => { if (!cell.classList.contains('selected')) cell.style.background = 'transparent'; };

                // Dot indicator for bookings
                if (count > 0) {
                    const dot = document.createElement('div');
                    dot.style.position = 'absolute';
                    dot.style.bottom = '4px';
                    dot.style.width = '4px';
                    dot.style.height = '4px';
                    dot.style.borderRadius = '50%';
                    dot.style.background = '#e67e22'; // Orange dot for admin platform-wide tracking
                    cell.appendChild(dot);
                }

                cell.onclick = () => {
                    document.querySelectorAll('#calendar-grid > div').forEach(el => {
                        el.style.background = el.style.cursor === 'not-allowed' ? '#f8fafc' : 'transparent';
                        el.style.color = el.style.cursor === 'not-allowed' ? '#cbd5e1' : '#1e293b';
                        el.style.fontWeight = 'normal';
                        el.classList.remove('selected');
                    });

                    cell.style.background = '#e67e22'; // Orange highlight for selection
                    cell.style.color = '#fff';
                    cell.style.fontWeight = '700';
                    cell.classList.add('selected');

                    fetchDayBookings(dateString);
                };
            }
        }
        return cell;
    }

    prevMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(currentDate); });
    nextMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(currentDate); });

    renderCalendar(currentDate);


    // --- Fetch Breakdown by Restaurant ---
    function fetchDayBookings(dateString) {
        const container = document.getElementById('date-bookings-container');
        container.innerHTML = `<div style="padding: 2rem; text-align: center;"><i class="fas fa-spinner fa-spin" style="color: #cbd5e1; font-size: 2rem;"></i></div>`;

        fetch(`get_bookings.php?date=${dateString}`)
            .then(res => res.json())
            .then(data => {
                const displayDate = new Date(dateString).toLocaleDateString('default', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

                if (data.length === 0) {
                    container.innerHTML = `
                        <div style="padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; font-size: 1.1rem;">${displayDate}</h3>
                        </div>
                        <div style="padding: 3rem; text-align: center; color: #95a5a6;">
                            <i class="fas fa-store-slash" style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.2;"></i>
                            No platform bookings for this date.
                        </div>`;
                    return;
                }

                let html = `
                    <div style="padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fafafa; border-radius: 8px 8px 0 0;">
                        <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">${displayDate}</h3>
                        <span style="font-size: 0.85rem; background: #e67e22; color: #fff; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: 700;">${data.length} Restaurants Active</span>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                `;

                data.forEach(rest => {
                    html += `
                        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <div>
                                <h4 style="margin: 0 0 0.3rem 0; color: #2c3e50; font-size: 1rem;">${rest.restaurant_name}</h4>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 700; color: #1e293b;">${rest.total_bookings} Bookings</div>
                                <div style="font-size: 0.8rem; color: #e67e22; font-weight: 600;"><i class="fas fa-users"></i> ${rest.total_guests} Guests Total</div>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                container.innerHTML = html;
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = '<div style="padding: 2rem; color: #e74c3c; text-align: center;">Error loading platform booking breakdown.</div>';
            });
    }
</script>

<?php require_once '../footer.php'; ?>