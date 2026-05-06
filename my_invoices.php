<?php
session_start();
require 'includes/db.php'; // Connect to database

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customerName = $_SESSION['username'] ?? 'Customer Name';
$customerEmail = 'customer@email.com'; 

// 1. Calculate Stats for this specific user
$statsQuery = $pdo->prepare("SELECT status, SUM(amount) as total FROM invoices WHERE user_id = ? GROUP BY status");
$statsQuery->execute([$user_id]);
$statsData = $statsQuery->fetchAll(PDO::FETCH_KEY_PAIR);

$totalPaid = $statsData['Paid'] ?? 0;
$pendingBalance = $statsData['Pending'] ?? 0;
$overdueAmount = $statsData['Overdue'] ?? 0;

// 2. Fetch Invoice List for this specific user
$invQuery = $pdo->prepare("
    SELECT i.id, i.created_at, i.amount, i.status, i.due_date, jo.id as job_id, a.service_type
    FROM invoices i
    JOIN job_orders jo ON i.job_order_id = jo.id
    JOIN appointments a ON jo.appointment_id = a.id
    WHERE i.user_id = ?
    ORDER BY i.created_at DESC
");
$invQuery->execute([$user_id]);
$invoices = $invQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoices - ServiceHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --sidebar-bg: #101623;
            --sidebar-hover: #1f2937;
            --primary-orange: #FF7A00;
            --bg-light: #f9fafb;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
        }

        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background-color: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .sidebar-header p {
            margin: 0;
            font-size: 11px;
            color: #8b949e;
        }

        .nav-links {
            list-style: none;
            padding: 15px 0;
            margin: 0;
            flex-grow: 1;
        }

        .nav-links li {
            padding: 5px 20px;
        }

        .nav-links a {
            color: #c9d1d9;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.2s;
        }

        .nav-links a i {
            width: 20px;
            margin-right: 10px;
            font-size: 16px;
        }

        .nav-links a:hover {
            background-color: var(--sidebar-hover);
            color: #fff;
        }

        .nav-links a.active {
            background-color: var(--primary-orange);
            color: #fff;
            font-weight: bold;
        }

        .user-profile-container {
            border-top: 1px solid #1f2937;
            padding: 15px 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 35px;
            height: 35px;
            background-color: var(--primary-orange);
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            font-size: 16px;
        }

        .user-info {
            flex-grow: 1;
        }

        .user-info h4 {
            margin: 0;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .customer-badge {
            background-color: #3b82f6;
            color: white;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .user-info p {
            margin: 2px 0 0 0;
            font-size: 10px;
            color: #8b949e;
        }

        .logout-btn {
            color: #c9d1d9;
            text-decoration: none;
            transition: 0.2s;
        }

        .logout-btn:hover {
            color: #ff7b72;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            width: calc(100% - 250px);
            padding: 30px 40px;
            overflow-y: auto;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .top-header h1 {
            margin: 0 0 5px 0;
            font-size: 22px;
            color: var(--text-dark);
        }

        .top-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            margin: 0 0 5px 0;
            font-size: 24px;
            color: var(--text-dark);
        }

        .stat-card span {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .stat-card p {
            margin: 0;
            font-size: 11px;
            color: var(--text-muted);
        }

        .search-container {
            position: relative;
            max-width: 300px;
            margin-bottom: 20px;
        }

        .search-container input {
            width: 100%;
            padding: 10px 35px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background-color: #f3f4f6;
            font-size: 13px;
            outline: none;
        }

        .search-container input:focus {
            border-color: var(--primary-orange);
        }

        .search-container .fa-magnifying-glass {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 13px;
        }

        /* Table Card */
        .table-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .table-card h2 {
            margin: 0 0 15px 0;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-wrapper {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
            min-height: 400px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f9fafb;
            color: var(--text-dark);
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-dark);
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-wrench" style="color: var(--primary-orange); font-size: 20px;"></i>
            <div>
                <h2>ServiceHub</h2>
                <p>Customer Portal</p>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="customer_dashboard.php"><i class="fa-solid fa-border-all"></i> Dashboard</a></li>
            <li><a href="my_vehicles.php"><i class="fa-solid fa-car"></i> My Vehicles</a></li>
            <li><a href="book_appointment.php"><i class="fa-regular fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="service_history.php"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a></li>
            <li><a href="my_invoices.php" class="active"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
            <li><a href="support.php"><i class="fa-regular fa-circle-question"></i> Support</a></li>
            <li><a href="customer_profile.php"><i class="fa-regular fa-user"></i> Profile</a></li>
        </ul>

        <div class="user-profile-container">
            <div class="user-profile">
                <div class="avatar"><?php echo strtoupper(substr($customerName, 0, 1)); ?></div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($customerName); ?> <span class="customer-badge">Customer</span></h4>
                    <p><?php echo htmlspecialchars($customerEmail); ?></p>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-header">
            <div>
                <h1>My Invoices</h1>
                <p>View and manage your service invoices and payments</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>₱<?php echo number_format($totalPaid, 2); ?></h3>
                <span>Total Paid</span>
                <p>Lifetime payments</p>
            </div>
            <div class="stat-card">
                <h3 style="color: #f59e0b;">₱<?php echo number_format($pendingBalance, 2); ?></h3>
                <span>Pending Balance</span>
                <p>Current due</p>
            </div>
            <div class="stat-card">
                <h3 style="color: #ef4444;">₱<?php echo number_format($overdueAmount, 2); ?></h3>
                <span>Overdue Amount</span>
                <p>Past due</p>
            </div>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search invoices...">
        </div>

        <div class="table-card">
            <h2><i class="fa-solid fa-file-invoice"></i> Billing History</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Date</th>
                            <th>Service/Job Order</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($invoices) > 0): ?>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td><strong>INV-<?php echo str_pad($inv['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo date("M d, Y", strtotime($inv['created_at'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($inv['service_type']); ?><br>
                                        <span style="font-size: 11px; color: var(--text-muted);">Ref: JOB-<?php echo str_pad($inv['job_id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td><strong>₱<?php echo number_format($inv['amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="status-badge <?php echo $inv['status']; ?>">
                                            <?php echo $inv['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="action-btn" title="View Details"><i class="fa-solid fa-eye"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px; color: var(--text-muted);">No invoices found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>