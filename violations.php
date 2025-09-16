<?php
// violations.php
// VIOTRACK System - Enhanced Violations Page with Active Data
include 'db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_violations') {
        $search = $_POST['search'] ?? '';
        $status_filter = $_POST['status'] ?? '';
        $category_filter = $_POST['category'] ?? '';
        
        $sql = "SELECT v.*, s.name as student_name, s.grade, s.section 
                FROM violations v 
                JOIN students s ON v.student_id = s.student_id";
        
        $conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $conditions[] = "(s.name LIKE ? OR v.violation_type LIKE ? OR v.student_id LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status_filter)) {
            $conditions[] = "v.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($category_filter)) {
            $conditions[] = "v.violation_category = ?";
            $params[] = $category_filter;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY v.violation_date DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $violations = [];
        while ($row = $result->fetch_assoc()) {
            $violations[] = $row;
        }
        
        echo json_encode($violations);
        exit;
    }
    
    if ($action === 'resolve_violation') {
        $violation_id = $_POST['violation_id'];
        $resolved_by = $_POST['resolved_by'] ?? 'System';
        $notes = $_POST['notes'] ?? '';
        
        $sql = "UPDATE violations SET 
                status = 'Resolved', 
                resolved_by = ?, 
                resolved_date = NOW(),
                notes = CONCAT(COALESCE(notes, ''), '\n[RESOLVED] ', ?)
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $resolved_by, $notes, $violation_id);
        
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }
    
    if ($action === 'add_violation') {
        $student_id = $_POST['student_id'];
        $violation_type = $_POST['violation_type'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $recorded_by = $_POST['recorded_by'];
        
        $sql = "INSERT INTO violations (student_id, violation_type, violation_category, notes, recorded_by) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $student_id, $violation_type, $category, $description, $recorded_by);
        
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }
    
    if ($action === 'add_bulk_violations') {
        $student_id = $_POST['student_id'];
        $violations = json_decode($_POST['violations'], true);
        $recorded_by = $_POST['recorded_by'];
        
        $success_count = 0;
        foreach ($violations as $violation) {
            $sql = "INSERT INTO violations (student_id, violation_type, violation_category, notes, recorded_by) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $student_id, $violation['text'], $violation['category'], $violation['notes'] ?? '', $recorded_by);
            
            if ($stmt->execute()) {
                $success_count++;
            }
        }
        
        echo json_encode(['success' => $success_count > 0, 'count' => $success_count]);
        exit;
    }
    
    if ($action === 'get_report_data') {
        $start_date = $_POST['start_date'] ?? date('Y-m-01');
        $end_date = $_POST['end_date'] ?? date('Y-m-t');
        
        // Get violation statistics
        $stats_sql = "SELECT 
                        COUNT(*) as total_violations,
                        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_violations,
                        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_violations,
                        SUM(CASE WHEN violation_category = 'Minor' THEN 1 ELSE 0 END) as minor_violations,
                        SUM(CASE WHEN violation_category = 'Serious' THEN 1 ELSE 0 END) as serious_violations,
                        SUM(CASE WHEN violation_category = 'Major' THEN 1 ELSE 0 END) as major_violations
                      FROM violations 
                      WHERE violation_date BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($stats_sql);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        // Get top violators
        $top_sql = "SELECT s.name, s.student_id, COUNT(*) as violation_count
                    FROM violations v
                    JOIN students s ON v.student_id = s.student_id
                    WHERE v.violation_date BETWEEN ? AND ?
                    GROUP BY v.student_id
                    ORDER BY violation_count DESC
                    LIMIT 10";
        
        $stmt = $conn->prepare($top_sql);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $top_violators = [];
        while ($row = $result->fetch_assoc()) {
            $top_violators[] = $row;
        }
        
        echo json_encode([
            'stats' => $stats,
            'top_violators' => $top_violators
        ]);
        exit;
    }
    
    if ($action === 'get_student_info') {
        $student_id = $_POST['student_id'];
        $sql = "SELECT * FROM students WHERE student_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => true, 'student' => $result->fetch_assoc()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
        }
        exit;
    }
}

// Get initial violation data
$violations_query = "SELECT v.*, s.name as student_name, s.grade, s.section 
                     FROM violations v 
                     JOIN students s ON v.student_id = s.student_id 
                     ORDER BY v.violation_date DESC 
                     LIMIT 50";
