<?php
// violationss.php
// Enhanced Student Violations Tracker with Database Integration
include 'db.php';

// Get student ID from QR scan or URL parameter
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$student_data = null;
$current_violations = [];
$attendance_records = [];

// Fetch student data if student_id is provided
if (!empty($student_id)) {
    $student_result = $conn->query("SELECT * FROM students WHERE student_id = '$student_id' LIMIT 1");
    if ($student_result->num_rows > 0) {
        $student_data = $student_result->fetch_assoc();
        
        // Fetch current active violations
        $violations_result = $conn->query("SELECT * FROM violations WHERE student_id = '$student_id' AND status = 'Active' ORDER BY violation_date DESC");
        while ($row = $violations_result->fetch_assoc()) {
            $current_violations[] = $row;
        }
        
        // Fetch recent attendance (last 5 records)
        $attendance_result = $conn->query("SELECT * FROM attendance WHERE student_id = '$student_id' ORDER BY attendance_date DESC LIMIT 5");
        while ($row = $attendance_result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'record_violations' && !empty($student_id)) {
        $violations = json_decode($_POST['violations'], true);
        $recorded_by = $_POST['recorded_by'] ?? 'System';
        
        $success_count = 0;
        foreach ($violations as $violation) {
            $violation_text = $conn->real_escape_string($violation['text']);
            $category = $conn->real_escape_string($violation['category']);
            $notes = $conn->real_escape_string($violation['notes'] ?? '');
            
            $sql = "INSERT INTO violations (student_id, violation_type, violation_category, recorded_by, notes) 
                    VALUES ('$student_id', '$violation_text', '$category', '$recorded_by', '$notes')";
            
            if ($conn->query($sql)) {
                $success_count++;
            }
        }
        
        echo json_encode(['success' => true, 'count' => $success_count]);
        exit;
    }
    
    if ($action === 'record_attendance' && !empty($student_id)) {
        $status = $_POST['status'];
        $date = date('Y-m-d');
        
        // Check if attendance already exists for today
        $check_sql = "SELECT id FROM attendance WHERE student_id = '$student_id' AND attendance_date = '$date'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $sql = "UPDATE attendance SET status = '$status' WHERE student_id = '$student_id' AND attendance_date = '$date'";
        } else {
            // Insert new record
            $sql = "INSERT INTO attendance (student_id, attendance_date, status) VALUES ('$student_id', '$date', '$status')";
        }
        
        $success = $conn->query($sql);
        echo json_encode(['success' => $success]);
        exit;
    }
    
    if ($action === 'resolve_violation' && !empty($student_id)) {
        $violation_id = $_POST['violation_id'];
        $sql = "UPDATE violations SET status = 'Resolved' WHERE id = '$violation_id' AND student_id = '$student_id'";
        $success = $conn->query($sql);
        echo json_encode(['success' => $success]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIOTRACK</title>
    <link rel="stylesheet" href="violations.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Enhanced Modern Styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #1a202c;
        }
        
        /* Enhanced Back Button */
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .back-button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
        }
        
        .back-button:active {
            transform: translateY(-1px) scale(0.98);
        }
        
        .back-arrow {
            font-size: 18px;
            transition: transform 0.3s ease;
        }
        
        .back-button:hover .back-arrow {
            transform: translateX(-4px);
        }
        
        /* Enhanced Header */
        .page-header {
            text-align: center;
            padding: 60px 20px 40px 160px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 40px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
            letter-spacing: -0.5px;
        }
        
        /* Enhanced Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 40px;
        }
        
        /* Enhanced Profile Card */
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
            animation: slideInLeft 0.6s ease-out;
        }
        
        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899, #f59e0b);
        }
        
        .profile-img {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: 700;
            margin: 0 auto 25px;
            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .profile-img::after {
            content: '';
            position: absolute;
            inset: -3px;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6, #ec4899, #f59e0b);
            border-radius: 50%;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .profile-card:hover .profile-img {
            transform: scale(1.08) rotate(10deg);
            box-shadow: 0 20px 60px rgba(59, 130, 246, 0.4);
        }
        
        .profile-card:hover .profile-img::after {
            opacity: 1;
        }
        
        .profile-name {
            font-size: 22px;
            font-weight: 700;
            color: #1a202c;
            text-align: center;
            margin-bottom: 15px;
            letter-spacing: -0.3px;
        }
        
        .student-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.8;
            border: 1px solid #e2e8f0;
        }
        
        /* Enhanced Action Buttons */
        .action-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 16px 28px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 160px;
            text-transform: none;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }
        
        .action-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 48px rgba(59, 130, 246, 0.4);
        }
        
        .action-btn:hover::before {
            left: 100%;
        }
        
        .action-btn:active {
            transform: translateY(-1px) scale(0.98);
        }
        
        .action-btn.print-btn {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 8px 32px rgba(5, 150, 105, 0.3);
        }
        
        .action-btn.print-btn:hover {
            box-shadow: 0 12px 48px rgba(5, 150, 105, 0.4);
        }
        
        .action-btn.meeting-btn {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
        }
        
        .action-btn.meeting-btn:hover {
            box-shadow: 0 12px 48px rgba(139, 92, 246, 0.4);
        }
        
        /* Enhanced Attendance Buttons */
        .attendance-btn {
            padding: 14px 24px;
            border: none;
            border-radius: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .attendance-btn.present {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .attendance-btn.absent {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .attendance-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }
        
        .attendance-btn.active {
            transform: scale(1.1);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.5);
        }
        
        /* Enhanced Violations Panel */
        .violations-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideInRight 0.6s ease-out;
        }
        
        .violations-panel h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 30px;
            text-align: center;
            letter-spacing: -0.4px;
        }
        
        /* Enhanced Category Buttons */
        .category {
            margin-bottom: 20px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .category-btn {
            width: 100%;
            padding: 20px 25px;
            border: none;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #374151;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 1px solid #e5e7eb;
        }
        
        .category.minor .category-btn {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border-bottom-color: #a7f3d0;
        }
        
        .category.serious .category-btn {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: #92400e;
            border-bottom-color: #fde68a;
        }
        
        .category.major .category-btn {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-bottom-color: #fecaca;
        }
        
        .category-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .violations-list {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
        }
        
        .category.open .violations-list {
            max-height: 800px;
        }
        
        .violation-item {
            padding: 16px 25px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            color: #4b5563;
            line-height: 1.5;
        }
        
        .violation-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: #1e40af;
            font-weight: 500;
            transform: translateX(8px);
            border-left: 4px solid #3b82f6;
            padding-left: 21px;
        }
        
        .violation-item:last-child {
            border-bottom: none;
        }
        
        /* Enhanced Selected Section */
        .selected-section {
            margin-top: 40px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .selected-section h3 {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .record-btn {
            width: 100%;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border: none;
            padding: 18px 30px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(220, 38, 38, 0.3);
            display: none;
        }
        
        .record-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 48px rgba(220, 38, 38, 0.4);
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
        }
        
        /* Enhanced Selected Item */
        .selected-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .selected-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        }
        
        .violation-text {
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
        }
        
        .violation-timestamp {
            font-size: 12px;
            color: #6b7280;
        }
        
        .remove-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .remove-btn:hover {
            transform: scale(1.2) rotate(90deg);
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.4);
        }
        
        /* Enhanced Current Violations Card */
        .current-violations-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .current-violations-card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .violations-count {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            min-width: 24px;
            text-align: center;
        }
        
        /* Student Search Section */
        .student-not-found {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            margin: 40px auto;
            max-width: 600px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeInUp 0.6s ease-out;
        }
        
        .student-not-found h2 {
            font-size: 24px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 15px;
        }
        
        .manual-entry {
            margin-top: 30px;
        }
        
        .manual-entry h3 {
            font-size: 18px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }
        
        .form-group input {
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            width: 100%;
            max-width: 300px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
            transform: scale(1.02);
        }
        
        .search-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.3);
        }
        
        .search-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 48px rgba(59, 130, 246, 0.4);
        }
        
        /* Animations */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .container {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .back-button {
                position: relative;
                top: auto;
                left: auto;
                margin: 15px;
                width: calc(100% - 30px);
                justify-content: center;
            }
            
            .page-header {
                padding: 30px 20px 20px;
                text-align: center;
            }
            
            .page-header h1 {
                font-size: 24px;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .profile-card,
            .violations-panel {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="students.php" class="back-button" onclick="return confirmBack()">
        <span class="back-arrow">←</span>
        <span>Back to Students</span>
    </a>
    
    <header class="page-header">
        <h1>VioTrack - Professional Student Management System</h1>
    </header>
    
    <?php if (!$student_data && !empty($student_id)): ?>
        <div class="student-not-found">
            <h2>⚠️ Student Not Found</h2>
            <p>Student ID "<?php echo htmlspecialchars($student_id); ?>" was not found in our database.</p>
            <div class="manual-entry">
                <h3>🔍 Manual Student Search</h3>
                <div class="form-group">
                    <label for="manual_student_id">Enter Student ID:</label>
                    <input type="text" id="manual_student_id" placeholder="Type student ID here..." autocomplete="off">
                    <button class="search-btn" onclick="searchStudent()">🔍 Search Student</button>
                </div>
            </div>
        </div>
    <?php elseif (!$student_data): ?>
        <div class="student-not-found">
            <h2>🎯 Welcome to VioTrack</h2>
            <p>Please scan a QR code or enter a student ID to begin tracking violations and attendance.</p>
            <div class="manual-entry">
                <h3>🔍 Student ID Search</h3>
                <div class="form-group">
                    <label for="manual_student_id">Enter Student ID:</label>
                    <input type="text" id="manual_student_id" placeholder="Type student ID here..." autocomplete="off">
                    <button class="search-btn" onclick="searchStudent()">🔍 Search Student</button>
                </div>
            </div>
        </div>
    <?php else: ?>
    <div class="container">
        <div class="left-panel">
            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-img"><?php echo strtoupper(substr($student_data['name'], 0, 1)); ?></div>
                <div class="profile-name"><?php echo htmlspecialchars($student_data['name']); ?></div>
                <div class="student-info">
                    <strong>ID:</strong> <?php echo htmlspecialchars($student_data['student_id']); ?><br>
                    <strong>Grade & Section:</strong> <?php echo htmlspecialchars($student_data['grade']); ?> - <?php echo htmlspecialchars($student_data['section']); ?><br>
                    <strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($student_data['status']); ?>">
                        <?php echo htmlspecialchars($student_data['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Current Violations Card -->
            <div class="current-violations-card">
                <h3>
                    📋 Active Violations 
                    <?php if (count($current_violations) > 0): ?>
                    <span class="violations-count"><?php echo count($current_violations); ?></span>
                    <?php endif; ?>
                </h3>
                <div id="currentViolationsList">
                    <?php if (empty($current_violations)): ?>
                        <div class="no-violations">✅ No Active Violations</div>
                    <?php else: ?>
                        <?php foreach ($current_violations as $violation): ?>
                        <div class="current-violation-item">
                            <div class="violation-info">
                                <div class="violation-text"><?php echo htmlspecialchars($violation['violation_type']); ?></div>
                                <div class="violation-date">
                                    📅 <?php echo date('M j, Y g:i A', strtotime($violation['violation_date'])); ?>
                                    <span class="violation-category category-<?php echo strtolower($violation['violation_category']); ?>">
                                        <?php echo $violation['violation_category']; ?>
                                    </span>
                                </div>
                            </div>
                            <button class="remove-current-btn" onclick="resolveViolation(<?php echo $violation['id']; ?>)" title="Mark as Resolved">✓</button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <div class="attendance-buttons">
                        <button class="attendance-btn present" id="presentBtn">✓ Present</button>
                        <button class="attendance-btn absent" id="absentBtn">✗ Absent</button>
                    </div>
                    
                    <?php if (!empty($attendance_records)): ?>
                    <div class="attendance-history">
                        <h4>📊 Recent Attendance</h4>
                        <?php foreach (array_slice($attendance_records, 0, 3) as $record): ?>
                        <div class="attendance-record">
                            <span>📅 <?php echo date('M j', strtotime($record['attendance_date'])); ?></span>
                            <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                                <?php echo $record['status']; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons-row">
                        <button class="action-btn print-btn" id="printBtn">🖨️ Print Record</button>
                        <button class="action-btn meeting-btn" id="meetingBtn">📅 Schedule Meeting</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="violations-panel">
            <h2>⚡ Violation Categories</h2>
            
            <div class="category minor">
                <button class="category-btn">
                    🟢 Minor Offenses
                    <span class="arrow">▼</span>
                </button>
                <div class="violations-list">
                    <div class="violation-item" data-category="Minor">Uniform not worn or worn improperly</div>
                    <div class="violation-item" data-category="Minor">School ID not worn properly</div>
                    <div class="violation-item" data-category="Minor">Playing non-educational games</div>
                    <div class="violation-item" data-category="Minor">Sleeping during class or activities</div>
                    <div class="violation-item" data-category="Minor">Borrowing or lending money</div>
                    <div class="violation-item" data-category="Minor">Loitering or shouting in hallways</div>
                    <div class="violation-item" data-category="Minor">Leaving classroom messy</div>
                    <div class="violation-item" data-category="Minor">Use of mobile gadgets without permission</div>
                    <div class="violation-item" data-category="Minor">Excessive jewelry or inappropriate appearance</div>
                    <div class="violation-item" data-category="Minor">Wearing inappropriate clothing</div>
                    <div class="violation-item" data-category="Minor">Using vulgar language</div>
                    <div class="violation-item" data-category="Minor">Minor bullying or teasing</div>
                    <div class="violation-item" data-category="Minor">Fighting or causing disturbances</div>
                    <div class="violation-item" data-category="Minor">Horseplay inside classroom</div>
                    <div class="violation-item" data-category="Minor">Making inappropriate jokes</div>
                    <div class="violation-item" data-category="Minor">Not completing clearance requirements</div>
                    <div class="violation-item" data-category="Minor">Taking food without consent</div>
                    <div class="violation-item" data-category="Minor">Not joining required school activities</div>
                    <div class="violation-item" data-category="Minor">Chewing gum or spitting in public</div>
                    <div class="violation-item" data-category="Minor">Buying food during class hours</div>
                    <div class="violation-item" data-category="Minor">Using school properties without permission</div>
                    <div class="violation-item" data-category="Minor">Forging parent's signature</div>
                    <div class="violation-item" data-category="Minor">Misbehavior in the canteen</div>
                    <div class="violation-item" data-category="Minor">Disturbing class sessions</div>
                    <div class="violation-item" data-category="Minor">Unauthorized playing in the gym</div>
                    <div class="violation-item" data-category="Minor">Other similar minor offenses</div>
                </div>
            </div>

            <div class="category serious">
                <button class="category-btn">
                    🟡 Serious Offenses
                    <span class="arrow">▼</span>
                </button>
                <div class="violations-list">
                    <div class="violation-item" data-category="Serious">Cheating or academic dishonesty</div>
                    <div class="violation-item" data-category="Serious">Plagiarism</div>
                    <div class="violation-item" data-category="Serious">Rough or dangerous play</div>
                    <div class="violation-item" data-category="Serious">Lying or misleading statements</div>
                    <div class="violation-item" data-category="Serious">Possession of smoking items</div>
                    <div class="violation-item" data-category="Serious">Vulgar or malicious acts</div>
                    <div class="violation-item" data-category="Serious">Bribery or dishonest acts</div>
                    <div class="violation-item" data-category="Serious">Theft or aiding theft</div>
                    <div class="violation-item" data-category="Serious">Gambling or unauthorized collection</div>
                    <div class="violation-item" data-category="Serious">Not delivering official communications</div>
                    <div class="violation-item" data-category="Serious">Rude behavior to visitors or parents</div>
                    <div class="violation-item" data-category="Serious">Disrespect to school authorities</div>
                    <div class="violation-item" data-category="Serious">Use of vulgar language repeatedly</div>
                    <div class="violation-item" data-category="Serious">Causing chaos during events</div>
                    <div class="violation-item" data-category="Serious">Disrespect during flag ceremonies</div>
                    <div class="violation-item" data-category="Serious">Property damage from mischief</div>
                    <div class="violation-item" data-category="Serious">Skipping class / truancy</div>
                    <div class="violation-item" data-category="Serious">Entering/exiting without permission</div>
                    <div class="violation-item" data-category="Serious">Public display of affection</div>
                    <div class="violation-item" data-category="Serious">Minor vandalism</div>
                    <div class="violation-item" data-category="Serious">Posting unauthorized notices</div>
                    <div class="violation-item" data-category="Serious">Posting offensive content online</div>
                    <div class="violation-item" data-category="Serious">Removing school announcements</div>
                    <div class="violation-item" data-category="Serious">Gang-like behavior</div>
                    <div class="violation-item" data-category="Serious">Cyberbullying</div>
                    <div class="violation-item" data-category="Serious">Gross misconduct</div>
                </div>
            </div>

            <div class="category major">
                <button class="category-btn">
                    🔴 Major Offenses
                    <span class="arrow">▼</span>
                </button>
                <div class="violations-list">
                    <div class="violation-item" data-category="Major">Forgery or document tampering</div>
                    <div class="violation-item" data-category="Major">Using fake receipts or forms</div>
                    <div class="violation-item" data-category="Major">Major vandalism</div>
                    <div class="violation-item" data-category="Major">Physical assault</div>
                    <div class="violation-item" data-category="Major">Stealing school or class funds</div>
                    <div class="violation-item" data-category="Major">Extortion or blackmail</div>
                    <div class="violation-item" data-category="Major">Immoral or scandalous behavior</div>
                    <div class="violation-item" data-category="Major">Possessing pornography</div>
                    <div class="violation-item" data-category="Major">Criminal charges or conviction</div>
                    <div class="violation-item" data-category="Major">Harassment of students/staff</div>
                    <div class="violation-item" data-category="Major">Causing injury requiring hospitalization</div>
                    <div class="violation-item" data-category="Major">Sexual harassment</div>
                    <div class="violation-item" data-category="Major">Lewd or immoral acts</div>
                    <div class="violation-item" data-category="Major">Inappropriate social media use</div>
                    <div class="violation-item" data-category="Major">Leaking confidential school info</div>
                    <div class="violation-item" data-category="Major">Misusing school logo or name</div>
                    <div class="violation-item" data-category="Major">Bribing school officials</div>
                    <div class="violation-item" data-category="Major">Drinking alcohol in school</div>
                    <div class="violation-item" data-category="Major">Drug-related activities</div>
                    <div class="violation-item" data-category="Major">Bringing deadly weapons</div>
                    <div class="violation-item" data-category="Major">Joining fraternities/sororities</div>
                </div>
            </div>

            <div class="selected-section">
                <h3>📝 Selected Violations for Recording</h3>
                <div id="selectedList"></div>
                <button class="record-btn" id="recordBtn">💾 Record Violations</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const studentId = '<?php echo $student_data ? $student_data['student_id'] : ''; ?>';
        let selectedViolations = [];
        let attendanceStatus = null;

        // Back button confirmation
        function confirmBack() {
            if (selectedViolations.length > 0) {
                return confirm('⚠️ You have unsaved violations selected. Are you sure you want to go back?\n\nClick OK to continue or Cancel to stay.');
            }
            return true;
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            if (studentId) {
                document.getElementById('recordBtn').addEventListener('click', recordViolations);
                document.getElementById('presentBtn').addEventListener('click', () => setAttendance('Present'));
                document.getElementById('absentBtn').addEventListener('click', () => setAttendance('Absent'));
                document.getElementById('printBtn').addEventListener('click', printRecord);
                document.getElementById('meetingBtn').addEventListener('click', scheduleMeeting);

                // Add event listeners for category buttons
                document.querySelectorAll('.category-btn').forEach(btn => {
                    btn.addEventListener('click', () => toggleCategory(btn));
                });

                // Add event listeners for violation items
                document.querySelectorAll('.violation-item').forEach(item => {
                    item.addEventListener('click', () => addViolation(item));
                });
            }
        });

        function searchStudent() {
            const studentId = document.getElementById('manual_student_id').value.trim();
            if (studentId) {
                // Show loading state
                const btn = document.querySelector('.search-btn');
                const originalText = btn.textContent;
                btn.textContent = '🔍 Searching...';
                btn.disabled = true;
                
                window.location.href = `violationss.php?student_id=${encodeURIComponent(studentId)}`;
            } else {
                alert('⚠️ Please enter a student ID');
            }
        }

        function toggleCategory(btn) {
            btn.parentElement.classList.toggle('open');
            const arrow = btn.querySelector('.arrow');
            arrow.textContent = btn.parentElement.classList.contains('open') ? '▲' : '▼';
        }

        function addViolation(item) {
            const text = item.textContent.trim();
            const category = item.getAttribute('data-category');
            
            if (selectedViolations.some(v => v.text === text)) {
                // Show visual feedback for duplicate
                item.style.background = '#fee2e2';
                item.style.color = '#991b1b';
                setTimeout(() => {
                    item.style.background = '';
                    item.style.color = '';
                }, 1000);
                return;
            }

            const violation = {
                text: text,
                category: category,
                timestamp: new Date().toLocaleString()
            };

            selectedViolations.push(violation);
            
            // Visual feedback for added item
            item.style.background = 'linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%)';
            item.style.color = '#166534';
            item.style.fontWeight = '600';
            item.style.transform = 'scale(1.02)';
            setTimeout(() => {
                item.style.background = '';
                item.style.color = '';
                item.style.fontWeight = '';
                item.style.transform = '';
            }, 1500);
            
            updateSelectedList();
        }

        function removeViolation(index) {
            selectedViolations.splice(index, 1);
            updateSelectedList();
        }

        function updateSelectedList() {
            const list = document.getElementById('selectedList');
            const recordBtn = document.getElementById('recordBtn');

            list.innerHTML = '';
            
            if (selectedViolations.length === 0) {
                list.innerHTML = '<div style="text-align: center; padding: 20px; opacity: 0.7; font-style: italic;">No violations selected yet. Click on violations from the categories above to add them here.</div>';
            }
            
            selectedViolations.forEach((violation, index) => {
                const div = document.createElement('div');
                div.className = 'selected-item';
                div.innerHTML = `
                    <div>
                        <div class="violation-text">${violation.text}</div>
                        <div class="violation-timestamp">⏰ ${violation.timestamp} • ${violation.category} Offense</div>
                    </div>
                    <button class="remove-btn" onclick="removeViolation(${index})" title="Remove violation">×</button>
                `;
                
                list.appendChild(div);
            });

            recordBtn.style.display = selectedViolations.length > 0 ? 'block' : 'none';
            
            if (selectedViolations.length > 0) {
                recordBtn.textContent = `💾 Record ${selectedViolations.length} Violation${selectedViolations.length > 1 ? 's' : ''}`;
            }
        }

        function recordViolations() {
            if (selectedViolations.length === 0) {
                alert('⚠️ No violations to record!');
                return;
            }

            const recordedBy = prompt('👤 Enter your name/position (required for record keeping):') || 'Anonymous';
            
            if (!confirm(`📝 Record ${selectedViolations.length} violation(s) for student ${studentId}?\n\nRecorded by: ${recordedBy}`)) {
                return;
            }

            // Show loading state
            const recordBtn = document.getElementById('recordBtn');
            const originalText = recordBtn.textContent;
            recordBtn.innerHTML = '⏳ Recording violations...';
            recordBtn.disabled = true;

            // Send to server
            const formData = new FormData();
            formData.append('action', 'record_violations');
            formData.append('violations', JSON.stringify(selectedViolations));
            formData.append('recorded_by', recordedBy);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success animation
                    recordBtn.innerHTML = '✅ Successfully Recorded!';
                    recordBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                    recordBtn.style.boxShadow = '0 8px 32px rgba(16, 185, 129, 0.4)';
                    
                    setTimeout(() => {
                        alert(`✅ Successfully recorded ${data.count} violation(s)!\n\nThe violations have been added to the student's record.`);
                        selectedViolations = [];
                        updateSelectedList();
                        location.reload(); // Refresh to show updated violations
                    }, 1000);
                } else {
                    throw new Error('Server error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error recording violations. Please check your internet connection and try again.');
                recordBtn.innerHTML = '❌ Error - Try Again';
                recordBtn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                recordBtn.style.boxShadow = '0 8px 32px rgba(239, 68, 68, 0.4)';
            })
            .finally(() => {
                setTimeout(() => {
                    recordBtn.innerHTML = originalText;
                    recordBtn.disabled = false;
                    recordBtn.style.background = '';
                    recordBtn.style.boxShadow = '';
                }, 2000);
            });
        }

        function setAttendance(status) {
            attendanceStatus = status;
            
            const presentBtn = document.getElementById('presentBtn');
            const absentBtn = document.getElementById('absentBtn');
            
            presentBtn.classList.toggle('active', status === 'Present');
            absentBtn.classList.toggle('active', status === 'Absent');

            // Visual feedback
            const activeBtn = status === 'Present' ? presentBtn : absentBtn;
            activeBtn.innerHTML = status === 'Present' ? '⏳ Marking Present...' : '⏳ Marking Absent...';

            // Record attendance immediately
            const formData = new FormData();
            formData.append('action', 'record_attendance');
            formData.append('status', status);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    activeBtn.innerHTML = status === 'Present' ? '✅ Present' : '✅ Absent';
                    setTimeout(() => {
                        activeBtn.innerHTML = status === 'Present' ? '✓ Present' : '✗ Absent';
                    }, 2000);
                } else {
                    throw new Error('Server error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error recording attendance. Please try again.');
                activeBtn.innerHTML = status === 'Present' ? '✓ Present' : '✗ Absent';
            });
        }

        function resolveViolation(violationId) {
            if (!confirm('✅ Mark this violation as resolved?\n\nThis action will remove it from active violations.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'resolve_violation');
            formData.append('violation_id', violationId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success feedback
                    const resolveBtn = event.target;
                    resolveBtn.innerHTML = '✅';
                    resolveBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                    
                    setTimeout(() => {
                        location.reload(); // Refresh to show updated violations
                    }, 1000);
                } else {
                    throw new Error('Server error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error resolving violation. Please try again.');
            });
        }

        // Enhanced Professional Print Function
        function printRecord() {
            const studentName = '<?php echo $student_data ? addslashes($student_data['name']) : ''; ?>';
            const studentId = '<?php echo $student_data ? addslashes($student_data['student_id']) : ''; ?>';
            const grade = '<?php echo $student_data ? addslashes($student_data['grade']) : ''; ?>';
            const section = '<?php echo $student_data ? addslashes($student_data['section']) : ''; ?>';
            const currentDate = new Date();
            const formattedDate = currentDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Official Student Disciplinary Report - ${studentName}</title>
                    <style>
                        * {
                            margin: 0;
                            padding: 0;
                            box-sizing: border-box;
                        }
                        
                        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
                        
                        body {
                            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                            line-height: 1.6;
                            color: #1a1a1a;
                            background: #ffffff;
                            font-size: 14px;
                        }
                        
                        @media print {
                            body { 
                                margin: 0; 
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                            .no-print { display: none !important; }
                            .page-break { page-break-before: always; }
                        }
                        
                        .letterhead {
                            text-align: center;
                            border-bottom: 3px solid #2563eb;
                            padding-bottom: 25px;
                            margin-bottom: 40px;
                            position: relative;
                            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                            padding: 30px;
                            border-radius: 8px 8px 0 0;
                        }
                        
                        .letterhead::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: 0;
                            right: 0;
                            height: 4px;
                            background: linear-gradient(90deg, #3b82f6, #1d4ed8, #1e40af);
                        }
                        
                        .school-logo {
                            width: 60px;
                            height: 60px;
                            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                            border-radius: 50%;
                            margin: 0 auto 20px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: white;
                            font-size: 24px;
                            font-weight: 700;
                            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
                        }
                        
                        .school-name {
                            font-size: 28px;
                            font-weight: 700;
                            color: #1e293b;
                            margin-bottom: 8px;
                            letter-spacing: -0.5px;
                        }
                        
                        .school-address {
                            color: #64748b;
                            font-size: 16px;
                            margin-bottom: 5px;
                        }
                        
                        .document-title {
                            font-size: 24px;
                            font-weight: 600;
                            color: #dc2626;
                            margin-top: 20px;
                            letter-spacing: 0.5px;
                            text-transform: uppercase;
                        }
                        
                        .document-subtitle {
                            color: #64748b;
                            font-size: 14px;
                            margin-top: 5px;
                            font-weight: 400;
                        }
                        
                        .container {
                            max-width: 800px;
                            margin: 0 auto;
                            padding: 40px;
                            background: white;
                        }
                        
                        .date-reference {
                            text-align: right;
                            margin-bottom: 40px;
                            font-size: 13px;
                            color: #64748b;
                        }
                        
                        .reference-number {
                            font-weight: 600;
                            color: #1e293b;
                        }
                        
                        .student-info-section {
                            background: #f8fafc;
                            border: 1px solid #e2e8f0;
                            border-radius: 12px;
                            padding: 25px;
                            margin-bottom: 35px;
                            position: relative;
                        }
                        
                        .student-info-section::before {
                            content: '👤';
                            position: absolute;
                            top: -12px;
                            left: 25px;
                            background: #f8fafc;
                            padding: 0 10px;
                            font-size: 16px;
                        }
                        
                        .section-title {
                            font-size: 18px;
                            font-weight: 600;
                            color: #1e293b;
                            margin-bottom: 20px;
                            display: flex;
                            align-items: center;
                            gap: 10px;
                        }
                        
                        .info-grid {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 15px;
                        }
                        
                        .info-item {
                            display: flex;
                            flex-direction: column;
                        }
                        
                        .info-label {
                            font-size: 12px;
                            color: #64748b;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            margin-bottom: 4px;
                            font-weight: 500;
                        }
                        
                        .info-value {
                            font-size: 15px;
                            font-weight: 600;
                            color: #1e293b;
                        }
                        
                        .violations-section {
                            margin-bottom: 40px;
                            counter-reset: violation-counter;
                        }
                        
                        .violation-item {
                            background: white;
                            border: 1px solid #f1f5f9;
                            border-left: 4px solid #ef4444;
                            border-radius: 0 8px 8px 0;
                            padding: 20px;
                            margin-bottom: 15px;
                            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                            position: relative;
                        }
                        
                        .violation-item::before {
                            content: counter(violation-counter);
                            counter-increment: violation-counter;
                            position: absolute;
                            top: 15px;
                            left: -2px;
                            background: #ef4444;
                            color: white;
                            width: 24px;
                            height: 24px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 12px;
                            font-weight: 600;
                        }
                        
                        .violation-text {
                            font-weight: 600;
                            color: #1e293b;
                            margin-bottom: 8px;
                            margin-left: 15px;
                            font-size: 15px;
                        }
                        
                        .violation-meta {
                            display: grid;
                            grid-template-columns: 1fr 1fr 1fr;
                            gap: 15px;
                            margin-left: 15px;
                            margin-top: 10px;
                        }
                        
                        .meta-item {
                            display: flex;
                            flex-direction: column;
                        }
                        
                        .meta-label {
                            font-size: 11px;
                            color: #64748b;
                            text-transform: uppercase;
                            letter-spacing: 0.3px;
                            margin-bottom: 2px;
                        }
                        
                        .meta-value {
                            font-size: 13px;
                            color: #374151;
                            font-weight: 500;
                        }
                        
                        .category-badge {
                            display: inline-block;
                            padding: 4px 10px;
                            border-radius: 20px;
                            font-size: 11px;
                            font-weight: 600;
                            text-transform: uppercase;
                            letter-spacing: 0.3px;
                        }
                        
                        .category-minor {
                            background: #dcfce7;
                            color: #166534;
                        }
                        
                        .category-serious {
                            background: #fef3c7;
                            color: #92400e;
                        }
                        
                        .category-major {
                            background: #fee2e2;
                            color: #991b1b;
                        }
                        
                        .no-violations {
                            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
                            border: 2px dashed #059669;
                            padding: 40px;
                            text-align: center;
                            border-radius: 12px;
                            position: relative;
                        }
                        
                        .no-violations::before {
                            content: '✅';
                            font-size: 48px;
                            display: block;
                            margin-bottom: 15px;
                        }
                        
                        .clean-record-text {
                            font-size: 18px;
                            font-weight: 600;
                            color: #059669;
                            margin-bottom: 8px;
                        }
                        
                        .clean-record-subtitle {
                            color: #047857;
                            font-size: 14px;
                        }
                        
                        .summary-section {
                            background: #f8fafc;
                            border-radius: 12px;
                            padding: 25px;
                            margin: 30px 0;
                            border: 1px solid #e2e8f0;
                        }
                        
                        .summary-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                            gap: 20px;
                        }
                        
                        .summary-item {
                            text-align: center;
                            padding: 15px;
                            background: white;
                            border-radius: 8px;
                            border: 1px solid #f1f5f9;
                        }
                        
                        .summary-number {
                            font-size: 24px;
                            font-weight: 700;
                            color: #1e293b;
                            display: block;
                        }
                        
                        .summary-label {
                            font-size: 12px;
                            color: #64748b;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            margin-top: 5px;
                        }
                        
                        .footer-section {
                            margin-top: 50px;
                            padding-top: 30px;
                            border-top: 2px solid #e2e8f0;
                        }
                        
                        .signature-area {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 50px;
                            margin-top: 40px;
                        }
                        
                        .signature-box {
                            text-align: center;
                        }
                        
                        .signature-line {
                            border-bottom: 2px solid #374151;
                            height: 60px;
                            margin-bottom: 10px;
                            position: relative;
                        }
                        
                        .signature-label {
                            font-size: 13px;
                            color: #64748b;
                            font-weight: 500;
                        }
                        
                        .generation-info {
                            text-align: center;
                            margin-top: 40px;
                            padding: 20px;
                            background: #f8fafc;
                            border-radius: 8px;
                            font-size: 12px;
                            color: #64748b;
                        }
                        
                        .print-button {
                            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                            color: white;
                            border: none;
                            padding: 15px 30px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: 16px;
                            font-weight: 600;
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            margin: 20px auto;
                            transition: all 0.3s ease;
                            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
                        }
                        
                        .print-button:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="letterhead">
                            <div class="school-logo">VT</div>
                            <div class="school-name">VIOTRACK ACADEMY</div>
                            <div class="school-address">Student Disciplinary Management System</div>
                            <div class="school-address">Quezon City, Metro Manila, Philippines</div>
                            <div class="document-title">Official Disciplinary Report</div>
                            <div class="document-subtitle">Confidential Student Record</div>
                        </div>
                        
                        <div class="date-reference">
                            <div><strong>Date Issued:</strong> ${formattedDate}</div>
                            <div><strong>Reference No:</strong> <span class="reference-number">VT-${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(currentDate.getDate()).padStart(2, '0')}-${studentId}</span></div>
                            <div><strong>Generated At:</strong> ${currentDate.toLocaleTimeString()}</div>
                        </div>
                        
                        <div class="student-info-section">
                            <h2 class="section-title">Student Information</h2>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value">${studentName}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Student ID</div>
                                    <div class="info-value">${studentId}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Grade Level</div>
                                    <div class="info-value">${grade}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Section</div>
                                    <div class="info-value">${section}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Current Status</div>
                                    <div class="info-value"><?php echo $student_data ? $student_data['status'] : ''; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Today's Attendance</div>
                                    <div class="info-value">${attendanceStatus || 'Not Recorded'}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="summary-section">
                            <h2 class="section-title">📊 Violation Summary</h2>
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-number"><?php echo count($current_violations); ?></span>
                                    <div class="summary-label">Active Violations</div>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-number"><?php 
                                        $minor = 0;
                                        foreach($current_violations as $v) {
                                            if($v['violation_category'] == 'Minor') $minor++;
                                        }
                                        echo $minor;
                                    ?></span>
                                    <div class="summary-label">Minor Offenses</div>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-number"><?php 
                                        $serious = 0;
                                        foreach($current_violations as $v) {
                                            if($v['violation_category'] == 'Serious') $serious++;
                                        }
                                        echo $serious;
                                    ?></span>
                                    <div class="summary-label">Serious Offenses</div>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-number"><?php 
                                        $major = 0;
                                        foreach($current_violations as $v) {
                                            if($v['violation_category'] == 'Major') $major++;
                                        }
                                        echo $major;
                                    ?></span>
                                    <div class="summary-label">Major Offenses</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="violations-section">
                            <h2 class="section-title">⚠️ Active Violations Record</h2>`;
            
            <?php if (!empty($current_violations)): ?>
            <?php foreach ($current_violations as $violation): ?>
            printContent += `
                            <div class="violation-item">
                                <div class="violation-text"><?php echo addslashes($violation['violation_type']); ?></div>
                                <div class="violation-meta">
                                    <div class="meta-item">
                                        <div class="meta-label">Date Recorded</div>
                                        <div class="meta-value"><?php echo date('M j, Y', strtotime($violation['violation_date'])); ?></div>
                                    </div>
                                    <div class="meta-item">
                                        <div class="meta-label">Time</div>
                                        <div class="meta-value"><?php echo date('g:i A', strtotime($violation['violation_date'])); ?></div>
                                    </div>
                                    <div class="meta-item">
                                        <div class="meta-label">Category</div>
                                        <div class="meta-value">
                                            <span class="category-badge category-<?php echo strtolower($violation['violation_category']); ?>">
                                                <?php echo $violation['violation_category']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($violation['recorded_by'])): ?>
                                <div style="margin-top: 10px; margin-left: 15px;">
                                    <div class="meta-label">Recorded By</div>
                                    <div class="meta-value"><?php echo addslashes($violation['recorded_by']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($violation['notes'])): ?>
                                <div style="margin-top: 8px; margin-left: 15px;">
                                    <div class="meta-label">Additional Notes</div>
                                    <div class="meta-value"><?php echo addslashes($violation['notes']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>`;
            <?php endforeach; ?>
            <?php else: ?>
            printContent += `
                            <div class="no-violations">
                                <div class="clean-record-text">Excellent Record</div>
                                <div class="clean-record-subtitle">This student currently maintains a clean disciplinary record with no active violations.</div>
                            </div>`;
            <?php endif; ?>
            
            printContent += `
                        </div>
                        
                        <div class="footer-section">
                            <div class="signature-area">
                                <div class="signature-box">
                                    <div class="signature-line"></div>
                                    <div class="signature-label">Discipline Officer</div>
                                    <div style="font-size: 11px; color: #9ca3af; margin-top: 5px;">Name & Signature</div>
                                </div>
                                <div class="signature-box">
                                    <div class="signature-line"></div>
                                    <div class="signature-label">Principal/Administrator</div>
                                    <div style="font-size: 11px; color: #9ca3af; margin-top: 5px;">Name & Signature</div>
                                </div>
                            </div>
                            
                            <div class="generation-info">
                                <div><strong>Document Generated By:</strong> VioTrack Digital System</div>
                                <div><strong>System Version:</strong> v2.0.1 | <strong>Generated On:</strong> ${currentDate.toLocaleString()}</div>
                                <div style="margin-top: 10px; font-size: 11px;">
                                    This is an official computer-generated document. No signature is required for authenticity.
                                </div>
                            </div>
                        </div>
                        
                        <div class="no-print" style="text-align: center; margin: 30px 0;">
                            <button class="print-button" onclick="window.print()">
                                <span>🖨️</span>
                                <span>Print Official Report</span>
                            </button>
                            <div style="margin-top: 15px; font-size: 13px; color: #64748b;">
                                💡 Tip: Use landscape orientation for better formatting
                            </div>
                        </div>
                    </div>
                </body>
                </html>`;
            
            // Open print window
            const printWindow = window.open('', '_blank', 'width=900,height=700');
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Auto-focus and show print dialog after content loads
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.focus();
                    printWindow.print();
                }, 500);
            };
            
            // Show success feedback
            const printBtn = document.getElementById('printBtn');
            const originalText = printBtn.innerHTML;
            printBtn.innerHTML = '✅ Print Ready!';
            printBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            printBtn.style.boxShadow = '0 8px 32px rgba(16, 185, 129, 0.4)';
            
            setTimeout(() => {
                printBtn.innerHTML = originalText;
                printBtn.style.background = '';
                printBtn.style.boxShadow = '';
            }, 2000);
        }

        function scheduleMeeting() {
            const studentName = '<?php echo $student_data ? addslashes($student_data['name']) : ''; ?>';
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            const meetingDate = prompt(`📅 Enter meeting date (YYYY-MM-DD):\n\nSuggested: ${tomorrow.toISOString().split('T')[0]}`);
            
            if (!meetingDate) return;
            
            const meetingTime = prompt('⏰ Enter meeting time (HH:MM):\n\nSuggested times:\n• 08:00 (Morning)\n• 13:00 (Afternoon)\n• 15:00 (After School)');
            
            if (meetingDate && meetingTime) {
                const meetingDetails = {
                    student: studentName,
                    student_id: studentId,
                    date: meetingDate,
                    time: meetingTime,
                    violations: <?php echo count($current_violations); ?>,
                    scheduled_by: prompt('👤 Your name (for meeting records):') || 'Anonymous',
                    notes: prompt('📝 Meeting purpose/notes (optional):') || 'Disciplinary discussion'
                };
                
                alert(`✅ Meeting scheduled successfully!\n\n📋 Meeting Details:\n• Student: ${studentName}\n• Date: ${meetingDate}\n• Time: ${meetingTime}\n• Purpose: ${meetingDetails.notes}\n• Scheduled by: ${meetingDetails.scheduled_by}\n\n📧 Please ensure to notify the student and parents.`);
                
                console.log('Meeting scheduled:', meetingDetails);
                
                // Here you could send the meeting data to your backend
                // fetch('/schedule_meeting.php', { method: 'POST', body: JSON.stringify(meetingDetails) });
            }
        }

        // Allow Enter key for manual search
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.activeElement.id === 'manual_student_id') {
                searchStudent();
            }
        });

        // Initialize selected list on page load
        if (studentId) {
            updateSelectedList();
        }

        // Add smooth scrolling for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Add intersection observer for animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe elements for scroll animations
            document.querySelectorAll('.violation-item, .selected-item').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'all 0.6s ease-out';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>