<?php
session_start();
require 'includes/db.php'; // Include your database connection

// Security check: Ensure the user is logged in and has the 'Admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['username'] ?? 'Admin';
$adminEmail = 'admin@gmail.com'; 

// --- DYNAMIC NOTIFICATION GENERATOR ---
$notifications = [];
$actionRequired = 0; // Pending Appointments
$criticalAlerts = 0; // Out of Stock & Overdue Invoices
$warnings = 0;       // Low Stock

// 1. Scan Inventory for Low/Out of Stock
$invStmt = $pdo->query("SELECT item_name, quantity, min_stock, status FROM inventory WHERE quantity <= min_stock OR status = 'Out of Stock'");
$inventoryAlerts = $invStmt->fetchAll();

foreach ($inventoryAlerts as $item) {
    if ($item['quantity'] == 0 || $item['status'] == 'Out of Stock') {
        $criticalAlerts++;
        $notifications[] = [
            'type' => 'notif-alert', 
            'icon' => 'fa-circle-xmark', 
            'title' => 'Out of Stock: ' . $item['item_name'], 
            'desc' => "This item is completely out of stock. Please reorder immediately.",
            'time' => 'Inventory System'
        ];
    } else {
        $warnings++;
        $notifications[] = [
            'type' => 'notif-warning', 
            'icon' => 'fa-triangle-exclamation', 
            'title' => 'Low Stock Alert: ' . $item['item_name'], 
            'desc' => "Running low! Only <strong>{$item['quantity']}</strong> left in stock. (Minimum threshold is {$item['min_stock']}).",
            'time' => 'Inventory System'
        ];
    }
}

