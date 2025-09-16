<?php
// students.php
// VIOTRACK System - Students Page with QR Code Generation
include 'db.php';

// Function to generate QR code using online API
function generateQRCode($student_id, $base_url) {
    $violation_url = $base_url . "/violationss.php?student_id=" . urlencode($student_id);
    $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($violation_url);
    return $qr_api_url;
}

// Get base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

// Handle QR Code Regeneration Only
if (isset($_POST['regenerate_qr_only'])) {
    $id = $_POST['id'];
    $student_id = $_POST['student_id'];
    
    // Generate new QR code
    $qr_code_url = generateQRCode($student_id, $base_url);
    
    $sql = "UPDATE students SET qr_code='$qr_code_url' WHERE id='$id'";
    
    if ($conn->query($sql)) {
        header("Location: students.php?message=" . urlencode("QR code regenerated successfully"));
        exit();
    } else {
        $error = "Error regenerating QR code: " . $conn->error;
    }
}

// Handle Delete Student
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM students WHERE id = '$id'";
    if ($conn->query($sql)) {
        header("Location: students.php?message=Student deleted successfully");
        exit();
    }
}

// Handle Add Student
if (isset($_POST['add_student'])) {
    $student_id = $_POST['student_id'];
    $name       = $_POST['name'];
    $grade      = $_POST['grade'];
    $section    = $_POST['section'];
    $status     = $_POST['status'];
    
    // Generate QR code URL
    $qr_code_url = generateQRCode($student_id, $base_url);

    $sql = "INSERT INTO students (student_id, name, grade, section, status, qr_code) 
            VALUES ('$student_id', '$name', '$grade', '$section', '$status', '$qr_code_url')";
    
    if ($conn->query($sql)) {
        header("Location: students.php?message=Student added successfully with QR code generated");
        exit();
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Handle Update Student
if (isset($_POST['update_student'])) {
    $id = $_POST['id'];
    $student_id = $_POST['student_id'];
    $name = $_POST['name'];
    $grade = $_POST['grade'];
    $section = $_POST['section'];
    $status = $_POST['status'];

    // Regenerate QR code URL
    $qr_code_url = generateQRCode($student_id, $base_url);

    $sql = "UPDATE students SET student_id='$student_id', name='$name', grade='$grade', section='$section', status='$status', qr_code='$qr_code_url' WHERE id='$id'";
    
    if ($conn->query($sql)) {
        header("Location: students.php?message=Student updated successfully");
        exit();
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Fetch Students with search functionality
$search = '';
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $result = $conn->query("SELECT * FROM students WHERE name LIKE '%$search%' OR student_id LIKE '%$search%' ORDER BY created_at DESC");
} else {
    $result = $conn->query("SELECT * FROM students ORDER BY created_at DESC");
}

// Get student for editing
$edit_student = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_result = $conn->query("SELECT * FROM students WHERE id = '$edit_id'");
    $edit_student = $edit_result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VIOTRACK - Students</title>
  <link rel="stylesheet" href="styles.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    /* Enhanced Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideIn {
        from { 
            transform: translate(-50%, -60%);
            opacity: 0;
        }
        to { 
            transform: translate(-50%, -50%);
            opacity: 1;
        }
    }
    
    .modal-content {
        background-color: #ffffff;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        padding: 0;
        border: none;
        border-radius: 16px;
        width: 650px;
        max-width: 95%;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease-out;
    }
    
    .modal-header {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        padding: 24px 30px;
        border-radius: 16px 16px 0 0;
        position: relative;
    }
    
    .modal-header h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
    }
    
    .modal-body {
        padding: 30px;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    .close {
        position: absolute;
        top: 20px;
        right: 25px;
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
    }
    
    .close:hover {
        background-color: rgba(255,255,255,0.2);
        transform: rotate(90deg);
    }
    
    /* Enhanced Form Styles */
    .form-group {
        margin-bottom: 20px;
        position: relative;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e1e8ed;
        border-radius: 10px;
        box-sizing: border-box;
        font-size: 15px;
        transition: all 0.3s ease;
        background-color: #fafbfc;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #007bff;
        background-color: white;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    
    .form-group input:hover,
    .form-group select:hover {
        border-color: #007bff;
    }
    
    /* Enhanced Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        min-width: 120px;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }
    
    .btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #0056b3, #004085);
    }
    
    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #545b62);
        color: white;
    }
    
    .btn-secondary:hover {
        background: linear-gradient(135deg, #545b62, #3d4142);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #bd2130);
        color: white;
    }
    
    .btn-danger:hover {
        background: linear-gradient(135deg, #bd2130, #a71e2a);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
    }
    
    .btn-success:hover {
        background: linear-gradient(135deg, #1e7e34, #155724);
    }
    
    .btn-info {
        background: linear-gradient(135deg, #17a2b8, #117a8b);
        color: white;
    }
    
    .btn-info:hover {
        background: linear-gradient(135deg, #117a8b, #0c5460);
    }
    
    .btn-warning {
        background: linear-gradient(135deg, #ffc107, #d39e00);
        color: #212529;
    }
    
    .btn-warning:hover {
        background: linear-gradient(135deg, #d39e00, #b08800);
    }
    
    /* Small button variant */
    .btn-sm {
        padding: 8px 16px;
        font-size: 12px;
        min-width: 90px;
    }
    
    /* Button with icons */
    .btn i {
        font-size: 14px;
    }
    
    /* Enhanced Search Bar */
    .search-container {
        position: relative;
        margin-bottom: 25px;
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .search-input-container {
        position: relative;
        flex: 1;
        min-width: 300px;
    }
    
    .search-input {
        width: 100%;
        padding: 14px 20px 14px 50px !important;
        border: 2px solid #e1e8ed;
        border-radius: 12px;
        font-size: 15px;
        background-color: #fafbfc;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }
    
    .search-input:focus {
        outline: none;
        border-color: #007bff;
        background-color: white;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    
    .search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        font-size: 16px;
    }
    
    /* Enhanced Alert Styles */
    .alert {
        padding: 16px 20px;
        margin-bottom: 25px;
        border-radius: 12px;
        border: none;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border-left: 4px solid #28a745;
    }
    
    .alert-error {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border-left: 4px solid #dc3545;
    }
    
    .alert i {
        font-size: 18px;
    }
    
    /* Enhanced QR Code Container */
    .qr-code-container {
        text-align: center;
        margin: 25px 0;
        padding: 25px;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 16px;
        border: 2px solid #dee2e6;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .qr-code-img {
        max-width: 180px;
        height: auto;
        border: 3px solid #007bff;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }
    
    .qr-code-img:hover {
        transform: scale(1.05);
    }
    
    .qr-code-label {
        font-weight: 700;
        margin-bottom: 15px;
        color: #495057;
        font-size: 16px;
    }
    
    .qr-code-url {
        font-size: 12px;
        color: #6c757d;
        margin-top: 12px;
        word-break: break-all;
    }
    
    /* Enhanced Student Details */
    .view-modal .modal-content {
        width: 600px;
    }
    
    .student-details {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 25px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .student-details h3 {
        margin-bottom: 20px;
        color: #343a40;
        font-size: 20px;
        font-weight: 700;
        border-bottom: 3px solid #007bff;
        padding-bottom: 10px;
    }
    
    .detail-row {
        display: flex;
        margin-bottom: 15px;
        padding: 12px 0;
        border-bottom: 1px solid #dee2e6;
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        font-weight: 700;
        width: 120px;
        color: #495057;
        font-size: 14px;
    }
    
    .detail-value {
        flex: 1;
        color: #212529;
        font-size: 15px;
    }
    
    /* Enhanced Action Buttons */
    .action-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-top: 25px;
    }
    
    .action-buttons .btn {
        justify-self: stretch;
        text-align: center;
        padding: 14px 20px;
    }
    
    /* Form Action Buttons */
    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #dee2e6;
    }
    
    /* Enhanced Table Actions */
    .table-actions {
        display: flex;
        gap: 8px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .table-actions .btn {
        padding: 6px 12px;
        font-size: 12px;
        min-width: 70px;
    }
    
    /* Loading Animation */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Status Badge Enhancement */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-active {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border: 1px solid #28a745;
    }
    
    .status-inactive {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border: 1px solid #dc3545;
    }
    
    .status-suspended {
        background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        color: #856404;
        border: 1px solid #ffc107;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            margin: 10px;
        }
        
        .search-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-input-container {
            min-width: unset;
        }
        
        .action-buttons {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .table-actions {
            flex-direction: column;
            gap: 4px;
        }
        
        .table-actions .btn {
            width: 100%;
        }
    }
    
    /* Hover effects for table rows */
    .data-table tbody tr {
        transition: all 0.3s ease;
    }
    
    .data-table tbody tr:hover {
        background-color: #f8f9fa;
        transform: translateX(2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    /* Enhanced QR Code in table */
    .table-qr-code {
        width: 60px;
        height: 60px;
        border: 2px solid #007bff;
        border-radius: 8px;
        transition: transform 0.3s ease;
        cursor: pointer;
    }
    
    .table-qr-code:hover {
        transform: scale(1.2);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
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
            <a href="#" class="menu-item active" data-page="students">
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
      <div id="students" class="page active">
          <h1 class="page-header">Student Management</h1>
          
          <?php if (isset($_GET['message'])): ?>
              <div class="alert alert-success">
                  <i class="fas fa-check-circle"></i>
                  <?php echo htmlspecialchars($_GET['message']); ?>
              </div>
          <?php endif; ?>
          
          <?php if (isset($error)): ?>
              <div class="alert alert-error">
                  <i class="fas fa-exclamation-triangle"></i>
                  <?php echo htmlspecialchars($error); ?>
              </div>
          <?php endif; ?>
          
          <div class="content-card">
              <div class="content-title">Student Directory</div>
              
              <div class="search-container">
                  <div class="search-input-container">
                      <i class="fas fa-search search-icon"></i>
                      <form method="GET" style="display: contents;">
                          <input type="text" name="search" class="search-input" placeholder="Search students by name or ID..." 
                                 value="<?php echo htmlspecialchars($search); ?>">
                      </form>
                  </div>
                  <button type="button" class="btn btn-primary" onclick="document.querySelector('form').submit()">
                      <i class="fas fa-search"></i> Search
                  </button>
                  <?php if ($search): ?>
                      <a href="students.php" class="btn btn-secondary">
                          <i class="fas fa-times"></i> Clear
                      </a>
                  <?php endif; ?>
              </div>
              
              <div class="content-section">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Grade</th>
                                <th>Section</th>
                                <th>Status</th>
                                <th>QR Code</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['grade']); ?></td>
                                    <td><?php echo htmlspecialchars($row['section']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['qr_code'])): ?>
                                            <img src="<?php echo htmlspecialchars($row['qr_code']); ?>" alt="QR Code" class="table-qr-code" onclick="viewQRCode('<?php echo htmlspecialchars($row['qr_code']); ?>', '<?php echo htmlspecialchars($row['student_id']); ?>')">
                                        <?php else: ?>
                                            <span style="color: #999;">No QR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-primary btn-sm" onclick="viewStudent(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_id']); ?>', '<?php echo htmlspecialchars($row['name']); ?>', '<?php echo htmlspecialchars($row['grade']); ?>', '<?php echo htmlspecialchars($row['section']); ?>', '<?php echo htmlspecialchars($row['status']); ?>', '<?php echo htmlspecialchars($row['qr_code']); ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button class="btn btn-danger btn-sm" onclick="deleteStudent(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-user-slash" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                        <br>No students found
                                        <?php if ($search): ?>
                                            <br><small>Try adjusting your search criteria</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
              </div>
              <div class="action-buttons">
                  <button class="btn btn-primary" onclick="openAddModal()">
                      <i class="fas fa-plus"></i> Add New Student
                  </button>
                  <button class="btn btn-info" onclick="exportData()">
                      <i class="fas fa-download"></i> Export Data
                  </button>
                  <button class="btn btn-success" onclick="bulkImport()">
                      <i class="fas fa-upload"></i> Bulk Import
                  </button>
                  <button class="btn btn-warning" onclick="generateAllQR()">
                      <i class="fas fa-qrcode"></i> Regenerate All QR
                  </button>
              </div>
          </div>
      </div>
  </div>

  <!-- Add/Edit Student Modal -->
  <div id="addModal" class="modal">
      <div class="modal-content">
          <div class="modal-header">
              <h2><?php echo $edit_student ? 'Edit Student Information' : 'Add New Student'; ?></h2>
              <span class="close" onclick="closeAddModal()">&times;</span>
          </div>
          <div class="modal-body">
              <form method="POST">
                  <?php if ($edit_student): ?>
                      <input type="hidden" name="id" value="<?php echo $edit_student['id']; ?>">
                  <?php endif; ?>
                  
                  <div class="form-group">
                      <label for="student_id">
                          <i class="fas fa-id-card"></i> Student ID:
                      </label>
                      <input type="text" id="student_id" name="student_id" required 
                             value="<?php echo $edit_student ? htmlspecialchars($edit_student['student_id']) : ''; ?>"
                             placeholder="Enter student ID">
                  </div>
                  
                  <div class="form-group">
                      <label for="name">
                          <i class="fas fa-user"></i> Full Name:
                      </label>
                      <input type="text" id="name" name="name" required 
                             value="<?php echo $edit_student ? htmlspecialchars($edit_student['name']) : ''; ?>"
                             placeholder="Enter full name">
                  </div>
                  
                  <div class="form-group">
                      <label for="grade">
                          <i class="fas fa-graduation-cap"></i> Grade:
                      </label>
                      <select id="grade" name="grade" required>
                          <option value="">Select Grade</option>
                          <option value="Grade 7" <?php echo ($edit_student && $edit_student['grade'] == 'Grade 7') ? 'selected' : ''; ?>>Grade 7</option>
                          <option value="Grade 8" <?php echo ($edit_student && $edit_student['grade'] == 'Grade 8') ? 'selected' : ''; ?>>Grade 8</option>
                          <option value="Grade 9" <?php echo ($edit_student && $edit_student['grade'] == 'Grade 9') ? 'selected' : ''; ?>>Grade 9</option>
                          <option value="Grade 10" <?php echo ($edit_student && $edit_student['grade'] == 'Grade 10') ? 'selected' : ''; ?>>Grade 10</option>
                          <option value="Grade 11" <?php echo ($edit_student && $edit_student['grade'] == 'Grade 11') ? 'selected' : ''; ?>>Grade 11</option>
                          <option value="Grade 12" <?php echo ($edit_student && $edit_student['grade'] == 'Grade 12') ? 'selected' : ''; ?>>Grade 12</option>
                      </select>
                  </div>
                  
                  <div class="form-group">
                      <label for="section">
                          <i class="fas fa-users"></i> Section:
                      </label>
                      <input type="text" id="section" name="section" required 
                             value="<?php echo $edit_student ? htmlspecialchars($edit_student['section']) : ''; ?>"
                             placeholder="Enter section">
                  </div>
                  
                  <div class="form-group">
                      <label for="status">
                          <i class="fas fa-toggle-on"></i> Status:
                      </label>
                      <select id="status" name="status" required>
                          <option value="Active" <?php echo ($edit_student && $edit_student['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                          <option value="Inactive" <?php echo ($edit_student && $edit_student['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                          <option value="Suspended" <?php echo ($edit_student && $edit_student['status'] == 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                      </select>
                  </div>
                  
                  <?php if ($edit_student && !empty($edit_student['qr_code'])): ?>
                  <div class="qr-code-container">
                      <div class="qr-code-label">
                          <i class="fas fa-qrcode"></i> Current QR Code:
                      </div>
                      <img src="<?php echo htmlspecialchars($edit_student['qr_code']); ?>" alt="Student QR Code" class="qr-code-img">
                      <div class="qr-code-url">QR Code will be automatically regenerated upon update</div>
                  </div>
                  <?php endif; ?>
                  
                  <div class="form-actions">
                      <button type="button" class="btn btn-secondary" onclick="closeAddModal()">
                          <i class="fas fa-times"></i> Cancel
                      </button>
                      <button type="submit" name="<?php echo $edit_student ? 'update_student' : 'add_student'; ?>" class="btn btn-primary">
                          <i class="fas fa-<?php echo $edit_student ? 'save' : 'plus'; ?>"></i>
                          <?php echo $edit_student ? 'Update Student' : 'Add Student'; ?>
                      </button>
                  </div>
              </form>
          </div>
      </div>
  </div>

  <!-- View Student Modal -->
  <div id="viewModal" class="modal view-modal">
      <div class="modal-content">
          <div class="modal-header">
              <h2><i class="fas fa-user-circle"></i> Student Details</h2>
              <span class="close" onclick="closeViewModal()">&times;</span>
          </div>
          <div class="modal-body">
              <div id="studentDetailsContent">
                  <!-- Content will be populated by JavaScript -->
              </div>
          </div>
      </div>
  </div>

  <!-- QR Code Viewer Modal -->
  <div id="qrModal" class="modal">
      <div class="modal-content" style="width: 400px;">
          <div class="modal-header">
              <h2><i class="fas fa-qrcode"></i> QR Code</h2>
              <span class="close" onclick="closeQRModal()">&times;</span>
          </div>
          <div class="modal-body">
              <div id="qrCodeContent" style="text-align: center;">
                  <!-- QR Code content will be populated by JavaScript -->
              </div>
              <div class="form-actions">
                  <button class="btn btn-primary" onclick="downloadCurrentQR()">
                      <i class="fas fa-download"></i> Download QR
                  </button>
                  <button class="btn btn-info" onclick="printCurrentQR()">
                      <i class="fas fa-print"></i> Print QR
                  </button>
              </div>
          </div>
      </div>
  </div>

  <script>
        let currentQRData = {};
        
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function closeQRModal() {
            document.getElementById('qrModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Show edit modal if editing
        <?php if ($edit_student): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openAddModal();
        });
        <?php endif; ?>

        // Enhanced delete student function
        function deleteStudent(id, name) {
            // Create custom confirmation modal
            const confirmed = confirm(`⚠️ DELETE STUDENT CONFIRMATION ⚠️\n\nAre you sure you want to delete:\n👤 Student: ${name}\n🆔 ID: ${id}\n\n❗ This action will permanently delete:\n• Student record\n• All violation records\n• All attendance records\n• QR code data\n\n❌ This action cannot be undone!\n\nClick OK to proceed with deletion.`);
            
            if (confirmed) {
                // Show loading indicator
                const deleteBtn = event.target;
                const originalText = deleteBtn.innerHTML;
                deleteBtn.innerHTML = '<div class="loading"></div> Deleting...';
                deleteBtn.disabled = true;
                
                // Simulate loading then redirect
                setTimeout(() => {
                    window.location.href = '?delete=' + id;
                }, 1000);
            }
        }

        // Enhanced view student function
        function viewStudent(id, studentId, name, grade, section, status, qrCode) {
            const detailsContent = document.getElementById('studentDetailsContent');
            
            let qrCodeHtml = '';
            if (qrCode && qrCode !== '') {
                qrCodeHtml = `
                    <div class="qr-code-container">
                        <div class="qr-code-label">
                            <i class="fas fa-qrcode"></i> Student QR Code:
                        </div>
                        <img src="${qrCode}" alt="Student QR Code" class="qr-code-img" onclick="viewQRCode('${qrCode}', '${studentId}')">
                        <div class="qr-code-url">Click QR code to view in full size</div>
                    </div>
                `;
            }
            
            detailsContent.innerHTML = `
                <div class="student-details">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-id-card"></i> Student ID:</span>
                        <span class="detail-value">${studentId}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-user"></i> Name:</span>
                        <span class="detail-value">${name}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-graduation-cap"></i> Grade:</span>
                        <span class="detail-value">${grade}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-users"></i> Section:</span>
                        <span class="detail-value">${section}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-toggle-on"></i> Status:</span>
                        <span class="detail-value"><span class="status-badge status-${status.toLowerCase()}">${status}</span></span>
                    </div>
                </div>
                ${qrCodeHtml}
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openViolationTracker('${studentId}')">
                        <i class="fas fa-exclamation-triangle"></i> Violation Tracker
                    </button>
                    <button class="btn btn-success" onclick="trackStudent('${studentId}', '${name}')">
                        <i class="fas fa-map-marker-alt"></i> Track Student
                    </button>
                    <button class="btn btn-info" onclick="messageParent('${studentId}', '${name}')">
                        <i class="fas fa-envelope"></i> Message Parent
                    </button>
                    <button class="btn btn-warning" onclick="printStudentCard('${studentId}', '${name}', '${grade}', '${section}', '${qrCode}')">
                        <i class="fas fa-print"></i> Print Student Card
                    </button>
                </div>
            `;
            
            document.getElementById('viewModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // QR Code viewer function
        function viewQRCode(qrUrl, studentId) {
            currentQRData = { url: qrUrl, studentId: studentId };
            
            const qrContent = document.getElementById('qrCodeContent');
            qrContent.innerHTML = `
                <div class="qr-code-container">
                    <div class="qr-code-label">Student ID: ${studentId}</div>
                    <img src="${qrUrl}" alt="QR Code" style="width: 250px; height: 250px; border: 3px solid #007bff; border-radius: 12px;">
                    <div class="qr-code-url">Scan this code to access violation tracker</div>
                </div>
            `;
            
            document.getElementById('qrModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Download current QR code
        function downloadCurrentQR() {
            if (currentQRData.url) {
                downloadQR(currentQRData.url, currentQRData.studentId);
            }
        }

        // Print current QR code
        function printCurrentQR() {
            if (currentQRData.url && currentQRData.studentId) {
                printQRCode(currentQRData.url, currentQRData.studentId);
            }
        }

        // Open violation tracker
        function openViolationTracker(studentId) {
            const url = `violationss.php?student_id=${encodeURIComponent(studentId)}`;
            window.open(url, '_blank');
        }

        // Enhanced Track Student function
        function trackStudent(studentId, name) {
            const features = [
                '📍 Real-time location tracking',
                '📊 Attendance monitoring', 
                '📝 Activity logs',
                '🔍 Behavioral pattern analysis',
                '⏰ Schedule adherence tracking',
                '🚨 Alert notifications'
            ];
            
            alert(`🔍 STUDENT TRACKING SYSTEM 🔍\n\n👤 Student: ${name}\n🆔 ID: ${studentId}\n\n🚀 Coming Soon Features:\n${features.join('\n')}\n\n⏳ This advanced tracking system is currently under development and will be available in the next update.`);
        }

        // Enhanced Message Parent function
        function messageParent(studentId, name) {
            const features = [
                '📱 SMS notifications',
                '📧 Email alerts',
                '💬 Parent portal messaging',
                '⚠️ Instant violation notifications',
                '📚 Academic progress updates',
                '📅 Attendance reports',
                '🏆 Achievement notifications'
            ];
            
            alert(`📧 PARENT COMMUNICATION SYSTEM 📧\n\n👤 Student: ${name}\n🆔 ID: ${studentId}\n\n🚀 Communication Features:\n${features.join('\n')}\n\n⏳ The parent messaging system is being integrated and will be available soon.`);
        }

        // Download QR Code
        function downloadQR(qrUrl, studentId) {
            const link = document.createElement('a');
            link.href = qrUrl;
            link.download = `VIOTRACK_QR_${studentId}_${new Date().getTime()}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Show success message
            showNotification('✅ QR Code downloaded successfully!', 'success');
        }

        // Print QR Code
        function printQRCode(qrUrl, studentId) {
            const printWindow = window.open('', '_blank');
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>QR Code - ${studentId}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
                        .qr-container { border: 3px solid #007bff; padding: 30px; border-radius: 15px; display: inline-block; }
                        .qr-title { font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #007bff; }
                        .qr-subtitle { font-size: 18px; margin-bottom: 20px; color: #333; }
                        .qr-image { width: 300px; height: 300px; border: 2px solid #ddd; border-radius: 10px; }
                        .qr-footer { margin-top: 20px; font-size: 14px; color: #666; }
                        @media print {
                            body { margin: 0; padding: 20px; }
                            .qr-container { page-break-inside: avoid; }
                        }
                    </style>
                </head>
                <body>
                    <div class="qr-container">
                        <div class="qr-title">🎓 VIOTRACK QR Code</div>
                        <div class="qr-subtitle">Student ID: ${studentId}</div>
                        <img src="${qrUrl}" alt="QR Code" class="qr-image">
                        <div class="qr-footer">
                            📱 Scan this QR code to access violation tracking system<br>
                            🗓️ Generated: ${new Date().toLocaleDateString()}<br>
                            ⚠️ For official use only
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }

        // Enhanced print student card
        function printStudentCard(studentId, name, grade, section, qrCode) {
            const printWindow = window.open('', '_blank');
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Student Card - ${studentId}</title>
                    <style>
                        body { 
                            font-family: 'Arial', sans-serif; 
                            padding: 20px; 
                            margin: 0;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            min-height: 100vh;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .card { 
                            background: white;
                            border: none;
                            padding: 30px; 
                            width: 400px; 
                            border-radius: 20px; 
                            text-align: center; 
                            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                            position: relative;
                            overflow: hidden;
                        }
                        .card::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: 0;
                            right: 0;
                            height: 100px;
                            background: linear-gradient(135deg, #007bff, #0056b3);
                            border-radius: 20px 20px 0 0;
                        }
                        .card-header {
                            position: relative;
                            z-index: 2;
                            color: white;
                            margin-bottom: 30px;
                        }
                        .card-title { 
                            font-size: 24px; 
                            font-weight: bold; 
                            margin: 0 0 10px 0; 
                        }
                        .card-subtitle {
                            font-size: 14px;
                            opacity: 0.9;
                            margin: 0;
                        }
                        .student-info { 
                            text-align: left; 
                            margin: 30px 0; 
                            background: #f8f9fa;
                            padding: 20px;
                            border-radius: 15px;
                        }
                        .info-row {
                            display: flex;
                            margin-bottom: 12px;
                            align-items: center;
                        }
                        .info-row:last-child {
                            margin-bottom: 0;
                        }
                        .info-label { 
                            font-weight: bold; 
                            width: 80px; 
                            color: #495057;
                            font-size: 14px;
                        }
                        .info-value { 
                            flex: 1; 
                            color: #212529;
                            font-size: 15px;
                        }
                        .qr-section { 
                            margin: 25px 0; 
                            padding: 20px;
                            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
                            border-radius: 15px;
                        }
                        .qr-label {
                            font-weight: bold;
                            margin-bottom: 15px;
                            color: #007bff;
                            font-size: 16px;
                        }
                        .qr-image {
                            width: 180px; 
                            height: 180px; 
                            border: 3px solid #007bff; 
                            border-radius: 15px;
                            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
                        }
                        .card-footer { 
                            margin-top: 25px; 
                            font-size: 12px; 
                            color: #6c757d; 
                            line-height: 1.4;
                        }
                        @media print {
                            body { 
                                background: white !important; 
                                padding: 0;
                                min-height: auto;
                            }
                            .card { 
                                box-shadow: none; 
                                page-break-inside: avoid; 
                                margin: 20px auto;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">🎓 VIOTRACK</div>
                            <div class="card-subtitle">Student Identification Card</div>
                        </div>
                        
                        <div class="student-info">
                            <div class="info-row">
                                <span class="info-label">👤 Name:</span>
                                <span class="info-value">${name}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">🆔 ID:</span>
                                <span class="info-value">${studentId}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">🎓 Grade:</span>
                                <span class="info-value">${grade}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">👥 Section:</span>
                                <span class="info-value">${section}</span>
                            </div>
                        </div>
                        
                        ${qrCode ? `
                        <div class="qr-section">
                            <div class="qr-label">📱 QR Code for Violation Tracking</div>
                            <img src="${qrCode}" alt="QR Code" class="qr-image">
                        </div>` : ''}
                        
                        <div class="card-footer">
                            📅 Issued: ${new Date().toLocaleDateString()}<br>
                            🏫 Valid for current academic year<br>
                            ⚠️ Report if lost or damaged
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.onload = function() {
                printWindow.print();
            };
        }

        // Enhanced export data function
        function exportData() {
            const exportOptions = [
                '📊 Excel Spreadsheet (.xlsx)',
                '📄 CSV File (.csv)', 
                '📋 PDF Report (.pdf)',
                '📝 Text File (.txt)',
                '📧 Email Report'
            ];
            
            alert(`📥 EXPORT DATA OPTIONS 📥\n\n📋 Available Export Formats:\n${exportOptions.join('\n')}\n\n🚀 Export functionality is being developed with the following features:\n\n✅ Multiple format support\n✅ Custom date ranges\n✅ Filtered exports\n✅ Automated scheduling\n✅ Email delivery\n\n⏳ Coming soon in the next update!`);
        }

        // New bulk import function
        function bulkImport() {
            alert(`📤 BULK IMPORT SYSTEM 📤\n\n🚀 Features being developed:\n\n✅ Excel/CSV file import\n✅ Data validation\n✅ Duplicate detection\n✅ Error reporting\n✅ Progress tracking\n✅ QR code auto-generation\n\n📋 Supported formats:\n• Excel (.xlsx, .xls)\n• CSV files (.csv)\n• Text files (.txt)\n\n⏳ This feature will be available soon!`);
        }

        // New generate all QR function
        function generateAllQR() {
            if (confirm('🔄 REGENERATE ALL QR CODES\n\n⚠️ This will regenerate QR codes for ALL students.\n\n⏳ This process may take a few minutes depending on the number of students.\n\n✅ Click OK to proceed with bulk QR generation.')) {
                alert('🚀 QR CODE REGENERATION\n\n⏳ This feature is being implemented with:\n\n✅ Batch processing\n✅ Progress indicators\n✅ Error handling\n✅ Backup creation\n✅ Email notifications\n\n📧 You will be notified when this feature is available!');
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
                animation: slideInRight 0.3s ease-out;
                box-shadow: 0 8px 16px rgba(0,0,0,0.2);
                max-width: 350px;
            `;
            
            const colors = {
                success: 'linear-gradient(135deg, #28a745, #1e7e34)',
                error: 'linear-gradient(135deg, #dc3545, #bd2130)',
                warning: 'linear-gradient(135deg, #ffc107, #d39e00)',
                info: 'linear-gradient(135deg, #17a2b8, #117a8b)'
            };
            
            notification.style.background = colors[type] || colors.info;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => document.body.removeChild(notification), 300);
            }, 3000);
        }

        // Real-time search functionality
        let searchTimeout;
        document.querySelector('.search-input').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const searchTerm = e.target.value.toLowerCase();
            
            searchTimeout = setTimeout(() => {
                const rows = document.querySelectorAll('.data-table tbody tr');
                
                rows.forEach(row => {
                    if (row.cells.length === 1) return; // Skip "no data" row
                    
                    const studentId = row.cells[0].textContent.toLowerCase();
                    const name = row.cells[1].textContent.toLowerCase();
                    
                    if (studentId.includes(searchTerm) || name.includes(searchTerm)) {
                        row.style.display = '';
                        row.style.animation = 'fadeIn 0.3s ease-out';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }, 300);
        });

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+N or Cmd+N to add new student
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                openAddModal();
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                closeAddModal();
                closeViewModal();
                closeQRModal();
            }
            
            // Ctrl+F or Cmd+F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.querySelector('.search-input').focus();
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['addModal', 'viewModal', 'qrModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .btn-loading {
                pointer-events: none;
                opacity: 0.7;
            }
            
            .table-row-highlight {
                background-color: #e3f2fd !important;
                transform: scale(1.01);
                box-shadow: 0 4px 12px rgba(0,123,255,0.2);
            }
        `;
        document.head.appendChild(style);

        // Form validation enhancement
        function validateForm(form) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    field.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                    isValid = false;
                    
                    // Reset validation styling after user starts typing
                    field.addEventListener('input', function() {
                        this.style.borderColor = '#007bff';
                        this.style.boxShadow = '0 0 0 3px rgba(0, 123, 255, 0.1)';
                    }, { once: true });
                }
            });
            
            return isValid;
        }

        // Enhanced form submission
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!validateForm(this)) {
                        e.preventDefault();
                        showNotification('⚠️ Please fill in all required fields!', 'warning');
                        return false;
                    }
                    
                    // Add loading state to submit button
                    const submitBtn = this.querySelector('[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<div class="loading"></div> Processing...';
                        submitBtn.classList.add('btn-loading');
                        
                        // Re-enable after 5 seconds as fallback
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.classList.remove('btn-loading');
                        }, 5000);
                    }
                });
            });
        });

        // Table row hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            
            tableRows.forEach(row => {
                if (row.cells.length > 1) { // Skip empty state row
                    row.addEventListener('mouseenter', function() {
                        this.classList.add('table-row-highlight');
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.classList.remove('table-row-highlight');
                    });
                }
            });
        });

        // Auto-save draft functionality for forms
        let draftData = {};
        
        function saveDraft() {
            const form = document.querySelector('#addModal form');
            if (form && form.style.display !== 'none') {
                const formData = new FormData(form);
                draftData = {};
                
                for (let [key, value] of formData.entries()) {
                    if (key !== 'id') { // Don't save ID for new records
                        draftData[key] = value;
                    }
                }
                
                localStorage.setItem('viotrack_student_draft', JSON.stringify(draftData));
            }
        }
        
        function loadDraft() {
            const saved = localStorage.getItem('viotrack_student_draft');
            if (saved && !<?php echo $edit_student ? 'true' : 'false'; ?>) {
                draftData = JSON.parse(saved);
                
                if (Object.keys(draftData).length > 0) {
                    if (confirm('📝 DRAFT RECOVERY\n\nWe found an unsaved draft from your previous session.\n\n✅ Would you like to restore your draft data?\n\n❌ Click Cancel to start fresh.')) {
                        Object.keys(draftData).forEach(key => {
                            const field = document.querySelector(`[name="${key}"]`);
                            if (field) {
                                field.value = draftData[key];
                            }
                        });
                        showNotification('📝 Draft restored successfully!', 'success');
                    }
                }
            }
        }
        
        function clearDraft() {
            localStorage.removeItem('viotrack_student_draft');
            draftData = {};
        }

        // Auto-save every 30 seconds
        setInterval(saveDraft, 30000);

        // Load draft when opening add modal
        const originalOpenAddModal = openAddModal;
        openAddModal = function() {
            originalOpenAddModal();
            setTimeout(loadDraft, 100); // Small delay to ensure form is rendered
        };

        // Clear draft on successful submission
        window.addEventListener('beforeunload', function() {
            // Only clear if we're navigating away after a successful operation
            if (window.location.href.includes('message=')) {
                clearDraft();
            }
        });

        // Advanced search filters
        function showAdvancedSearch() {
            const searchContainer = document.querySelector('.search-container');
            const existingFilters = document.querySelector('.advanced-filters');
            
            if (existingFilters) {
                existingFilters.remove();
                return;
            }
            
            const filtersHtml = `
                <div class="advanced-filters" style="width: 100%; margin-top: 15px; padding: 20px; background: #f8f9fa; border-radius: 12px; border: 1px solid #dee2e6;">
                    <h4 style="margin-top: 0; color: #007bff;"><i class="fas fa-filter"></i> Advanced Filters</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <label style="font-weight: 600; margin-bottom: 5px; display: block;">Grade:</label>
                            <select id="filterGrade" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">All Grades</option>
                                <option value="Grade 7">Grade 7</option>
                                <option value="Grade 8">Grade 8</option>
                                <option value="Grade 9">Grade 9</option>
                                <option value="Grade 10">Grade 10</option>
                                <option value="Grade 11">Grade 11</option>
                                <option value="Grade 12">Grade 12</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-weight: 600; margin-bottom: 5px; display: block;">Status:</label>
                            <select id="filterStatus" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">All Status</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-weight: 600; margin-bottom: 5px; display: block;">Section:</label>
                            <input type="text" id="filterSection" placeholder="Filter by section" onkeyup="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                        </div>
                        <div style="display: flex; align-items: end; gap: 10px;">
                            <button class="btn btn-secondary btn-sm" onclick="clearFilters()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="exportFiltered()">
                                <i class="fas fa-download"></i> Export Filtered
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            searchContainer.insertAdjacentHTML('afterend', filtersHtml);
        }

        function applyFilters() {
            const gradeFilter = document.getElementById('filterGrade')?.value.toLowerCase() || '';
            const statusFilter = document.getElementById('filterStatus')?.value.toLowerCase() || '';
            const sectionFilter = document.getElementById('filterSection')?.value.toLowerCase() || '';
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            
            const rows = document.querySelectorAll('.data-table tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip "no data" row
                
                const studentId = row.cells[0].textContent.toLowerCase();
                const name = row.cells[1].textContent.toLowerCase();
                const grade = row.cells[2].textContent.toLowerCase();
                const section = row.cells[3].textContent.toLowerCase();
                const status = row.cells[4].textContent.toLowerCase();
                
                const matchesSearch = !searchTerm || studentId.includes(searchTerm) || name.includes(searchTerm);
                const matchesGrade = !gradeFilter || grade.includes(gradeFilter);
                const matchesStatus = !statusFilter || status.includes(statusFilter);
                const matchesSection = !sectionFilter || section.includes(sectionFilter);
                
                if (matchesSearch && matchesGrade && matchesStatus && matchesSection) {
                    row.style.display = '';
                    row.style.animation = 'fadeIn 0.3s ease-out';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update results counter
            updateResultsCounter(visibleCount);
        }

        function clearFilters() {
            document.getElementById('filterGrade').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterSection').value = '';
            document.querySelector('.search-input').value = '';
            
            const rows = document.querySelectorAll('.data-table tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            
            updateResultsCounter(rows.length - 1); // Subtract 1 for header or no-data row
        }

        function updateResultsCounter(count) {
            let counter = document.querySelector('.results-counter');
            if (!counter) {
                counter = document.createElement('div');
                counter.className = 'results-counter';
                counter.style.cssText = 'margin: 10px 0; font-weight: 600; color: #007bff;';
                document.querySelector('.content-section').insertBefore(counter, document.querySelector('.data-table'));
            }
            counter.innerHTML = `<i class="fas fa-users"></i> Showing ${count} student${count !== 1 ? 's' : ''}`;
        }

        function exportFiltered() {
            alert('📊 FILTERED EXPORT\n\n✅ This will export only the currently visible/filtered students\n\n🚀 Export options:\n• Excel format\n• PDF report\n• Email delivery\n\n⏳ This feature is coming soon!');
        }

        // Add advanced search button
        document.addEventListener('DOMContentLoaded', function() {
            const searchContainer = document.querySelector('.search-container');
            const advancedBtn = document.createElement('button');
            advancedBtn.type = 'button';
            advancedBtn.className = 'btn btn-info';
            advancedBtn.innerHTML = '<i class="fas fa-sliders-h"></i> Advanced';
            advancedBtn.onclick = showAdvancedSearch;
            searchContainer.appendChild(advancedBtn);
            
            // Initialize results counter
            setTimeout(() => {
                const totalRows = document.querySelectorAll('.data-table tbody tr').length;
                const visibleRows = totalRows > 1 ? totalRows - 1 : 0; // Account for no-data row
                updateResultsCounter(visibleRows);
            }, 100);
        });

        // Bulk operations
        function initBulkOperations() {
            const table = document.querySelector('.data-table');
            if (!table) return;
            
            // Add checkboxes to table
            const headerRow = table.querySelector('thead tr');
            const selectAllHtml = '<th><input type="checkbox" id="selectAll" onchange="toggleAllSelection()"></th>';
            headerRow.insertAdjacentHTML('afterbegin', selectAllHtml);
            
            const bodyRows = table.querySelectorAll('tbody tr');
            bodyRows.forEach((row, index) => {
                if (row.cells.length > 1) { // Skip no-data row
                    const checkbox = `<td><input type="checkbox" class="row-select" value="${index}" onchange="updateBulkActions()"></td>`;
                    row.insertAdjacentHTML('afterbegin', checkbox);
                }
            });
            
            // Add bulk actions toolbar
            const bulkToolbar = `
                <div id="bulkToolbar" class="bulk-toolbar" style="display: none; margin: 15px 0; padding: 15px; background: #e3f2fd; border-radius: 10px; border-left: 4px solid #007bff;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <span id="selectedCount" style="font-weight: 600; color: #007bff;">0 selected</span>
                        <button class="btn btn-warning btn-sm" onclick="bulkStatusChange()">
                            <i class="fas fa-edit"></i> Change Status
                        </button>
                        <button class="btn btn-info btn-sm" onclick="bulkExport()">
                            <i class="fas fa-download"></i> Export Selected
                        </button>
                        <button class="btn btn-success btn-sm" onclick="bulkGenerateQR()">
                            <i class="fas fa-qrcode"></i> Regenerate QR
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="bulkDelete()">
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="clearSelection()">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                    </div>
                </div>
            `;
            
            document.querySelector('.content-section').insertAdjacentHTML('beforebegin', bulkToolbar);
        }

        function toggleAllSelection() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.row-select');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const selected = document.querySelectorAll('.row-select:checked');
            const toolbar = document.getElementById('bulkToolbar');
            const counter = document.getElementById('selectedCount');
            
            if (selected.length > 0) {
                toolbar.style.display = 'block';
                counter.textContent = `${selected.length} selected`;
            } else {
                toolbar.style.display = 'none';
            }
        }

        function clearSelection() {
            document.getElementById('selectAll').checked = false;
            document.querySelectorAll('.row-select').forEach(cb => cb.checked = false);
            updateBulkActions();
        }

        function bulkStatusChange() {
            const selected = document.querySelectorAll('.row-select:checked');
            if (selected.length === 0) return;
            
            const newStatus = prompt(`🔄 BULK STATUS CHANGE\n\nChange status for ${selected.length} selected students to:\n\n1. Active\n2. Inactive\n3. Suspended\n\nEnter 1, 2, or 3:`);
            
            const statusMap = { '1': 'Active', '2': 'Inactive', '3': 'Suspended' };
            if (statusMap[newStatus]) {
                alert(`✅ Status will be changed to "${statusMap[newStatus]}" for ${selected.length} students.\n\n⏳ This bulk operation is being implemented and will be available soon!`);
            }
        }

        function bulkExport() {
            const selected = document.querySelectorAll('.row-select:checked');
            alert(`📊 BULK EXPORT\n\n✅ Exporting ${selected.length} selected students\n\n🚀 Export options:\n• Excel spreadsheet\n• PDF report\n• CSV file\n\n⏳ This feature is coming soon!`);
        }

        function bulkGenerateQR() {
            const selected = document.querySelectorAll('.row-select:checked');
            if (confirm(`🔄 BULK QR GENERATION\n\n⚠️ This will regenerate QR codes for ${selected.length} selected students.\n\n✅ Click OK to proceed.`)) {
                alert(`⏳ Generating QR codes for ${selected.length} students...\n\nThis bulk operation is being implemented!`);
            }
        }

        function bulkDelete() {
            const selected = document.querySelectorAll('.row-select:checked');
            if (selected.length === 0) return;
            
            if (confirm(`⚠️ BULK DELETE WARNING ⚠️\n\n❌ This will permanently delete ${selected.length} selected students and all their associated data:\n\n• Student records\n• Violation history\n• Attendance records\n• QR codes\n\n🚨 THIS ACTION CANNOT BE UNDONE!\n\nType "DELETE" to confirm this action.`)) {
                const confirmation = prompt('Type "DELETE" to confirm:');
                if (confirmation === 'DELETE') {
                    alert(`⏳ Deleting ${selected.length} students...\n\nThis bulk operation is being implemented!`);
                }
            }
        }

        // Initialize bulk operations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add a small delay to ensure table is fully rendered
            setTimeout(initBulkOperations, 500);
        });

        // Enhanced tooltips for better user experience
        function addTooltips() {
            const tooltipElements = [
                { selector: '.btn', attr: 'title' },
                { selector: '.status-badge', attr: 'title' },
                { selector: '.table-qr-code', attr: 'title' }
            ];
            
            tooltipElements.forEach(({ selector, attr }) => {
                document.querySelectorAll(selector).forEach(el => {
                    if (!el.getAttribute(attr)) {
                        // Add appropriate tooltip based on element
                        if (el.classList.contains('btn-primary')) {
                            el.setAttribute(attr, 'Primary action button');
                        } else if (el.classList.contains('status-badge')) {
                            el.setAttribute(attr, `Student status: ${el.textContent}`);
                        } else if (el.classList.contains('table-qr-code')) {
                            el.setAttribute(attr, 'Click to view QR code in full size');
                        }
                    }
                });
            });
        }

        // Call addTooltips after page load
        document.addEventListener('DOMContentLoaded', addTooltips);

        console.log('🎓 VIOTRACK Student Management System Enhanced');
        console.log('✅ All features initialized successfully');
    </script>
</body>
</html>