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
        .approval-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            margin-top: 24px;
        }
        .approval-header {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            padding: 16px 24px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .approval-row {
            padding: 16px 24px;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: background 0.2s;
        }
        .approval-row:hover { background: #fafafa; }
        .approval-row:last-child { border-bottom: none; }
        .approval-info { flex: 1; }
        .approval-type {
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        .approval-meta {
            font-size: 12px;
            color: #888;
            display: flex;
            gap: 12px;
            margin-top: 4px;
        }
        .approval-student {
            font-size: 13px;
            color: #8e44ad;
            font-weight: 600;
            margin-top: 4px;
        }
        .approval-subjects-preview {
            font-size: 11px;
            color: #888;
            margin-top: 4px;
            font-style: italic;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .stats-row {
            display: flex;
            gap: 16px;
            margin-top: 20px;
        }
        .stat-card {
            flex: 1;
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card.pending { border-top: 4px solid #ffc107; }
        .stat-card.approved { border-top: 4px solid #28a745; }
        .stat-card.rejected { border-top: 4px solid #dc3545; }
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .stat-card .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #888;
        }
        .empty-state .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
            padding: 16px 24px 0;
        }
        .filter-tab {
            padding: 6px 16px;
            border: none;
            background: #f0f2f5;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .filter-tab.active {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            color: #fff;
        }
        .filter-tab:hover:not(.active) {
            background: #e0e0e0;
        }
        .semester-badge {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            color: #fff;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 6px;
        }
        .btn-action {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            margin-left: 4px;
        }
        .btn-approve {
            background: #28a745;
            color: #fff;
        }
        .btn-approve:hover { background: #218838; }
        .btn-reject {
            background: #dc3545;
            color: #fff;
        }
        .btn-reject:hover { background: #c82333; }
        .btn-view {
            background: #17a2b8;
            color: #fff;
        }
        .btn-view:hover { background: #138496; }
        
        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: 16px;
            max-width: 550px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            padding: 20px 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { margin: 0; }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
        }
        .modal-body { padding: 24px; }
        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }
        .detail-label {
            font-weight: 600;
            color: #333;
            width: 120px;
            flex-shrink: 0;
        }
        .detail-value { color: #555; }
        .subjects-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
        .subject-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        .subject-item:last-child { border-bottom: none; }
        .remarks-section {
            margin-top: 16px;
        }
        .remarks-section label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }
        .remarks-section textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            min-height: 80px;
            resize: vertical;
            box-sizing: border-box;
        }
        .remarks-section textarea:focus {
            outline: none;
            border-color: #8e44ad;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .action-buttons button {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-approve-large {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: #fff;
        }
        .btn-approve-large:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(40,167,69,0.3); }
        .btn-reject-large {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: #fff;
        }
        .btn-reject-large:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220,53,69,0.3); }
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
    <div class="stats-row" id="statsRow">
        <div class="stat-card pending" onclick="filterBy('pending')">
            <div class="stat-number" id="pendingCount">0</div>
            <div class="stat-label">Pending Requests</div>
        </div>
        <div class="stat-card approved" onclick="filterBy('approved')">
            <div class="stat-number" id="approvedCount">0</div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card rejected" onclick="filterBy('rejected')">
            <div class="stat-number" id="rejectedCount">0</div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Approval List -->
    <div class="approval-card">
        <div class="approval-header">
            <span>📋 Student Semester Registration Requests</span>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">All</button>
            <button class="filter-tab" data-filter="pending">Pending</button>
            <button class="filter-tab" data-filter="approved">Approved</button>
            <button class="filter-tab" data-filter="rejected">Rejected</button>
        </div>
        
        <div id="approvalList">
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>Loading approval requests...</p>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-content">
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
let currentFilter = 'all';
let approvals = [];

// Load approvals on page load
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

function renderApprovals() {
    const list = document.getElementById('approvalList');
    const filtered = currentFilter === 'all' 
        ? approvals 
        : approvals.filter(a => a.status === currentFilter);
    
    if (filtered.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>No ${currentFilter === 'all' ? '' : currentFilter} approval requests found.</p>
            </div>
        `;
        return;
    }
    
    list.innerHTML = filtered.map(a => `
        <div class="approval-row">
            <div class="approval-info">
                <div class="approval-type">
                    📚 ${a.type || 'Semester Registration'}
                    <span class="semester-badge">Sem ${a.semester || '?'}</span>
                </div>
                <div class="approval-student">👤 ${a.student_name} (${a.student_roll})</div>
                <div class="approval-meta">
                    <span>📅 ${a.created_at}</span>
                    <span>📝 ${a.subject_count || 0} subjects</span>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="status-badge status-${a.status}">${a.status}</span>
                ${a.status === 'pending' ? `
                    <button class="btn-action btn-approve" onclick="openActionModal('${a._id}', 'approve')" title="Approve">✓</button>
                    <button class="btn-action btn-reject" onclick="openActionModal('${a._id}', 'reject')" title="Reject">✗</button>
                ` : `
                    <button class="btn-action btn-view" onclick="showDetails('${a._id}')" title="View Details">👁</button>
                `}
            </div>
        </div>
    `).join('');
}

function filterBy(filter) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    const tab = document.querySelector(`[data-filter="${filter}"]`);
    if (tab) tab.classList.add('active');
    currentFilter = filter;
    renderApprovals();
}

function showDetails(id) {
    const approval = approvals.find(a => a._id === id);
    if (!approval) return;
    
    document.getElementById('modalTitle').textContent = `Semester ${approval.semester || approval.type} Registration - ${approval.student_name}`;
    document.getElementById('modalBody').innerHTML = `
        <div class="detail-row">
            <div class="detail-label">Student:</div>
            <div class="detail-value">${approval.student_name} (${approval.student_roll})</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Status:</div>
            <div class="detail-value"><span class="status-badge status-${approval.status}">${approval.status}</span></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Semester:</div>
            <div class="detail-value">${approval.semester || 'N/A'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Submitted:</div>
            <div class="detail-value">${approval.created_at}</div>
        </div>
        ${approval.updated_at ? `
        <div class="detail-row">
            <div class="detail-label">Updated:</div>
            <div class="detail-value">${approval.updated_at}</div>
        </div>` : ''}
        ${approval.mentor_remarks ? `
        <div class="detail-row">
            <div class="detail-label">Remarks:</div>
            <div class="detail-value">${escapeHtml(approval.mentor_remarks)}</div>
        </div>` : ''}
        <div style="margin-top:16px;">
            <div class="detail-label" style="margin-bottom:8px;">Subjects (${approval.subject_count || 0}):</div>
            <div class="subjects-list">
                ${approval.subjects ? approval.subjects.map(s => `
                    <div class="subject-item">
                        <span>${escapeHtml(s.name)} (${escapeHtml(s.code)})</span>
                        <span>Credits: ${s.credits} | Internal: ${s.internal}</span>
                    </div>
                `).join('') : '<div style="color:#888;font-size:13px;">No subject details available</div>'}
            </div>
        </div>
    `;
    document.getElementById('detailModal').classList.add('active');
}

function openActionModal(id, action) {
    const approval = approvals.find(a => a._id === id);
    if (!approval) return;
    
    const isApprove = action === 'approve';
    const title = isApprove ? 'Approve Request' : 'Reject Request';
    const btnClass = isApprove ? 'btn-approve-large' : 'btn-reject-large';
    const btnText = isApprove ? '✓ Approve' : '✗ Reject';
    
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').innerHTML = `
        <div style="background:${isApprove ? '#d4edda' : '#f8d7da'};padding:16px;border-radius:10px;margin-bottom:16px;">
            <strong>${approval.student_name}</strong> (${approval.student_roll})<br>
            <span style="font-size:13px;">Semester ${approval.semester} Registration - ${approval.subject_count || 0} subjects</span>
        </div>
        <div class="remarks-section">
            <label>${isApprove ? 'Approval Remarks (optional)' : 'Rejection Reason (required)'}</label>
            <textarea id="remarksInput" placeholder="${isApprove ? 'Add any comments for the student...' : 'Please provide a reason for rejection...'}" ${!isApprove ? 'required' : ''}></textarea>
        </div>
        <div class="action-buttons">
            <button class="${btnClass}" onclick="processApproval('${id}', '${action}')">${btnText}</button>
            <button style="flex:1;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid #ddd;background:#fff;" onclick="closeModal()">Cancel</button>
        </div>
    `;
    document.getElementById('detailModal').classList.add('active');
}

function processApproval(id, action) {
    const remarks = document.getElementById('remarksInput').value.trim();
    
    if (action === 'reject' && !remarks) {
        alert('Please provide a reason for rejection.');
        return;
    }
    
    const formData = new FormData();
    formData.append('approval_id', id);
    formData.append('status', action);
    formData.append('remarks', remarks);
    formData.append('update_status', '1');
    
    fetch('approvals.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                closeModal();
                loadApprovals();
            } else {
                alert(data.message || 'Failed to process');
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

// Filter tabs
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.dataset.filter;
        renderApprovals();
    });
});

// Close modal on outside click
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close modal on Escape
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