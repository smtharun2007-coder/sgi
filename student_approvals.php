<?php
include 'config.php';
requireLogin();

$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – Semester Approval Requests</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .approval-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            margin-top: 24px;
        }
        .approval-header {
            background: linear-gradient(135deg, #1a1a2e, #e94560);
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
            cursor: pointer;
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
        .approval-subject {
            font-size: 13px;
            color: #555;
            margin-top: 6px;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
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
        .mentor-remarks {
            background: #fff3f3;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 8px;
            font-size: 12px;
            color: #555;
            border-left: 3px solid #dc3545;
            display: none;
        }
        .mentor-remarks strong {
            color: #dc3545;
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .btn-delete {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
            margin-left: 8px;
        }
        .btn-delete:hover { background: #c82333; }
        
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
        }
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
            background: linear-gradient(135deg, #1a1a2e, #e94560);
            color: #fff;
        }
        .filter-tab:hover:not(.active) {
            background: #e0e0e0;
        }
        .semester-badge {
            background: linear-gradient(135deg, #1a1a2e, #e94560);
            color: #fff;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 6px;
        }
        .subjects-preview {
            font-size: 11px;
            color: #888;
            margin-top: 4px;
            font-style: italic;
        }
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
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: linear-gradient(135deg, #1a1a2e, #e94560);
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
    </style>
</head>
<body>
<nav class="navbar">
<a href="dashboard.php" class="nav-brand">
    <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI
</a>
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="student_approvals.php" style="color:#e94560;font-weight:600;">Approvals</a>
        <a href="update_profile.php">Profile</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                &#128276;<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">
    <!-- Stats -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card pending">
            <div class="stat-number" id="pendingCount">0</div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-number" id="approvedCount">0</div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-number" id="rejectedCount">0</div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Approval List -->
    <div class="approval-card">
        <div class="approval-header">
            <span>📋 Semester Registration Requests</span>
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
                <div class="empty-icon">📝</div>
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
        <div class="approval-row" onclick="showDetails('${a._id}')">
            <div class="approval-info">
                <div class="approval-type">
                    📚 Semester ${a.semester || a.type}
                    <span class="semester-badge">Sem ${a.semester || '?'}</span>
                </div>
                <div class="approval-meta">
                    <span>📅 ${a.created_at}</span>
                    <span>👤 ${a.student_name}</span>
                </div>
                <div class="subjects-preview">${a.subject_count || 0} subjects registered</div>
                ${a.status === 'rejected' && a.mentor_remarks ? 
                    `<div class="mentor-remarks" style="display:block">
                        <strong>⚠️ Rejection Reason:</strong>
                        ${escapeHtml(a.mentor_remarks)}
                    </div>` : ''}
            </div>
            <div style="display:flex;align-items:center;">
                <span class="status-badge status-${a.status}">${a.status}</span>
                ${a.status === 'pending' ? 
                    `<button class="btn-delete" onclick="event.stopPropagation();deleteApproval('${a._id}')">Cancel</button>` 
                    : ''}
            </div>
        </div>
    `).join('');
}

function showDetails(id) {
    const approval = approvals.find(a => a._id === id);
    if (!approval) return;
    
    document.getElementById('modalTitle').textContent = `Semester ${approval.semester || approval.type} Registration`;
    document.getElementById('modalBody').innerHTML = `
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
            <div class="detail-label">Mentor Remarks:</div>
            <div class="detail-value">${escapeHtml(approval.mentor_remarks)}</div>
        </div>` : ''}
        <div style="margin-top:16px;">
            <div class="detail-label" style="margin-bottom:8px;">Subjects:</div>
            <div class="subjects-list">
                ${approval.subjects ? approval.subjects.map(s => `
                    <div class="subject-item">
                        <span>${escapeHtml(s.name)} (${escapeHtml(s.code)})</span>
                        <span>Credits: ${s.credits}</span>
                    </div>
                `).join('') : '<div style="color:#888;font-size:13px;">No subject details available</div>'}
            </div>
        </div>
    `;
    document.getElementById('detailModal').classList.add('active');
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
}

function deleteApproval(id) {
    if (!confirm('Are you sure you want to cancel this pending request?')) return;
    
    const formData = new FormData();
    formData.append('approval_id', id);
    formData.append('delete', '1');
    
    fetch('approvals.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                loadApprovals();
            } else {
                alert(data.message || 'Failed to delete');
            }
        });
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
    fetch('notifications.php?fetch=1')
        .then(r=>r.json()).then(data=>{
            const list = document.getElementById('notifList');
            if (!data.length) { list.innerHTML='<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');
        });
}
function markAll(e) {
    e.preventDefault();
    fetch('notifications.php?mark_all=1');
    document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
function clearAll(e) {
    e.preventDefault();
    fetch('notifications.php?delete_all=1');
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