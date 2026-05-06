<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$customerName = $_SESSION['username'] ?? 'Customer Name';
$customerEmail = 'customer@email.com'; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['make_model'])) {
    $user_id = $_SESSION['user_id'];
    $make_model = $_POST['make_model'];
    $year = $_POST['year'];
    $plate_number = $_POST['plate_number'];
    $engine_type = $_POST['engine_type'];
    $vin = !empty($_POST['vin']) ? $_POST['vin'] : null;
    $notes = !empty($_POST['notes']) ? $_POST['notes'] : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO vehicles (user_id, make_model, year, plate_number, engine_type, vin, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $make_model, $year, $plate_number, $engine_type, $vin, $notes]);
        // Refresh to avoid re-submitting data on page reload
        header("Location: my_vehicles.php?success=1"); 
        exit();
    } catch (PDOException $e) {
        $error = "Error adding vehicle: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicles - ServiceHub</title>
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

        .btn-primary {
            background-color: var(--primary-orange);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-primary:hover {
            background-color: #e66a00;
        }

        /* Table Card */
        .table-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .table-card-header {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }

        .table-card-header h2 {
            margin: 0 0 5px 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-card-header p {
            margin: 0;
            font-size: 12px;
            color: var(--text-muted);
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

        /* --- MODAL STYLES --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-box {
            background-color: #fff;
            width: 550px;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .modal-header h2 {
            margin: 0 0 5px 0;
            font-size: 18px;
            color: var(--text-dark);
        }

        .modal-header p {
            margin: 0 0 20px 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 13px;
            outline: none;
            background: #fff;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-orange);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
        }

        .btn-cancel {
            background: #fff;
            border: 1px solid var(--border-color);
            padding: 10px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .btn-cancel:hover {
            background: #f3f4f6;
        }

        .btn-save {
            background: var(--primary-orange);
            border: none;
            padding: 10px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .btn-save:hover {
            background: #e66a00;
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
            <li><a href="my_vehicles.php" class="active"><i class="fa-solid fa-car"></i> My Vehicles</a></li>
            <li><a href="book_appointment.php"><i class="fa-regular fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="service_history.php"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a></li>
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
                <h1>My Vehicles</h1>
                <p>Manage your registered vehicles</p>
            </div>
            <button class="btn-primary" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add Vehicle</button>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <h2><i class="fa-solid fa-car"></i> Registered Vehicles</h2>
                <p>0 vehicles found</p>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Make & Model</th>
                            <th>Year</th>
                            <th>Plate Number</th>
                            <th>Next Service</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC");
                        $stmt->execute([$_SESSION['user_id']]);
                        $vehicles = $stmt->fetchAll();

                        if (count($vehicles) > 0) {
                            foreach ($vehicles as $vehicle) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($vehicle['make_model']) . "</td>";
                                echo "<td>" . htmlspecialchars($vehicle['year']) . "</td>";
                                echo "<td>" . htmlspecialchars($vehicle['plate_number']) . "</td>";
                                echo "<td>-</td>"; // Placeholder for Next Service
                                echo "<td style='color: #10b981;'>Active</td>";
                                echo "<td>
                                        <button class='btn-primary' style='padding: 5px 10px; font-size: 11px;'><i class='fa-solid fa-pen'></i> Edit</button>
                                    </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 20px; color: var(--text-muted);'>No vehicles registered.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="addVehicleModal">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Add New Vehicle</h2>
                <p>Register a new vehicle to your profile.</p>
            </div>
            <form action="" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Make & Model</label>
                        <input type="text" name="make_model" placeholder="e.g. Honda Civic" required>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" placeholder="e.g. 2020" required>
                    </div>
                    <div class="form-group">
                        <label>Plate Number</label>
                        <input type="text" name="plate_number" placeholder="e.g. ABC 1234" required>
                    </div>
                    <div class="form-group">
                        <label>Engine Type</label>
                        <select name="engine_type">
                            <option>Gasoline</option>
                            <option>Diesel</option>
                            <option>Electric</option>
                            <option>Hybrid</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>VIN / Chassis No. (Optional)</label>
                        <input type="text" name="vin" placeholder="Vehicle Identification Number">
                    </div>
                    <div class="form-group full-width">
                        <label>Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any specific details about the vehicle..."></textarea>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Vehicle</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('addVehicleModal');

        function openModal() {
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Close the modal if the user clicks outside of the white box
        window.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>

</body>
</html>