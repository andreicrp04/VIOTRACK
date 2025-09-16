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
        
        $sql = "SELECT v.*, s.name as student_name 
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
        $violation_id = $_POST['violation_id'] ?? null;
        $resolved_by = $_POST['resolved_by'] ?? 'System';
        $notes = $_POST['notes'] ?? '';
        
        // Validate violation_id
        if (empty($violation_id) || !is_numeric($violation_id)) {
            echo json_encode(['success' => false, 'error' => 'Invalid violation ID']);
            exit;
        }
        
        // First check if violation exists and is active
        $check_sql = "SELECT id, status FROM violations WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if (!$check_stmt) {
            echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        
        $check_stmt->bind_param("i", $violation_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Violation not found']);
            exit;
        }
        
        $violation = $check_result->fetch_assoc();
        if ($violation['status'] !== 'Active') {
            echo json_encode(['success' => false, 'error' => 'Violation is already resolved']);
            exit;
        }
        
        // Prepare the resolution notes
        $resolution_note = "\n[RESOLVED on " . date('Y-m-d H:i:s') . " by " . $resolved_by . "] " . $notes;
        
        // Update the violation
        $sql = "UPDATE violations SET 
                status = 'Resolved', 
                resolved_by = ?, 
                resolved_date = NOW(),
                notes = CONCAT(COALESCE(notes, ''), ?)
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("ssi", $resolved_by, $resolution_note, $violation_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Violation resolved successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'No rows were updated']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Database execution error: ' . $stmt->error]);
        }
        
        $stmt->close();
        exit;
    }
    
    if ($action === 'add_violation') {
        $student_id = $_POST['student_id'] ?? '';
        $violation_type = $_POST['violation_type'] ?? '';
        $category = $_POST['category'] ?? '';
        $description = $_POST['description'] ?? '';
        $recorded_by = $_POST['recorded_by'] ?? '';
        
        // Validate required fields
        if (empty($student_id) || empty($violation_type) || empty($category) || empty($recorded_by)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }
        
        $sql = "INSERT INTO violations (student_id, violation_type, violation_category, notes, recorded_by) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("sssss", $student_id, $violation_type, $category, $description, $recorded_by);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Violation added successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database execution error: ' . $stmt->error]);
        }
        
        $stmt->close();
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
    
    // If no valid action found
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Get initial violation data
$violations_query = "SELECT v.*, s.name as student_name 
                     FROM violations v 
                     JOIN students s ON v.student_id = s.student_id 
                     ORDER BY v.violation_date DESC 
                     LIMIT 20";
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
    }
    
    .violation-form-modal.show {
        display: flex;
    }
    
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
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
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
        padding: 5px;
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
        display: block;
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
    
    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    
    .btn-modal {
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
    }
    
    .filter-group select,
    .filter-group input {
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 14px;
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
    
    .btn-small {
        padding: 4px 8px;
        font-size: 12px;
        border-radius: 4px;
    }
    
    .btn-resolve {
        background: #10b981;
        color: white;
        border: none;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    
    .btn-resolve:hover {
        background: #059669;
    }
    
    .btn-view {
        background: #3b82f6;
        color: white;
        border: none;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    
    .btn-view:hover {
        background: #2563eb;
    }
    
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card-small {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    
    .stat-number-small {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-label-small {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
    }
    
    .report-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        margin-top: 25px;
    }
    
    @media (max-width: 768px) {
        .form-grid {
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
            <a href="violations.php" class="menu-item active" data-page="violations">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                        <path d="M12 9v4"/>
                        <path d="m12 17 .01 0"/>
                    </svg>
                </div>
                <span class="menu-text">VIOLATIONS</span>
            </a>
            <a href="track.php" class="menu-item" data-page="track">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <span class="menu-text">TRACK LOCATION</span>
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
          <h1 class="page-header">Violation Management</h1>
          
          <!-- Statistics Cards -->
          <div class="stats-cards" id="statsCards">
              <div class="stat-card-small">
                  <div class="stat-number-small" id="totalViolations">0</div>
                  <div class="stat-label-small">Total Violations</div>
              </div>
              <div class="stat-card-small">
                  <div class="stat-number-small" id="activeViolations">0</div>
                  <div class="stat-label-small">Active Violations</div>
              </div>
              <div class="stat-card-small">
                  <div class="stat-number-small" id="resolvedViolations">0</div>
                  <div class="stat-label-small">Resolved Violations</div>
              </div>
              <div class="stat-card-small">
                  <div class="stat-number-small" id="majorViolations">0</div>
                  <div class="stat-label-small">Major Violations</div>
              </div>
          </div>

          <div class="content-card">
              <div class="content-title">Active Violations Management</div>
              
              <!-- Filters -->
              <div class="filters-section">
                  <div class="filter-group">
                      <label>Search</label>
                      <input type="text" id="searchInput" placeholder="Search violations...">
                  </div>
                  <div class="filter-group">
                      <label>Status</label>
                      <select id="statusFilter">
                          <option value="">All Statuses</option>
                          <option value="Active">Active</option>
                          <option value="Resolved">Resolved</option>
                      </select>
                  </div>
                  <div class="filter-group">
                      <label>Actions</label>
                      <button class="btn btn-primary" onclick="refreshViolations()">🔄 Refresh</button>
                  </div>
              </div>

              <div class="content-section">
                  <div id="violationsTableContainer">
                      <table class="data-table" id="violationsTable">
                          <thead>
                              <tr>
                                  <th>Date</th>
                                  <th>Student</th>
                                  <th>Violation Type</th>
                                  <th>Category</th>
                                  <th>Status</th>
                                  <th>Actions</th>
                              </tr>
                          </thead>
                          <tbody id="violationsTableBody">
                              <?php while($row = $violations_result->fetch_assoc()): ?>
                              <tr>
                                  <td><?php echo date('M j, Y', strtotime($row['violation_date'])); ?></td>
                                  <td><?php echo htmlspecialchars($row['student_name']) . ' (' . htmlspecialchars($row['student_id']) . ')'; ?></td>
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
                                          <button class="btn-small btn-view" onclick="viewViolation(<?php echo $row['id']; ?>)">View</button>
                                          <?php if($row['status'] == 'Active'): ?>
                                              <button class="btn-small btn-resolve" onclick="resolveViolation(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')">Resolve</button>
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
                  <button class="btn btn-primary" onclick="openNewViolationModal()">➕ Report New Violation</button>
                  <button class="btn btn-secondary" onclick="generateReport()">📊 Generate Report</button>
                  <button class="btn btn-secondary" onclick="exportData()">📄 Export Data</button>
              </div>
          </div>
          
          <!-- Report Section -->
          <div class="report-section" id="reportSection" style="display: none;">
              <div class="section-header">Violation Report</div>
              <div id="reportContent"></div>
          </div>
      </div>
  </div>

  <!-- New Violation Modal -->
  <div id="violationModal" class="violation-form-modal">
      <div class="modal-content">
          <div class="modal-header">
              <h2 class="modal-title">Report New Violation</h2>
              <button class="modal-close" onclick="closeViolationModal()">&times;</button>
          </div>
          
          <form id="violationForm">
              <div class="form-grid">
                  <div class="form-group">
                      <label for="studentId">Student ID</label>
                      <input type="text" id="studentId" name="student_id" required>
                  </div>
                  
                  <div class="form-group">
                      <label for="violationCategory">Category</label>
                      <select id="violationCategory" name="category" required>
                          <option value="">Select Category</option>
                          <option value="Minor">Minor Offense</option>
                          <option value="Serious">Serious Offense</option>
                          <option value="Major">Major Offense</option>
                      </select>
                  </div>
              </div>
              
              <div class="form-group full-width">
                  <label for="violationType">Violation Type</label>
                  <input type="text" id="violationType" name="violation_type" required placeholder="Enter violation type">
              </div>
              
              <div class="form-group full-width">
                  <label for="violationDescription">Description</label>
                  <textarea id="violationDescription" name="description" placeholder="Enter detailed description of the violation"></textarea>
              </div>
              
              <div class="form-group full-width">
                  <label for="recordedBy">Recorded By</label>
                  <input type="text" id="recordedBy" name="recorded_by" required placeholder="Your name/position">
              </div>
          </form>
          
          <div class="modal-actions">
              <button class="btn-modal btn-save" onclick="saveViolation()">Save Violation</button>
              <button class="btn-modal btn-cancel" onclick="closeViolationModal()">Cancel</button>
          </div>
      </div>
  </div>

  <!-- View/Resolve Violation Modal -->
  <div id="viewViolationModal" class="violation-form-modal">
      <div class="modal-content">
          <div class="modal-header">
              <h2 class="modal-title">Violation Details</h2>
              <button class="modal-close" onclick="closeViewModal()">&times;</button>
          </div>
          
          <div id="violationDetails"></div>
          
          <div id="resolveSection" style="display: none;">
              <div class="form-group">
                  <label for="resolvedBy">Resolved By</label>
                  <input type="text" id="resolvedBy" placeholder="Your name/position">
              </div>
              
              <div class="form-group">
                  <label for="resolutionNotes">Resolution Notes</label>
                  <textarea id="resolutionNotes" placeholder="Enter resolution details"></textarea>
              </div>
          </div>
          
          <div class="modal-actions">
              <button class="btn-modal btn-resolve" id="resolveBtn" onclick="confirmResolveViolation()" style="display: none;">Mark as Resolved</button>
              <button class="btn-modal btn-cancel" onclick="closeViewModal()">Close</button>
          </div>
      </div>
  </div>

  <script>
        // Complete JavaScript for violations.php - Fixed version
let currentViolations = [];
let currentViolationId = null;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    loadInitialData();
    setupEventListeners();
});

function setupEventListeners() {
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', debounce(filterViolations, 300));
    document.getElementById('statusFilter').addEventListener('change', filterViolations);
    
    // Modal close handlers
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('violation-form-modal')) {
            if (e.target.id === 'violationModal') closeViolationModal();
            if (e.target.id === 'viewViolationModal') closeViewModal();
        }
    });
    
    // Escape key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('violationModal').classList.contains('show')) {
                closeViolationModal();
            }
            if (document.getElementById('viewViolationModal').classList.contains('show')) {
                closeViewModal();
            }
        }
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
    showLoadingState('Loading violations...');
    await refreshViolations();
    await updateStatistics();
    hideLoadingState();
}

