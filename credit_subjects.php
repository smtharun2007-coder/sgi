e<?php
include 'config.php';
requireLogin();

if (empty($_GET['sem_id'])) { header("Location: dashboard.php"); exit; }

$sem_id = $_GET['sem_id'];
$roll   = $_SESSION['user']['roll'];
$u      = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);

$sem = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($sem_id), 'roll' => $roll]);
if (!$sem) { header("Location: dashboard.php"); exit; }

// Get all subjects for this semester
$subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
$subList   = iterator_to_array($subCursor);

// Separate internal and non-internal subjects
$internalSubs = [];
$nonInternalSubs = [];
foreach ($subList as $sub) {
    if ($sub['internal'] === 'yes') {
        $internalSubs[] = $sub;
    } else {
        $nonInternalSubs[] = $sub;
    }
}

// Check for existing pending approval to display pending subjects
$pendingApproval = $approvals->findOne([
    'student_roll' => $roll,
    'semester' => (int)$sem['sem'],
    'type' => 'Credit Subjects',
    'status' => 'pending'
]);
$pendingApprovalAdditions = $pendingApproval['credit_subjects'] ?? [];
$pendingApprovalDeletions = $pendingApproval['credit_deletions'] ?? [];
$pendingApprovalDeletionIds = array_column($pendingApprovalDeletions, 'subject_id');

$success = '';
$error = '';

// Track pending changes in session (not saved to DB until mentor approves)
if (!isset($_SESSION['credit_changes'])) {
    $_SESSION['credit_changes'] = [
        'additions' => [],
        'deletions' => []
    ];
}

// Handle adding new non-internal subject (stored as pending, not saved to DB)
if (isset($_POST['add_subject'])) {
    $name    = trim($_POST['new_subject_name']);
    $code    = trim($_POST['new_subject_code']);
    $credits = (int)$_POST['new_credits'];
    
    if (!empty($name) && !empty($code) && $credits > 0) {
        // Generate a temporary ID for the pending subject
        $tempId = 'pending_' . uniqid();
        
        // Add to pending additions in session
        $_SESSION['credit_changes']['additions'][$tempId] = [
            'temp_id' => $tempId,
            'subject_name' => $name,
            'subject_code' => $code,
            'credits' => $credits
        ];
        
        $success = "Subject '$name' added to pending changes. Will be saved after mentor approval.";
    } else {
        $error = "Please fill all fields correctly.";
    }
}

// Handle removing a pending addition (from session)
if (isset($_GET['remove_pending']) && !empty($_GET['remove_pending'])) {
    $tempId = $_GET['remove_pending'];
    if (isset($_SESSION['credit_changes']['additions'][$tempId])) {
        unset($_SESSION['credit_changes']['additions'][$tempId]);
        $success = "Pending subject removed.";
    }
    header("Location: credit_subjects.php?sem_id=$sem_id&success=1");
    exit;
}

// Handle marking an existing subject for deletion (stored as pending deletion)
if (isset($_GET['mark_delete']) && !empty($_GET['mark_delete'])) {
    $deleteId = $_GET['mark_delete'];
    // Check if this subject exists and is non-internal
    $subject = $subjects->findOne(['_id' => new MongoDB\BSON\ObjectId($deleteId), 'roll' => $roll, 'internal' => 'no']);
    if ($subject) {
        $_SESSION['credit_changes']['deletions'][$deleteId] = [
            'subject_id' => $deleteId,
            'subject_name' => $subject['subject_name'],
            'subject_code' => $subject['subject_code'],
            'credits' => $subject['credits']
        ];
        $success = "Subject marked for deletion. Will be removed after mentor approval.";
    }
    header("Location: credit_subjects.php?sem_id=$sem_id&success=1");
    exit;
}

// Handle removing a pending deletion
if (isset($_GET['undo_delete']) && !empty($_GET['undo_delete'])) {
    $deleteId = $_GET['undo_delete'];
    if (isset($_SESSION['credit_changes']['deletions'][$deleteId])) {
        unset($_SESSION['credit_changes']['deletions'][$deleteId]);
        $success = "Deletion mark removed.";
    }
    header("Location: credit_subjects.php?sem_id=$sem_id&success=1");
    exit;
}

