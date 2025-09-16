<?php
// dashboard.php
// VIOTRACK System - Enhanced Dashboard with Dynamic Calendar and Violation Tracking
include 'db.php';

// Handle appointment management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_appointment') {
        $appointment_id = $_POST['appointment_id'] ?? '';
        $date = $_POST['date'];
        $title = $_POST['title'];
        $time = $_POST['time'];
        $description = $_POST['description'];
        $type = $_POST['type'];
        $status = $_POST['status'] ?? 'pending';
        
        if (empty($appointment_id)) {
            // Create new appointment
            $sql = "INSERT INTO appointments (appointment_date, title, time, description, type, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $date, $title, $time, $description, $type, $status);
        } else {
            // Update existing appointment
            $sql = "UPDATE appointments SET appointment_date=?, title=?, time=?, description=?, type=?, status=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $date, $title, $time, $description, $type, $status, $appointment_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }
    
    if ($action === 'delete_appointment') {
        $appointment_id = $_POST['appointment_id'];
        $sql = "DELETE FROM appointments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }
    
    if ($action === 'mark_done') {
        $appointment_id = $_POST['appointment_id'];
        $sql = "UPDATE appointments SET status = 'done' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }
    
    if ($action === 'schedule_meeting') {
        $student_id = $_POST['student_id'];
        $student_name = $_POST['student_name'];
        $meeting_date = $_POST['meeting_date'];
        $meeting_time = $_POST['meeting_time'];
        $violation_count = $_POST['violation_count'] ?? 0;
        
        $title = "Meeting: " . $student_name;
        $description = "Violation meeting with " . $student_name . " (" . $student_id . "). Current violations: " . $violation_count;
        $type = 'violation_meeting';
        $status = 'pending';
        
        $sql = "INSERT INTO appointments (appointment_date, title, time, description, type, status, student_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $meeting_date, $title, $meeting_time, $description, $type, $status, $student_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'appointment_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }
    
    if ($action === 'get_appointments') {
        $year = $_POST['year'] ?? date('Y');
        $month = $_POST['month'] ?? date('m');
        
        $sql = "SELECT * FROM appointments WHERE YEAR(appointment_date) = ? AND MONTH(appointment_date) = ? ORDER BY appointment_date, time";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $appointments = [];
        while ($row = $result->fetch_assoc()) {
            $appointments[$row['appointment_date']][] = $row;
        }
        
        echo json_encode(['success' => true, 'appointments' => $appointments]);
        exit;
    }
    
    // Handle recent activities pagination
    if ($action === 'get_recent_activities') {
        $page = $_POST['page'] ?? 1;
        $offset = ($page - 1) * 5;
        
        // Get recent activity with pagination
        $recent_activity_query = "
            SELECT 'violation' as type, v.student_id, s.name, v.violation_type as activity, 
                   v.violation_date as activity_time, v.status
            FROM violations v 
            JOIN students s ON v.student_id = s.student_id 
            WHERE v.violation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            
            UNION ALL
            
            SELECT 'attendance' as type, a.student_id, s.name, 
                   CONCAT('Attendance Check-in (', a.status, ')') as activity,
                   TIMESTAMP(a.attendance_date, '09:00:00') as activity_time, 
                   CASE WHEN a.status = 'Present' THEN 'Complete' ELSE 'Pending' END as status
            FROM attendance a 
            JOIN students s ON a.student_id = s.student_id 
            WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            
            ORDER BY activity_time DESC 
            LIMIT 5 OFFSET ?";
        
        $stmt = $conn->prepare($recent_activity_query);
        $stmt->bind_param("i", $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        // Get total count for pagination
        $count_query = "
            SELECT COUNT(*) as total FROM (
                SELECT v.id FROM violations v 
                JOIN students s ON v.student_id = s.student_id 
                WHERE v.violation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT a.id FROM attendance a 
                JOIN students s ON a.student_id = s.student_id 
                WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ) as combined";
        
        $count_result = $conn->query($count_query);
        $total_count = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_count / 5);
        
        echo json_encode([
            'success' => true, 
            'activities' => $activities,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_count' => $total_count
        ]);
        exit;
    }
}

// Get total students count from database
$student_count_result = $conn->query("SELECT COUNT(*) as total FROM students");
$student_count = $student_count_result->fetch_assoc()['total'];

// Get active violations count
$active_violations_result = $conn->query("SELECT COUNT(*) as total FROM violations WHERE status = 'Active'");
$active_violations_count = $active_violations_result->fetch_assoc()['total'];

// Get violation types count for chart
$violation_types_query = "
    SELECT 
        violation_category,
        COUNT(*) as count
    FROM violations 
    WHERE status = 'Active' 
    GROUP BY violation_category
    ORDER BY count DESC";

$violation_types_result = $conn->query($violation_types_query);
$violation_data = [];
while ($row = $violation_types_result->fetch_assoc()) {
    $violation_data[] = $row;
}

// Get initial recent activity (first 5 records)
$recent_activity_query = "
    SELECT 'violation' as type, v.student_id, s.name, v.violation_type as activity, 
           v.violation_date as activity_time, v.status
    FROM violations v 
    JOIN students s ON v.student_id = s.student_id 
    WHERE v.violation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 'attendance' as type, a.student_id, s.name, 
           CONCAT('Attendance Check-in (', a.status, ')') as activity,
           TIMESTAMP(a.attendance_date, '09:00:00') as activity_time, 
           CASE WHEN a.status = 'Present' THEN 'Complete' ELSE 'Pending' END as status
    FROM attendance a 
    JOIN students s ON a.student_id = s.student_id 
    WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    
    ORDER BY activity_time DESC 
    LIMIT 5";

$recent_activity_result = $conn->query($recent_activity_query);

// Get total count for initial pagination
$total_activities_query = "
    SELECT COUNT(*) as total FROM (
        SELECT v.id FROM violations v 
        JOIN students s ON v.student_id = s.student_id 
        WHERE v.violation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT a.id FROM attendance a 
        JOIN students s ON a.student_id = s.student_id 
        WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ) as combined";

$total_activities_result = $conn->query($total_activities_query);
$total_activities = $total_activities_result->fetch_assoc()['total'];
$total_pages = ceil($total_activities / 5);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIOTRACK</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Enhanced Chart Styles */
        .donut-chart {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: conic-gradient(
                #ef4444 0deg,
                #ef4444 var(--major-angle, 0deg),
                #f59e0b var(--major-angle, 0deg),
                #f59e0b var(--serious-angle, 0deg),
                #10b981 var(--serious-angle, 0deg),
                #10b981 360deg
            );
            position: relative;
            margin: 20px auto;
        }
        
        .donut-chart::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
        }
        
        .chart-center-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            font-weight: 700;
            color: #1e293b;
            z-index: 10;
        }
        
        .chart-total {
            font-size: 32px;
            line-height: 1;
        }
        
        .chart-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        
        .legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .color-minor { background: #10b981; }
        .color-serious { background: #f59e0b; }
        .color-major { background: #ef4444; }
        
        .legend-count {
            margin-left: auto;
            font-weight: 600;
            color: #1e293b;
            background: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        /* Enhanced Pagination Styles */
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .pagination-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            color: #475569;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            min-width: 110px;
            justify-content: center;
        }
        
        .pagination-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(59, 130, 246, 0.3);
        }
        
        .pagination-btn:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.2);
        }
        
        .pagination-btn:disabled {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
            border-color: #e5e7eb;
            transform: none;
            box-shadow: none;
        }
        
        .pagination-btn:disabled svg {
            opacity: 0.5;
        }
        
        .pagination-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .pagination-info svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
        }
        
        /* Loading animation */
        .loading {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-weight: 500;
        }
        
        .loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Modal Styles for Appointment Management */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 30px;
            border: none;
            border-radius: 16px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .close {
            color: #9ca3af;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
            line-height: 1;
        }
        
        .close:hover {
            color: #ef4444;
            transform: scale(1.1);
        }
        
        .modal-title {
            color: #1e293b;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-title svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #f9fafb;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        /* Enhanced Form Buttons Layout with SVG Icons */
        .form-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            justify-content: center;
        }

        .btn-modal {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            min-width: 110px;
            max-width: 130px;
            justify-content: center;
            flex: 1;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-modal svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            flex-shrink: 0;
        }

        /* Responsive layout for smaller screens */
        @media (max-width: 500px) {
            .form-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-modal {
                flex: none;
                width: 100%;
                max-width: none;
            }
        }

        /* Button color schemes */
        .btn-save {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-done {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }

        .btn-done:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(5, 150, 105, 0.3);
        }

        .btn-cancel {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
            color: #4b5563;
        }
        
        /* Enhanced Calendar Styles */
        .calendar-table .appointment {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1d4ed8;
            font-weight: 600;
            position: relative;
            cursor: pointer;
        }
        
        .calendar-table .appointment:hover {
            background: linear-gradient(135deg, #bfdbfe 0%, #93c5fd 100%);
            transform: scale(1.02);
        }
        
        .calendar-table .appointment.done {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }
        
        .calendar-table .appointment.done:hover {
            background: linear-gradient(135deg, #a7f3d0 0%, #6ee7b7 100%);
        }
        
        .appointment-indicator {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ef4444;
        }
        
        .appointment-indicator.done {
            background: #10b981;
        }
        
        .appointment-indicator.meeting {
            background: #f59e0b;
        }
        
        .appointment-count {
            position: absolute;
            bottom: 2px;
            right: 4px;
            font-size: 10px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 2px 6px;
            color: #4b5563;
        }
        
        /* Enhanced Tooltip */
        .tooltip {
            position: absolute;
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            font-size: 13px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            max-width: 300px;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .tooltip.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .tooltip::after {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid #1f2937;
        }
        
        .tooltip-title {
            font-weight: 700;
            margin-bottom: 8px;
            color: #fbbf24;
            font-size: 15px;
        }
        
        .tooltip-time {
            font-size: 12px;
            color: #d1d5db;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .tooltip-description {
            font-size: 12px;
            color: #e5e7eb;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .tooltip-status {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .tooltip-status.pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        
        .tooltip-status.done {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .tooltip-actions {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 11px;
            color: #9ca3af;
        }

        /* Enhanced Add New Appointment Button */
        .add-appointment-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-appointment-btn svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
        }
        
        .add-appointment-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 25px rgba(59, 130, 246, 0.4);
        }

        .activity-period {
            font-size: 14px;
            color: #6b7280;
            font-weight: normal;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="menu-container">
        <div class="menu-header">
            <div class="logo">
                <img src="images/logo.png" alt="College Logo" class="college-logo-img">
            </div>
        </div>
        <div class="menu-items">
            <a href="#" class="menu-item active" data-page="dashboard">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9"/>
                        <rect x="14" y="3" width="7" height="5"/>
                        <rect x="14" y="12" width="7" height="9"/>
                        <rect x="3" y="16" width="7" height="5"/>
                    </svg>
                </div>
                <span class="menu-text">DASHBOARD</span>
            </a>
            <a href="students.php" class="menu-item" data-page="students">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" 
                         width="22" height="22" viewBox="0 0 24 24" 
                         fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" 
                         stroke-linejoin="round">
                        <path d="M4 10l8-4 8 4-8 4-8-4z"/>
                        <path d="M12 14v7"/>
                        <path d="M6 12v5c0 1 2 2 6 2s6-1 6-2v-5"/>
                    </svg>
                </div>
                <span class="menu-text">STUDENTS</span>
            </a>
            <a href="violations.php" class="menu-item" data-page="violations">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                        <path d="M12 9v4"/>
                        <path d="m12 17 .01 0"/>
                    </svg>
                </div>
                <span class="menu-text">VIOLATIONS</span>
            </a>
                        <a href="reports.php" class="menu-item" data-page="reports">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                        <polyline points="14,2 14,8 20,8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10,9 9,9 8,9"/>
                    </svg>
                </div>
                <span class="menu-text">REPORTS</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div id="dashboard" class="page active">
            <h1 class="page-header">Dashboard</h1>
            
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-header">Total Students</div>
                    <div class="stat-number"><?php echo number_format($student_count); ?></div>
                    <div class="stat-icon">👥</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">Active Violations</div>
                    <div class="stat-number"><?php echo number_format($active_violations_count); ?></div>
                    <div class="stat-icon">⚠️</div>
                </div>
            </div>

            <div class="content-row">
                <div class="activity-section">
                    <div class="section-header">
                        Recent Activity
                        <div class="activity-period">Last 7 Days</div>
                    </div>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Student</th>
                                <th>Activity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="activityTableBody">
                            <?php if ($recent_activity_result->num_rows > 0): ?>
                                <?php while($row = $recent_activity_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M j, g:i A', strtotime($row['activity_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']) . ' (' . htmlspecialchars($row['student_id']) . ')'; ?></td>
                                    <td><?php echo htmlspecialchars($row['activity']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php 
                                            echo $row['type'] === 'violation' ? 
                                                ($row['status'] === 'Active' ? 'pending' : 'resolved') : 
                                                ($row['status'] === 'Complete' ? 'active' : 'pending'); 
                                        ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #64748b;">No recent activity in the last 7 days</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Enhanced Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-controls" id="paginationControls">
                        <button class="pagination-btn" id="prevBtn" onclick="loadPage(currentPage - 1)" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                            </svg>
                            Previous
                        </button>
                        <div class="pagination-info" id="paginationInfo">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25A8.966 8.966 0 0118 3.75c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                            </svg>
                            Page <span id="currentPageSpan">1</span> of <span id="totalPagesSpan"><?php echo $total_pages; ?></span>
                        </div>
                        <button class="pagination-btn" id="nextBtn" onclick="loadPage(currentPage + 1)" <?php echo $total_pages <= 1 ? 'disabled' : ''; ?>>
                            Next
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                            </svg>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="chart-section">
                    <div class="section-header">Violation Types</div>
                    <div style="display: flex; flex-direction: column; align-items: center;">
                        <div class="donut-chart" id="violationChart">
                            <div class="chart-center-text">
                                <div class="chart-total" id="chartTotal"><?php echo $active_violations_count; ?></div>
                                <div class="chart-label">Total Active</div>
                            </div>
                        </div>
                        <div class="legend" id="violationLegend">
                            <?php 
                            $total_violations = array_sum(array_column($violation_data, 'count'));
                            $colors = ['Minor' => '#10b981', 'Serious' => '#f59e0b', 'Major' => '#ef4444'];
                            $class_names = ['Minor' => 'color-minor', 'Serious' => 'color-serious', 'Major' => 'color-major'];
                            
                            if (empty($violation_data)): ?>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #e5e7eb;"></div>
                                    <span>No Active Violations</span>
                                    <span class="legend-count">0</span>
                                </div>
                            <?php else:
                                foreach ($violation_data as $violation): ?>
                                <div class="legend-item">
                                    <div class="legend-color <?php echo $class_names[$violation['violation_category']] ?? 'color-minor'; ?>"></div>
                                    <span><?php echo htmlspecialchars($violation['violation_category']); ?> Offenses</span>
                                    <span class="legend-count"><?php echo $violation['count']; ?></span>
                                </div>
                            <?php endforeach; 
                            endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Calendar Card -->
            <div class="calendar-section">
                <div class="section-header">Academic Calendar & Appointments</div>
                
                <div class="calendar-navigation">
                    <button class="calendar-nav-btn" id="prevMonth">← Previous</button>
                    <div class="current-month" id="currentMonth"></div>
                    <button class="calendar-nav-btn" id="nextMonth">Next →</button>
                </div>

                <div class="calendar-card">
                    <table class="calendar-table" id="calendarTable">
                        <thead>
                            <tr>
                                <th>Sun</th>
                                <th>Mon</th>
                                <th>Tue</th>
                                <th>Wed</th>
                                <th>Thu</th>
                                <th>Fri</th>
                                <th>Sat</th>
                            </tr>
                        </thead>
                        <tbody id="calendarBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Tooltip -->
    <div id="tooltip" class="tooltip">
        <div class="tooltip-title"></div>
        <div class="tooltip-time"></div>
        <div class="tooltip-description"></div>
        <div class="tooltip-status"></div>
        <div class="tooltip-actions">Click to edit • Right-click for options</div>
    </div>

    <!-- Enhanced Appointment Modal with SVG Icons -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAppointmentModal()">&times;</span>
            <h2 class="modal-title">
                <svg id="modalIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5"/>
                </svg>
                <span id="modalTitle">Edit Appointment</span>
            </h2>
            <form id="appointmentForm">
                <input type="hidden" id="appointmentId" name="appointment_id">
                <input type="hidden" id="appointmentDate" name="date">
                
                <div class="form-group">
                    <label for="appointmentTitle">Title:</label>
                    <input type="text" id="appointmentTitle" name="title" required placeholder="Enter appointment title...">
                </div>
                
                <div class="form-group">
                    <label for="appointmentTime">Time:</label>
                    <input type="time" id="appointmentTime" name="time" required>
                </div>
                
                <div class="form-group">
                    <label for="appointmentDescription">Description:</label>
                    <textarea id="appointmentDescription" name="description" placeholder="Enter description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="appointmentType">Type:</label>
                    <select id="appointmentType" name="type">
                        <option value="meeting">📅 Meeting</option>
                        <option value="violation_meeting">⚠️ Violation Meeting</option>
                        <option value="event">🎉 Event</option>
                        <option value="academic">📚 Academic</option>
                        <option value="holiday">🏖️ Holiday</option>
                    </select>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn-modal btn-save" onclick="saveAppointment()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
                        </svg>
                        Save
                    </button>
                    <button type="button" class="btn-modal btn-done" onclick="markAsDone()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Done
                    </button>
                    <button type="button" class="btn-modal btn-delete" onclick="deleteAppointment()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                        Delete
                    </button>
                    <button type="button" class="btn-modal btn-cancel" onclick="closeAppointmentModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enhanced Add Appointment Button with SVG Icon -->
    <button class="add-appointment-btn" onclick="openNewAppointmentModal()" title="Add New Appointment">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
    </button>

    <script>
        // Global variables for pagination
        let currentPage = 1;
        let totalPages = <?php echo $total_pages; ?>;
        let isLoading = false;

        // Violation chart data from PHP
        const violationData = <?php echo json_encode($violation_data); ?>;
        
        // Enhanced Calendar System with Database Integration
        class CalendarSystem {
            constructor() {
                this.currentDate = new Date();
                this.currentMonth = this.currentDate.getMonth();
                this.currentYear = this.currentDate.getFullYear();
                this.appointments = {};
                this.tooltip = document.getElementById('tooltip');
                this.selectedDate = null;
                this.selectedAppointment = null;
                
                this.init();
            }

            init() {
                this.loadAppointments();
                this.attachEventListeners();
            }

            async loadAppointments() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_appointments');
                    formData.append('year', this.currentYear);
                    formData.append('month', this.currentMonth + 1);

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.appointments = data.appointments;
                        this.render();
                    }
                } catch (error) {
                    console.error('Error loading appointments:', error);
                    this.render(); // Render empty calendar
                }
            }

            attachEventListeners() {
                document.getElementById('prevMonth').addEventListener('click', () => this.previousMonth());
                document.getElementById('nextMonth').addEventListener('click', () => this.nextMonth());
            }

            previousMonth() {
                this.currentMonth--;
                if (this.currentMonth < 0) {
                    this.currentMonth = 11;
                    this.currentYear--;
                }
                this.loadAppointments();
            }

            nextMonth() {
                this.currentMonth++;
                if (this.currentMonth > 11) {
                    this.currentMonth = 0;
                    this.currentYear++;
                }
                this.loadAppointments();
            }

            render() {
                const monthNames = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];

                document.getElementById('currentMonth').textContent = 
                    `${monthNames[this.currentMonth]} ${this.currentYear}`;

                this.renderCalendarDays();
            }

            renderCalendarDays() {
                const calendarBody = document.getElementById('calendarBody');
                const firstDay = new Date(this.currentYear, this.currentMonth, 1);
                const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
                const daysInMonth = lastDay.getDate();
                const startingDay = firstDay.getDay();

                calendarBody.innerHTML = '';

                let date = 1;
                for (let i = 0; i < 6; i++) {
                    const row = document.createElement('tr');

                    for (let j = 0; j < 7; j++) {
                        const cell = document.createElement('td');
                        
                        if (i === 0 && j < startingDay) {
                            const prevMonth = this.currentMonth === 0 ? 11 : this.currentMonth - 1;
                            const prevYear = this.currentMonth === 0 ? this.currentYear - 1 : this.currentYear;
                            const prevMonthDays = new Date(prevYear, prevMonth + 1, 0).getDate();
                            const prevDate = prevMonthDays - (startingDay - j - 1);
                            
                            cell.textContent = prevDate;
                            cell.classList.add('other-month');
                        } else if (date > daysInMonth) {
                            const nextDate = date - daysInMonth;
                            cell.textContent = nextDate;
                            cell.classList.add('other-month');
                            date++;
                        } else {
                            cell.textContent = date;
                            
                            const dateKey = `${this.currentYear}-${String(this.currentMonth + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                            
                            if (this.appointments[dateKey]) {
                                const appointments = this.appointments[dateKey];
                                const firstAppointment = appointments[0];
                                
                                cell.classList.add('appointment');
                                if (firstAppointment.status === 'done') {
                                    cell.classList.add('done');
                                }
                                
                                const indicator = document.createElement('div');
                                indicator.classList.add('appointment-indicator');
                                if (firstAppointment.status === 'done') {
                                    indicator.classList.add('done');
                                } else if (firstAppointment.type === 'violation_meeting') {
                                    indicator.classList.add('meeting');
                                }
                                cell.appendChild(indicator);
                                
                                // Show count if multiple appointments
                                if (appointments.length > 1) {
                                    const count = document.createElement('div');
                                    count.classList.add('appointment-count');
                                    count.textContent = appointments.length;
                                    cell.appendChild(count);
                                }
                                
                                this.addAppointmentEvents(cell, dateKey, appointments);
                            }
                            
                            // Add double-click to create new appointment
                            cell.addEventListener('dblclick', () => {
                                if (!cell.classList.contains('other-month')) {
                                    this.openNewAppointmentModal(dateKey);
                                }
                            });
                            
                            date++;
                        }
                        
                        row.appendChild(cell);
                    }
                    
                    calendarBody.appendChild(row);
                    
                    if (date > daysInMonth && i > 3) break;
                }
            }

            addAppointmentEvents(cell, dateKey, appointments) {
                cell.addEventListener('mouseenter', (e) => {
                    this.showTooltip(e, appointments);
                });

                cell.addEventListener('mouseleave', () => {
                    this.hideTooltip();
                });

                cell.addEventListener('mousemove', (e) => {
                    this.updateTooltipPosition(e);
                });

                cell.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.openAppointmentModal(appointments[0], dateKey);
                });

                cell.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    this.showContextMenu(e, appointments, dateKey);
                });
            }

            showTooltip(event, appointments) {
                const tooltip = this.tooltip;
                const appointment = appointments[0];
                
                tooltip.querySelector('.tooltip-title').textContent = appointment.title;
                tooltip.querySelector('.tooltip-time').innerHTML = `⏰ ${appointment.time}`;
                tooltip.querySelector('.tooltip-description').textContent = appointment.description || 'No description';
                
                const statusElement = tooltip.querySelector('.tooltip-status');
                statusElement.textContent = appointment.status;
                statusElement.className = `tooltip-status ${appointment.status}`;
                
                if (appointments.length > 1) {
                    tooltip.querySelector('.tooltip-description').textContent += ` (+${appointments.length - 1} more)`;
                }
                
                tooltip.classList.add('show');
                this.updateTooltipPosition(event);
            }

            hideTooltip() {
                this.tooltip.classList.remove('show');
            }

            updateTooltipPosition(event) {
                const tooltip = this.tooltip;
                const rect = tooltip.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                
                let left = event.pageX + 15;
                let top = event.pageY - rect.height - 15;
                
                if (left + rect.width > viewportWidth) {
                    left = event.pageX - rect.width - 15;
                }
                
                if (top < 0) {
                    top = event.pageY + 15;
                }
                
                tooltip.style.left = `${left}px`;
                tooltip.style.top = `${top}px`;
            }

            openAppointmentModal(appointment, dateKey) {
                const modal = document.getElementById('appointmentModal');
                const form = document.getElementById('appointmentForm');
                
                // Populate form
                document.getElementById('appointmentId').value = appointment.id;
                document.getElementById('appointmentDate').value = dateKey;
                document.getElementById('appointmentTitle').value = appointment.title;
                document.getElementById('appointmentTime').value = appointment.time;
                document.getElementById('appointmentDescription').value = appointment.description || '';
                document.getElementById('appointmentType').value = appointment.type;
                
                // Update modal title with appropriate icon
                this.updateModalIcon(appointment.type);
                document.getElementById('modalTitle').textContent = 'Edit Appointment';
                
                modal.style.display = 'block';
                this.selectedAppointment = appointment;
                this.selectedDate = dateKey;
            }

            openNewAppointmentModal(dateKey = null) {
                const modal = document.getElementById('appointmentModal');
                const form = document.getElementById('appointmentForm');
                
                // Clear form
                form.reset();
                document.getElementById('appointmentId').value = '';
                
                // Set date
                if (dateKey) {
                    document.getElementById('appointmentDate').value = dateKey;
                } else {
                    const today = new Date();
                    const todayStr = today.getFullYear() + '-' + 
                                  String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                                  String(today.getDate()).padStart(2, '0');
                    document.getElementById('appointmentDate').value = todayStr;
                }
                
                // Set default time
                document.getElementById('appointmentTime').value = '09:00';
                
                // Update modal title
                this.updateModalIcon('meeting');
                document.getElementById('modalTitle').textContent = 'New Appointment';
                
                modal.style.display = 'block';
                this.selectedAppointment = null;
                this.selectedDate = dateKey;
            }

            updateModalIcon(type) {
                const modalIcon = document.getElementById('modalIcon');
                const iconPaths = {
                    'meeting': 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5',
                    'violation_meeting': 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
                    'event': 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    'academic': 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
                    'holiday': 'M12 3v2.25m6.364.386l-1.591 1.591M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z'
                };
                
                modalIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="${iconPaths[type] || iconPaths['meeting']}"/>`;
            }
        }

        // Pagination Functions
        async function loadPage(page) {
            if (isLoading || page < 1 || page > totalPages) {
                return;
            }

            isLoading = true;
            const tbody = document.getElementById('activityTableBody');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            // Show loading state
            tbody.innerHTML = '<tr><td colspan="4" class="loading">Loading activities...</td></tr>';
            prevBtn.disabled = true;
            nextBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'get_recent_activities');
                formData.append('page', page);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    currentPage = data.current_page;
                    totalPages = data.total_pages;
                    
                    // Update table content
                    tbody.innerHTML = '';
                    
                    if (data.activities.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #64748b;">No activities found for this page</td></tr>';
                    } else {
                        data.activities.forEach(activity => {
                            const row = document.createElement('tr');
                            
                            const statusClass = activity.type === 'violation' 
                                ? (activity.status === 'Active' ? 'pending' : 'resolved')
                                : (activity.status === 'Complete' ? 'active' : 'pending');
                                
                            row.innerHTML = `
                                <td>${new Date(activity.activity_time).toLocaleDateString('en-US', { 
                                    month: 'short', 
                                    day: 'numeric', 
                                    hour: 'numeric', 
                                    minute: '2-digit',
                                    hour12: true 
                                })}</td>
                                <td>${activity.name} (${activity.student_id})</td>
                                <td>${activity.activity}</td>
                                <td>
                                    <span class="status-badge status-${statusClass}">
                                        ${activity.status}
                                    </span>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                    }
                    
                    // Update pagination info
                    document.getElementById('currentPageSpan').textContent = currentPage;
                    document.getElementById('totalPagesSpan').textContent = totalPages;
                    
                    // Update button states
                    prevBtn.disabled = currentPage <= 1;
                    nextBtn.disabled = currentPage >= totalPages;
                    
                    // Show/hide pagination if only one page
                    const paginationControls = document.getElementById('paginationControls');
                    if (paginationControls) {
                        paginationControls.style.display = totalPages > 1 ? 'flex' : 'none';
                    }
                } else {
                    throw new Error(data.error || 'Failed to load activities');
                }
            } catch (error) {
                console.error('Error loading activities:', error);
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #ef4444;">Error loading activities. Please try again.</td></tr>';
            } finally {
                isLoading = false;
            }
        }

        // Update violation chart based on data
        function updateViolationChart() {
            const chart = document.getElementById('violationChart');
            const total = violationData.reduce((sum, item) => sum + parseInt(item.count), 0);
            
            if (total === 0) {
                chart.style.background = '#f3f4f6';
                return;
            }
            
            let currentAngle = 0;
            let gradientStops = [];
            
            const colors = {
                'Major': '#ef4444',
                'Serious': '#f59e0b', 
                'Minor': '#10b981'
            };
            
            // Sort by severity (Major -> Serious -> Minor)
            const sortedData = violationData.sort((a, b) => {
                const order = {'Major': 0, 'Serious': 1, 'Minor': 2};
                return order[a.violation_category] - order[b.violation_category];
            });
            
            sortedData.forEach((item, index) => {
                const percentage = (item.count / total) * 100;
                const angle = (percentage / 100) * 360;
                const color = colors[item.violation_category] || '#6b7280';
                
                gradientStops.push(`${color} ${currentAngle}deg ${currentAngle + angle}deg`);
                currentAngle += angle;
            });
            
            if (gradientStops.length > 0) {
                chart.style.background = `conic-gradient(${gradientStops.join(', ')})`;
            }
        }

        // Modal Functions
        function closeAppointmentModal() {
            document.getElementById('appointmentModal').style.display = 'none';
        }

        function openNewAppointmentModal() {
            calendar.openNewAppointmentModal();
        }

        async function saveAppointment() {
            const form = document.getElementById('appointmentForm');
            const formData = new FormData(form);
            formData.append('action', 'save_appointment');
            formData.append('status', 'pending');

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    closeAppointmentModal();
                    calendar.loadAppointments();
                    showNotification('Appointment saved successfully!', 'success');
                } else {
                    showNotification('Error saving appointment: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error saving appointment', 'error');
            }
        }

        async function deleteAppointment() {
            const appointmentId = document.getElementById('appointmentId').value;
            if (!appointmentId) {
                showNotification('No appointment selected', 'error');
                return;
            }

            if (!confirm('Are you sure you want to delete this appointment?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_appointment');
            formData.append('appointment_id', appointmentId);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    closeAppointmentModal();
                    calendar.loadAppointments();
                    showNotification('Appointment deleted successfully!', 'success');
                } else {
                    showNotification('Error deleting appointment: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error deleting appointment', 'error');
            }
        }

        async function markAsDone() {
            const appointmentId = document.getElementById('appointmentId').value;
            if (!appointmentId) {
                showNotification('No appointment selected', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'mark_done');
            formData.append('appointment_id', appointmentId);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    closeAppointmentModal();
                    calendar.loadAppointments();
                    showNotification('Appointment marked as done!', 'success');
                } else {
                    showNotification('Error updating appointment: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error updating appointment', 'error');
            }
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 10px;
                color: white;
                font-weight: 600;
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
                max-width: 300px;
                word-wrap: break-word;
            `;

            if (type === 'success') {
                notification.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            } else if (type === 'error') {
                notification.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
            } else {
                notification.style.background = 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)';
            }

            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100%);
                }
            }
        `;
        document.head.appendChild(style);

        // Navigation system
        function showPage(pageId) {
            document.querySelectorAll('.page').forEach(page => {
                page.classList.remove('active');
            });
            
            document.getElementById(pageId).classList.add('active');
            
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            
            document.querySelector(`[data-page="${pageId}"]`).classList.add('active');
            
            const pageTitles = {
                dashboard: 'VIOTRACK',
                students: 'Student Management - PHC',
                violations: 'Violation Management - PHC',
                track: 'Track Location - PHC',
                reports: 'Reports & Analytics - PHC'
            };
            
            document.title = pageTitles[pageId] || 'PHC System';
        }

        // Global calendar instance
        let calendar;

        // Initialize application
        document.addEventListener('DOMContentLoaded', function() {
            const activePage = document.querySelector('.page.active');
            if (!activePage) {
                showPage('dashboard');
            }
            
            // Initialize calendar
            calendar = new CalendarSystem();
            
            // Update violation chart on load
            updateViolationChart();
            
            // Add click event listeners to menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.addEventListener('click', function() {
                    const pageId = this.getAttribute('data-page');
                    if (pageId) {
                        showPage(pageId);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('appointmentModal');
                if (event.target === modal) {
                    closeAppointmentModal();
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAppointmentModal();
                }
                
                // Navigation shortcuts
                const menuItems = document.querySelectorAll('.menu-item');
                const currentActive = document.querySelector('.menu-item.active');
                const currentIndex = Array.from(menuItems).indexOf(currentActive);
                
                if (e.key === 'ArrowDown' && currentIndex < menuItems.length - 1) {
                    e.preventDefault();
                    const nextItem = menuItems[currentIndex + 1];
                    const pageId = nextItem.getAttribute('data-page');
                    showPage(pageId);
                } else if (e.key === 'ArrowUp' && currentIndex > 0) {
                    e.preventDefault();
                    const prevItem = menuItems[currentIndex - 1];
                    const pageId = prevItem.getAttribute('data-page');
                    showPage(pageId);
                }
            });
        });

        // Function to add appointment from violations page
        window.addViolationMeeting = function(studentName, studentId, violationCount) {
            const today = new Date();
            const dateStr = today.getFullYear() + '-' + 
                          String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                          String(today.getDate()).padStart(2, '0');
            
            calendar.openNewAppointmentModal(dateStr);
            
            // Pre-fill form with violation meeting data
            setTimeout(() => {
                document.getElementById('appointmentTitle').value = `Meeting: ${studentName}`;
                document.getElementById('appointmentDescription').value = `Violation meeting with ${studentName} (${studentId}). Current violations: ${violationCount}`;
                document.getElementById('appointmentType').value = 'violation_meeting';
            }, 100);
        };
    </script>
</body>
</html>