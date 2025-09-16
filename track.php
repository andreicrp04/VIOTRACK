<?php
// track.php
// VIOTRACK System - Track Location Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VIOTRACK - Track Location</title>
  <link rel="stylesheet" href="styles.css">
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
            <a href="track.php" class="menu-item active" data-page="track">
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
      <div id="track" class="page active">
          <h1 class="page-header">Track Location</h1>

          <div class="content-card">
              <div class="content-title">Real-time Student Tracking</div>
              <div class="content-text">
                  Monitor student locations within campus premises for safety and attendance purposes.
              </div>
              <div style="background: #fcfcf8; padding: 40px; border-radius: 8px; text-align: center; margin: 20px 0;">
                  <div style="color: #64748b; font-size: 18px; margin-bottom: 20px;">📍 Interactive Campus Map</div>
                  <div style="color: #94a3b8; font-size: 14px;">Map integration would be implemented here</div>
                  <div style="color: #94a3b8; font-size: 14px;">Showing real-time student locations on campus</div>
              </div>
              <div class="action-buttons">
                  <button class="btn btn-primary">Refresh Locations</button>
                  <button class="btn btn-secondary">Export Location Data</button>
                  <button class="btn btn-secondary">Set Geofences</button>
              </div>
          </div>

          <div class="content-card">
              <div class="content-title">Recent Location Updates</div>
              <div class="content-section">
                  <table class="data-table">
                      <thead>
                          <tr>
                              <th>Time</th>
                              <th>Student</th>
                              <th>Location</th>
                              <th>Status</th>
                          </tr>
                      </thead>
                      <tbody>
                          <tr>
                              <td>09:45 AM</td>
                              <td>John Doe (2023-001)</td>
                              <td>Library - 2nd Floor</td>
                              <td><span class="status-badge status-active">Active</span></td>
                          </tr>
                          <tr>
                              <td>09:43 AM</td>
                              <td>Jane Smith (2023-002)</td>
                              <td>Computer Lab A</td>
                              <td><span class="status-badge status-active">Active</span></td>
                          </tr>
                          <tr>
                              <td>09:40 AM</td>
                              <td>Mike Johnson (2023-003)</td>
                              <td>Cafeteria</td>
                              <td><span class="status-badge status-active">Active</span></td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
  </div>

  <script>
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
            item.addEventListener('click', function() {
                const pageId = this.getAttribute('data-page');
                if (pageId) {
                    showPage(pageId);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const activePage = document.querySelector('.page.active');
            if (!activePage) {
                showPage('dashboard');
            }
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
                showPage(pageId);
            } else if (e.key === 'ArrowUp' && currentIndex > 0) {
                e.preventDefault();
                const prevItem = menuItems[currentIndex - 1];
                const pageId = prevItem.getAttribute('data-page');
                showPage(pageId);
            }
        });
  </script>
</body>
</html>
