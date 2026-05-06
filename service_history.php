<?php
session_start();
require 'includes/db.php'; // Include your database connection

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customerName = $_SESSION['username'] ?? 'Customer';
$customerEmail = 'customer@email.com'; 

// Fetch completed and cancelled services for this customer
$historyQuery = $pdo->prepare("
    SELECT 
        a.appointment_date, 
        v.make_model, 
        v.plate_number, 
        a.service_type, 
        a.status, 
        jo.cost,
        jo.id as job_id
    FROM appointments a
    JOIN vehicles v ON a.vehicle_id = v.id
    LEFT JOIN job_orders jo ON a.id = jo.appointment_id
    WHERE a.user_id = ? AND a.status IN ('Completed', 'Cancelled')
    ORDER BY a.appointment_date DESC
");
$historyQuery->execute([$user_id]);
$serviceHistory = $historyQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service History - ServiceHub</title>
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

        .table-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
            <li><a href="service_history.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a></li>
            <li><a href="my_invoices.php"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
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
                <h1>Service History</h1>
                <p>View past services and maintenance records</p>
            </div>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search history...">
        </div>

        <div class="table-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Vehicle</th>
                            <th>Service Description</th>
                            <th>Cost</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                   <tbody>
                    <?php if (count($serviceHistory) > 0): ?>
                        <?php foreach ($serviceHistory as $record): ?>
                            <tr>
                                <td><?php echo date("M d, Y", strtotime($record['appointment_date'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['make_model']); ?></strong><br>
                                    <span style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($record['plate_number']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($record['service_type']); ?></td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; 
                                        background: <?php echo ($record['status'] == 'Completed') ? '#ecfdf5' : '#fef2f2'; ?>; 
                                        color: <?php echo ($record['status'] == 'Completed') ? '#10b981' : '#ef4444'; ?>;">
                                        <?php echo $record['status']; ?>
                                    </span>
                                </td>
                                <td><strong>₱<?php echo number_format($record['cost'] ?? 0, 2); ?></strong></td>
                                <td>
                                    <?php if ($record['job_id']): ?>
                                        <span style="font-size: 11px; color: var(--text-muted);">JOB-<?php echo str_pad($record['job_id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 30px; color: var(--text-muted);">
                                <i class="fa-solid fa-clock-rotate-left" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                                No service history found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>