// 2. Scan Appointments for Pending Requests
$aptStmt = $pdo->query("SELECT a.id, a.appointment_date, a.appointment_time, a.service_type, u.full_name 
                        FROM appointments a 
                        JOIN users u ON a.user_id = u.id 
                        WHERE a.status = 'Pending' 
                        ORDER BY a.appointment_date ASC");
$pendingAppointments = $aptStmt->fetchAll();

foreach ($pendingAppointments as $apt) {
    $actionRequired++;
    $date = date("M d, Y", strtotime($apt['appointment_date']));
    $time = date("h:i A", strtotime($apt['appointment_time']));
    $notifications[] = [
        'type' => 'notif-info', 
        'icon' => 'fa-calendar-day', 
        'title' => 'Pending Appointment: ' . htmlspecialchars($apt['full_name']), 
        'desc' => "Requested <strong>{$apt['service_type']}</strong> on <strong>{$date} at {$time}</strong>. Please confirm or decline.",
        'time' => 'Appointments System'
    ];
}

// 3. Scan Invoices for Overdue Payments
// Note: Checks for explicitly 'Overdue' status OR 'Pending' invoices where the due date has passed
$invcStmt = $pdo->query("SELECT id, due_date, amount FROM invoices WHERE status = 'Overdue' OR (status = 'Pending' AND due_date < CURRENT_DATE)");
$overdueInvoices = $invcStmt->fetchAll();

foreach ($overdueInvoices as $invoice) {
    $criticalAlerts++;
    $dueDate = date("M d, Y", strtotime($invoice['due_date']));
    $notifications[] = [
        'type' => 'notif-alert', 
        'icon' => 'fa-file-invoice-dollar', 
        'title' => 'Overdue Invoice: INV-' . str_pad($invoice['id'], 4, '0', STR_PAD_LEFT), 
        'desc' => "Payment of <strong>₱" . number_format($invoice['amount'], 2) . "</strong> is overdue. Originally due on {$dueDate}.",
        'time' => 'Billing System'
    ];
}

$totalNotifications = count($notifications);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ServiceHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        :root {
            --sidebar-bg: #101623; --sidebar-hover: #1f2937; --primary-orange: #FF7A00;
            --bg-light: #f9fafb; --text-dark: #1f2937; --text-muted: #6b7280; --border-color: #e5e7eb;
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
        
        .user-profile-container { border-top: 1px solid #1f2937; padding: 15px 20px; }
        .user-profile { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .avatar { width: 35px; height: 35px; background-color: var(--primary-orange); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 16px; }
        .user-info { flex-grow: 1; }
        .user-info h4 { margin: 0; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .admin-badge { background-color: #ff7b72; color: white; font-size: 9px; padding: 2px 6px; border-radius: 10px; }
        .user-info p { margin: 2px 0 0 0; font-size: 10px; color: #8b949e; }
        .logout-btn { color: #c9d1d9; text-decoration: none; transition: 0.2s; }
        .logout-btn:hover { color: #ff7b72; }

        /* Main Content */
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .top-header h1 { margin: 0 0 5px 0; font-size: 22px; color: var(--text-dark); }
        .top-header p { margin: 0; color: var(--text-muted); font-size: 13px; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card p { margin:0 0 5px 0; font-size:12px; color:var(--text-muted); font-weight: 600; text-transform: uppercase;}
        .stat-card h3 { margin: 0; font-size: 24px; color: var(--text-dark); }

        .controls-row { margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
        
        /* Notification List */
        .notification-list { display: flex; flex-direction: column; gap: 10px; }
        .notification-item { display: flex; align-items: flex-start; padding: 15px 20px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff; gap: 15px; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .notification-item:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-color: #d1d5db; }
        
        .notification-item i.icon-main { font-size: 20px; margin-top: 2px; }
        
        /* Notification Colors */
        .notif-alert i.icon-main { color: #ef4444; } /* Red for Critical */
        .notif-warning i.icon-main { color: #f59e0b; } /* Orange for Warning */
        .notif-info i.icon-main { color: #3b82f6; } /* Blue for Actions */

        .notification-content { flex-grow: 1; }
        .notification-content h4 { margin: 0 0 5px 0; font-size: 14px; color: var(--text-dark); }
        .notification-content p { margin: 0 0 8px 0; font-size: 13px; color: var(--text-muted); line-height: 1.4; }
        .notif-source { font-size: 10px; color: #9ca3af; font-weight: 600; text-transform: uppercase; background: #f3f4f6; padding: 3px 8px; border-radius: 10px;}
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
            <li><a href="admin_dashboard.php"><i class="fa-solid fa-border-all"></i> Dashboard</a></li>
            <li><a href="appointments.php"><i class="fa-regular fa-calendar-check"></i> Appointments</a></li>
            <li><a href="job_orders.php"><i class="fa-solid fa-clipboard-list"></i> Job Orders</a></li>
            <li><a href="invoices.php"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
            <li><a href="clients.php"><i class="fa-solid fa-users"></i> Clients</a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> Inventory</a></li>
            <li><a href="notifications.php" class="active"><i class="fa-regular fa-bell"></i> Notifications</a></li>
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
                <h1>Notifications</h1>
                <p>System alerts and important updates</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <p>Active Signals</p>
                <h3><?php echo $totalNotifications; ?></h3>
            </div>
            <div class="stat-card">
                <p>Action Required</p>
                <h3 style="color: #3b82f6;"><?php echo $actionRequired; ?></h3>
            </div>
            <div class="stat-card">
                <p>Critical Alerts</p>
                <h3 style="color: #ef4444;"><?php echo $criticalAlerts; ?></h3>
            </div>
            <div class="stat-card">
                <p>Warnings</p>
                <h3 style="color: #f59e0b;"><?php echo $warnings; ?></h3>
            </div>
        </div>

        <div class="controls-row">
            <h3 style="margin: 0; font-size: 16px; color: var(--text-dark);">Action Items Overview</h3>
        </div>

        <div class="notification-list">
            <?php
            if ($totalNotifications > 0) {
                // Loop through and display the dynamically generated notifications
                foreach ($notifications as $notif) {
                    echo "<div class='notification-item {$notif['type']}'>";
                    echo "<i class='fa-solid {$notif['icon']} icon-main'></i>";
                    
                    echo "<div class='notification-content'>";
                    echo "<h4>" . htmlspecialchars($notif['title']) . "</h4>";
                    echo "<p>" . $notif['desc'] . "</p>";
                    echo "<span class='notif-source'>" . htmlspecialchars($notif['time']) . "</span>";
                    echo "</div>";
                    
                    echo "</div>";
                }
            } else {
                // Empty state if the shop is perfectly maintained
                echo "<div style='text-align:center; padding: 40px; background: #fff; border: 1px dashed #d1d5db; border-radius: 6px; color: var(--text-muted);'>";
                echo "<i class='fa-regular fa-bell-slash' style='font-size: 30px; margin-bottom: 15px; color: #d1d5db;'></i>";
                echo "<h3 style='margin:0 0 5px 0; color: var(--text-dark);'>You're all caught up!</h3>";
                echo "<p style='margin:0; font-size: 13px;'>There are no pending appointments, overdue invoices, or inventory shortages.</p>";
                echo "</div>";
            }
            ?>
        </div>
    </main>

</body>
</html>