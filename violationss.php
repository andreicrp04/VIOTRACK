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
    <style>
        /* Back button styles */
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background: linear-gradient(145deg, #1e40af, #3b82f6);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .back-button:active {
            transform: translateY(0);
        }
        
        .back-arrow {
            font-size: 16px;
            transition: transform 0.3s ease;
        }
        
        .back-button:hover .back-arrow {
            transform: translateX(-2px);
        }
        
        /* Adjust header to account for back button */
        .page-header {
            padding-left: 140px;
        }
        
        @media (max-width: 768px) {
            .back-button {
                position: relative;
                top: auto;
                left: auto;
                margin: 10px;
                width: calc(100% - 20px);
                justify-content: center;
            }
            
            .page-header {
                padding-left: 20px;
                text-align: center;
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
    <h1>VioTrack - Student Violations System</h1>
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
        item.style.background = '#dcfce7';
        item.style.color = '#166534';
        item.style.fontWeight = '600';
        setTimeout(() => {
            item.style.background = '';
            item.style.color = '';
            item.style.fontWeight = '';
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
                recordBtn.style.background = 'rgba(16, 185, 129, 0.3)';
                
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
            recordBtn.style.background = 'rgba(239, 68, 68, 0.3)';
        })
        .finally(() => {
            setTimeout(() => {
                recordBtn.innerHTML = originalText;
                recordBtn.disabled = false;
                recordBtn.style.background = '';
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
                resolveBtn.style.background = '#10b981';
                
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

    function printRecord() {
        const studentName = '<?php echo $student_data ? addslashes($student_data['name']) : ''; ?>';
        const studentInfo = '<?php echo $student_data ? addslashes($student_data['student_id'] . ' - ' . $student_data['grade'] . ' ' . $student_data['section']) : ''; ?>';
        
        let printContent = `
            <div style="max-width: 800px; margin: 0 auto; padding: 40px; font-family: 'Segoe UI', Arial, sans-serif;">
                <div style="text-align: center; border-bottom: 3px solid #3b82f6; padding-bottom: 20px; margin-bottom: 30px;">
                    <h1 style="color: #1e293b; margin-bottom: 10px;">📋 STUDENT VIOLATION RECORD</h1>
                    <p style="color: #64748b; font-size: 16px;">Official Disciplinary Report</p>
                </div>
                
                <div style="background: #f8fafc; padding: 25px; border-radius: 12px; margin-bottom: 30px;">
                    <h2 style="color: #1e293b; margin-bottom: 15px;">👤 Student Information</h2>
                    <p><strong>Name:</strong> ${studentName}</p>
                    <p><strong>Student ID:</strong> ${studentInfo}</p>
                    <p><strong>Report Date:</strong> ${new Date().toLocaleDateString()}</p>
                    <p><strong>Report Time:</strong> ${new Date().toLocaleTimeString()}</p>
                    <p><strong>Current Attendance:</strong> ${attendanceStatus || 'Not Set'}</p>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <h2 style="color: #1e293b; margin-bottom: 20px;">⚠️ Current Active Violations</h2>`;
        
        <?php if (!empty($current_violations)): ?>
        <?php foreach ($current_violations as $index => $violation): ?>
        printContent += `
                    <div style="background: white; border-left: 4px solid #ef4444; padding: 20px; margin-bottom: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <p style="font-weight: 600; color: #1e293b; margin-bottom: 5px;">${<?php echo $index + 1; ?>}. <?php echo addslashes($violation['violation_type']); ?></p>
                        <p style="color: #64748b; font-size: 14px;">Date: <?php echo date('M j, Y g:i A', strtotime($violation['violation_date'])); ?></p>
                        <p style="color: #64748b; font-size: 14px;">Category: <span style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?php echo $violation['violation_category']; ?></span></p>
                        <?php if (!empty($violation['recorded_by'])): ?>
                        <p style="color: #64748b; font-size: 14px;">Recorded by: <?php echo addslashes($violation['recorded_by']); ?></p>
                        <?php endif; ?>
                    </div>`;
        <?php endforeach; ?>
        <?php else: ?>
        printContent += `
                    <div style="background: #ecfdf5; border: 2px dashed #059669; padding: 30px; text-align: center; border-radius: 12px;">
                        <p style="color: #059669; font-weight: 600; font-size: 18px;">✅ No Active Violations</p>
                        <p style="color: #047857; margin-top: 10px;">This student currently has a clean disciplinary record.</p>
                    </div>`;
        <?php endif; ?>
        
        printContent += `
                </div>
                
                <div style="text-align: center; padding-top: 30px; border-top: 2px solid #e2e8f0;">
                    <p style="color: #64748b; font-size: 14px;">Generated by VioTrack System • ${new Date().toLocaleString()}</p>
                </div>
            </div>`;
        
        // Open print dialog
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Student Violation Record - ${studentName}</title>
                    <style>
                        @media print {
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; }
                    </style>
                </head>
                <body>
                    ${printContent}
                    <div class="no-print" style="text-align: center; margin: 20px;">
                        <button onclick="window.print()" style="background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 16px;">🖨️ Print Document</button>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        
        // Auto focus and show print dialog
        setTimeout(() => {
            printWindow.print();
        }, 500);
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
  </script>
</body>
</html>