// Handle confirming and creating approval request for credit subjects
if (isset($_POST['confirm_credits'])) {
    $hasAdditions = !empty($_SESSION['credit_changes']['additions']);
    $hasDeletions = !empty($_SESSION['credit_changes']['deletions']);
    
    // If no changes pending, just mark as done and proceed
    if (!$hasAdditions && !$hasDeletions) {
        // Check if there are existing non-internal subjects
        if (empty($nonInternalSubs)) {
            $semesters->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($sem_id)],
                ['$set' => ['credits_done' => true]]
            );
            header("Location: verify_marks.php?sem_id=$sem_id");
            exit;
        } else {
            $error = "No pending changes to submit. Add or mark subjects for deletion first, or proceed if existing subjects are correct.";
        }
    } else {
        // Check if there's already a pending approval for this
        $existingApproval = $approvals->findOne([
            'student_roll' => $roll,
            'semester' => (int)$sem['sem'],
            'type' => 'Credit Subjects',
            'status' => 'pending'
        ]);
        
        if ($existingApproval) {
            $error = "You already have a pending Credit Subjects approval for this semester.";
        } else {
            // Prepare data for approval
            $additionsData = array_values($_SESSION['credit_changes']['additions']);
            $deletionsData = array_values($_SESSION['credit_changes']['deletions']);
            
            // Prepare existing internal subjects for display
            $existingSubjectsData = [];
            foreach ($internalSubs as $sub) {
                $existingSubjectsData[] = [
                    'name' => $sub['subject_name'],
                    'code' => $sub['subject_code'],
                    'credits' => (int)$sub['credits'],
                    'internal' => $sub['internal']
                ];
            }
            
            // Prepare existing non-internal subjects (showing which are marked for deletion)
            $existingNonInternalData = [];
            foreach ($nonInternalSubs as $sub) {
                $isMarkedForDeletion = isset($_SESSION['credit_changes']['deletions'][(string)$sub['_id']]);
                $existingNonInternalData[] = [
                    'subject_id' => (string)$sub['_id'],
                    'name' => $sub['subject_name'],
                    'code' => $sub['subject_code'],
                    'credits' => (int)$sub['credits'],
                    'marked_for_deletion' => $isMarkedForDeletion
                ];
            }
            
            // Create approval request
            $approvalData = [
                'student_roll' => $roll,
                'student_name' => $u['name'],
                'type' => 'Credit Subjects',
                'semester' => (int)$sem['sem'],
                'reg' => $u['reg'],
                'mentor_id' => $u['mentor_id'] ?? '',
                'message' => 'Request to confirm credit subject changes for Semester ' . $sem['sem'] . 
                            ' (' . count($additionsData) . ' additions, ' . count($deletionsData) . ' deletions)',
                'status' => 'pending',
                'credit_subjects' => $additionsData,  // New subjects to add
                'credit_deletions' => $deletionsData,  // Subjects to delete
                'existing_subjects' => $existingSubjectsData,  // Internal subjects (unchanged)
                'existing_non_internal' => $existingNonInternalData,  // Existing non-internal with deletion status
                'subject_count' => count($additionsData),
                'deletion_count' => count($deletionsData),
                'sem_id' => $sem_id,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $approvals->insertOne($approvalData);
            
            // Create notification for mentor
            if (!empty($u['mentor_id'])) {
                $notifications->insertOne([
                    'mentor_id' => $u['mentor_id'],
                    'message' => '📝 Credit Subjects changes approval request from ' . $u['name'] . ' (' . $roll . ') - Semester ' . $sem['sem'],
                    'read' => false,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ]);
            }
            
            // Create notification for student
            $notifications->insertOne([
                'roll' => $roll,
                'message' => '✅ Your Credit Subjects changes request for Semester ' . $sem['sem'] . ' has been submitted and is pending mentor approval.',
                'read' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            // Clear the session changes since they're now in the approval
            unset($_SESSION['credit_changes']);
            $_SESSION['credit_changes'] = ['additions' => [], 'deletions' => []];
            
            $success = 'Your Credit Subjects changes request has been submitted for approval. You will be notified once your mentor reviews it.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Credit Subjects</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
<a href="dashboard.php" class="nav-brand">
    <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo">
    SGI
</a>
    <div class="nav-links">
        <a href="semester_detail.php?id=<?= $sem_id ?>">&#8592; Back</a>
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
<div class="form-box">
    <h2>Credit Subjects – Semester <?= $sem['sem'] ?></h2>
    <p style="color:#888;font-size:14px;margin-bottom:20px;">
        Add or mark subjects for deletion. Changes will be submitted for mentor approval before being saved.
    </p>
    <hr style="margin:16px 0;">
    <?php if (isset($_GET['success'])): ?><p class="success">Change applied to pending list.</p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>
    
    <h3>Add New Credit Subject (Pending)</h3>
    <p style="color:#888;font-size:14px;margin-bottom:15px;">Add subjects that don't have CAT marks (e.g., Environmental Science, Audit Courses, etc.)</p>
    <form method="POST" style="margin-bottom:20px;">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:2;min-width:150px;">
                <label>Subject Name</label>
                <input type="text" name="new_subject_name" placeholder="e.g., Environmental Science" style="width:100%;" required>
            </div>
            <div style="flex:1;min-width:100px;">
                <label>Subject Code</label>
                <input type="text" name="new_subject_code" placeholder="e.g., ECS101" style="width:100%;" required>
            </div>
            <div style="flex:1;min-width:80px;">
                <label>Credits</label>
                <input type="number" name="new_credits" min="1" max="6" value="2" style="width:100%;" required>
            </div>
            <div>
                <button type="submit" name="add_subject" class="btn-calc">+ Add (Pending)</button>
            </div>
        </div>
    </form>
    
    <?php if (!empty($_SESSION['credit_changes']['additions'])): ?>
    <h3>Pending Additions (Will be added after approval)</h3>
    <div class="cat-table-wrap">
        <table class="cat-table">
            <thead>
                <tr>
                    <th>Subject Name</th>
                    <th>Code</th>
                    <th>Credits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['credit_changes']['additions'] as $tempId => $sub): ?>
                <tr>
                    <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                    <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                    <td><?= $sub['credits'] ?></td>
                    <td style="text-align:center;">
                        <a href="credit_subjects.php?sem_id=<?= $sem_id ?>&remove_pending=<?= $tempId ?>" 
                           onclick="return confirm('Remove this pending subject?')" 
                           class="btn-remove" style="display:inline-block;text-decoration:none;padding:4px 10px;font-size:12px;">Remove</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <hr style="margin:24px 0;">
    
    <h3>Existing Non-Internal Subjects</h3>
    <p style="color:#888;font-size:14px;margin-bottom:15px;">Click "Mark for Deletion" on subjects you want to remove (requires mentor approval).</p>
    <?php if (!empty($nonInternalSubs)): ?>
    <div class="cat-table-wrap">
        <table class="cat-table">
            <thead>
                <tr>
                    <th>Subject Name</th>
                    <th>Code</th>
                    <th>Credits</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nonInternalSubs as $sub): $sid = (string)$sub['_id']; 
                    $isMarkedForDeletion = isset($_SESSION['credit_changes']['deletions'][$sid]);
                ?>
                <tr style="<?= $isMarkedForDeletion ? 'background:#fff3f3;text-decoration:line-through;' : '' ?>">
                    <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                    <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                    <td><?= $sub['credits'] ?></td>
                    <td>
                        <?php if ($isMarkedForDeletion): ?>
                            <span style="color:#dc3545;font-weight:600;font-size:12px;">⚠ Marked for Deletion</span>
                        <?php else: ?>
                            <span style="color:#28a745;font-size:12px;">✓ Active</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($isMarkedForDeletion): ?>
                            <a href="credit_subjects.php?sem_id=<?= $sem_id ?>&undo_delete=<?= $sid ?>" 
                               onclick="return confirm('Undo deletion mark?')" 
                               class="btn-remove" style="display:inline-block;text-decoration:none;padding:4px 10px;font-size:12px;background:#ffc107;color:#000;">Undo</a>
                        <?php else: ?>
                            <a href="credit_subjects.php?sem_id=<?= $sem_id ?>&mark_delete=<?= $sid ?>" 
                               onclick="return confirm('Mark this subject for deletion? It will be removed after mentor approval.')" 
                               class="btn-remove" style="display:inline-block;text-decoration:none;padding:4px 10px;font-size:12px;background:#dc3545;">Mark Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p style="color:#888;text-align:center;padding:20px;">No existing non-internal subjects.</p>
    <?php endif; ?>
    
    <?php if (!empty($_SESSION['credit_changes']['deletions'])): ?>
    <div style="background:#fff3f3;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #dc3545;">
        <strong style="color:#dc3545;">⚠ Pending Deletions:</strong> 
        <?= count($_SESSION['credit_changes']['deletions']) ?> subject(s) marked for deletion
    </div>
    <?php endif; ?>
    
    <!-- Display pending approval subjects (from submitted approval request) -->
    <?php if (!empty($pendingApproval)): ?>
    <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:16px;border-radius:8px;margin:20px 0;">
        <h3 style="margin:0 0 12px 0;color:#856404;">⏳ Pending Approval - Changes Submitted</h3>
        <p style="margin:0 0 12px 0;color:#856404;font-size:13px;">Your credit subject changes are awaiting mentor approval.</p>
        
        <?php if (!empty($pendingApprovalAdditions)): ?>
        <h4 style="color:#28a745;margin:12px 0 8px 0;font-size:14px;">➕ Subjects to be Added (<?= count($pendingApprovalAdditions) ?>)</h4>
        <table class="cat-table" style="margin-bottom:12px;">
            <thead><tr><th>Subject Name</th><th>Code</th><th>Credits</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($pendingApprovalAdditions as $sub): 
                    $name = $sub['subject_name'] ?? $sub['name'] ?? '';
                    $code = $sub['subject_code'] ?? $sub['code'] ?? '';
                    $credits = $sub['credits'] ?? 0;
                ?>
                <tr style="background:#f3fff3;">
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= htmlspecialchars($code) ?></td>
                    <td><?= $credits ?></td>
                    <td><span style="color:#ffc107;font-weight:600;font-size:12px;">⏳ Pending</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php if (!empty($pendingApprovalDeletions)): ?>
        <h4 style="color:#dc3545;margin:12px 0 8px 0;font-size:14px;">➖ Subjects to be Removed (<?= count($pendingApprovalDeletions) ?>)</h4>
        <table class="cat-table">
            <thead><tr><th>Subject Name</th><th>Code</th><th>Credits</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($pendingApprovalDeletions as $sub): 
                    $name = $sub['subject_name'] ?? $sub['name'] ?? '';
                    $code = $sub['subject_code'] ?? $sub['code'] ?? '';
                    $credits = $sub['credits'] ?? 0;
                ?>
                <tr style="background:#fff3f3;">
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= htmlspecialchars($code) ?></td>
                    <td><?= $credits ?></td>
                    <td><span style="color:#ffc107;font-weight:600;font-size:12px;">⏳ Pending</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <hr style="margin:24px 0;">
    
    <div style="display:flex;gap:15px;justify-content:center;flex-wrap:wrap;">
        <form method="POST" style="display:inline;">
            <button type="submit" name="confirm_credits" class="btn-primary" 
                    <?= (empty($_SESSION['credit_changes']['additions']) && empty($_SESSION['credit_changes']['deletions'])) ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                Submit Changes for Approval
            </button>
        </form>
        <a href="semester_detail.php?id=<?= $sem_id ?>" class="btn-secondary">Back to Semester</a>
    </div>
</div>
</div>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
<script>
function toggleNotif(){const d=document.getElementById('notifDrop');d.classList.toggle('open');if(d.classList.contains('open'))loadNotifs();}
function loadNotifs(){fetch('notifications.php?fetch=1').then(r=>r.json()).then(data=>{const l=document.getElementById('notifList');if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');});}
function markAll(e){e.preventDefault();fetch('notifications.php?mark_all=1');document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));const b=document.querySelector('.notif-badge');if(b)b.remove();}
function clearAll(e){e.preventDefault();fetch('notifications.php?delete_all=1');document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';const b=document.querySelector('.notif-badge');if(b)b.remove();}
document.addEventListener('click',e=>{const btn=document.getElementById('bellBtn');const d=document.getElementById('notifDrop');if(btn&&d&&!btn.contains(e.target)&&!d.contains(e.target))d.classList.remove('open');});
</script>
</body>
</html>