<?php
session_start();
require 'includes/db.php';

// Optional: Security check to ensure only admins can access the data
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

// --- 1. Fetch Appointments Data ---
$apptStats = $pdo->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$appointmentsData = [
    $apptStats['Pending'] ?? 0,
    $apptStats['In Progress'] ?? 0,
    $apptStats['On Hold'] ?? 0,
    $apptStats['Completed'] ?? 0,
    $apptStats['Cancelled'] ?? 0
];

// --- 2. Fetch Revenue Data ---
$last7Days = [];
$revenue7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last7Days[] = date('M d', strtotime($date));
    
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM invoices WHERE status = 'Paid' AND DATE(created_at) = ?");
    $stmt->execute([$date]);
    $dailyRev = $stmt->fetchColumn();
    $revenue7Days[] = $dailyRev ? (float)$dailyRev : 0;
}

// Return as JSON
echo json_encode([
    'appointments' => $appointmentsData,
    'revenueLabels' => $last7Days,
    'revenueData' => $revenue7Days
]);
?>