$violations_result = $conn->query($violations_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VIOTRACK - Violations Management</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Modern button styles */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-primary {
        background: #2563eb;
        color: white;
    }

    .btn-primary:hover {
        background: #1d4ed8;
    }

    .btn-success {
        background: #059669;
        color: white;
    }

    .btn-success:hover {
        background: #047857;
    }

    .btn-warning {
        background: #d97706;
        color: white;
    }

    .btn-warning:hover {
        background: #b45309;
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .btn-secondary:hover {
        background: #4b5563;
    }

    .btn-danger {
        background: #dc2626;
        color: white;
    }

    .btn-danger:hover {
        background: #b91c1c;
    }

    /* Action button styles */
    .btn-small {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 500;
    }

    .btn-view {
        background: #2563eb;
        color: white;
    }

    .btn-view:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .btn-resolve {
        background: #059669;
        color: white;
    }

    .btn-resolve:hover {
        background: #047857;
        transform: translateY(-1px);
    }

    .btn-edit {
        background: #6b7280;
        color: white;
    }

    .btn-edit:hover {
        background: #4b5563;
        transform: translateY(-1px);
    }

    .btn-delete {
        background: #dc2626;
        color: white;
    }

    .btn-delete:hover {
        background: #b91c1c;
        transform: translateY(-1px);
    }

    /* Modern icons */
    .icon {
        width: 16px;
        height: 16px;
        display: inline-block;
    }

    .icon-large {
        width: 18px;
        height: 18px;
    }

    .violation-form-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 2000;
        backdrop-filter: blur(4px);
    }
    
    .violation-form-modal.show {
        display: flex;
    }
    
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px rgba(0, 0, 0, 0.3);
        animation: modalFadeIn 0.3s ease;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f5f9;
    }
    
    .modal-title {
        font-size: 24px;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
        padding: 5px;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        background: #f1f5f9;
        color: #1e293b;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    
    .form-group label {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 5px;
        font-weight: 500;
        color: #374151;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s ease;
        box-sizing: border-box;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #3b82f6;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .student-info-display {
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
        margin: 10px 0;
        border-left: 4px solid #3b82f6;
    }
    
    .student-info-display.not-found {
        border-left-color: #ef4444;
        background: #fef2f2;
    }
    
    .violations-categories {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 15px;
        margin: 20px 0;
    }
    
    .category-section {
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .category-section.minor {
        border-color: #10b981;
    }
    
    .category-section.serious {
        border-color: #f59e0b;
    }
    
    .category-section.major {
        border-color: #ef4444;
    }
    
    .category-header {
        padding: 12px;
        font-weight: 600;
        color: white;
        text-align: center;
        cursor: pointer;
        user-select: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .category-section.minor .category-header {
        background: #10b981;
    }
    
    .category-section.serious .category-header {
        background: #f59e0b;
    }
    
    .category-section.major .category-header {
        background: #ef4444;
    }
    
    .violations-list {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    
    .category-section.expanded .violations-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .violation-option {
        padding: 8px 12px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        font-size: 13px;
        transition: background-color 0.2s ease;
    }
    
    .violation-option:hover {
        background: #f8fafc;
    }
    
    .violation-option.selected {
        background: #dbeafe;
        color: #1d4ed8;
        font-weight: 500;
    }
    
    .violation-option:last-child {
        border-bottom: none;
    }
    
    .selected-violations {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        margin: 20px 0;
        min-height: 100px;
    }
    
    .selected-violations h4 {
        margin: 0 0 10px 0;
        color: #374151;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .selected-violation-item {
        background: white;
        padding: 8px 12px;
        margin: 5px 0;
        border-radius: 6px;
        border-left: 4px solid #3b82f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .selected-violation-text {
        font-size: 14px;
        color: #374151;
    }
    
    .remove-violation-btn {
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s ease;
    }
    
    .remove-violation-btn:hover {
        background: #dc2626;
    }
    
    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    
    .btn-modal {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-save {
        background: #3b82f6;
        color: white;
    }
    
    .btn-save:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }
    
    .btn-save:disabled {
        background: #9ca3af;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-cancel {
        background: #6b7280;
        color: white;
    }
    
    .btn-cancel:hover {
        background: #4b5563;
        transform: translateY(-1px);
    }
    
    .filters-section {
        background: #f8fafc;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .filter-group label {
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .filter-group select,
    .filter-group input {
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 14px;
        min-width: 150px;
    }
    
    .loading {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }
    
    .violation-actions {
        display: flex;
        gap: 5px;
    }
    
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card-small {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        text-align: center;
        border-left: 4px solid #3b82f6;
        transition: transform 0.3s ease;
    }
    
    .stat-card-small:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }
    
    .stat-card-small:nth-child(2) {
        border-left-color: #f59e0b;
    }
    
    .stat-card-small:nth-child(3) {
        border-left-color: #10b981;
    }
    
    .stat-card-small:nth-child(4) {
        border-left-color: #ef4444;
    }
    
    .stat-number-small {
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 8px;
        color: #1e293b;
    }
    
    .stat-label-small {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 500;
    }
    
    .report-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        margin-top: 25px;
    }
    
    .violation-details {
        background: #f8fafc;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
    }
    
    .detail-label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 500;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .detail-value {
        font-size: 14px;
        color: #1e293b;
        font-weight: 500;
    }
    
    .no-data {
        text-align: center;
        padding: 40px;
        color: #64748b;
        font-style: italic;
    }

    .action-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .violations-categories {
            grid-template-columns: 1fr;
        }
        
        .filters-section {
            flex-direction: column;
            align-items: stretch;
        }
        
        .modal-content {
            margin: 10px;
            width: calc(100% - 20px);
        }
        
        .detail-grid {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }
    }

    @keyframes modalFadeIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
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
            <a href="dashboard.php" class="menu-item" data-page="dashboard">
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 10l8-4 8 4-8 4-8-4z"/>
                        <path d="M12 14v7"/>
                        <path d="M6 12v5c0 1 2 2 6 2s6-1 6-2v-5"/>
                    </svg>
                </div>
                <span class="menu-text">STUDENTS</span>
            </a>
            <a href="#" class="menu-item active" data-page="violations">
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
      <div id="violations" class="page active">
          <h1 class="page-header">Violation Management System</h1>
          
          <div class="content-card">
              <div class="content-title">Violations Management Dashboard</div>
              
              <!-- Filters -->
              <div class="filters-section">
                  <div class="filter-group">
                      <label>
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <circle cx="11" cy="11" r="8"/>
                              <path d="m21 21-4.35-4.35"/>
                          </svg>
                          Search
                      </label>
                      <input type="text" id="searchInput" placeholder="Search violations, students...">
                  </div>
                  <div class="filter-group">
                      <label>
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <circle cx="12" cy="12" r="3"/>
                              <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                          </svg>
                          Status
                      </label>
                      <select id="statusFilter">
                          <option value="">All Statuses</option>
                          <option value="Active">Active</option>
                          <option value="Resolved">Resolved</option>
                      </select>
                  </div>
                  <div class="filter-group">
                      <label>
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="M3 6h18M7 12h10m-7 6h4"/>
                          </svg>
                          Category
                      </label>
                      <select id="categoryFilter">
                          <option value="">All Categories</option>
                          <option value="Minor">Minor Offenses</option>
                          <option value="Serious">Serious Offenses</option>
                          <option value="Major">Major Offenses</option>
                      </select>
                  </div>
                  <div class="filter-group">
                      <label>Actions</label>
                      <button class="btn btn-primary" onclick="refreshViolations()">
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                              <path d="M21 3v5h-5"/>
                              <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                              <path d="M3 21v-5h5"/>
                          </svg>
                          Refresh
                      </button>
                  </div>
              </div>

              <div class="content-section">
                  <div id="violationsTableContainer">
                      <table class="data-table" id="violationsTable">
                          <thead>
                              <tr>
                                  <th>Date</th>
                                  <th>Student</th>
                                  <th>Violation</th>
                                  <th>Category</th>
                                  <th>Status</th>
                                  <th>Actions</th>
                              </tr>
                          </thead>
                          <tbody id="violationsTableBody">
                              <?php while($row = $violations_result->fetch_assoc()): ?>
                              <tr>
                                  <td><?php echo date('M j, Y', strtotime($row['violation_date'])); ?></td>
                                  <td>
                                      <div style="font-weight: 500;"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                      <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($row['student_id']); ?> - <?php echo htmlspecialchars($row['grade'] . ' ' . $row['section']); ?></div>
                                  </td>
                                  <td><?php echo htmlspecialchars($row['violation_type']); ?></td>
                                  <td>
                                      <span class="status-badge status-<?php 
                                          echo $row['violation_category'] == 'Minor' ? 'active' : 
                                              ($row['violation_category'] == 'Serious' ? 'pending' : 'resolved'); 
                                      ?>">
                                          <?php echo htmlspecialchars($row['violation_category']); ?>
                                      </span>
                                  </td>
                                  <td>
                                      <span class="status-badge status-<?php echo $row['status'] == 'Active' ? 'pending' : 'resolved'; ?>">
                                          <?php echo htmlspecialchars($row['status']); ?>
                                      </span>
                                  </td>
                                  <td>
                                      <div class="violation-actions">
                                          <button class="btn-small btn-view" onclick="viewViolation(<?php echo $row['id']; ?>)">
                                              <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                  <circle cx="12" cy="12" r="3"/>
                                              </svg>
                                              View
                                          </button>
                                          <?php if($row['status'] == 'Active'): ?>
                                              <button class="btn-small btn-resolve" onclick="resolveViolation(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')">
                                                  <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                      <path d="M9 12l2 2 4-4"/>
                                                      <circle cx="12" cy="12" r="10"/>
                                                  </svg>
                                                  Resolve
                                              </button>
                                          <?php endif; ?>
                                      </div>
                                  </td>
                              </tr>
                              <?php endwhile; ?>
                          </tbody>
                      </table>
                  </div>
              </div>
              
              <div class="action-buttons">
                  <button class="btn btn-primary" onclick="openNewViolationModal()">
                      <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10"/>
                          <line x1="12" y1="8" x2="12" y2="16"/>
                          <line x1="8" y1="12" x2="16" y2="12"/>
                      </svg>
                      Add New Violation
                  </button>
                  <button class="btn btn-success" onclick="openBulkViolationModal()">
                      <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                          <polyline points="14,2 14,8 20,8"/>
                          <line x1="16" y1="13" x2="8" y2="13"/>
                          <line x1="16" y1="17" x2="8" y2="17"/>
                          <polyline points="10,9 9,9 8,9"/>
                      </svg>
                      Bulk Import
                  </button>
                  <button class="btn btn-warning" onclick="generateReport()">
                      <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <line x1="18" y1="20" x2="18" y2="10"/>
                          <line x1="12" y1="20" x2="12" y2="4"/>
                          <line x1="6" y1="20" x2="6" y2="14"/>
                      </svg>
                      Generate Report
                  </button>
                  <button class="btn btn-secondary" onclick="exportData()">
                      <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                          <polyline points="7,10 12,15 17,10"/>
                          <line x1="12" y1="15" x2="12" y2="3"/>
                      </svg>
                      Export Data
                  </button>
              </div>
          </div>
          
          <!-- Report Section -->
          <div class="report-section" id="reportSection" style="display: none;">
              <div class="section-header">
                  <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="20" x2="18" y2="10"/>
                      <line x1="12" y1="20" x2="12" y2="4"/>
                      <line x1="6" y1="20" x2="6" y2="14"/>
                  </svg>
                  Violation Report
              </div>
              <div id="reportContent"></div>
          </div>
      </div>
  </div>

  <!-- New Violation Modal -->
  <div id="violationModal" class="violation-form-modal">
      <div class="modal-content">
          <div class="modal-header">
              <h2 class="modal-title">
                  <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"/>
                      <line x1="12" y1="8" x2="12" y2="16"/>
                      <line x1="8" y1="12" x2="16" y2="12"/>
                  </svg>
                  Report New Violation
              </h2>
              <button class="modal-close" onclick="closeViolationModal()">×</button>
          </div>
          
          <form id="violationForm">
              <div class="form-grid">
                  <div class="form-group">
                      <label for="studentId">
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                              <circle cx="12" cy="7" r="4"/>
                          </svg>
                          Student ID
                      </label>
                      <input type="text" id="studentId" name="student_id" required placeholder="Enter student ID">
                  </div>
                  
                  <div class="form-group">
                      <label for="violationCategory">
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="M3 6h18M7 12h10m-7 6h4"/>
                          </svg>
                          Category
                      </label>
                      <select id="violationCategory" name="category" required>
                          <option value="">Select Category</option>
                          <option value="Minor">Minor Offense</option>
                          <option value="Serious">Serious Offense</option>
                          <option value="Major">Major Offense</option>
                      </select>
                  </div>
              </div>
              
              <div id="studentInfo" class="student-info-display" style="display: none;"></div>
              
              <div class="form-group full-width">
                  <label for="violationType">
                      <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                          <path d="M12 9v4"/>
                          <path d="m12 17 .01 0"/>
                      </svg>
                      Violation Type
                  </label>
                  <input type="text" id="violationType" name="violation_type" required placeholder="Enter violation type">
              </div>
              
              <div class="form-group full-width">
                  <label for="violationDescription">
                      <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                          <polyline points="14,2 14,8 20,8"/>
                          <line x1="16" y1="13" x2="8" y2="13"/>
                          <line x1="16" y1="17" x2="8" y2="17"/>
                          <polyline points="10,9 9,9 8,9"/>
                      </svg>
                      Description
                  </label>
                  <textarea id="violationDescription" name="description" placeholder="Enter detailed description of the violation"></textarea>
              </div>
              
              <div class="form-group full-width">
                  <label for="recordedBy">
                      <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                          <circle cx="9" cy="7" r="4"/>
                          <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                      </svg>
                      Recorded By
                  </label>
                  <input type="text" id="recordedBy" name="recorded_by" required placeholder="Your name/position">
              </div>
          </form>
          
          <div class="modal-actions">
              <button class="btn-modal btn-save" onclick="saveViolation()">
                  <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                      <polyline points="17,21 17,13 7,13 7,21"/>
                      <polyline points="7,3 7,8 15,8"/>
                  </svg>
                  Save Violation
              </button>
              <button class="btn-modal btn-cancel" onclick="closeViolationModal()">
                  <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="6" x2="6" y2="18"/>
                      <line x1="6" y1="6" x2="18" y2="18"/>
                  </svg>
                  Cancel
              </button>
          </div>
      </div>
  </div>

  <!-- Bulk Violation Modal -->
  <div id="bulkViolationModal" class="violation-form-modal">
      <div class="modal-content">
          <div class="modal-header">
              <h2 class="modal-title">
                  <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                      <polyline points="14,2 14,8 20,8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/>
                      <line x1="16" y1="17" x2="8" y2="17"/>
                      <polyline points="10,9 9,9 8,9"/>
                  </svg>
                  Bulk Violation Report
              </h2>
              <button class="modal-close" onclick="closeBulkViolationModal()">×</button>
          </div>
          
          <form id="bulkViolationForm">
              <div class="form-grid">
                  <div class="form-group">
                      <label for="bulkStudentId">
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                              <circle cx="12" cy="7" r="4"/>
                          </svg>
                          Student ID
                      </label>
                      <input type="text" id="bulkStudentId" name="student_id" required placeholder="Enter student ID">
                  </div>
                  
                  <div class="form-group">
                      <label for="bulkRecordedBy">
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                              <circle cx="9" cy="7" r="4"/>
                              <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                          </svg>
                          Recorded By
                      </label>
                      <input type="text" id="bulkRecordedBy" name="recorded_by" required placeholder="Your name/position">
                  </div>
              </div>
              
              <div id="bulkStudentInfo" class="student-info-display" style="display: none;"></div>
              
              <!-- Violation Categories -->
              <div class="violations-categories">
                  <div class="category-section minor" data-category="Minor">
                      <div class="category-header" onclick="toggleViolationCategory(this)">
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <circle cx="12" cy="12" r="10"/>
                              <path d="m9 12 2 2 4-4"/>
                          </svg>
                          Minor Offenses
                      </div>
                      <div class="violations-list">
                          <div class="violation-option" data-violation="Uniform not worn or worn improperly">Uniform not worn or worn improperly</div>
                          <div class="violation-option" data-violation="School ID not worn properly">School ID not worn properly</div>
                          <div class="violation-option" data-violation="Playing non-educational games">Playing non-educational games</div>
                          <div class="violation-option" data-violation="Sleeping during class or activities">Sleeping during class or activities</div>
                          <div class="violation-option" data-violation="Borrowing or lending money">Borrowing or lending money</div>
                          <div class="violation-option" data-violation="Loitering or shouting in hallways">Loitering or shouting in hallways</div>
                          <div class="violation-option" data-violation="Leaving classroom messy">Leaving classroom messy</div>
                          <div class="violation-option" data-violation="Use of mobile gadgets without permission">Use of mobile gadgets without permission</div>
                          <div class="violation-option" data-violation="Excessive jewelry or inappropriate appearance">Excessive jewelry or inappropriate appearance</div>
                          <div class="violation-option" data-violation="Using vulgar language">Using vulgar language</div>
                          <div class="violation-option" data-violation="Minor bullying or teasing">Minor bullying or teasing</div>
                          <div class="violation-option" data-violation="Fighting or causing disturbances">Fighting or causing disturbances</div>
                          <div class="violation-option" data-violation="Horseplay inside classroom">Horseplay inside classroom</div>
                          <div class="violation-option" data-violation="Not completing clearance requirements">Not completing clearance requirements</div>
                          <div class="violation-option" data-violation="Chewing gum or spitting in public">Chewing gum or spitting in public</div>
                          <div class="violation-option" data-violation="Using school properties without permission">Using school properties without permission</div>
                          <div class="violation-option" data-violation="Forging parent's signature">Forging parent's signature</div>
                          <div class="violation-option" data-violation="Misbehavior in the canteen">Misbehavior in the canteen</div>
                          <div class="violation-option" data-violation="Disturbing class sessions">Disturbing class sessions</div>
                      </div>
                  </div>
                  
                  <div class="category-section serious" data-category="Serious">
                      <div class="category-header" onclick="toggleViolationCategory(this)">
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                              <path d="M12 9v4"/>
                              <path d="m12 17 .01 0"/>
                          </svg>
                          Serious Offenses
                      </div>
                      <div class="violations-list">
                          <div class="violation-option" data-violation="Cheating or academic dishonesty">Cheating or academic dishonesty</div>
                          <div class="violation-option" data-violation="Plagiarism">Plagiarism</div>
                          <div class="violation-option" data-violation="Rough or dangerous play">Rough or dangerous play</div>
                          <div class="violation-option" data-violation="Lying or misleading statements">Lying or misleading statements</div>
                          <div class="violation-option" data-violation="Possession of smoking items">Possession of smoking items</div>
                          <div class="violation-option" data-violation="Vulgar or malicious acts">Vulgar or malicious acts</div>
                          <div class="violation-option" data-violation="Theft or aiding theft">Theft or aiding theft</div>
                          <div class="violation-option" data-violation="Gambling or unauthorized collection">Gambling or unauthorized collection</div>
                          <div class="violation-option" data-violation="Disrespect to school authorities">Disrespect to school authorities</div>
                          <div class="violation-option" data-violation="Skipping class / truancy">Skipping class / truancy</div>
                          <div class="violation-option" data-violation="Public display of affection">Public display of affection</div>
                          <div class="violation-option" data-violation="Minor vandalism">Minor vandalism</div>
                          <div class="violation-option" data-violation="Cyberbullying">Cyberbullying</div>
                          <div class="violation-option" data-violation="Gang-like behavior">Gang-like behavior</div>
                          <div class="violation-option" data-violation="Gross misconduct">Gross misconduct</div>
                      </div>
                  </div>
                  
                  <div class="category-section major" data-category="Major">
                      <div class="category-header" onclick="toggleViolationCategory(this)">
                          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <circle cx="12" cy="12" r="10"/>
                              <line x1="15" y1="9" x2="9" y2="15"/>
                              <line x1="9" y1="9" x2="15" y2="15"/>
                          </svg>
                          Major Offenses
                      </div>
                      <div class="violations-list">
                          <div class="violation-option" data-violation="Forgery or document tampering">Forgery or document tampering</div>
                          <div class="violation-option" data-violation="Major vandalism">Major vandalism</div>
                          <div class="violation-option" data-violation="Physical assault">Physical assault</div>
                          <div class="violation-option" data-violation="Stealing school or class funds">Stealing school or class funds</div>
                          <div class="violation-option" data-violation="Extortion or blackmail">Extortion or blackmail</div>
                          <div class="violation-option" data-violation="Immoral or scandalous behavior">Immoral or scandalous behavior</div>
                          <div class="violation-option" data-violation="Criminal charges or conviction">Criminal charges or conviction</div>
                          <div class="violation-option" data-violation="Harassment of students/staff">Harassment of students/staff</div>
                          <div class="violation-option" data-violation="Sexual harassment">Sexual harassment</div>
                          <div class="violation-option" data-violation="Lewd or immoral acts">Lewd or immoral acts</div>
                          <div class="violation-option" data-violation="Bribing school officials">Bribing school officials</div>
                          <div class="violation-option" data-violation="Drinking alcohol in school">Drinking alcohol in school</div>
                          <div class="violation-option" data-violation="Drug-related activities">Drug-related activities</div>
                          <div class="violation-option" data-violation="Bringing deadly weapons">Bringing deadly weapons</div>
                          <div class="violation-option" data-violation="Joining fraternities/sororities">Joining fraternities/sororities</div>
                      </div>
                  </div>
              </div>
              
              <div class="selected-violations">
                  <h4>
                      <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M9 11H3l5-5 5 5h-6v8h-2v-8Z"/>
                          <path d="M22 12h-6v8h-2v-8l-5-5 5-5h8Z"/>
                      </svg>
                      Selected Violations (0)
                  </h4>
                  <div id="selectedViolationsList">
                      <div class="no-data">No violations selected yet</div>
                  </div>
              </div>
          </form>
          
          <div class="modal-actions">
              <button class="btn-modal btn-save" id="saveBulkBtn" onclick="saveBulkViolations()" disabled>
                  <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                      <polyline points="17,21 17,13 7,13 7,21"/>
                      <polyline points="7,3 7,8 15,8"/>
                  </svg>
                  Record Violations
              </button>
              <button class="btn-modal btn-cancel" onclick="closeBulkViolationModal()">
                  <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="6" x2="6" y2="18"/>
                      <line x1="6" y1="6" x2="18" y2="18"/>
                  </svg>
                  Cancel
              </button>
          </div>
      </div>
  </div>

  <!-- View/Resolve Violation Modal -->
  <div id="viewViolationModal" class="violation-form-modal">
      <div class="modal-content">
          <div class="modal-header">
              <h2 class="modal-title">
                  <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                  </svg>
                  Violation Details
              </h2>
              <button class="modal-close" onclick="closeViewModal()">×</button>
          </div>
          
          <div id="violationDetails"></div>
          
          <div id="resolveSection" style="display: none;">
              <div class="form-group">
                  <label for="resolvedBy">
                      <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                          <circle cx="9" cy="7" r="4"/>
                          <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                      </svg>
                      Resolved By
                  </label>
                  <input type="text" id="resolvedBy" placeholder="Your name/position">
              </div>
              
              <div class="form-group">
                  <label for="resolutionNotes">
                      <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                          <polyline points="14,2 14,8 20,8"/>
                          <line x1="16" y1="13" x2="8" y2="13"/>
                          <line x1="16" y1="17" x2="8" y2="17"/>
                          <polyline points="10,9 9,9 8,9"/>
                      </svg>
                      Resolution Notes
                  </label>
                  <textarea id="resolutionNotes" placeholder="Enter resolution details"></textarea>
              </div>
          </div>
          
          <div class="modal-actions">
              <button class="btn-modal btn-resolve" id="resolveBtn" onclick="confirmResolveViolation()" style="display: none;">
                  <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M9 12l2 2 4-4"/>
                      <circle cx="12" cy="12" r="10"/>
                  </svg>
                  Mark as Resolved
              </button>
              <button class="btn-modal btn-cancel" onclick="closeViewModal()">
                  <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M9 14 4 9l5-5"/>
                      <path d="M20 20v-7a4 4 0 0 0-4-4H4"/>
                  </svg>
                  Close
              </button>
          </div>
      </div>
  </div>

  <script>
        let currentViolations = [];
        let currentViolationId = null;
        let selectedBulkViolations = [];

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            loadInitialData();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Search functionality
            document.getElementById('searchInput').addEventListener('input', debounce(filterViolations, 300));
            document.getElementById('statusFilter').addEventListener('change', filterViolations);
            document.getElementById('categoryFilter').addEventListener('change', filterViolations);
            
            // Student ID lookup for single violation
            document.getElementById('studentId').addEventListener('blur', lookupStudent);
            
            // Student ID lookup for bulk violations
            document.getElementById('bulkStudentId').addEventListener('blur', lookupBulkStudent);
            
            // Bulk violation selection
            document.querySelectorAll('.violation-option').forEach(option => {
                option.addEventListener('click', toggleViolationSelection);
            });
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        async function loadInitialData() {
            await refreshViolations();
            await updateStatistics();
        }

        async function refreshViolations() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_violations');
                formData.append('search', document.getElementById('searchInput').value);
                formData.append('status', document.getElementById('statusFilter').value);
                formData.append('category', document.getElementById('categoryFilter').value);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                currentViolations = await response.json();
                renderViolationsTable(currentViolations);
                
            } catch (error) {
                console.error('Error loading violations:', error);
                showNotification('Error loading violations data', 'error');
            }
        }

        function renderViolationsTable(violations) {
            const tbody = document.getElementById('violationsTableBody');
            tbody.innerHTML = '';

            if (violations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">No violations found matching your criteria</td></tr>';
                return;
            }

            violations.forEach(violation => {
                const row = document.createElement('tr');
                
                const categoryClass = violation.violation_category === 'Minor' ? 'active' : 
                                    (violation.violation_category === 'Serious' ? 'pending' : 'resolved');
                const statusClass = violation.status === 'Active' ? 'pending' : 'resolved';
                
                row.innerHTML = `
                    <td>${new Date(violation.violation_date).toLocaleDateString('en-US', { 
                        month: 'short', day: 'numeric', year: 'numeric' 
                    })}</td>
                    <td>
                        <div style="font-weight: 500;">${violation.student_name}</div>
                        <div style="font-size: 12px; color: #64748b;">${violation.student_id} - ${violation.grade} ${violation.section}</div>
                    </td>
                    <td>${violation.violation_type}</td>
                    <td>
                        <span class="status-badge status-${categoryClass}">
                            ${violation.violation_category}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-${statusClass}">
                            ${violation.status}
                        </span>
                    </td>
                    <td>
                        <div class="violation-actions">
                            <button class="btn-small btn-view" onclick="viewViolation(${violation.id})">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                View
                            </button>
                            ${violation.status === 'Active' ? 
                                `<button class="btn-small btn-resolve" onclick="resolveViolation(${violation.id}, '${violation.student_name}')">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 12l2 2 4-4"/>
                                        <circle cx="12" cy="12" r="10"/>
                                    </svg>
                                    Resolve
                                </button>` : 
                                ''
                            }
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }

        function filterViolations() {
            refreshViolations();
        }

        async function updateStatistics() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_report_data');
                formData.append('start_date', new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
                formData.append('end_date', new Date().toISOString().split('T')[0]);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                const stats = data.stats;

                document.getElementById('totalViolations').textContent = stats.total_violations || 0;
                document.getElementById('activeViolations').textContent = stats.active_violations || 0;
                document.getElementById('resolvedViolations').textContent = stats.resolved_violations || 0;
                document.getElementById('majorViolations').textContent = stats.major_violations || 0;

            } catch (error) {
                console.error('Error updating statistics:', error);
            }
        }

        // Student lookup functionality
        async function lookupStudent() {
            const studentId = document.getElementById('studentId').value.trim();
            const infoDiv = document.getElementById('studentInfo');
            
            if (!studentId) {
                infoDiv.style.display = 'none';
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'get_student_info');
                formData.append('student_id', studentId);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    const student = result.student;
                    infoDiv.innerHTML = `
                        <strong>Student Found:</strong><br>
                        Name: ${student.name}<br>
                        Grade: ${student.grade} - ${student.section}<br>
                        Status: ${student.status}
                    `;
                    infoDiv.className = 'student-info-display';
                } else {
                    infoDiv.innerHTML = `
                        <strong>Student Not Found:</strong><br>
                        No student found with ID: ${studentId}
                    `;
                    infoDiv.className = 'student-info-display not-found';
                }
                
                infoDiv.style.display = 'block';
                
            } catch (error) {
                console.error('Error looking up student:', error);
            }
        }

        async function lookupBulkStudent() {
            const studentId = document.getElementById('bulkStudentId').value.trim();
            const infoDiv = document.getElementById('bulkStudentInfo');
            
            if (!studentId) {
                infoDiv.style.display = 'none';
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'get_student_info');
                formData.append('student_id', studentId);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    const student = result.student;
                    infoDiv.innerHTML = `
                        <strong>Student Found:</strong><br>
                        Name: ${student.name}<br>
                        Grade: ${student.grade} - ${student.section}<br>
                        Status: ${student.status}
                    `;
                    infoDiv.className = 'student-info-display';
                } else {
                    infoDiv.innerHTML = `
                        <strong>Student Not Found:</strong><br>
                        No student found with ID: ${studentId}
                    `;
                    infoDiv.className = 'student-info-display not-found';
                }
                
                infoDiv.style.display = 'block';
                
            } catch (error) {
                console.error('Error looking up student:', error);
            }
        }

        // Modal functions
        function openNewViolationModal() {
            document.getElementById('violationModal').classList.add('show');
            document.getElementById('violationForm').reset();
            document.getElementById('studentInfo').style.display = 'none';
        }

        function closeViolationModal() {
            document.getElementById('violationModal').classList.remove('show');
        }

        function openBulkViolationModal() {
            document.getElementById('bulkViolationModal').classList.add('show');
            document.getElementById('bulkViolationForm').reset();
            document.getElementById('bulkStudentInfo').style.display = 'none';
            selectedBulkViolations = [];
            updateSelectedViolationsList();
        }

        function closeBulkViolationModal() {
            document.getElementById('bulkViolationModal').classList.remove('show');
        }

        // Bulk violation functions
        function toggleViolationCategory(header) {
            const section = header.parentElement;
            section.classList.toggle('expanded');
        }

        function toggleViolationSelection(event) {
            const option = event.target;
            const violation = option.dataset.violation;
            const category = option.closest('.category-section').dataset.category;
            
            if (option.classList.contains('selected')) {
                // Remove from selection
                option.classList.remove('selected');
                selectedBulkViolations = selectedBulkViolations.filter(v => v.text !== violation);
            } else {
                // Add to selection
                option.classList.add('selected');
                selectedBulkViolations.push({
                    text: violation,
                    category: category,
                    timestamp: new Date().toLocaleString()
                });
            }
            
            updateSelectedViolationsList();
        }

        function updateSelectedViolationsList() {
            const list = document.getElementById('selectedViolationsList');
            const saveBtn = document.getElementById('saveBulkBtn');
            const title = list.previousElementSibling;
            
            title.innerHTML = `
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11H3l5-5 5 5h-6v8h-2v-8Z"/>
                    <path d="M22 12h-6v8h-2v-8l-5-5 5-5h8Z"/>
                </svg>
                Selected Violations (${selectedBulkViolations.length})
            `;
            
            if (selectedBulkViolations.length === 0) {
                list.innerHTML = '<div class="no-data">No violations selected yet</div>';
                saveBtn.disabled = true;
            } else {
                list.innerHTML = '';
                selectedBulkViolations.forEach((violation, index) => {
                    const item = document.createElement('div');
                    item.className = 'selected-violation-item';
                    item.innerHTML = `
                        <div class="selected-violation-text">
                            <strong>${violation.category}:</strong> ${violation.text}
                        </div>
                        <button class="remove-violation-btn" onclick="removeSelectedViolation(${index})">×</button>
                    `;
                    list.appendChild(item);
                });
                saveBtn.disabled = false;
            }
        }

        function removeSelectedViolation(index) {
            const violation = selectedBulkViolations[index];
            
            // Remove selection from UI
            const option = document.querySelector(`[data-violation="${violation.text}"]`);
            if (option) {
                option.classList.remove('selected');
            }
            
            // Remove from array
            selectedBulkViolations.splice(index, 1);
            updateSelectedViolationsList();
        }

        // Save functions
        async function saveViolation() {
            const form = document.getElementById('violationForm');
            const formData = new FormData(form);
            formData.append('action', 'add_violation');

            try {
                const saveBtn = document.querySelector('.btn-save');
                saveBtn.disabled = true;
                saveBtn.innerHTML = `
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                    </svg>
                    Saving...
                `;

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('Violation recorded successfully!', 'success');
                    closeViolationModal();
                    refreshViolations();
                    updateStatistics();
                } else {
                    showNotification('Error recording violation', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error', 'error');
            } finally {
                const saveBtn = document.querySelector('.btn-save');
                saveBtn.disabled = false;
                saveBtn.innerHTML = `
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17,21 17,13 7,13 7,21"/>
                        <polyline points="7,3 7,8 15,8"/>
                    </svg>
                    Save Violation
                `;
            }
        }

        async function saveBulkViolations() {
            if (selectedBulkViolations.length === 0) {
                showNotification('Please select at least one violation', 'error');
                return;
            }

            const studentId = document.getElementById('bulkStudentId').value.trim();
            const recordedBy = document.getElementById('bulkRecordedBy').value.trim();

            if (!studentId || !recordedBy) {
                showNotification('Please fill in all required fields', 'error');
                return;
            }

            if (!confirm(`Record ${selectedBulkViolations.length} violation(s) for student ${studentId}?`)) {
                return;
            }

            try {
                const saveBtn = document.getElementById('saveBulkBtn');
                saveBtn.disabled = true;
                saveBtn.innerHTML = `
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                    </svg>
                    Recording...
                `;

                const formData = new FormData();
                formData.append('action', 'add_bulk_violations');
                formData.append('student_id', studentId);
                formData.append('violations', JSON.stringify(selectedBulkViolations));
                formData.append('recorded_by', recordedBy);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    showNotification(`Successfully recorded ${result.count} violation(s)!`, 'success');
                    closeBulkViolationModal();
                    refreshViolations();
                    updateStatistics();
                } else {
                    showNotification('Error recording violations', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error', 'error');
            } finally {
                const saveBtn = document.getElementById('saveBulkBtn');
                saveBtn.disabled = false;
                saveBtn.innerHTML = `
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17,21 17,13 7,13 7,21"/>
                        <polyline points="7,3 7,8 15,8"/>
                    </svg>
                    Record Violations
                `;
            }
        }

        // View and resolve functions
        function viewViolation(violationId) {
            const violation = currentViolations.find(v => v.id == violationId);
            if (!violation) {
                showNotification('Violation not found', 'error');
                return;
            }

            const detailsHtml = `
                <div class="violation-details">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                Student
                            </div>
                            <div class="detail-value">${violation.student_name}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                Student ID
                            </div>
                            <div class="detail-value">${violation.student_id}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                </svg>
                                Grade & Section
                            </div>
                            <div class="detail-value">${violation.grade} - ${violation.section}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18M7 12h10m-7 6h4"/>
                                </svg>
                                Category
                            </div>
                            <div class="detail-value">
                                <span class="status-badge status-${violation.violation_category === 'Minor' ? 'active' : 
                                    (violation.violation_category === 'Serious' ? 'pending' : 'resolved')}">
                                    ${violation.violation_category}
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                                </svg>
                                Status
                            </div>
                            <div class="detail-value">
                                <span class="status-badge status-${violation.status === 'Active' ? 'pending' : 'resolved'}">
                                    ${violation.status}
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                Date Recorded
                            </div>
                            <div class="detail-value">${new Date(violation.violation_date).toLocaleString()}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                Recorded By
                            </div>
                            <div class="detail-value">${violation.recorded_by || 'N/A'}</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <div class="detail-label">
                            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                                <path d="M12 9v4"/>
                                <path d="m12 17 .01 0"/>
                            </svg>
                            Violation Type
                        </div>
                        <div class="detail-value" style="background: white; padding: 10px; border-radius: 6px; border-left: 4px solid #3b82f6;">
                            ${violation.violation_type}
                        </div>
                    </div>
                    
                    ${violation.notes ? `
                        <div style="margin-top: 15px;">
                            <div class="detail-label">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                    <polyline points="10,9 9,9 8,9"/>
                                </svg>
                                Notes
                            </div>
                            <div class="detail-value" style="background: white; padding: 10px; border-radius: 6px; white-space: pre-wrap;">
                                ${violation.notes}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${violation.resolved_date ? `
                        <div style="margin-top: 15px;">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 12l2 2 4-4"/>
                                            <circle cx="12" cy="12" r="10"/>
                                        </svg>
                                        Resolved Date
                                    </div>
                                    <div class="detail-value">${new Date(violation.resolved_date).toLocaleString()}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                        </svg>
                                        Resolved By
                                    </div>
                                    <div class="detail-value">${violation.resolved_by || 'N/A'}</div>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;

            document.getElementById('violationDetails').innerHTML = detailsHtml;
            
            // Show resolve section if violation is active
            const resolveSection = document.getElementById('resolveSection');
            const resolveBtn = document.getElementById('resolveBtn');
            
            if (violation.status === 'Active') {
                resolveSection.style.display = 'block';
                resolveBtn.style.display = 'inline-block';
                currentViolationId = violationId;
            } else {
                resolveSection.style.display = 'none';
                resolveBtn.style.display = 'none';
                currentViolationId = null;
            }

            document.getElementById('viewViolationModal').classList.add('show');
        }

        function closeViewModal() {
            document.getElementById('viewViolationModal').classList.remove('show');
            currentViolationId = null;
        }

        function resolveViolation(violationId, studentName) {
            if (confirm(`Mark violation for ${studentName} as resolved?`)) {
                viewViolation(violationId);
            }
        }

        async function confirmResolveViolation() {
            if (!currentViolationId) return;

            const resolvedBy = document.getElementById('resolvedBy').value.trim();
            const notes = document.getElementById('resolutionNotes').value.trim();

            if (!resolvedBy) {
                showNotification('Please enter who resolved this violation', 'error');
                return;
            }

            try {
                const resolveBtn = document.getElementById('resolveBtn');
                resolveBtn.disabled = true;
                resolveBtn.innerHTML = `
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                    </svg>
                    Resolving...
                `;

                const formData = new FormData();
                formData.append('action', 'resolve_violation');
                formData.append('violation_id', currentViolationId);
                formData.append('resolved_by', resolvedBy);
                formData.append('notes', notes);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('Violation marked as resolved!', 'success');
                    closeViewModal();
                    refreshViolations();
                    updateStatistics();
                } else {
                    showNotification('Error resolving violation', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error', 'error');
            } finally {
                const resolveBtn = document.getElementById('resolveBtn');
                resolveBtn.disabled = false;
                resolveBtn.innerHTML = `
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4"/>
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                    Mark as Resolved
                `;
            }
        }

        // Report and export functions
        async function generateReport() {
            try {
                const startDate = prompt('Enter start date (YYYY-MM-DD):', new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
                const endDate = prompt('Enter end date (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
                
                if (!startDate || !endDate) return;

                const formData = new FormData();
                formData.append('action', 'get_report_data');
                formData.append('start_date', startDate);
                formData.append('end_date', endDate);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                const stats = data.stats;
                const topViolators = data.top_violators;

                let reportHtml = `
                    <h3>
                        <svg class="icon-large" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        Violation Report (${startDate} to ${endDate})
                    </h3>
                    <div class="stats-cards" style="margin: 20px 0;">
                        <div class="stat-card-small">
                            <div class="stat-number-small">${stats.total_violations}</div>
                            <div class="stat-label-small">Total Violations</div>
                        </div>
                        <div class="stat-card-small">
                            <div class="stat-number-small">${stats.active_violations}</div>
                            <div class="stat-label-small">Active</div>
                        </div>
                        <div class="stat-card-small">
                            <div class="stat-number-small">${stats.resolved_violations}</div>
                            <div class="stat-label-small">Resolved</div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                        <div style="background: #f8fafc; padding: 20px; border-radius: 8px;">
                            <h4>
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="20" x2="18" y2="10"/>
                                    <line x1="12" y1="20" x2="12" y2="4"/>
                                    <line x1="6" y1="20" x2="6" y2="14"/>
                                </svg>
                                Violations by Category
                            </h4>
                            <ul style="list-style: none; padding: 0;">
                                <li style="padding: 5px 0; border-bottom: 1px solid #e2e8f0;">Minor: <strong>${stats.minor_violations}</strong></li>
                                <li style="padding: 5px 0; border-bottom: 1px solid #e2e8f0;">Serious: <strong>${stats.serious_violations}</strong></li>
                                <li style="padding: 5px 0;">Major: <strong>${stats.major_violations}</strong></li>
                            </ul>
                        </div>
                        <div style="background: #f8fafc; padding: 20px; border-radius: 8px;">
                            <h4>
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                Top Violators
                            </h4>
                            <ol style="padding-left: 20px;">
                `;

                if (topViolators.length === 0) {
                    reportHtml += '<li style="padding: 5px 0; color: #64748b; font-style: italic;">No violators found in this period</li>';
                } else {
                    topViolators.forEach((violator, index) => {
                        reportHtml += `<li style="padding: 5px 0; border-bottom: 1px solid #e2e8f0;">${violator.name} (${violator.student_id}) - <strong>${violator.violation_count} violations</strong></li>`;
                    });
                }

                reportHtml += `
                            </ol>
                        </div>
                    </div>
                `;

                document.getElementById('reportContent').innerHTML = reportHtml;
                document.getElementById('reportSection').style.display = 'block';
                document.getElementById('reportSection').scrollIntoView({ behavior: 'smooth' });

            } catch (error) {
                console.error('Error generating report:', error);
                showNotification('Error generating report', 'error');
            }
        }

        function exportData() {
            if (currentViolations.length === 0) {
                showNotification('No data to export', 'error');
                return;
            }

            const csvData = [
                ['Date', 'Student ID', 'Student Name', 'Grade', 'Section', 'Violation Type', 'Category', 'Status', 'Recorded By', 'Resolved By', 'Notes']
            ];

            currentViolations.forEach(violation => {
                csvData.push([
                    new Date(violation.violation_date).toLocaleDateString(),
                    violation.student_id,
                    violation.student_name,
                    violation.grade || '',
                    violation.section || '',
                    violation.violation_type,
                    violation.violation_category,
                    violation.status,
                    violation.recorded_by || '',
                    violation.resolved_by || '',
                    (violation.notes || '').replace(/\n/g, ' ')
                ]);
            });

            const csvContent = csvData.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `violations_export_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showNotification('Data exported successfully!', 'success');
        }

        // Notification system
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 9999;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                animation: slideInRight 0.3s ease;
                display: flex;
                align-items: center;
                gap: 8px;
            `;
            
            const icon = type === 'success' ? 
                `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 12l2 2 4-4"/>
                    <circle cx="12" cy="12" r="10"/>
                </svg>` : 
                `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>`;
            
            notification.innerHTML = icon + message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

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

        // Add click event listeners to menu items
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close any open modals
                document.querySelectorAll('.violation-form-modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        // Auto-refresh every 30 seconds for active violations
        setInterval(async () => {
            if (document.getElementById('statusFilter').value === 'Active' || document.getElementById('statusFilter').value === '') {
                await refreshViolations();
                await updateStatistics();
            }
        }, 30000);

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
  </script>
</body>
</html>