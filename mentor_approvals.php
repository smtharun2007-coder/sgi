<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }

$m = $_SESSION['mentor'];
$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – Mentor Approvals</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .mentor-navbar { background: linear-gradient(135deg, #1a1a2e, #8e44ad); }
        
        /* Stats Cards */
        .stats-row {
            display: flex;
            gap: 20px;
            margin-top: 24px;
        }
        .stat-card {
            flex: 1;
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s ease;
            border-top: 4px solid transparent;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .stat-card.pending { border-top-color: #ffc107; }
        .stat-card.approved { border-top-color: #28a745; }
        .stat-card.rejected { border-top-color: #dc3545; }
        .stat-card .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 13px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 8px;
        }

        /* Main Card */
        .main-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-top: 24px;
        }
        .main-header {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            padding: 20px 28px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .main-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        /* Filters */
        .filters-bar {
            padding: 16px 28px;
            background: #f8f9fa;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            border-bottom: 1px solid #eee;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
            white-space: nowrap;
        }
        .filter-group select {
            padding: 8px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .filter-group select:focus {
            outline: none;
            border-color: #8e44ad;
        }
        .status-tabs {
            display: flex;
            gap: 6px;
        }
        .status-tab {
            padding: 6px 16px;
            border: none;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            color: #555;
        }
        .status-tab.active {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            color: #fff;
        }
        .status-tab:hover:not(.active) {
            background: #dee2e6;
        }

        /* Approval Items */
        .approval-list {
            max-height: 600px;
            overflow-y: auto;
        }
        .approval-item {
            padding: 18px 28px;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: background 0.2s;
            cursor: pointer;
        }
        .approval-item:hover {
            background: #f8f9fa;
        }
        .approval-item:last-child {
            border-bottom: none;
        }
        .approval-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        .approval-content {
            flex: 1;
            min-width: 0;
        }
        .approval-title {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .approval-subtitle {
            font-size: 12px;
            color: #888;
        }
        .approval-meta {
            display: flex;
            gap: 12px;
            margin-top: 4px;
        }
        .approval-meta span {
            font-size: 11px;
            color: #aaa;
        }
        .approval-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }
        
        /* Status Badges */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        /* Action Buttons */
        .btn-sm {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-approve-sm {
            background: #28a745;
            color: #fff;
        }
        .btn-approve-sm:hover { background: #218838; }
        .btn-reject-sm {
            background: #dc3545;
            color: #fff;
        }
        .btn-reject-sm:hover { background: #c82333; }
        .btn-view-sm {
            background: #17a2b8;
            color: #fff;
        }
        .btn-view-sm:hover { background: #138496; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            color: #888;
        }
        .empty-state .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #fff;
            border-radius: 20px;
            max-width: 560px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlide 0.3s ease;
        }
        @keyframes modalSlide {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            padding: 20px 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 20px 20px 0 0;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.2); }
        .modal-body { padding: 24px; }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
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
            font-weight: 500;
        }
        
        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        .subjects-table th {
            text-align: left;
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            padding: 8px 12px;
            border-bottom: 2px solid #eee;
        }
        .subjects-table td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid #f0f2f5;
        }
        .subjects-table tr:last-child td { border-bottom: none; }
        
        .remarks-box {
            background: #fff3f3;
            border-left: 4px solid #dc3545;
            padding: 14px 16px;
            border-radius: 8px;
            margin-top: 16px;
        }
        .remarks-box.approved {
            background: #f3fff3;
            border-left-color: #28a745;
        }
        .remarks-box label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            display: block;
            margin-bottom: 4px;
        }
        .remarks-box p {
            margin: 0;
            font-size: 14px;
            color: #333;
        }

        .textarea-box {
            margin-top: 16px;
        }
        .textarea-box label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .textarea-box textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            min-height: 80px;
            resize: vertical;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .textarea-box textarea:focus {
            outline: none;
            border-color: #8e44ad;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .modal-actions button {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-approve-full {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: #fff;
        }
        .btn-approve-full:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40,167,69,0.3);
        }
        .btn-reject-full {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: #fff;
        }
        .btn-reject-full:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220,53,69,0.3);
        }
        .btn-cancel-full {
            background: #e9ecef;
            color: #555;
        }
        .btn-cancel-full:hover { background: #dee2e6; }
    </style>
</head>
<body>
<nav class="navbar mentor-navbar">
<a href="mentor_dashboard.php" class="nav-brand">
    <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo">
    SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span>
</a>
    <div class="nav-links">
        <a href="mentor_dashboard.php">Home</a>
        <a href="mentor_approvals.php" style="color:#fff;font-weight:600;">Approvals</a>
        <a href="mentor_update_profile.php">Profile</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                &#128276;<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll(event)">Mark all read</a></div>
                <div id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">
    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card pending" onclick="setStatusFilter('pending')">
            <div class="stat-number" id="pendingCount">0</div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card approved" onclick="setStatusFilter('approved')">
            <div class="stat-number" id="approvedCount">0</div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card rejected" onclick="setStatusFilter('rejected')">
            <div class="stat-number" id="rejectedCount">0</div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="main-card">
        <div class="main-header">
            <h2>📋 Student Semester Registration Requests</h2>
        </div>
        
        <!-- Filters -->
        <div class="filters-bar">
            <div class="filter-group">
                <label>Semester:</label>
                <select id="semesterFilter" onchange="applyFilters()">
                    <option value="all">All Semesters</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                    <option value="3">Semester 3</option>
                    <option value="4">Semester 4</option>
                    <option value="5">Semester 5</option>
                    <option value="6">Semester 6</option>
                    <option value="7">Semester 7</option>
                    <option value="8">Semester 8</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status:</label>
                <div class="status-tabs" id="statusTabs">
                    <button class="status-tab active" data-status="all" onclick="setStatusFilter('all')">All</button>
                    <button class="status-tab" data-status="pending" onclick="setStatusFilter('pending')">Pending</button>
                    <button class="status-tab" data-status="approved" onclick="setStatusFilter('approved')">Approved</button>
                    <button class="status-tab" data-status="rejected" onclick="setStatusFilter('rejected')">Rejected</button>
                </div>
            </div>
        </div>
        
        <!-- Approval List -->
        <div class="approval-list" id="approvalList">
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>Loading approval requests...</p>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle">Request Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<script>
let currentStatusFilter = 'all';
let currentSemesterFilter = 'all';
let approvals = [];

document.addEventListener('DOMContentLoaded', function() {
    loadApprovals();
});

function loadApprovals() {
    fetch('approvals.php?fetch=1')
        .then(r => r.json())
        .then(data => {
            approvals = data;
            updateStats();
            renderApprovals();
        });
}

function updateStats() {
    let pending = 0, approved = 0, rejected = 0;
    approvals.forEach(a => {
        if (a.status === 'pending') pending++;
        else if (a.status === 'approved') approved++;
        else if (a.status === 'rejected') rejected++;
    });
    document.getElementById('pendingCount').textContent = pending;
    document.getElementById('approvedCount').textContent = approved;
    document.getElementById('rejectedCount').textContent = rejected;
}

function setStatusFilter(status) {
    currentStatusFilter = status;
    document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
    const tab = document.querySelector(`[data-status="${status}"]`);
    if (tab) tab.classList.add('active');
    renderApprovals();
}

function applyFilters() {
    currentSemesterFilter = document.getElementById('semesterFilter').value;
    renderApprovals();
}

function getFilteredApprovals() {
    return approvals.filter(a => {
        const statusMatch = currentStatusFilter === 'all' || a.status === currentStatusFilter;
        const semMatch = currentSemesterFilter === 'all' || a.semester == currentSemesterFilter;
        return statusMatch && semMatch;
    });
}

function renderApprovals() {
    const list = document.getElementById('approvalList');
    const filtered = getFilteredApprovals();
    
    if (filtered.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>No approval requests found.</p>
            </div>
        `;
        return;
    }
    
    list.innerHTML = filtered.map(a => `
        <div class="approval-item" onclick="showDetails('${a._id}')">
            <div class="approval-avatar">${a.student_name.charAt(0).toUpperCase()}</div>
            <div class="approval-content">
                <div class="approval-title">
                    ${a.type || 'Semester Registration'}
                    <span class="badge badge-${a.status}">${a.status}</span>
                </div>
                <div class="approval-subtitle">${a.student_name} (${a.student_roll})</div>
                <div class="approval-meta">
                    <span>📅 ${a.created_at}</span>
                    <span>📚 Sem ${a.semester || '?'}</span>
                    <span>📝 ${a.subject_count || 0} subjects</span>
                </div>
            </div>
            <div class="approval-actions">
                ${a.status === 'pending' ? `
                    <button class="btn-sm btn-approve-sm" onclick="event.stopPropagation();openActionModal('${a._id}', 'approve')" title="Approve">✓</button>
                    <button class="btn-sm btn-reject-sm" onclick="event.stopPropagation();openActionModal('${a._id}', 'reject')" title="Reject">✗</button>
                ` : `
                    <button class="btn-sm btn-view-sm" onclick="event.stopPropagation();showDetails('${a._id}')" title="View">👁</button>
                `}
            </div>
        </div>
    `).join('');
}

function showDetails(id) {
    const approval = approvals.find(a => a._id === id);
    if (!approval) return;
    
    const statusColor = approval.status === 'approved' ? '#28a745' : approval.status === 'rejected' ? '#dc3545' : '#ffc107';
    const statusIcon = approval.status === 'approved' ? '✅' : approval.status === 'rejected' ? '❌' : '⏳';
    
    document.getElementById('modalTitle').textContent = `${statusIcon} ${approval.type || 'Semester Registration'} - Sem ${approval.semester}`;
    
    let subjectsHtml = '';
    
    // Handle Verification approval type
    if (approval.type === 'Verification') {
        const verificationData = approval.verification_data || {};
        const catMarks = approval.cat_marks || [];
        
        subjectsHtml = `
            <!-- Verification Data -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span style="color:#28a745;">📊</span> Verification Details
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="background:#f8f9fa;padding:16px;border-radius:10px;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">Previous GPA</div>
                        <div style="font-size:24px;font-weight:700;color:#1a1a2e;">${verificationData.prev_gpa || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:16px;border-radius:10px;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">Attendance</div>
                        <div style="font-size:24px;font-weight:700;color:#1a1a2e;">${verificationData.attendance || '—'}%</div>
                    </div>
                </div>
            </div>
            
            <!-- CAT Marks for all subjects -->
            ${catMarks.length > 0 ? `
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>📋</span> CAT Marks (${catMarks.length} Subjects)
                </h4>
                <table class="subjects-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>CAT 1</th>
                            <th>CAT 2</th>
                            <th>CAT 3</th>
                            <th>Total</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${catMarks.map(s => `
                            <tr>
                                <td><strong>${escapeHtml(s.subject_name)}</strong></td>
                                <td style="color:#888;">${escapeHtml(s.subject_code)}</td>
                                <td style="text-align:center;">${s.cat1 !== undefined ? (s.cat1 === 'nil' ? '<span style="color:#aaa;">NIL</span>' : s.cat1) : '—'}</td>
                                <td style="text-align:center;">${s.cat2 !== undefined ? (s.cat2 === 'nil' ? '<span style="color:#aaa;">NIL</span>' : s.cat2) : '—'}</td>
                                <td style="text-align:center;">${s.cat3 !== undefined ? (s.cat3 === 'nil' ? '<span style="color:#aaa;">NIL</span>' : s.cat3) : '—'}</td>
                                <td style="text-align:center;font-weight:600;">${s.total !== undefined ? s.total : '—'}</td>
                                <td style="text-align:center;color:${parseFloat(s.percentage) >= 80 ? '#28a745' : parseFloat(s.percentage) >= 60 ? '#f5a623' : '#dc3545'};font-weight:600;">${s.percentage !== undefined ? s.percentage + '%' : '—'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            ` : ''}
            
            <!-- Summary -->
            <div style="margin-top:20px;padding:16px;background:#f8f9fa;border-radius:12px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;">📊 Summary</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div style="background:#d4edda;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">Subjects</div>
                        <div style="font-size:24px;font-weight:700;color:#28a745;">${catMarks.length}</div>
                    </div>
                    <div style="background:#e7f3ff;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">Prev GPA</div>
                        <div style="font-size:24px;font-weight:700;color:#0066cc;">${verificationData.prev_gpa || '—'}</div>
                    </div>
                    <div style="background:#fff3cd;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">Attendance</div>
                        <div style="font-size:24px;font-weight:700;color:#856404;">${verificationData.attendance || '—'}%</div>
                    </div>
                </div>
            </div>
        `;
    }
    // Handle Final CA Marks approval type
    else if (approval.type === 'Final CA Marks') {
        const caSubjects = approval.ca_subjects || [];
        const caData = approval.ca_data || {};
        const documents = approval.documents || {};
        
        subjectsHtml = `
            <!-- CA Data -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span style="color:#28a745;">📊</span> Final CA Details
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="background:#f8f9fa;padding:16px;border-radius:10px;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">GPA</div>
                        <div style="font-size:24px;font-weight:700;color:#1a1a2e;">${caData.gpa || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:16px;border-radius:10px;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">CGPA</div>
                        <div style="font-size:24px;font-weight:700;color:#1a1a2e;">${caData.cgpa || '—'}</div>
                    </div>
                </div>
            </div>
            
            <!-- CA Marks for all subjects -->
            ${caSubjects.length > 0 ? `
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>📋</span> CA Marks (${caSubjects.length} Subjects)
                </h4>
                <table class="subjects-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Credits</th>
                            <th>Scored</th>
                            <th>Max</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${caSubjects.map(s => `
                            <tr>
                                <td><strong>${escapeHtml(s.subject_name)}</strong></td>
                                <td style="color:#888;">${escapeHtml(s.subject_code)}</td>
                                <td style="text-align:center;">${s.credits || '—'}</td>
                                <td style="text-align:center;font-weight:600;">${s.ca_scored !== undefined ? s.ca_scored : '—'}</td>
                                <td style="text-align:center;">${s.ca_max !== undefined ? s.ca_max : '—'}</td>
                                <td style="text-align:center;color:${parseFloat(s.ca_percent) >= 80 ? '#28a745' : parseFloat(s.ca_percent) >= 60 ? '#f5a623' : '#dc3545'};font-weight:600;">${s.ca_percent !== undefined ? s.ca_percent + '%' : '—'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            ` : ''}
            
            <!-- Uploaded Documents -->
            ${(documents.result_photo || documents.ca_photo || caData.result_photo || caData.ca_photo) ? `
            <div style="margin-top:20px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>📎</span> Uploaded Documents
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    ${(documents.result_photo || caData.result_photo) ? `
                    <div style="background:#f8f9fa;padding:16px;border-radius:10px;text-align:center;">
                        <div style="font-size:14px;font-weight:600;color:#1a1a2e;margin-bottom:8px;">📄 Semester Result</div>
                        <div style="font-size:12px;color:#28a745;">✓ Document uploaded</div>
                        <div style="margin-top:8px;">
                            <a href="${documents.result_photo || caData.result_photo}" target="_blank" style="font-size:13px;color:#0066cc;text-decoration:none;">View Document →</a>
                        </div>
                    </div>
                    ` : ''}
                    ${(documents.ca_photo || caData.ca_photo) ? `
                    <div style="background:#f8f9fa;padding:16px;border-radius:10px;text-align:center;">
                        <div style="font-size:14px;font-weight:600;color:#1a1a2e;margin-bottom:8px;">📋 CA Mark Sheet</div>
                        <div style="font-size:12px;color:#28a745;">✓ Document uploaded</div>
                        <div style="margin-top:8px;">
                            <a href="${documents.ca_photo || caData.ca_photo}" target="_blank" style="font-size:13px;color:#0066cc;text-decoration:none;">View Document →</a>
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}
            
            <!-- Summary -->
            <div style="margin-top:20px;padding:16px;background:#f8f9fa;border-radius:12px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;">📊 Summary</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div style="background:#d4edda;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">Subjects</div>
                        <div style="font-size:24px;font-weight:700;color:#28a745;">${caSubjects.length}</div>
                    </div>
                    <div style="background:#e7f3ff;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">GPA</div>
                        <div style="font-size:24px;font-weight:700;color:#0066cc;">${caData.gpa || '—'}</div>
                    </div>
                    <div style="background:#fff3cd;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">CGPA</div>
                        <div style="font-size:24px;font-weight:700;color:#856404;">${caData.cgpa || '—'}</div>
                    </div>
                </div>
            </div>
        `;
    }
    // Handle SGI Calculation approval type
    else if (approval.type === 'SGI Calculation') {
        const sgiData = approval.sgi_data || {};
        
        // Check for pending evaluator projects
        const pendingEvalProjects = sgiData.pending_evaluator_projects || [];
        const otherProjects = sgiData.other_projects || [];
        
        subjectsHtml = `
            <!-- SGI Score Summary -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span style="color:#28a745;">📊</span> SGI Calculation Summary
                </h4>
                <div style="background:#f8f9fa;padding:20px;border-radius:12px;">
                    <div style="text-align:center;margin-bottom:16px;">
                        <div style="font-size:14px;color:#888;margin-bottom:4px;">Calculated SGI</div>
                        <div style="font-size:36px;font-weight:700;color:#1a1a2e;">${sgiData.sgi || '—'}</div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Academic Score</div>
                            <div style="font-size:18px;font-weight:600;color:#1a1a2e;">${sgiData.academic_score || '—'}</div>
                        </div>
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Skills Score</div>
                            <div style="font-size:18px;font-weight:600;color:#1a1a2e;">${sgiData.skills_score || '—'}</div>
                        </div>
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Projects Score</div>
                            <div style="font-size:18px;font-weight:600;color:#1a1a2e;">${sgiData.projects_score || '—'}</div>
                        </div>
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Activities Score</div>
                            <div style="font-size:18px;font-weight:600;color:#1a1a2e;">${sgiData.activities_score || '—'}</div>
                        </div>
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Discipline Score</div>
                            <div style="font-size:18px;font-weight:600;color:#1a1a2e;">${sgiData.discipline_score || '—'}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Academic Details -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>📚</span> Academic Details
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">CAT 1 (10)</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.cat1_10 || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">CAT 2 (10)</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.cat2_10 || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">CAT 3 (10)</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.cat3_10 || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">GPA</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.gpa || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">CGPA</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.cgpa || '—'}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Attendance</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.attendance || '—'}%</div>
                    </div>
                </div>
            </div>
            
            <!-- Skills Details -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>💡</span> Skills
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Credit Courses</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.credit_courses || 0}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Coding Platforms</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.coding_platforms || 0}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Normal Courses</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.normal_courses || 0}</div>
                    </div>
                </div>
            </div>
            
            <!-- Projects Details -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>🔧</span> Projects
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Mini Projects</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.mini_projects || 0}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Main Projects</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.main_projects || 0}</div>
                    </div>
                </div>
                
                ${otherProjects.length > 0 ? `
                <div style="margin-top:12px;">
                    <div style="font-size:14px;font-weight:600;color:#1a1a2e;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        🔧 Other Projects (Need Evaluator Approval)
                    </div>
                    <table class="subjects-table">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Count</th>
                                <th>Points</th>
                                <th>Evaluator ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${otherProjects.map(p => {
                                const pendingProj = pendingEvalProjects.find(ep => ep.project_name === p.name);
                                const status = pendingProj ? '⏳ Pending' : '✅ Approved';
                                const statusColor = pendingProj ? '#ffc107' : '#28a745';
                                return `
                                <tr>
                                    <td><strong>${escapeHtml(p.name)}</strong></td>
                                    <td style="text-align:center;">${p.count}</td>
                                    <td style="text-align:center;">${p.points}</td>
                                    <td style="text-align:center;color:#888;">${escapeHtml(p.evaluator_id || '—')}</td>
                                    <td style="text-align:center;font-weight:600;color:${statusColor};">${status}</td>
                                </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                    ${pendingEvalProjects.length > 0 ? `
                    <div style="margin-top:10px;padding:10px 14px;background:#fff3cd;border-radius:8px;font-size:12px;color:#856404;">
                        ⚠️ ${pendingEvalProjects.length} project(s) pending evaluator approval. Once all evaluators approve, you can proceed with SGI approval.
                    </div>
                    ` : `
                    <div style="margin-top:10px;padding:10px 14px;background:#d4edda;border-radius:8px;font-size:12px;color:#155724;">
                        ✅ All other projects have been approved by evaluators.
                    </div>
                    `}
                </div>
                ` : ''}
            </div>
            
            <!-- Activities Details -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>🏆</span> Activities / Hackathons
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Leader Wins</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.leader_wins || 0}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Member Wins</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.member_wins || 0}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Leader Places</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.leader_places || 0}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Member Places</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.member_places || 0}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Participations</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.participations || 0}</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Workshops</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.workshops || 0}</div>
                    </div>
                </div>
            </div>
            
            <!-- Discipline Details -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>📈</span> Discipline
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Attendance</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.attendance || '—'}%</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:#888;">Previous GPA</div>
                        <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${sgiData.prev_gpa || '—'}</div>
                    </div>
                </div>
            </div>
        `;
    }
    // Handle Project Evaluation approval type (for evaluators)
    else if (approval.type === 'Project Evaluation') {
        let projectData = approval.project_data || {};
        // Handle BSONDocument conversion - if projectData has $numberInt or similar, it's a BSON doc
        if (typeof projectData === 'object' && projectData !== null && !Array.isArray(projectData)) {
            // Check if it's a BSON-like object with weird keys
            if (projectData.project_name === undefined && Object.keys(projectData).length > 0) {
                // Try to extract values from BSON-like structure
                projectData = {
                    project_name: projectData.project_name || projectData.name || '',
                    count: projectData.count || projectData.no || 0,
                    points: projectData.points || projectData.point || 0,
                    submitted_by: projectData.submitted_by || projectData.student || '',
                    submitted_by_mentor: projectData.submitted_by_mentor || projectData.mentor_id || ''
                };
            }
        }
        
        subjectsHtml = `
            <!-- Project Evaluation Details -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span style="color:#28a745;">🔧</span> Project Evaluation Request
                </h4>
                <div style="background:#f8f9fa;padding:20px;border-radius:12px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Student Name</div>
                            <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${approval.student_name || '—'}</div>
                        </div>
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Student Roll</div>
                            <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${approval.student_roll || '—'}</div>
                        </div>
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Submitted By Mentor</div>
                            <div style="font-size:16px;font-weight:600;color:#1a1a2e;">${projectData.submitted_by_mentor || '—'}</div>
                        </div>
                        <div style="background:#fff;padding:12px;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">Semester</div>
                            <div style="font-size:16px;font-weight:600;color:#1a1a2e;">Semester ${approval.semester || '—'}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Project Details -->
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>📋</span> Project Details
                </h4>
                <div style="background:#f8f9fa;padding:20px;border-radius:12px;">
                    <table class="subjects-table">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>No. of Projects</th>
                                <th>Points / Project</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>${escapeHtml(projectData.project_name || '—')}</strong></td>
                                <td style="text-align:center;">${projectData.count || 0}</td>
                                <td style="text-align:center;">${projectData.points || 0}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top:12px;padding:12px;background:#e7f3ff;border-radius:8px;font-size:13px;color:#0066cc;">
                        ℹ️ Please verify that the student has completed the project(s) as described. If approved, the points will be added to the student's SGI calculation.
                    </div>
                </div>
            </div>
            
            <!-- Summary -->
            <div style="margin-top:20px;padding:16px;background:#f8f9fa;border-radius:12px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;">📊 Evaluation Summary</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div style="background:#d4edda;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">Projects</div>
                        <div style="font-size:24px;font-weight:700;color:#28a745;">${projectData.count || 0}</div>
                    </div>
                    <div style="background:#e7f3ff;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">Points Each</div>
                        <div style="font-size:24px;font-weight:700;color:#0066cc;">${projectData.points || 0}</div>
                    </div>
                    <div style="background:#fff3cd;padding:12px;border-radius:8px;text-align:center;">
                        <div style="font-size:12px;color:#888;">Total Points</div>
                        <div style="font-size:24px;font-weight:700;color:#856404;">${(projectData.count || 0) * (projectData.points || 0)}</div>
                    </div>
                </div>
            </div>
        `;
    }
    // Handle Credit Subjects approval type with detailed comparison
    else if (approval.type === 'Credit Subjects') {
        const creditSubjects = approval.credit_subjects || [];
        const creditDeletions = approval.credit_deletions || [];
        const existingSubjects = approval.existing_subjects || [];
        const existingNonInternal = approval.existing_non_internal || [];
        
        subjectsHtml = `
            <!-- Existing Internal Subjects (Unchanged) -->
            ${existingSubjects.length > 0 ? `
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:8px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span style="color:#28a745;">✓</span> Existing Internal Subjects (No Changes)
                </h4>
                <table class="subjects-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${existingSubjects.map(s => `
                            <tr>
                                <td><strong>${escapeHtml(s.name)}</strong></td>
                                <td style="color:#888;">${escapeHtml(s.code)}</td>
                                <td style="text-align:center;">${s.credits}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            ` : ''}
            
            <!-- Existing Non-Internal Subjects -->
            ${existingNonInternal.length > 0 ? `
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:8px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>📋</span> Existing Credit Subjects
                </h4>
                <table class="subjects-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Credits</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${existingNonInternal.map(s => `
                            <tr style="${s.marked_for_deletion ? 'background:#fff3f3;text-decoration:line-through;' : ''}">
                                <td><strong>${escapeHtml(s.name)}</strong></td>
                                <td style="color:#888;">${escapeHtml(s.code)}</td>
                                <td style="text-align:center;">${s.credits}</td>
                                <td style="text-align:center;">
                                    ${s.marked_for_deletion ? 
                                        '<span style="color:#dc3545;font-weight:600;font-size:12px;">⚠ Marked for Deletion</span>' : 
                                        '<span style="color:#28a745;font-size:12px;">✓ Keeping</span>'
                                    }
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            ` : ''}
            
            <!-- Subjects to Add (New) -->
            ${creditSubjects.length > 0 ? `
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:8px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span style="color:#28a745;">➕</span> New Credit Subjects to Add (${creditSubjects.length})
                </h4>
                <table class="subjects-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${creditSubjects.map(s => {
                            const name = s.subject_name || s.name || '';
                            const code = s.subject_code || s.code || '';
                            return `
                            <tr style="background:#f3fff3;">
                                <td><strong>${escapeHtml(name)}</strong></td>
                                <td style="color:#888;">${escapeHtml(code)}</td>
                                <td style="text-align:center;">${s.credits}</td>
                            </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
            ` : ''}
            
            <!-- Subjects to Delete -->
            ${creditDeletions.length > 0 ? `
            <div style="margin-top:16px;">
                <h4 style="margin-bottom:8px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span style="color:#dc3545;">➖</span> Credit Subjects to Remove (${creditDeletions.length})
                </h4>
                <table class="subjects-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${creditDeletions.map(s => {
                            const name = s.subject_name || s.name || '';
                            const code = s.subject_code || s.code || '';
                            return `
                            <tr style="background:#fff3f3;">
                                <td><strong>${escapeHtml(name)}</strong></td>
                                <td style="color:#888;">${escapeHtml(code)}</td>
                                <td style="text-align:center;">${s.credits}</td>
                            </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
            ` : ''}
            
            <!-- Summary -->
            <div style="margin-top:20px;padding:16px;background:#f8f9fa;border-radius:12px;">
                <h4 style="margin-bottom:12px;color:#1a1a2e;">📊 Summary</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="background:#d4edda;padding:12px;border-radius:8px;">
                        <div style="font-size:12px;color:#888;">Subjects to Add</div>
                        <div style="font-size:24px;font-weight:700;color:#28a745;">${creditSubjects.length}</div>
                    </div>
                    <div style="background:#f8d7da;padding:12px;border-radius:8px;">
                        <div style="font-size:12px;color:#888;">Subjects to Remove</div>
                        <div style="font-size:24px;font-weight:700;color:#dc3545;">${creditDeletions.length}</div>
                    </div>
                </div>
            </div>
        `;
    } else {
        // Default subjects display for other approval types
        subjectsHtml = `
            <h4 style="margin-top:20px;margin-bottom:8px;color:#1a1a2e;">📚 Registered Subjects (${approval.subject_count || 0})</h4>
            <table class="subjects-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Credits</th>
                        <th>Internal</th>
                    </tr>
                </thead>
                <tbody>
                    ${approval.subjects && approval.subjects.length > 0 ? approval.subjects.map(s => `
                        <tr>
                            <td><strong>${escapeHtml(s.name)}</strong></td>
                            <td style="color:#888;">${escapeHtml(s.code)}</td>
                            <td style="text-align:center;">${s.credits}</td>
                            <td style="text-align:center;color:${s.internal === 'yes' ? '#28a745' : '#dc3545'};font-weight:600;">
                                ${s.internal === 'yes' ? '✓ Yes' : '✗ No'}
                            </td>
                        </tr>
                    `).join('') : '<tr><td colspan="4" style="text-align:center;color:#888;">No subjects found</td></tr>'}
                </tbody>
            </table>
        `;
    }
    
    document.getElementById('modalBody').innerHTML = `
        <div class="info-grid">
            <div class="info-item">
                <label>Student</label>
                <span>${escapeHtml(approval.student_name)}</span>
            </div>
            <div class="info-item">
                <label>Roll Number</label>
                <span>${escapeHtml(approval.student_roll)}</span>
            </div>
            <div class="info-item">
                <label>Status</label>
                <span class="badge badge-${approval.status}">${approval.status}</span>
            </div>
            <div class="info-item">
                <label>Semester</label>
                <span>Semester ${approval.semester}</span>
            </div>
            <div class="info-item">
                <label>Submitted</label>
                <span>${approval.created_at}</span>
            </div>
            <div class="info-item">
                <label>${approval.updated_at ? 'Processed' : 'Last Updated'}</label>
                <span>${approval.updated_at || '—'}</span>
            </div>
        </div>
        
        ${approval.mentor_remarks ? `
        <div class="remarks-box ${approval.status === 'approved' ? 'approved' : ''}">
            <label>${approval.status === 'approved' ? '✅ Approval Remarks' : '❌ Rejection Reason'}</label>
            <p>${escapeHtml(approval.mentor_remarks)}</p>
        </div>` : ''}
        
        ${subjectsHtml}
    `;
    document.getElementById('detailModal').classList.add('active');
}

function openActionModal(id, action) {
    const approval = approvals.find(a => a._id === id);
    if (!approval) return;
    
    const isApprove = action === 'approve';
    
    document.getElementById('modalTitle').textContent = isApprove ? '✅ Approve Request' : '❌ Reject Request';
    document.getElementById('modalBody').innerHTML = `
        <div style="background:${isApprove ? '#d4edda' : '#f8d7da'};padding:16px;border-radius:12px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
            <div style="font-size:32px;">${isApprove ? '✅' : '❌'}</div>
            <div>
                <div style="font-weight:600;color:#333;">${approval.student_name}</div>
                <div style="font-size:13px;color:#666;">${approval.student_roll} · Semester ${approval.semester} · ${approval.subject_count || 0} subjects</div>
            </div>
        </div>
        
        <div class="textarea-box">
            <label>${isApprove ? 'Approval Remarks (optional)' : 'Rejection Reason (required) *'}</label>
            <textarea id="remarksInput" placeholder="${isApprove ? 'Add any comments for the student...' : 'Please provide a reason for rejection...'}"></textarea>
        </div>
        
        <div class="modal-actions">
            <button class="${isApprove ? 'btn-approve-full' : 'btn-reject-full'}" onclick="processApproval('${id}', '${action}')">
                ${isApprove ? '✓ Approve Request' : '✗ Reject Request'}
            </button>
            <button class="btn-cancel-full" onclick="closeModal()">Cancel</button>
        </div>
    `;
    document.getElementById('detailModal').classList.add('active');
}

function processApproval(id, action) {
    const remarks = document.getElementById('remarksInput').value.trim();
    const status = action === 'approve' ? 'approved' : 'rejected';
    
    if (action === 'reject' && !remarks) {
        showToast('Please provide a reason for rejection.', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('approval_id', id);
    formData.append('status', status);
    formData.append('remarks', remarks);
    formData.append('update_status', '1');
    
    fetch('approvals.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                closeModal();
                loadApprovals();
            } else {
                showToast(data.message || 'Failed to process', 'error');
            }
        });
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// Notification functions
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