<?php
session_start();
require 'includes/db.php'; // Connect to the database

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customerName = $_SESSION['username'] ?? 'Customer Name';
$customerEmail = 'customer@email.com'; 

// 1. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = $_POST['vehicle'];
    $service_type = $_POST['service'];
    $appointment_date = $_POST['date'];
    $appointment_time = $_POST['time'];
    $mechanic_preference = $_POST['mechanic'] ?? null;
    $notes = $_POST['notes'] ?? null;

    try {
        // Insert the booking into the database with a 'Pending' status
        $stmt = $pdo->prepare("
            INSERT INTO appointments 
            (user_id, vehicle_id, service_type, appointment_date, appointment_time, mechanic_preference, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        
        $stmt->execute([
            $user_id, 
            $vehicle_id, 
            $service_type, 
            $appointment_date, 
            $appointment_time, 
            $mechanic_preference, 
            $notes
        ]);
        
        // Success! Redirect the user to their history or show a success message
        $success_message = "Appointment booked successfully!";
    } catch (PDOException $e) {
        $error_message = "Error booking appointment: " . $e->getMessage();
    }
}

// 2. Fetch Customer's Vehicles for the dropdown
$stmt = $pdo->prepare("SELECT id, make_model, plate_number FROM vehicles WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - ServiceHub</title>
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

        .sidebar-header h2 { margin: 0; font-size: 18px; font-weight: 600; }
        .sidebar-header p { margin: 0; font-size: 11px; color: #8b949e; }

        .nav-links {
            list-style: none;
            padding: 15px 0;
            margin: 0;
            flex-grow: 1;
        }

        .nav-links li { padding: 5px 20px; }
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
        .nav-links a i { width: 20px; margin-right: 10px; font-size: 16px; }
        .nav-links a:hover { background-color: var(--sidebar-hover); color: #fff; }
        .nav-links a.active { background-color: var(--primary-orange); color: #fff; font-weight: bold; }

        .user-profile-container { border-top: 1px solid #1f2937; padding: 15px 20px; }
        .user-profile { display: flex; align-items: center; gap: 10px; }
        .avatar { width: 35px; height: 35px; background-color: var(--primary-orange); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 16px; }
        .user-info { flex-grow: 1; }
        .user-info h4 { margin: 0; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .customer-badge { background-color: #3b82f6; color: white; font-size: 9px; padding: 2px 6px; border-radius: 10px; }
        .user-info p { margin: 2px 0 0 0; font-size: 10px; color: #8b949e; }
        .logout-btn { color: #c9d1d9; text-decoration: none; transition: 0.2s; }
        .logout-btn:hover { color: #ff7b72; }

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
        .top-header h1 { margin: 0 0 5px 0; font-size: 22px; color: var(--text-dark); }
        .top-header p { margin: 0; color: var(--text-muted); font-size: 13px; }

        /* Layout */
        .form-layout {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .form-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            flex: 2;
        }
        .help-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            flex: 1;
            text-align: center;
        }

        /* --- MULTI-STEP PROGRESS BAR --- */
        .stepper-wrapper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .stepper-tab {
            flex: 1;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            padding-bottom: 10px;
            border-bottom: 3px solid var(--border-color);
            transition: 0.3s;
        }
        .stepper-tab.active {
            color: var(--primary-orange);
            border-bottom-color: var(--primary-orange);
        }

        .step-section h2 {
            margin: 0 0 20px 0;
            font-size: 18px;
            color: var(--text-dark);
        }

        /* Form Elements */
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 13px;
            outline: none;
            background: #fff;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary-orange);
        }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }

        /* Buttons */
        .btn-submit {
            background-color: var(--primary-orange);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            flex: 1;
        }
        .btn-submit:hover { background-color: #e66a00; }
        
        .btn-outline {
            background: white;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        .btn-outline:hover { background: #f3f4f6; }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }

        /* Help Card */
        .help-card i { font-size: 40px; color: #3b82f6; margin-bottom: 15px; }
        .help-card h3 { margin: 0 0 10px 0; font-size: 16px; }
        .help-card p { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }

        /* Summary Box in Step 3 */
        .summary-box {
            background: #f9fafb;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .summary-box p {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: var(--text-dark);
        }
        .summary-box p:last-child { margin: 0; }
        .summary-value { font-weight: bold; color: var(--primary-orange); }
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
            <li><a href="book_appointment.php" class="active"><i class="fa-regular fa-calendar-plus"></i> Book Appointment</a></li>
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
                <h1>Book Appointment</h1>
                <p>Schedule a service for your vehicle in just 3 easy steps</p>
            </div>
        </div>
               <?php if (isset($success_message)): ?>
            <div style="background-color: #dcfce7; color: #166534; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                <i class="fa-solid fa-circle-check"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div style="background-color: #fee2e2; color: #991b1b; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #fecaca;">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="form-layout">
            <div class="form-card">
                
                <div class="stepper-wrapper">
                    <div class="stepper-tab active" id="tab-1">1. Vehicle & Service</div>
                    <div class="stepper-tab" id="tab-2">2. Date & Time</div>
                    <div class="stepper-tab" id="tab-3">3. Review</div>
                </div>

                <form action="" method="POST" id="bookingForm">
                    
                    <div class="step-section" id="step-1">
                        <h2>Service Details</h2>
                        <div class="form-group">
                            <label>Select Vehicle</label>
                           <select id="vehicleSelect" name="vehicle" required>
                                <option value="">Choose a registered vehicle</option>
                                <?php if (empty($user_vehicles)): ?>
                                    <option value="" disabled>No vehicles found. Please add a vehicle first.</option>
                                <?php else: ?>
                                    <?php foreach ($user_vehicles as $v): ?>
                                        <option value="<?php echo $v['id']; ?>">
                                            <?php echo htmlspecialchars($v['make_model'] . ' (' . $v['plate_number'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Service Type</label>
                            <select id="serviceSelect" name="service" required>
                                <option value="">Choose the required service</option>
                                <option value="General Maintenance">General Maintenance</option>
                                <option value="Oil Change">Oil Change</option>
                                <option value="Engine Repair">Engine Repair</option>
                                <option value="Tire Replacement">Tire Replacement & Balancing</option>
                            </select>
                        </div>
                        <div class="form-actions" style="justify-content: flex-end;">
                            <button type="button" class="btn-submit" onclick="nextStep(2)">Next Step <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <div class="step-section" id="step-2" style="display: none;">
                        <h2>Schedule Appointment</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Preferred Date</label>
                                <input type="date" id="dateInput" name="date" required>
                            </div>
                            <div class="form-group">
                                <label>Preferred Time</label>
                                <input type="time" id="timeInput" name="time" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Mechanic Preference (Optional)</label>
                            <select name="mechanic">
                                <option value="">Any Available Mechanic</option>
                                <option value="John">John (Specialist)</option>
                                <option value="Mike">Mike (General Auto)</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-outline" onclick="prevStep(1)"><i class="fa-solid fa-arrow-left"></i> Back</button>
                            <button type="button" class="btn-submit" onclick="nextStep(3)">Next Step <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <div class="step-section" id="step-3" style="display: none;">
                        <h2>Review & Confirm</h2>
                        
                        <div class="summary-box">
                            <p>Vehicle: <span class="summary-value" id="sum-vehicle">-</span></p>
                            <p>Service: <span class="summary-value" id="sum-service">-</span></p>
                            <p>Schedule: <span class="summary-value" id="sum-datetime">-</span></p>
                        </div>

                        <div class="form-group">
                            <label>Additional Notes or Instructions</label>
                            <textarea name="notes" rows="4" placeholder="Describe any specific issues, sounds, or requests..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-outline" onclick="prevStep(2)"><i class="fa-solid fa-arrow-left"></i> Back</button>
                            <button type="submit" class="btn-submit"><i class="fa-solid fa-check"></i> Confirm Booking</button>
                        </div>
                    </div>

                </form>
            </div>

            <div class="help-card">
                <i class="fa-regular fa-circle-question"></i>
                <h3>Need Help?</h3>
                <p>If you're unsure about what service you need or have questions regarding availability, you can contact our support team directly.</p>
                <a href="support.php" class="btn-outline" style="text-decoration:none; display:block;">Contact Support</a>
            </div>
        </div>
    </main>

    <script>
        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.step-section').forEach(el => el.style.display = 'none');
            
            // Show requested step
            document.getElementById('step-' + step).style.display = 'block';
            
            // Update Tab Styling
            for (let i = 1; i <= 3; i++) {
                const tab = document.getElementById('tab-' + i);
                tab.classList.remove('active');
                
                if (i < step) {
                    // Completed steps
                    tab.style.borderBottomColor = 'var(--primary-orange)';
                    tab.style.color = 'var(--primary-orange)';
                } else if (i === step) {
                    // Current active step
                    tab.classList.add('active');
                    tab.style.borderBottomColor = ''; // Resets inline style so class takes over
                    tab.style.color = '';
                } else {
                    // Future steps
                    tab.style.borderBottomColor = 'var(--border-color)';
                    tab.style.color = 'var(--text-muted)';
                }
            }
        }

        function nextStep(step) {
            // --- NEW: Validate Step 1 before moving to Step 2 ---
            if (step === 2) {
                const vehicle = document.getElementById('vehicleSelect').value;
                const service = document.getElementById('serviceSelect').value;
                if (!vehicle || !service) {
                    alert("Please select both a vehicle and a service type before proceeding.");
                    return; // Stop the code here so they can't advance
                }
            }

            // --- NEW: Validate Step 2 before moving to Step 3 ---
            if (step === 3) {
                const date = document.getElementById('dateInput').value;
                const time = document.getElementById('timeInput').value;
                if (!date || !time) {
                    alert("Please select a preferred date and time.");
                    return; // Stop the code here so they can't advance
                }

                // Populate the Review summary text when moving to step 3
                const vSelect = document.getElementById('vehicleSelect');
                const sSelect = document.getElementById('serviceSelect');
                
                const vehicleName = vSelect.options[vSelect.selectedIndex].text;
                const serviceName = sSelect.options[sSelect.selectedIndex].text;

                document.getElementById('sum-vehicle').innerText = vehicleName;
                document.getElementById('sum-service').innerText = serviceName;
                document.getElementById('sum-datetime').innerText = `${date} at ${time}`;
            }
            
            showStep(step);
        }

        function prevStep(step) {
            showStep(step);
        }
    </script>

</body>
</html>