function showLoadingState(message) {
    const tbody = document.getElementById('violationsTableBody');
    tbody.innerHTML = `<tr><td colspan="6" class="loading">${message}</td></tr>`;
}

function hideLoadingState() {
    // Loading state is handled by renderViolationsTable
}

async function refreshViolations() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_violations');
        formData.append('search', document.getElementById('searchInput').value);
        formData.append('status', document.getElementById('statusFilter').value);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.text();
        
        try {
            currentViolations = JSON.parse(data);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', data);
            throw new Error('Invalid response from server');
        }

        renderViolationsTable(currentViolations);
        
    } catch (error) {
        console.error('Error loading violations:', error);
        const tbody = document.getElementById('violationsTableBody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #dc3545; padding: 40px;">Error loading violations data. Please refresh and try again.</td></tr>';
        showAlert('Error loading violations data', 'error');
    }
}

function renderViolationsTable(violations) {
    const tbody = document.getElementById('violationsTableBody');
    tbody.innerHTML = '';

    if (violations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #64748b; padding: 40px;">No violations found</td></tr>';
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
            <td>${escapeHtml(violation.student_name)} (${escapeHtml(violation.student_id)})</td>
            <td>${escapeHtml(violation.violation_type)}</td>
            <td>
                <span class="status-badge status-${categoryClass}">
                    ${escapeHtml(violation.violation_category)}
                </span>
            </td>
            <td>
                <span class="status-badge status-${statusClass}">
                    ${escapeHtml(violation.status)}
                </span>
            </td>
            <td>
                <div class="violation-actions">
                    <button class="btn-small btn-view" onclick="viewViolation(${violation.id})">View</button>
                    ${violation.status === 'Active' ? 
                        `<button class="btn-small btn-resolve" onclick="resolveViolation(${violation.id}, '${escapeHtml(violation.student_name).replace(/'/g, '\\\'')}')" data-violation-id="${violation.id}">Resolve</button>` : 
                        ''
                    }
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

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

function openNewViolationModal() {
    document.getElementById('violationModal').classList.add('show');
    document.getElementById('violationForm').reset();
    document.getElementById('studentId').focus();
}

function closeViolationModal() {
    document.getElementById('violationModal').classList.remove('show');
}

async function saveViolation() {
    const form = document.getElementById('violationForm');
    const saveBtn = document.querySelector('.btn-save');
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Show loading state
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;

    try {
        const formData = new FormData(form);
        formData.append('action', 'add_violation');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        
        if (result.success) {
            showAlert('Violation recorded successfully!', 'success');
            closeViolationModal();
            refreshViolations();
            updateStatistics();
        } else {
            throw new Error(result.error || 'Server returned error');
        }
        
    } catch (error) {
        console.error('Error saving violation:', error);
        showAlert('Error recording violation. Please try again.', 'error');
    } finally {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    }
}

function viewViolation(violationId) {
    const violation = currentViolations.find(v => v.id == violationId);
    if (!violation) {
        showAlert('Violation not found', 'error');
        return;
    }

    const detailsHtml = `
        <div style="margin-bottom: 20px;">
            <h3 style="color: #1e293b; margin-bottom: 15px;">Violation Information</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><strong>Student:</strong> ${escapeHtml(violation.student_name)}</div>
                <div><strong>Student ID:</strong> ${escapeHtml(violation.student_id)}</div>
                <div><strong>Category:</strong> 
                    <span class="status-badge status-${violation.violation_category === 'Minor' ? 'active' : 
                        (violation.violation_category === 'Serious' ? 'pending' : 'resolved')}">
                        ${escapeHtml(violation.violation_category)}
                    </span>
                </div>
                <div><strong>Status:</strong> 
                    <span class="status-badge status-${violation.status === 'Active' ? 'pending' : 'resolved'}">
                        ${escapeHtml(violation.status)}
                    </span>
                </div>
                <div><strong>Date:</strong> ${new Date(violation.violation_date).toLocaleString()}</div>
                <div><strong>Recorded By:</strong> ${escapeHtml(violation.recorded_by || 'N/A')}</div>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Violation Type:</strong><br>
                <div style="background: #f8fafc; padding: 10px; border-radius: 4px; margin-top: 5px;">
                    ${escapeHtml(violation.violation_type)}
                </div>
            </div>
            ${violation.notes ? `
                <div style="margin-bottom: 15px;">
                    <strong>Notes:</strong><br>
                    <div style="background: #f8fafc; padding: 10px; border-radius: 4px; white-space: pre-wrap; margin-top: 5px;">
                        ${escapeHtml(violation.notes)}
                    </div>
                </div>
            ` : ''}
            ${violation.resolved_date ? `
                <div style="margin-bottom: 15px;">
                    <strong>Resolved Date:</strong> ${new Date(violation.resolved_date).toLocaleString()}<br>
                    <strong>Resolved By:</strong> ${escapeHtml(violation.resolved_by || 'N/A')}
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
        // Clear previous values
        document.getElementById('resolvedBy').value = '';
        document.getElementById('resolutionNotes').value = '';
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
    // Direct resolution approach - ask for confirmation and resolver info
    const resolvedBy = prompt(`Mark violation for ${studentName} as resolved?\n\nEnter your name/position:`, '');
    
    if (!resolvedBy || !resolvedBy.trim()) {
        return; // User cancelled or didn't provide name
    }

    const notes = prompt('Add resolution notes (optional):', '') || '';

    // Find the button and show loading state
    const resolveButton = document.querySelector(`button[data-violation-id="${violationId}"]`);
    if (resolveButton) {
        const originalText = resolveButton.textContent;
        resolveButton.textContent = 'Resolving...';
        resolveButton.disabled = true;
        
        // Reset button after operation
        setTimeout(() => {
            if (resolveButton.textContent === 'Resolving...') {
                resolveButton.textContent = originalText;
                resolveButton.disabled = false;
            }
        }, 10000); // Timeout after 10 seconds
    }

    // Send resolution request
    submitResolution(violationId, resolvedBy.trim(), notes)
        .then(success => {
            if (success) {
                showAlert('Violation marked as resolved!', 'success');
                refreshViolations();
                updateStatistics();
            }
        })
        .catch(error => {
            console.error('Error resolving violation:', error);
            showAlert('Error resolving violation. Please try again.', 'error');
        })
        .finally(() => {
            if (resolveButton) {
                resolveButton.textContent = 'Resolve';
                resolveButton.disabled = false;
            }
        });
}

async function confirmResolveViolation() {
    if (!currentViolationId) {
        showAlert('No violation selected', 'error');
        return;
    }

    const resolvedBy = document.getElementById('resolvedBy').value.trim();
    const notes = document.getElementById('resolutionNotes').value.trim();

    if (!resolvedBy) {
        showAlert('Please enter who resolved this violation', 'error');
        document.getElementById('resolvedBy').focus();
        return;
    }

    // Show loading state
    const resolveBtn = document.getElementById('resolveBtn');
    const originalText = resolveBtn.textContent;
    resolveBtn.textContent = 'Resolving...';
    resolveBtn.disabled = true;

    try {
        const success = await submitResolution(currentViolationId, resolvedBy, notes);
        
        if (success) {
            showAlert('Violation marked as resolved!', 'success');
            closeViewModal();
            refreshViolations();
            updateStatistics();
        }
        
    } catch (error) {
        console.error('Error resolving violation:', error);
        showAlert('Error resolving violation. Please try again.', 'error');
    } finally {
        resolveBtn.textContent = originalText;
        resolveBtn.disabled = false;
    }
}

async function submitResolution(violationId, resolvedBy, notes) {
    try {
        const formData = new FormData();
        formData.append('action', 'resolve_violation');
        formData.append('violation_id', violationId);
        formData.append('resolved_by', resolvedBy);
        formData.append('notes', notes);

        console.log('Submitting resolution for violation ID:', violationId);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse resolution response:', responseText);
            throw new Error('Invalid response from server: ' + responseText);
        }

        if (!result.success) {
            throw new Error(result.error || 'Server returned error');
        }

        console.log('Resolution successful:', result);
        return true;
        
    } catch (error) {
        console.error('Submit resolution error:', error);
        throw error;
    }
}

async function generateReport() {
    const reportSection = document.getElementById('reportSection');
    const reportContent = document.getElementById('reportContent');
    
    try {
        const startDate = prompt('Enter start date (YYYY-MM-DD):', new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
        const endDate = prompt('Enter end date (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
        
        if (!startDate || !endDate) return;

        // Validate dates
        if (new Date(startDate) > new Date(endDate)) {
            showAlert('Start date cannot be later than end date', 'error');
            return;
        }

        reportContent.innerHTML = '<div class="loading">Generating report...</div>';
        reportSection.style.display = 'block';

        const formData = new FormData();
        formData.append('action', 'get_report_data');
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        const stats = data.stats;
        const topViolators = data.top_violators;

        let reportHtml = `
            <h3>Violation Report (${startDate} to ${endDate})</h3>
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
                <div>
                    <h4>Violations by Category</h4>
                    <ul>
                        <li>Minor: ${stats.minor_violations}</li>
                        <li>Serious: ${stats.serious_violations}</li>
                        <li>Major: ${stats.major_violations}</li>
                    </ul>
                </div>
                <div>
                    <h4>Top Violators</h4>
                    ${topViolators.length > 0 ? '<ol>' : '<p>No violations in this period</p>'}
        `;

        topViolators.forEach(violator => {
            reportHtml += `<li>${escapeHtml(violator.name)} (${escapeHtml(violator.student_id)}) - ${violator.violation_count} violations</li>`;
        });

        if (topViolators.length > 0) {
            reportHtml += '</ol>';
        }

        reportHtml += `
                </div>
            </div>
        `;

        reportContent.innerHTML = reportHtml;
        reportSection.scrollIntoView({ behavior: 'smooth' });

    } catch (error) {
        console.error('Error generating report:', error);
        reportContent.innerHTML = '<div style="color: #dc3545; text-align: center; padding: 20px;">Error generating report. Please try again.</div>';
        showAlert('Error generating report', 'error');
    }
}

function exportData() {
    try {
        if (currentViolations.length === 0) {
            showAlert('No data to export', 'warning');
            return;
        }

        const csvData = [
            ['Date', 'Student ID', 'Student Name', 'Violation Type', 'Category', 'Status', 'Recorded By']
        ];

        currentViolations.forEach(violation => {
            csvData.push([
                new Date(violation.violation_date).toLocaleDateString(),
                violation.student_id,
                violation.student_name,
                violation.violation_type,
                violation.violation_category,
                violation.status,
                violation.recorded_by || ''
            ]);
        });

        const csvContent = csvData.map(row => 
            row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
        ).join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = `violations_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showAlert('Data exported successfully!', 'success');
        
    } catch (error) {
        console.error('Error exporting data:', error);
        showAlert('Error exporting data', 'error');
    }
}

function showAlert(message, type = 'info') {
    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 4px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;

    // Set background color based on type
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    alert.style.backgroundColor = colors[type] || colors.info;
    
    alert.textContent = message;
    
    // Add to page
    document.body.appendChild(alert);
    
    // Animate in
    setTimeout(() => {
        alert.style.opacity = '1';
        alert.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after delay
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 300);
    }, 3000);
}

// Navigation system
function showPage(pageId) {
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    
    const targetPage = document.getElementById(pageId);
    if (targetPage) {
        targetPage.classList.add('active');
    }
    
    document.querySelectorAll('.menu-item').forEach(item => {
        item.classList.remove('active');
    });
    
    const activeMenuItem = document.querySelector(`[data-page="${pageId}"]`);
    if (activeMenuItem) {
        activeMenuItem.classList.add('active');
    }
    
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
        const pageId = this.getAttribute('data-page');
        if (pageId && pageId !== 'violations') {
            // For other pages, you might want to navigate to actual URLs
            window.location.href = `${pageId}.php`;
        }
    });
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const menuItems = document.querySelectorAll('.menu-item');
    const currentActive = document.querySelector('.menu-item.active');
    const currentIndex = Array.from(menuItems).indexOf(currentActive);
    
    if (e.key === 'ArrowDown' && currentIndex < menuItems.length - 1) {
        e.preventDefault();
        const nextItem = menuItems[currentIndex + 1];
        const pageId = nextItem.getAttribute('data-page');
        if (pageId === 'violations') {
            showPage(pageId);
        }
    } else if (e.key === 'ArrowUp' && currentIndex > 0) {
        e.preventDefault();
        const prevItem = menuItems[currentIndex - 1];
        const pageId = prevItem.getAttribute('data-page');
        if (pageId === 'violations') {
            showPage(pageId);
        }
    }
});
  </script>
</body>
</html>