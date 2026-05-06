<?php
session_start();
require 'includes/db.php'; // Include database connection

// Security check: Ensure the user is logged in and has the 'Admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get the logged-in user's details
$adminName = $_SESSION['username'] ?? 'Admin';
$adminEmail = 'admin@gmail.com'; 

// --- 1. FETCH DASHBOARD STATISTICS ---

// Total Appointments (All time)
$totalAppointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

// Active Jobs (Pending or In Progress)
$activeJobs = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status IN ('Pending', 'In Progress')")->fetchColumn();

// Total Registered Clients
$totalClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Customer'")->fetchColumn();

// Monthly Revenue (Paid Invoices in the current month)
$monthlyRevenue = $pdo->query("
    SELECT SUM(amount) 
    FROM invoices 
    WHERE status = 'Paid' 
    AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetchColumn();
$monthlyRevenue = $monthlyRevenue ? $monthlyRevenue : 0.00; // Handle null if no revenue yet


// --- 2. FETCH DATA FOR CHARTS ---

// Chart 1: Appointments by Status
$apptStats = $pdo->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingApt = $apptStats['Pending'] ?? 0;
$confirmedApt = $apptStats['Confirmed'] ?? 0;
$inProgressApt = $apptStats['In Progress'] ?? 0;
$onHoldApt = $apptStats['On Hold'] ?? 0;
$completedApt = $apptStats['Completed'] ?? 0;
$cancelledApt = $apptStats['Cancelled'] ?? 0;

// Chart 2: Last 7 Days Revenue
$last7Days = [];
$revenue7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last7Days[] = date('M d', strtotime($date)); // e.g., "Oct 25"
    
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM invoices WHERE status = 'Paid' AND DATE(created_at) = ?");
    $stmt->execute([$date]);
    $dailyRev = $stmt->fetchColumn();
    $revenue7Days[] = $dailyRev ? (float)$dailyRev : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ServiceHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; }
        :root {
            --sidebar-bg: #101623; --sidebar-hover: #1f2937; --primary-orange: #FF7A00;
            --bg-light: #f3f4f6; --text-dark: #1f2937; --text-muted: #6b7280; --border-color: #e5e7eb;
        }

       body, html { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-light); display: flex; height: 100vh; width: 100vw; overflow: hidden; }

        /* Sidebar Styles */
       .sidebar { width: 250px; flex-shrink: 0; background-color: var(--sidebar-bg); color: #fff; display: flex; flex-direction: column; height: 100%; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #1f2937; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 { margin: 0; font-size: 18px; font-weight: 600; }
        .sidebar-header p { margin: 0; font-size: 11px; color: #8b949e; }
        .nav-links { list-style: none; padding: 15px 0; margin: 0; flex-grow: 1; }
        .nav-links li { padding: 5px 20px; }
        .nav-links a { color: #c9d1d9; text-decoration: none; display: flex; align-items: center; padding: 10px 15px; border-radius: 8px; font-size: 14px; transition: 0.2s; }
        .nav-links a i { width: 20px; margin-right: 10px; font-size: 16px; }
        .nav-links a:hover { background-color: var(--sidebar-hover); color: #fff; }
        .nav-links a.active { background-color: var(--primary-orange); color: #fff; font-weight: bold; }

        /* User Profile in Sidebar */
        .user-profile-container { border-top: 1px solid #1f2937; padding: 15px 20px; }
        .user-profile { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .avatar { width: 35px; height: 35px; background-color: var(--primary-orange); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 16px; }
        .user-info { flex-grow: 1; }
        .user-info h4 { margin: 0; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .admin-badge { background-color: #ff7b72; color: white; font-size: 9px; padding: 2px 6px; border-radius: 10px; }
        .user-info p { margin: 2px 0 0 0; font-size: 10px; color: #8b949e; }
        
        .logout-btn { color: #c9d1d9; text-decoration: none; transition: 0.2s; }
        .logout-btn:hover { color: #ff7b72; }

        /* Main Content Area */
       .main-content { flex: 1; width: calc(100% - 250px); padding: 30px 40px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
        .top-header h1 { margin: 0 0 5px 0; font-size: 24px; color: var(--text-dark); }
        .top-header p { margin: 0; color: var(--text-muted); font-size: 14px; }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card h3 { margin: 0; font-size: 28px; color: var(--primary-orange); }
        .stat-card p { margin: 5px 0 0 0; font-size: 13px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

        /* Charts Section */
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .chart-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); min-height: 300px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .chart-card h4 { margin: 0 0 5px 0; font-size: 16px; color: var(--text-dark); }
        .chart-card p { margin: 0 0 20px 0; font-size: 12px; color: var(--text-muted); }
        .chart-container { position: relative; height: 250px; width: 100%; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-wrench" style="color: var(--primary-orange); font-size: 20px;"></i>
            <div>
                <h2>ServiceHub</h2>
                <p>Workshop Management</p>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="admin_dashboard.php" class="active"><i class="fa-solid fa-border-all"></i> Dashboard</a></li>
            <li><a href="appointments.php"><i class="fa-regular fa-calendar-check"></i> Appointments</a></li>
            <li><a href="job_orders.php"><i class="fa-solid fa-clipboard-list"></i> Job Orders</a></li>
            <li><a href="invoices.php"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
            <li><a href="clients.php"><i class="fa-solid fa-users"></i> Clients</a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> Inventory</a></li>
            <li><a href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>

        <div class="user-profile-container">
            <div class="user-profile">
                <div class="avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($adminName); ?> <span class="admin-badge">Admin</span></h4>
                    <p><?php echo htmlspecialchars($adminEmail); ?></p>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-header">
            <div>
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($adminName); ?>! Here is your workshop overview.</p>
            </div>
            </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3 id="stat-appointments"><?php echo $totalAppointments; ?></h3>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-active-jobs" style="color: #3b82f6;"><?php echo $activeJobs; ?></h3>
                <p>Active Jobs</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-total-clients" style="color: #10b981;"><?php echo $totalClients; ?></h3>
                <p>Total Clients</p>
            </div>
            <div class="stat-card">
                <h3 id="stat-monthly-revenue" style="color: #1f2937;">₱<?php echo number_format($monthlyRevenue, 2); ?></h3>
                <p>Monthly Revenue</p>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h4>Appointments by Status</h4>
                <p>Distribution of all recorded service appointments.</p>
                <div class="chart-container">
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h4>Weekly Revenue</h4>
                <p>Paid invoice totals for the last 7 days.</p>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <script>
    // 1. Initialize Appointments Doughnut Chart
    const ctxApt = document.getElementById('appointmentsChart').getContext('2d');
    const appointmentsChart = new Chart(ctxApt, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'In Progress', 'On Hold', 'Completed', 'Cancelled'],
            datasets: [{
                data: [
                    <?php echo $pendingApt; ?>,
                    <?php echo $inProgressApt; ?>,
                    <?php echo $onHoldApt; ?>,
                    <?php echo $completedApt; ?>, 
                    <?php echo $cancelledApt; ?>
                ],
                backgroundColor: ['#f59e0b', '#8b5cf6', '#ef4444', '#10b981', '#6b7280'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            },
            cutout: '70%'
        }
    });

    // 2. Initialize Revenue Bar Chart
    const ctxRev = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(ctxRev, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($last7Days); ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data: <?php echo json_encode($revenue7Days); ?>,
                backgroundColor: '#FF7A00',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '₱' + value; }
                    }
                }
            }
        }
    });

    // 3. REAL-TIME UPDATE LOGIC (Updates every 5 seconds)
    function fetchChartData() {
        fetch('api_chart_data.php')
            .then(response => response.json())
            .then(data => {
                // Update Appointments Chart
                appointmentsChart.data.datasets[0].data = data.appointments;
                appointmentsChart.update(); // Re-draw real-time

                // Update Revenue Chart
                revenueChart.data.labels = data.revenueLabels;
                revenueChart.data.datasets[0].data = data.revenueData;
                revenueChart.update(); // Re-draw real-time
            })
            .catch(error => console.error("Error fetching live chart data:", error));
    }

    // Call the API every 5000 milliseconds (5 seconds)
    setInterval(fetchChartData, 5000);
</script>
</body>
</html>