<?php
// dashboard_api.php
// API endpoints for dashboard functionality
include 'db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'get_stats':
        $student_count_result = $conn->query("SELECT COUNT(*) as total FROM students");
        $student_count = $student_count_result->fetch_assoc()['total'];
        
        $active_violations_result = $conn->query("SELECT COUNT(*) as total FROM violations WHERE status = 'Active'");
        $active_violations_count = $active_violations_result->fetch_assoc()['total'];
        
        $violation_stats_query = "SELECT violation_category, COUNT(*) as count FROM violations WHERE status = 'Active' GROUP BY violation_category";
        $violation_stats_result = $conn->query($violation_stats_query);
        $violation_breakdown = ['Minor' => 0, 'Serious' => 0, 'Major' => 0];
        while ($row = $violation_stats_result->fetch_assoc()) {
            $violation_breakdown[$row['violation_category']] = (int)$row['count'];
        }
        
        echo json_encode([
            'total_students' => (int)$student_count,
            'active_violations' => (int)$active_violations_count,
            'violation_breakdown' => $violation_breakdown
        ]);
        break;
        
    case 'get_recent_activity':
        $recent_activity_query = "
            SELECT 'violation' as type, v.student_id, s.name as student_name, v.violation_type as activity, 
                   DATE_FORMAT(v.violation_date, '%h:%i %p') as time, v.status
            FROM violations v 
            JOIN students s ON v.student_id = s.student_id 
            WHERE v.violation_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            
            UNION ALL
            
            SELECT 'attendance' as type, a.student_id, s.name as student_name, 
                   CONCAT('Attendance Check-in (', a.status, ')') as activity,
                   DATE_FORMAT(TIMESTAMP(a.attendance_date, '09:00:00'), '%h:%i %p') as time, 
                   CASE WHEN a.status = 'Present' THEN 'Complete' ELSE 'Pending' END as status
            FROM attendance a 
            JOIN students s ON a.student_id = s.student_id 
            WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            
            ORDER BY time DESC 
            LIMIT 10";
            
        $result = $conn->query($recent_activity_query);
        $activities = [];
        while($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        echo json_encode($activities);
        break;
        
    case 'get_appointments':
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        $start_date = "$year-$month-01";
        $end_date = date("Y-m-t", strtotime($start_date));
        
        $appointments_query = "
            SELECT a.*, s.name as student_name 
            FROM appointments a 
            LEFT JOIN students s ON a.student_id = s.student_id 
            WHERE a.appointment_date BETWEEN ? AND ?
            ORDER BY a.appointment_date ASC, a.appointment_time ASC";
            
        $stmt = $conn->prepare($appointments_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $appointments = [];
        while($row = $result->fetch_assoc()) {
            $date_key = $row['appointment_date'];
            if (!isset($appointments[$date_key])) {
                $appointments[$date_key] = [];
            }
            $appointments[$date_key][] = $row;
        }
        
        echo json_encode($appointments);
        break;
        
    case 'update_appointment':
        $id = $_POST['id'];
        $title = $_POST['title'];
        $date = $_POST['date'];
        $time = $_POST['time'];
        $location = $_POST['location'];
        $description = $_POST['description'];
        
        $sql = "UPDATE appointments SET title = ?, appointment_date = ?, appointment_time = ?, location = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $title, $date, $time, $location, $description, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;
        
    case 'mark_appointment_done':
        $id = $_POST['id'];
        $sql = "UPDATE appointments SET status = 'completed' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>