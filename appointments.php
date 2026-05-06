<?php
session_start();
require 'includes/db.php'; // Include database connection

// Security check: Ensure the user is logged in and has the 'Admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['username'] ?? 'Admin';
$adminEmail = 'admin@gmail.com'; 

// Handle Appointment Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];

    try {
        // 1. Update the Appointment
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $appointment_id]);
        
        // 2. Automatically sync the linked Job Order (if it exists) to have the SAME status
        $syncStmt = $pdo->prepare("UPDATE job_orders SET status = ? WHERE appointment_id = ?");
        $syncStmt->execute([$new_status, $appointment_id]);
        
        header("Location: appointments.php?success=1");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - ServiceHub</title>
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

        /* User Profile */
        .user-profile-container {
            border-top: 1px solid #1f2937;
            padding: 15px 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
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

        .admin-badge {
            background-color: #ff7b72;
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

        .app-version {
            font-size: 9px;
            color: #4b5563;
            text-align: left;
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

        /* Page Specific Styles */
        .outline-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .outline-card h3 {
            margin: 0 0 15px 0;
            font-size: 15px;
            color: var(--text-dark);
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
                /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .data-table th {
            background-color: #f9fafb;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
        }
        .data-table tbody tr:hover {
            background-color: #f3f4f6;
        }

        /* Status Select Form */
        .status-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-select {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            font-size: 12px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }
        .status-select.Pending { color: #f59e0b; background: #fffbeb; border-color: #fcd34d; }
        .status-select.Confirmed { color: #3b82f6; background: #eff6ff; border-color: #bfdbfe; }
        .status-select.Completed { color: #10b981; background: #ecfdf5; border-color: #a7f3d0; }
        .status-select.Cancelled { color: #ef4444; background: #fef2f2; border-color: #fecaca; }

        .btn-update {
            background: var(--text-dark);
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: 0.2s;
        }
        .btn-update:hover {
            background: var(--primary-orange);
        }
        /* Weekly Calendar Styles */
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .cal-col {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .cal-day-header {
            background: #f3f4f6;
            padding: 10px 5px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
        }
        .cal-day-body {
            padding: 8px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            max-height: 250px;
        }
        .cal-appt {
            background: white;
            border: 1px solid var(--border-color);
            border-left: 3px solid var(--border-color);
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 11px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        /* Color code the calendar cards based on status */
        .cal-appt.Pending { border-left-color: #f59e0b; }
        .cal-appt.Confirmed { border-left-color: #3b82f6; }
        .cal-appt.Completed { border-left-color: #10b981; }
        .cal-appt.Cancelled { border-left-color: #ef4444; background-color: #fef2f2; }

        .cal-time {
            font-weight: 700;
            color: var(--text-dark);
            display: block;
            margin-bottom: 2px;
        }
        .cal-detail {
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        /* Highlight Today */
        .today-col {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 1px var(--primary-orange);
        }
        .today-col .cal-day-header {
            background: var(--primary-orange);
            color: white;
            border-bottom: none;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-wrench" style="color: var(--primary-orange); font-size: 20px;"></i>
            <div>
                <h2>Name</h2>
                <p>Workshop Management</p>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="admin_dashboard.php"><i class="fa-solid fa-border-all"></i> Dashboard</a></li>
            <li><a href="appointments.php" class="active"><i class="fa-regular fa-calendar-check"></i> Appointments</a></li>
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
                <a href="index.php" class="logout-btn" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
            <div class="app-version">Workshop Manager v1.0</div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-header">
            <div>
                <h1>Appointments</h1>
                <p>Manage and schedule vehicle service appointments</p>
            </div>
        </div>

        <div class="outline-card">
            <?php
            // 1. Calculate the dates for the current week (Monday to Sunday)
            $currentDate = new DateTime();
            $dayOfWeek = $currentDate->format('N'); // 1 (Mon) through 7 (Sun)
            $startOfWeek = clone $currentDate;
            $startOfWeek->modify('-' . ($dayOfWeek - 1) . ' days');
            
            $weekDays = [];
            for ($i = 0; $i < 7; $i++) {
                $day = clone $startOfWeek;
                $day->modify("+$i days");
                $weekDays[] = $day->format('Y-m-d');
            }

            // 2. Fetch all appointments for these 7 days
            $placeholders = implode(',', array_fill(0, count($weekDays), '?'));
            $calQuery = "
                SELECT a.appointment_date, a.appointment_time, u.full_name, v.make_model, a.status
                FROM appointments a
                JOIN users u ON a.user_id = u.id
                JOIN vehicles v ON a.vehicle_id = v.id
                WHERE a.appointment_date IN ($placeholders)
                ORDER BY a.appointment_time ASC
            ";
            $calStmt = $pdo->prepare($calQuery);
            $calStmt->execute($weekDays);
            
            // FETCH_GROUP automatically groups the results by the first column (appointment_date)
            $weekAppointments = $calStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
            ?>

            <div class="calendar-header">
                <h3><i class="fa-regular fa-calendar-week"></i> Weekly Calendar</h3>
                <span style="font-size: 13px; font-weight: 600; color: var(--text-muted); background: #f3f4f6; padding: 4px 10px; border-radius: 12px;">
                    <?php echo date('M d', strtotime($weekDays[0])) . ' — ' . date('M d, Y', strtotime($weekDays[6])); ?>
                </span>
            </div>
            
            <div class="calendar-grid">
                <?php
                $todayDate = date('Y-m-d');
                
                // Loop through the 7 days of the week to create the columns
                foreach ($weekDays as $date) {
                    $dayName = date('D', strtotime($date)); // e.g., Mon, Tue
                    $dayNum = date('d', strtotime($date));  // e.g., 01, 15
                    
                    // Highlight the column if the date is today
                    $isToday = ($date === $todayDate) ? 'today-col' : '';
                    
                    echo "<div class='cal-col {$isToday}'>";
                    echo "<div class='cal-day-header'>{$dayName} {$dayNum}</div>";
                    echo "<div class='cal-day-body'>";
                    
                    // Check if there are appointments for this specific date
                    if (isset($weekAppointments[$date])) {
                        foreach ($weekAppointments[$date] as $appt) {
                            $time = date('g:i A', strtotime($appt['appointment_time']));
                            $statusClass = $appt['status'];
                            
                            echo "<div class='cal-appt {$statusClass}' title='{$appt['full_name']} - {$appt['make_model']}'>";
                            echo "<span class='cal-time'>{$time}</span>";
                            echo "<span class='cal-detail'>{$appt['make_model']}</span>";
                            echo "</div>";
                        }
                    } else {
                        // Empty state for days without appointments
                        echo "<span style='color: #d1d5db; font-size: 11px; text-align: center; display: block; margin-top: 10px;'>No appointments</span>";
                    }
                    
                    echo "</div></div>";
                }
                ?>
            </div>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search Appointments...">
        </div>

        <div class="outline-card">
            <h3><i class="fa-regular fa-calendar-lines"></i> All Appointments</h3>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Service Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch all appointments, joining with users and vehicles tables
                    $query = "
                        SELECT 
                            a.id, a.appointment_date, a.appointment_time, a.service_type, a.status,
                            u.full_name as customer_name,
                            v.make_model, v.plate_number
                        FROM appointments a
                        JOIN users u ON a.user_id = u.id
                        JOIN vehicles v ON a.vehicle_id = v.id
                        ORDER BY a.appointment_date ASC, a.appointment_time ASC
                    ";
                    $stmt = $pdo->query($query);
                    $appointments = $stmt->fetchAll();

                    if (count($appointments) > 0) {
                        foreach ($appointments as $appt) {
                            $formattedDate = date("M d, Y", strtotime($appt['appointment_date']));
                            $formattedTime = date("h:i A", strtotime($appt['appointment_time']));
                            
                            echo "<tr>";
                            echo "<td>APT-" . str_pad($appt['id'], 4, '0', STR_PAD_LEFT) . "</td>";
                            echo "<td><strong>{$formattedDate}</strong><br><span style='color: var(--text-muted); font-size: 11px;'>{$formattedTime}</span></td>";
                            echo "<td>" . htmlspecialchars($appt['customer_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($appt['make_model']) . "<br><span style='color: var(--text-muted); font-size: 11px;'>" . htmlspecialchars($appt['plate_number']) . "</span></td>";
                            echo "<td>" . htmlspecialchars($appt['service_type']) . "</td>";
                            
                            // Status Update Form
                           // Determine text color based on the unified status
                            $statusColor = '#1f2937'; // Default gray
                            if ($appt['status'] == 'Completed') {
                                $statusColor = '#10b981'; // Green
                            } elseif ($appt['status'] == 'In Progress' || $appt['status'] == 'Confirmed') {
                                $statusColor = '#3b82f6'; // Blue
                            } elseif ($appt['status'] == 'Pending') {
                                $statusColor = '#f59e0b'; // Orange
                            } elseif ($appt['status'] == 'On Hold' || $appt['status'] == 'Cancelled') {
                                $statusColor = '#ef4444'; // Red
                            }

                            echo "<td style='color: {$statusColor}; font-weight: 600;'>" . htmlspecialchars($appt['status']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center; padding: 20px; color: var(--text-muted);'>No appointments scheduled yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>