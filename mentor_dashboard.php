<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }

// Mentor session timeout - 30 minutes
if (isset($_SESSION['last_mentor_activity']) && (time() - $_SESSION['last_mentor_activity'] > 1800)) {
    unset($_SESSION['mentor']);
    header("Location: mentor_login.php?timeout=1");
    exit;
}
$_SESSION['last_mentor_activity'] = time();

$m = $_SESSION['mentor'];
$unreadCount = $notifications->countDocuments(['mentor_id'=>$m['mentor_id'],'read'=>false]);

// Fetch all students linked to this mentor
$studentCursor = $users->find(['mentor_id' => $m['mentor_id']]);
$studentList   = iterator_to_array($studentCursor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Mentor Dashboard</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .mentor-badge {
            display: inline-block;
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .mentor-navbar { background: linear-gradient(135deg, #1a1a2e, #8e44ad); }
        .student-list-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            margin-top: 24px;
        }
        .student-list-header {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            padding: 16px 24px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
        }
        .student-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid #f0f2f5;
        }
        .student-row:last-child { border-bottom: none; }
        .student-row:hover { background: #fafafa; }
        .student-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #8e44ad;
            margin-right: 14px;
        }
        .student-avatar-placeholder {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 16px;
            margin-right: 14px; flex-shrink: 0;
        }
        .student-info { flex: 1; }
        .student-info .s-name { font-size: 15px; font-weight: 700; color: #1a1a2e; }
        .student-info .s-roll { font-size: 13px; color: #888; margin-top: 2px; }
        .student-info .s-dept { font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
<nav class="navbar" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
    <a href="mentor_dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span>
    </a>
    <div class="nav-links">
        <a href="mentor_dashboard.php">Home</a>
        <a href="mentor_approvals.php">Approvals</a>
        <a href="mentor_calendar.php">Calendar</a>
        <a href="mentor_announcements.php">Announcements</a>
        <a href="mentor_update_profile.php">Profile</a>
        <a href="mentor_about.php">About</a>
        <a href="mentor_contact.php">Contact</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                🔔<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">

    <!-- MENTOR PROFILE CARD -->
    <div class="profile-card">
        <div class="profile-info">
            <h2><?= htmlspecialchars($m['name']) ?></h2>
            <p class="profile-roll"><?= htmlspecialchars($m['mentor_id']) ?></p>
            <p><strong>Department:</strong> <?= htmlspecialchars($m['dept']) ?></p>
            <?php if (!empty($m['batch_no'])): ?>
            <p><strong>Batch No:</strong> <?= htmlspecialchars($m['batch_no']) ?></p>
            <?php endif; ?>
            <p><strong>Email:</strong> <?= htmlspecialchars($m['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($m['phone']) ?></p>
            <div style="margin-top:14px;"><span class="mentor-badge">MENTOR</span></div>
        </div>
        <div class="profile-photo">
            <?php if (!empty($m['photo'])): ?>
                <img src="<?= htmlspecialchars(imgUrl($m['photo'])) ?>" alt="Photo">
            <?php else: ?>
                <div class="no-photo">No Photo</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUMMARY -->
    <div class="summary-row">
        <div class="summary-card" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
            <h3>My Students</h3>
            <p><?= count($studentList) ?></p>
        </div>
    </div>

    <!-- STUDENTS LIST -->
    <div class="student-list-card">
        <div class="student-list-header">👥 Assigned Students</div>
        <?php if (empty($studentList)): ?>
            <p class="no-data" style="padding:24px;">No students linked to your Mentor ID yet.</p>
        <?php else: ?>
<?php foreach ($studentList as $st): ?>
            <div class="student-row" style="cursor:pointer;" onclick="showStudentSemesters('<?= htmlspecialchars($st['roll']) ?>')">
                <div style="display:flex;align-items:center;">
                    <?php if (!empty($st['photo'])): ?>
                        <img src="<?= htmlspecialchars(imgUrl($st['photo'])) ?>" class="student-avatar" alt="">
                    <?php else: ?>
                        <div class="student-avatar-placeholder"><?= strtoupper(substr($st['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="student-info">
                        <div class="s-name"><?= htmlspecialchars($st['name']) ?></div>
                        <div class="s-roll"><?= htmlspecialchars($st['roll']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($st['reg']) ?></div>
                        <div class="s-dept"><?= htmlspecialchars($st['dept']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($st['class']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
<script>
async function showStudentSemesters(roll) {
    try {
        const res = await fetch('mentor_student_semesters.php?roll=' + encodeURIComponent(roll));
        const data = await res.json();
        if (!data || data.status !== 'success') {
            alert(data?.message || 'Failed to load student semesters');
            return;
        }

        // Remove old modal if exists
        const existing = document.getElementById('studentDetailModal');
        if (existing) existing.remove();

        const student = data.student || {};
        const semesters = data.semesters || [];

        // Store semester data globally for click handlers
        window._semesterDataMap = {};
        semesters.forEach(s => {
            window._semesterDataMap[s.sem] = s;
        });

        // Create semester cards HTML
        const semesterCards = semesters.map(s => {
            const sgi = s.sgi !== null ? s.sgi.toFixed(2) : '—';
            const grade = (function(g){
                if (g === null || g === undefined) return '—';
                if (g >= 9) return 'O';
                if (g >= 8) return 'A';
                if (g >= 7) return 'B';
                if (g >= 6) return 'C';
                return 'D';
            })(s.sgi);

            const statusBadge = `<span style="display:inline-block;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600;background:${s.status_color};color:#fff;">${s.status}</span>`;

            return `
                <div class="semester-card" onclick="showSemesterDetailBySem('${s.sem}')" style="cursor:pointer;">
                    <div class="sem-header">
                        <span class="sem-num">Semester ${s.sem}</span>
                        ${statusBadge}
                    </div>
                    <div class="sem-grid">
                        <div class="sem-stat">
                            <div class="stat-label">SGI</div>
                            <div class="stat-value">${sgi}</div>
                        </div>
                        <div class="sem-stat">
                            <div class="stat-label">Grade</div>
                            <div class="stat-value">${grade}</div>
                        </div>
                        <div class="sem-stat">
                            <div class="stat-label">GPA</div>
                            <div class="stat-value">${s.gpa || '—'}</div>
                        </div>
                        <div class="sem-stat">
                            <div class="stat-label">CGPA</div>
                            <div class="stat-value">${s.cgpa || '—'}</div>
                        </div>
                        <div class="sem-stat">
                            <div class="stat-label">Attendance</div>
                            <div class="stat-value">${s.attendance || '—'}%</div>
                        </div>
                        <div class="sem-stat">
                            <div class="stat-label">Subjects</div>
                            <div class="stat-value">${s.subject_count || 0}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        const modal = document.createElement('div');
        modal.id = 'studentDetailModal';
        modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:3000;display:flex;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto;';
        modal.innerHTML = `
            <style>
                .student-detail-modal {
                    background: #fff;
                    border-radius: 16px;
                    max-width: 900px;
                    width: 100%;
                    max-height: none;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.35);
                    margin: 20px auto;
                }
                .student-detail-header {
                    background: linear-gradient(135deg, #1a1a2e, #8e44ad);
                    color: #fff;
                    padding: 20px 24px;
                    border-radius: 16px 16px 0 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .student-detail-header h2 {
                    margin: 0;
                    font-size: 20px;
                }
                .student-detail-header .close-btn {
                    background: none;
                    border: none;
                    color: #fff;
                    font-size: 26px;
                    cursor: pointer;
                    width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    transition: background 0.2s;
                }
                .student-detail-header .close-btn:hover {
                    background: rgba(255,255,255,0.2);
                }
                .student-info-section {
                    padding: 20px 24px;
                    border-bottom: 1px solid #eee;
                }
                .student-info-section h3 {
                    margin: 0 0 16px;
                    font-size: 16px;
                    color: #1a1a2e;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 12px;
                }
                .info-item {
                    background: #f8f9fa;
                    padding: 12px 16px;
                    border-radius: 10px;
                }
                .info-item label {
                    font-size: 11px;
                    color: #888;
                    text-transform: uppercase;
                    display: block;
                    margin-bottom: 4px;
                }
                .info-item span {
                    font-size: 14px;
                    color: #333;
                    font-weight: 600;
                }
                .semesters-section {
                    padding: 20px 24px;
                }
                .semesters-section h3 {
                    margin: 0 0 16px;
                    font-size: 16px;
                    color: #1a1a2e;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .semester-card {
                    background: #f8f9fa;
                    border-radius: 12px;
                    padding: 16px;
                    margin-bottom: 12px;
                    transition: all 0.2s;
                    border: 2px solid transparent;
                }
                .semester-card:hover {
                    background: #e7f3ff;
                    border-color: #8e44ad;
                    transform: translateY(-2px);
                }
                .sem-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 12px;
                }
                .sem-num {
                    font-size: 16px;
                    font-weight: 700;
                    color: #1a1a2e;
                }
                .sem-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
                    gap: 8px;
                }
                .sem-stat {
                    text-align: center;
                    padding: 8px;
                    background: #fff;
                    border-radius: 8px;
                }
                .stat-label {
                    font-size: 10px;
                    color: #888;
                    text-transform: uppercase;
                    margin-bottom: 4px;
                }
                .stat-value {
                    font-size: 16px;
                    font-weight: 700;
                    color: #1a1a2e;
                }
                .no-semesters {
                    text-align: center;
                    padding: 40px 20px;
                    color: #888;
                }
                @media (max-width: 600px) {
                    .student-detail-modal { margin: 10px; border-radius: 12px; }
                    .student-detail-header { padding: 16px; border-radius: 12px 12px 0 0; }
                    .student-detail-header h2 { font-size: 16px; }
                    .student-info-section, .semesters-section { padding: 16px; }
                    .info-grid { grid-template-columns: 1fr 1fr; }
                    .sem-grid { grid-template-columns: repeat(3, 1fr); }
                    .stat-value { font-size: 14px; }
                }
            </style>
            <div class="student-detail-modal">
                <div class="student-detail-header">
                    <div>
                        <h2>👤 ${student.name || ''}</h2>
                        <div style="opacity:0.85;font-size:13px;margin-top:4px;">${student.roll || roll} · ${student.reg || ''}</div>
                    </div>
                    <button class="close-btn" onclick="document.getElementById('studentDetailModal').remove();">×</button>
                </div>
                
                <div class="student-info-section">
                    <h3>📋 Student Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Name</label>
                            <span>${student.name || '—'}</span>
                        </div>
                        <div class="info-item">
                            <label>Roll Number</label>
                            <span>${student.roll || '—'}</span>
                        </div>
                        <div class="info-item">
                            <label>Integrated No</label>
                            <span>${student.reg || '—'}</span>
                        </div>
                        <div class="info-item">
                            <label>Email</label>
                            <span>${student.email || '—'}</span>
                        </div>
                        <div class="info-item">
                            <label>Phone</label>
                            <span>${student.phone || '—'}</span>
                        </div>
                        <div class="info-item">
                            <label>Department</label>
                            <span>${student.dept || '—'}</span>
                        </div>
                        <div class="info-item">
                            <label>Class</label>
                            <span>${student.class || '—'}</span>
                        </div>
                        <div class="info-item">
                            <label>Batch No</label>
                            <span>${student.batch_no || '—'}</span>
                        </div>
                    </div>
                </div>
                
                <div class="semesters-section">
                    <h3>📚 Semester Progress</h3>
                    ${semesters.length ? semesterCards : '<div class="no-semesters">No semesters found for this student.</div>'}
                </div>
            </div>
        `;
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
        document.body.appendChild(modal);
    } catch (e) {
        alert('Error: ' + (e?.message || e));
    }
}

// Show detailed semester information by semester number
function showSemesterDetailBySem(sem) {
    const semData = window._semesterDataMap[sem];
    if (!semData) {
        alert('Semester data not found');
        return;
    }
    showSemesterDetail(sem, semData);
}

// Show detailed semester information
function showSemesterDetail(sem, semData) {
    const existing = document.getElementById('semDetailModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'semDetailModal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:4000;display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
        <div style="background:#fff;border-radius:16px;max-width:800px;width:95%;max-height:85vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,0.35);">
            <div style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);color:#fff;padding:18px 22px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-weight:800;font-size:18px;">Semester ${sem}</div>
                    <div style="opacity:0.85;font-size:13px;">${semData.status || ''}</div>
                </div>
                <button onclick="document.getElementById('semDetailModal').remove();" style="background:none;border:none;color:#fff;font-size:26px;cursor:pointer;">×</button>
            </div>
            <div style="padding:20px 24px;">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
                    <div style="background:#f8f9fa;padding:16px;border-radius:12px;text-align:center;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">SGI</div>
                        <div style="font-size:28px;font-weight:700;color:#1a1a2e;">${semData.sgi || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:16px;border-radius:12px;text-align:center;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">GPA</div>
                        <div style="font-size:28px;font-weight:700;color:#1a1a2e;">${semData.gpa || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:16px;border-radius:12px;text-align:center;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">CGPA</div>
                        <div style="font-size:28px;font-weight:700;color:#1a1a2e;">${semData.cgpa || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:16px;border-radius:12px;text-align:center;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">Attendance</div>
                        <div style="font-size:28px;font-weight:700;color:#1a1a2e;">${semData.attendance || '—'}%</div>
                    </div>
                </div>
                
                <h4 style="margin:16px 0 12px;color:#1a1a2e;">SGI Components</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;">
                    <div style="background:#e7f3ff;padding:12px;border-radius:10px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Academic</div>
                        <div style="font-size:20px;font-weight:700;color:#0066cc;">${semData.academic_score || '—'}</div>
                    </div>
                    <div style="background:#d4edda;padding:12px;border-radius:10px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Skills</div>
                        <div style="font-size:20px;font-weight:700;color:#28a745;">${semData.skills_score || '—'}</div>
                    </div>
                    <div style="background:#fff3cd;padding:12px;border-radius:10px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Projects</div>
                        <div style="font-size:20px;font-weight:700;color:#856404;">${semData.projects_score || '—'}</div>
                    </div>
                    <div style="background:#f8d7da;padding:12px;border-radius:10px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Activities</div>
                        <div style="font-size:20px;font-weight:700;color:#dc3545;">${semData.activities_score || '—'}</div>
                    </div>
                    <div style="background:#e2e3e5;padding:12px;border-radius:10px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Discipline</div>
                        <div style="font-size:20px;font-weight:700;color:#383d41;">${semData.discipline_score || '—'}</div>
                    </div>
                </div>
                
                <h4 style="margin:20px 0 12px;color:#1a1a2e;">📊 CAT Marks & Subject Details</h4>
                <div style="background:#f8f9fa;border-radius:12px;padding:16px;overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:700px;">
                        <thead>
                            <tr style="border-bottom:2px solid #dee2e6;">
                                <th style="padding:10px;text-align:left;color:#888;font-size:11px;text-transform:uppercase;">Subject</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">Code</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">Credits</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">Type</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">CAT 1</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">CAT 2</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">CAT 3</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">Total</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">%</th>
                                <th style="padding:10px;text-align:center;color:#888;font-size:11px;text-transform:uppercase;">Final CA</th>
                            </tr>
                        </thead>
                        <tbody id="catMarksBody">
                            <tr><td colspan="10" style="padding:20px;text-align:center;color:#888;">Loading subject details...</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- CAT Totals Summary -->
                <div style="margin-top:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
                    <div style="background:linear-gradient(135deg,#e94560,#c73e54);color:#fff;padding:16px;border-radius:12px;text-align:center;">
                        <div style="font-size:11px;opacity:0.8;text-transform:uppercase;">CAT 1 Total</div>
                        <div style="font-size:24px;font-weight:700;" id="cat1TotalDisplay">—</div>
                    </div>
                    <div style="background:linear-gradient(135deg,#1a1a2e,#2d3436);color:#fff;padding:16px;border-radius:12px;text-align:center;">
                        <div style="font-size:11px;opacity:0.8;text-transform:uppercase;">CAT 2 Total</div>
                        <div style="font-size:24px;font-weight:700;" id="cat2TotalDisplay">—</div>
                    </div>
                    <div style="background:linear-gradient(135deg,#f5a623,#e67e22);color:#fff;padding:16px;border-radius:12px;text-align:center;">
                        <div style="font-size:11px;opacity:0.8;text-transform:uppercase;">CAT 3 Total</div>
                        <div style="font-size:24px;font-weight:700;" id="cat3TotalDisplay">—</div>
                    </div>
                </div>
                
                ${semData.result_photo || semData.ca_photo ? `
                <h4 style="margin:20px 0 12px;color:#1a1a2e;">📎 Documents</h4>
                <div style="display:flex;justify-content:center;gap:24px;flex-wrap:wrap;">
                    ${semData.result_photo ? `
                    <div style="text-align:center;">
                        <div style="background:#f8f9fa;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                            <img src="${semData.result_photo}" alt="Result Photo" style="max-width:350px;max-height:280px;object-fit:contain;border-radius:8px;cursor:pointer;" onclick="window.open('${semData.result_photo}','_blank')">
                        </div>
                        <a href="${semData.result_photo}" target="_blank" style="font-size:14px;font-weight:600;color:#0066cc;text-decoration:none;">📄 View Full Result</a>
                    </div>
                    ` : ''}
                    ${semData.ca_photo ? `
                    <div style="text-align:center;">
                        <div style="background:#f8f9fa;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                            <img src="${semData.ca_photo}" alt="CA Photo" style="max-width:350px;max-height:280px;object-fit:contain;border-radius:8px;cursor:pointer;" onclick="window.open('${semData.ca_photo}','_blank')">
                        </div>
                        <a href="${semData.ca_photo}" target="_blank" style="font-size:14px;font-weight:600;color:#0066cc;text-decoration:none;">📋 View Full CA Sheet</a>
                    </div>
                    ` : ''}
                </div>
                ` : ''}
            </div>
        </div>
    `;
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
    document.body.appendChild(modal);

    // Populate CAT marks table from database
    const subjects = semData.subjects || [];
    const catMarksBody = document.getElementById('catMarksBody');
    
    if (subjects.length === 0) {
        catMarksBody.innerHTML = '<tr><td colspan="10" style="padding:20px;text-align:center;color:#888;">No subjects found for this semester.</td></tr>';
    } else {
        let tableHTML = '';
        subjects.forEach(sub => {
            const isInternal = sub.internal === 'yes';
            const cat1 = sub.cat1 !== null ? (sub.cat1 === 'nil' ? '<span style="color:#aaa;">NIL</span>' : sub.cat1) : '—';
            const cat2 = sub.cat2 !== null ? (sub.cat2 === 'nil' ? '<span style="color:#aaa;">NIL</span>' : sub.cat2) : '—';
            const cat3 = sub.cat3 !== null ? (sub.cat3 === 'nil' ? '<span style="color:#aaa;">NIL</span>' : sub.cat3) : '—';
            const catTotal = isInternal ? (sub.cat_total || '—') : '—';
            const catPercent = isInternal ? ((sub.cat_percentage || 0).toFixed(1) + '%') : '—';
            // Display CA marks out of 100 (converted)
            const finalCA = (sub.ca_out_of_100 !== null && sub.ca_out_of_100 !== undefined) ? `${sub.ca_out_of_100}/100` : (sub.ca_scored !== null && sub.ca_scored !== undefined ? `${sub.ca_scored}/${sub.ca_max}` : '—');
            
            tableHTML += `<tr>
                <td style="padding:10px;font-weight:600;color:#1a1a2e;">${sub.subject_name || '—'}</td>
                <td style="padding:10px;text-align:center;color:#555;">${sub.subject_code || '—'}</td>
                <td style="padding:10px;text-align:center;color:#555;">${sub.credits || '—'}</td>
                <td style="padding:10px;text-align:center;">${isInternal ? '<span style="background:#e7f3ff;color:#0066cc;padding:2px 8px;border-radius:10px;font-size:11px;">Internal</span>' : '<span style="background:#f0f0f0;color:#888;padding:2px 8px;border-radius:10px;font-size:11px;">External</span>'}</td>
                <td style="padding:10px;text-align:center;font-weight:600;color:${isInternal ? '#e94560' : '#ccc'};">${cat1}</td>
                <td style="padding:10px;text-align:center;font-weight:600;color:${isInternal ? '#1a1a2e' : '#ccc'};">${cat2}</td>
                <td style="padding:10px;text-align:center;font-weight:600;color:${isInternal ? '#f5a623' : '#ccc'};">${cat3}</td>
                <td style="padding:10px;text-align:center;font-weight:700;color:#1a1a2e;">${catTotal}</td>
                <td style="padding:10px;text-align:center;font-weight:700;color:${catPercent !== '—' && parseFloat(catPercent) >= 80 ? '#28a745' : catPercent !== '—' && parseFloat(catPercent) >= 60 ? '#f5a623' : catPercent !== '—' ? '#e94560' : '#ccc'};">${catPercent}</td>
                <td style="padding:10px;text-align:center;font-weight:600;color:#27ae60;">${finalCA}</td>
            </tr>`;
        });
        catMarksBody.innerHTML = tableHTML;
    }
    
    // Update CAT totals
    document.getElementById('cat1TotalDisplay').textContent = `${semData.cat1_total || 0} / ${semData.cat1_max || 0} (${semData.cat1_percent || 0}%)`;
    document.getElementById('cat2TotalDisplay').textContent = `${semData.cat2_total || 0} / ${semData.cat2_max || 0} (${semData.cat2_percent || 0}%)`;
    document.getElementById('cat3TotalDisplay').textContent = `${semData.cat3_total || 0} / ${semData.cat3_max || 0} (${semData.cat3_percent || 0}%)`;
}

function toggleNotif() {
    const drop = document.getElementById('notifDrop');
    drop.classList.toggle('open');
    if (drop.classList.contains('open')) loadNotifs();
}

function loadNotifs() {
    fetch('notifications.php?fetch=1&mentor=1')
        .then(r=>r.json()).then(data=>{
            const list = document.getElementById('notifList');
            if (!data.length) { list.innerHTML='<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');
        });
}
function markAll(e) {
    e.preventDefault();
    fetch('notifications.php?mark_all=1&mentor=1');
    document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
function clearAll(e) {
    e.preventDefault();
    fetch('notifications.php?delete_all=1&mentor=1');
    document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
document.addEventListener('click', e => {
    const btn = document.getElementById('bellBtn');
    const drop = document.getElementById('notifDrop');
    if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target))
        drop.classList.remove('open');
});
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
