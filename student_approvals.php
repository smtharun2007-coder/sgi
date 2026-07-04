<?php
include 'config.php';
requireLogin();

$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);

// Get current semester (latest semester without SGI)
$currentSemCursor = $semesters->find(
    ['roll' => $u['roll']],
    ['sort' => ['sem' => -1]]
);
$semList = iterator_to_array($currentSemCursor);
$currentSem = null;
foreach ($semList as $s) {
    if (empty($s['sgi'])) {
        $currentSem = $s;
        break;
    }
}
$currentSemNum = $currentSem['sem'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – My Approval Requests</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .approval-hero {
            background: linear-gradient(135deg, #1a1a2e, #e94560);
            padding: 40px 24px;
            border-radius: 20px;
            margin-top: 24px;
            text-align: center;
            color: #fff;
        }
        .approval-hero h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        .approval-hero p {
            margin: 0;
            opacity: 0.9;
            font-size: 15px;
        }

        .stats-row {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }
        .stat-card {
            flex: 1;
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s ease;
            border-top: 4px solid transparent;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .stat-card.pending { border-top-color: #ffc107; }
        .stat-card.approved { border-top-color: #28a745; }
        .stat-card.rejected { border-top-color: #dc3545; }
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .stat-card .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }

        .filters-bar {
            background: #fff;
            border-radius: 16px;
            padding: 20px 24px;
            margin-top: 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .filter-group select {
            padding: 8px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 13px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-group select:focus {
            outline: none;
            border-color: #e94560;
            background: #fff;
        }

        .status-tabs {
            display: flex;
            gap: 6px;
        }
        .status-tab {
            padding: 8px 18px;
            border: none;
            background: #f0f2f5;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
        }
        .status-tab.active {
            background: linear-gradient(135deg, #1a1a2e, #e94560);
            color: #fff;
            box-shadow: 0 4px 12px rgba(233,69,96,0.3);
        }
        .status-tab:hover:not(.active) {
            background: #e0e0e0;
        }

        .approval-list {
            margin-top: 20px;
        }
        .approval-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 16px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 4px solid transparent;
        }
        .approval-card:hover {
            transform: translateX(4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        .approval-card.pending { border-left-color: #ffc107; }
        .approval-card.approved { border-left-color: #28a745; }
        .approval-card.rejected { border-left-color: #dc3545; }

        .approval-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .approval-card.pending .approval-icon { background: #fff3cd; }
        .approval-card.approved .approval-icon { background: #d4edda; }
        .approval-card.rejected .approval-icon { background: #f8d7da; }

        .approval-content { flex: 1; }
        .approval-type {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        .approval-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: #888;
        }
        .approval-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .approval-status {
            padding: 6px 16px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        .approval-card.pending .approval-status {
            background: #fff3cd;
            color: #856404;
        }
        .approval-card.approved .approval-status {
            background: #d4edda;
            color: #155724;
        }
        .approval-card.rejected .approval-status {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-cancel {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .btn-cancel:hover { background: #c82333; }

        .empty-state {
            text-align: center;
            padding: 60px 24px;
            color: #888;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
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
        .modal-content {
            background: #fff;
            border-radius: 20px;
            max-width: 600px;
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
            background: linear-gradient(135deg, #1a1a2e, #e94560);
            padding: 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 20px 20px 0 0;
        }
        .modal-header h3 { margin: 0; font-size: 18px; }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
        }
        .modal-body { padding: 24px; }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        .detail-item {
            background: #f8f9fa;
            padding: 14px 18px;
            border-radius: 12px;
        }
        .detail-item label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            display: block;
            margin-bottom: 4px;
        }
        .detail-item span {
            font-size: 15px;
            color: #333;
            font-weight: 600;
        }

        .remarks-box {
            background: #fff3f3;
            border-radius: 12px;
            padding: 16px 20px;
            margin-top: 16px;
            border-left: 4px solid #dc3545;
        }
        .remarks-box.approved {
            background: #f3fff3;
            border-left-color: #28a745;
        }
        .remarks-box label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            display: block;
            margin-bottom: 6px;
        }
        .remarks-box p {
            margin: 0;
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }

        .subjects-list {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }
        .subject-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .subject-item:last-child { border-bottom: none; }
        .subject-item span:first-child {
            font-weight: 600;
            color: #1a1a2e;
        }
        .subject-item span:last-child {
            color: #888;
            font-size: 13px;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI
    </a>
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="update_profile.php">Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
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
    <!-- Hero Section -->
    <div class="approval-hero">
        <h1>📋 My Approval Requests</h1>
        <p>Track and manage your semester registration, credit subjects, verification, CA marks, and SGI calculation requests</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card pending" onclick="setStatusFilter('pending')">
            <div class="stat-number" id="pendingCount">0</div>
            <div class="stat-label">⏳ Pending</div>
        </div>
        <div class="stat-card approved" onclick="setStatusFilter('approved')">
            <div class="stat-number" id="approvedCount">0</div>
            <div class="stat-label">✅ Approved</div>
        </div>
        <div class="stat-card rejected" onclick="setStatusFilter('rejected')">
            <div class="stat-number" id="rejectedCount">0</div>
            <div class="stat-label">❌ Rejected</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <div class="filter-group">
            <label for="semesterFilter">Semester</label>
            <select id="semesterFilter" onchange="setSemesterFilter(this.value)">
                <option value="all" selected>All semesters</option>
                <?php foreach ($semList as $s): ?>
                    <?php $semNum = $s['sem']; ?>
                    <?php $label = 'Sem ' . $semNum; ?>
                    <option value="<?= htmlspecialchars((string)$semNum) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="status-tabs" role="tablist" aria-label="Approval Status">
            <button type="button" class="status-tab active" data-status="all" onclick="setStatusFilter('all')">All</button>
            <button type="button" class="status-tab" data-status="pending" onclick="setStatusFilter('pending')">Pending</button>
            <button type="button" class="status-tab" data-status="approved" onclick="setStatusFilter('approved')">Approved</button>
            <button type="button" class="status-tab" data-status="rejected" onclick="setStatusFilter('rejected')">Rejected</button>
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
const currentSemester = <?= $currentSemNum ? $currentSemNum : 'null' ?>;
let currentStatusFilter = 'all';
let currentSemesterFilter = 'all';
let approvals = [];

function setSemesterFilter(semesterValue) {
    currentSemesterFilter = semesterValue;
    renderApprovals();
}

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
        const normalizedStatus = (a.status === 'pending_evaluator') ? 'pending' : a.status;
        if (normalizedStatus === 'pending') pending++;
        else if (normalizedStatus === 'approved') approved++;
        else if (normalizedStatus === 'rejected') rejected++;
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

function getFilteredApprovals() {
    return approvals.filter(a => {
        const normalizedStatus = (a.status === 'pending_evaluator') ? 'pending' : a.status;

        const normalizedSemester = (a.semester === null || a.semester === undefined) ? null : String(a.semester);
        const semesterOk = currentSemesterFilter === 'all' || normalizedSemester === String(currentSemesterFilter);

        return (currentStatusFilter === 'all' || normalizedStatus === currentStatusFilter) && semesterOk;
    });
}



function renderApprovals() {
    const list = document.getElementById('approvalList');
    const filtered = getFilteredApprovals();

    if (filtered.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>No ${currentStatusFilter === 'all' ? '' : currentStatusFilter} approval requests found${currentSemesterFilter === 'current' ? ' for current semester' : ''}.</p>
            </div>
        `;
        return;
    }

    list.innerHTML = filtered.map(a => {
        const typeIcons = {
            'Semester Registration': '📚',
            'Credit Subjects': '➕',
            'Verification': '✅',
            'Final CA Marks': '📊',
            'SGI Calculation': '📈'
        };
        const icon = typeIcons[a.type] || '📋';

        return `
        <div class="approval-card ${a.status}" onclick="showDetails('${a._id}')">
            <div class="approval-icon">${icon}</div>
            <div class="approval-content">
                <div class="approval-type">${a.type || 'Semester Registration'}</div>
                <div class="approval-meta">
                    <span>📅 ${a.created_at}</span>
                    <span>📚 Sem ${a.semester || '?'}</span>
                    ${a.subject_count ? `<span>📝 ${a.subject_count} subjects</span>` : ''}
                </div>
            </div>
            <span class="approval-status">${a.status}</span>
${(a.status === 'pending' || a.status === 'pending_evaluator') ? `
                <button class="btn-cancel" onclick="event.stopPropagation();deleteApproval('${a._id}')">Delete</button>
            ` : ''}
        </div>
    `}).join('');
}

function showDetails(id) {
    const approval = approvals.find(a => a._id === id);
    if (!approval) return;

    const statusIcon = approval.status === 'approved' ? '✅' : approval.status === 'rejected' ? '❌' : '⏳';

    document.getElementById('modalTitle').textContent = `${statusIcon} ${approval.type} - Semester ${approval.semester}`;
    document.getElementById('modalBody').innerHTML = `
        <div class="detail-grid">
            <div class="detail-item">
                <label>Status</label>
                <span><span class="badge badge-${approval.status}" style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase;background:${approval.status === 'pending' ? '#fff3cd' : approval.status === 'approved' ? '#d4edda' : '#f8d7da'};color:${approval.status === 'pending' ? '#856404' : approval.status === 'approved' ? '#155724' : '#721c24'};">${approval.status}</span></span>
            </div>
            <div class="detail-item">
                <label>Semester</label>
                <span>Semester ${approval.semester}</span>
            </div>
            <div class="detail-item">
                <label>Submitted On</label>
                <span>${approval.created_at}</span>
            </div>
            <div class="detail-item">
                <label>${approval.updated_at ? 'Processed On' : 'Last Updated'}</label>
                <span>${approval.updated_at || '—'}</span>
            </div>
        </div>

        ${approval.mentor_remarks ? `
        <div class="remarks-box ${approval.status === 'approved' ? 'approved' : ''}">
            <label>${approval.status === 'approved' ? '✅ Approval Remarks' : '❌ Rejection Reason'}</label>
            <p>${escapeHtml(approval.mentor_remarks)}</p>
        </div>` : ''}

        <div class="subjects-list">
            <div style="font-size:14px;font-weight:600;color:#1a1a2e;margin-bottom:12px;">📋 Request Details</div>
            <div class="subject-item">
                <span>Type</span>
                <span>${approval.type}</span>
            </div>
            <div class="subject-item">
                <span>Student</span>
                <span>${approval.student_name} (${approval.student_roll})</span>
            </div>
            ${approval.subject_count ? `
            <div class="subject-item">
                <span>Subjects</span>
                <span>${approval.subject_count} subjects</span>
            </div>` : ''}
            <div class="subject-item">
                <span>Message</span>
                <span>${approval.message || '—'}</span>
            </div>
        </div>
    `;
    document.getElementById('detailModal').classList.add('active');
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
}

function deleteApproval(id) {
    // Keep UI messaging consistent with other pages by using our local showToast/customConfirm.
    customConfirm(
        'Are you sure you want to cancel this pending request?',
        function () {
            const formData = new FormData();
            formData.append('approval_id', id);
            formData.append('delete', '1');

            fetch('approvals.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Approval request deleted', 'success');
                        loadApprovals();
                    } else {
                        showToast(data.message || 'Failed to delete', 'error');
                    }
                });
        },
        function () {
            // cancelled
        }
    );
}



function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Filter tabs
document.querySelectorAll('.status-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        // dataset.filter is not used for these buttons (we call setStatusFilter directly)
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