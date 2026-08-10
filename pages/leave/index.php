<?php
$page_title = 'Leave Management';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
$businessId = $_SESSION['active_business_id'] ?? 0;
$membershipId = $_SESSION['membership_id'] ?? 0;
$canViewOwnLeave = hasPermission($conn, $membershipId, $businessId, $permissions['view_self']);
$canViewTeamLeave = hasPermission($conn, $membershipId, $businessId, $permissions['view_team']);
if (!$canViewOwnLeave && !$canViewTeamLeave) {
    requirePermission($conn, $membershipId, $businessId, $permissions['view_self']);
}
$canSubmitLeave = hasPermission($conn, $membershipId, $businessId, $permissions['submit']);
$canApproveLeave = hasPermission($conn, $membershipId, $businessId, $permissions['approve']);

// Team access controls the management view; approval remains a separate action.
$is_owner = $canViewTeamLeave;

// Ensure leave types exist, otherwise seed defaults
$checkTypes = "SELECT COUNT(*) as cnt FROM leave_types WHERE business_id = ?";
$ctStmt = mysqli_prepare($conn, $checkTypes);
mysqli_stmt_bind_param($ctStmt, 'i', $businessId);
mysqli_stmt_execute($ctStmt);
$typesCnt = mysqli_fetch_assoc(mysqli_stmt_get_result($ctStmt))['cnt'] ?? 0;

if ($typesCnt == 0 && !isRolePreviewActive() && $businessId > 0) {
    // Seed defaults
    $seed1 = "INSERT INTO leave_types (business_id, code, name, default_days_per_year, is_paid, requires_approval, is_active, created_at) VALUES (?, 'ANNUAL', 'Annual Leave', 21.00, 1, 1, 1, NOW(6))";
    $s1 = mysqli_prepare($conn, $seed1);
    mysqli_stmt_bind_param($s1, 'i', $businessId);
    mysqli_stmt_execute($s1);

    $seed2 = "INSERT INTO leave_types (business_id, code, name, default_days_per_year, is_paid, requires_approval, is_active, created_at) VALUES (?, 'SICK', 'Sick Leave', 14.00, 1, 1, 1, NOW(6))";
    $s2 = mysqli_prepare($conn, $seed2);
    mysqli_stmt_bind_param($s2, 'i', $businessId);
    mysqli_stmt_execute($s2);

    $seed3 = "INSERT INTO leave_types (business_id, code, name, default_days_per_year, is_paid, requires_approval, is_active, created_at) VALUES (?, 'MATERNITY', 'Maternity Leave', 90.00, 1, 1, 1, NOW(6))";
    $s3 = mysqli_prepare($conn, $seed3);
    mysqli_stmt_bind_param($s3, 'i', $businessId);
    mysqli_stmt_execute($s3);
}

// Fetch active leave types for dropdown
$ltQuery = "SELECT id, name FROM leave_types WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$ltStmt = mysqli_prepare($conn, $ltQuery);
mysqli_stmt_bind_param($ltStmt, 'i', $businessId);
mysqli_stmt_execute($ltStmt);
$leave_types_list = mysqli_stmt_get_result($ltStmt);

$csrfToken = generateCsrfToken();
$role_query = getRolePreviewQuery();
?>

<?php if ($is_owner): ?>
  <!-- ==========================================
       OWNER VIEW: ALL REQUESTS IN ENTERPRISE
       ========================================== -->
  <?php
  // Fetch pending leave requests
  $pendingQ = "
      SELECT lr.*, u.first_name, u.last_name, u.email, lt.name as leave_type_name
      FROM leave_requests lr
      JOIN business_memberships m ON lr.membership_id = m.id
      JOIN users u ON m.user_id = u.id
      JOIN leave_types lt ON lr.leave_type_id = lt.id
      WHERE lr.business_id = ? AND lr.status = 'PENDING'
      ORDER BY lr.submitted_at ASC
  ";
  $stmt = mysqli_prepare($conn, $pendingQ);
  mysqli_stmt_bind_param($stmt, 'i', $businessId);
  mysqli_stmt_execute($stmt);
  $pendingResult = mysqli_stmt_get_result($stmt);

  // Fetch decided leave requests
  $decidedQ = "
      SELECT lr.*, u.first_name, u.last_name, u.email, lt.name as leave_type_name,
             ap.first_name as approver_first, ap.last_name as approver_last
      FROM leave_requests lr
      JOIN business_memberships m ON lr.membership_id = m.id
      JOIN users u ON m.user_id = u.id
      JOIN leave_types lt ON lr.leave_type_id = lt.id
      LEFT JOIN business_memberships bm_ap ON lr.current_approver_membership_id = bm_ap.id
      LEFT JOIN users ap ON bm_ap.user_id = ap.id
      WHERE lr.business_id = ? AND lr.status != 'PENDING'
      ORDER BY lr.decided_at DESC
      LIMIT 10
  ";
  $stmt2 = mysqli_prepare($conn, $decidedQ);
  mysqli_stmt_bind_param($stmt2, 'i', $businessId);
  mysqli_stmt_execute($stmt2);
  $decidedResult = mysqli_stmt_get_result($stmt2);
  ?>

  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header"><div class="card-title">Pending Leave Approvals Queue</div></div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Employee Name</th>
          <th>Leave Type</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Days</th>
          <th>Reason Notes</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($pendingResult) === 0): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--text3); padding: 20px;">No leave requests in queue.</td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($pendingResult)): ?>
            <tr>
              <td class="td-name">
                <div style="font-weight:600;"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></div>
                <div style="font-size:10px; color:var(--text3);"><?php echo e($row['email']); ?></div>
              </td>
              <td><span class="status-pill pill-blue" style="font-size:10.5px; font-weight:600;"><?php echo e($row['leave_type_name']); ?></span></td>
              <td><?php echo e($row['start_date']); ?></td>
              <td><?php echo e($row['end_date']); ?></td>
              <td class="td-bold"><?php echo (float)$row['requested_days']; ?></td>
              <td style="font-size:11.5px; color:var(--text3);"><?php echo e($row['reason'] ?? 'N/A'); ?></td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 4px;">
                  <?php if ($canApproveLeave): ?>
                  <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Approve this leave request?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="leave_id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn-sm" style="background:var(--green); color:#fff; border:none;">Approve</button>
                  </form>
                  <button class="btn-action reject" style="font-size:11px; padding:4px 8px;" onclick="triggerReject(<?php echo (int)$row['id']; ?>)">Reject</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Recent Leave Decisions Log</div></div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Employee Name</th>
          <th>Leave Type</th>
          <th>Dates</th>
          <th>Days</th>
          <th>Status</th>
          <th>Decision By</th>
          <th>Decision Note</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($decidedResult) === 0): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--text3); padding: 20px;">No leave logs recorded.</td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($decidedResult)): ?>
            <tr>
              <td class="td-name"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></td>
              <td><span class="status-pill pill-blue" style="font-size:10px;"><?php echo e($row['leave_type_name']); ?></span></td>
              <td><?php echo e($row['start_date'] . ' to ' . $row['end_date']); ?></td>
              <td><?php echo (float)$row['requested_days']; ?></td>
              <td>
                <?php if ($row['status'] === 'APPROVED'): ?>
                  <span class="status-pill pill-green">Approved</span>
                <?php elseif ($row['status'] === 'REJECTED'): ?>
                  <span class="status-pill pill-red">Rejected</span>
                <?php else: ?>
                  <span class="status-pill pill-red" style="opacity: 0.6;">Cancelled</span>
                <?php endif; ?>
              </td>
              <td><?php echo e($row['approver_first'] ? ($row['approver_first'] . ' ' . $row['approver_last']) : 'System'); ?></td>
              <td style="font-size:11.5px; color:var(--text3);"><?php echo e($row['decision_note'] ?? 'N/A'); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>
  <!-- ==========================================
       EMPLOYEE VIEW: MY REQUESTS & SUBMIT FORM
       ========================================== -->
  <?php
  // Fetch my leaves
  $myLeavesQ = "
      SELECT lr.*, lt.name as leave_type_name
      FROM leave_requests lr
      JOIN leave_types lt ON lr.leave_type_id = lt.id
      WHERE lr.business_id = ? AND lr.membership_id = ?
      ORDER BY lr.submitted_at DESC
  ";
  $stmt = mysqli_prepare($conn, $myLeavesQ);
  mysqli_stmt_bind_param($stmt, 'ii', $businessId, $membershipId);
  mysqli_stmt_execute($stmt);
  $myLeavesResult = mysqli_stmt_get_result($stmt);
  ?>
<div>
  <!-- Left: My logs -->
  <div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <div class="card-title">My Leave History Requests</div>
      <?php if ($canSubmitLeave): ?>
        <button class="btn-primary" onclick="openAddModal()">+ Request Time Off</button>
      <?php endif; ?>
    </div>
    
      <table class="data-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Days</th>
            <th>Status</th>
            <th>Decision Note</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($myLeavesResult) === 0): ?>
            <tr>
              <td colspan="7" style="text-align: center; color: var(--text3); padding: 30px;">You haven't requested any leaves yet.</td>
            </tr>
          <?php else: ?>
            <?php while ($row = mysqli_fetch_assoc($myLeavesResult)): ?>
              <tr>
                <td class="td-name"><?php echo e($row['leave_type_name']); ?></td>
                <td><?php echo e($row['start_date']); ?></td>
                <td><?php echo e($row['end_date']); ?></td>
                <td class="td-bold"><?php echo (float)$row['requested_days']; ?></td>
                <td>
                  <?php if ($row['status'] === 'APPROVED'): ?>
                    <span class="status-pill pill-green">Approved</span>
                  <?php elseif ($row['status'] === 'PENDING'): ?>
                    <span class="status-pill pill-amber">Pending</span>
                  <?php elseif ($row['status'] === 'REJECTED'): ?>
                    <span class="status-pill pill-red">Rejected</span>
                  <?php else: ?>
                    <span class="status-pill pill-red" style="opacity: 0.6;">Cancelled</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:11.5px; color:var(--text3);"><?php echo e($row['decision_note'] ?? 'N/A'); ?></td>
                <td style="text-align: right;">
                  <?php if ($canSubmitLeave && $row['status'] === 'PENDING'): ?>
                    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Cancel this leave request?');">
                      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="leave_id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn-action reject" style="font-size:10px; padding:3px 6px;">Cancel</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
  </div>
</div>

<!-- ==========================================
     MODAL: APPLY FOR TIME OFF
     ========================================== -->
<?php if ($canSubmitLeave): ?>
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Apply for Time Off
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addLeaveForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="submit">

        <div class="field" style="margin-bottom: 12px;">
          <label for="l_type">Leave Type <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="leave_type_id" id="l_type" required>
              <option value="">-- Choose Type --</option>
              <?php 
              mysqli_data_seek($leave_types_list, 0);
              while ($t = mysqli_fetch_assoc($leave_types_list)): 
              ?>
                <option value="<?php echo $t['id']; ?>"><?php echo e($t['name']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="start_d">Start Date <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="date" name="start_date" id="start_d" required onchange="calcLeaveDays()">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="end_d">End Date <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="date" name="end_date" id="end_d" required onchange="calcLeaveDays()">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="req_days">Calculated Business Days</label>
          <div class="field-wrap">
            <input type="number" name="requested_days" id="req_days" step="0.5" readonly style="opacity: 0.7; background: var(--bg);" required>
          </div>
        </div>

        <div class="field">
          <label for="reason">Reason / Justification <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <textarea name="reason" id="reason" placeholder="State your reasons for requesting time off..." required></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="submitBtn">Submit Leave Request</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openAddModal() {
  document.getElementById('addModalOverlay').style.display = 'flex';
}

function closeAddModal() {
  document.getElementById('addModalOverlay').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('addModalOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});

function calcLeaveDays() {
  const start = document.getElementById('start_d').value;
  const end = document.getElementById('end_d').value;
  if (start && end) {
    const sDate = new Date(start);
    const eDate = new Date(end);
    const diffTime = eDate - sDate;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    
    if (diffDays > 0) {
      document.getElementById('req_days').value = diffDays;
    } else {
      document.getElementById('req_days').value = 0;
    }
  }
}

document.getElementById('addLeaveForm')?.addEventListener('submit', function(e) {
  const days = parseFloat(document.getElementById('req_days').value) || 0;
  if (days <= 0) {
    e.preventDefault();
    alert('End Date must be after or equal to Start Date.');
    return;
  }
  
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').style.opacity = '0.7';
  document.getElementById('submitBtn').textContent = 'Submitting...';
});
</script>
<?php endif; ?>

<!-- ==========================================
     MODAL: REJECT LEAVE (REASON REQUIRED)
     ========================================== -->
<?php if ($canApproveLeave): ?>
<div class="modal-overlay" id="rejectModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--red);">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        Reject Leave Request
      </div>
      <button type="button" class="modal-close-btn" onclick="closeReject()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="leave_id" id="reject_leave_id" value="">
        
        <div class="field">
          <label for="reject_note" style="font-weight: 500; font-size:12px;">Decision Note / Reason <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <textarea name="decision_note" id="reject_note" required placeholder="Specify reason for rejecting leave request (e.g. key project launch scheduled)..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeReject()">Cancel</button>
        <button type="submit" class="btn-action reject" style="padding: 6px 14px;">Reject Request</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function triggerReject(leaveId) {
  document.getElementById('reject_leave_id').value = leaveId;
  document.getElementById('rejectModalOverlay').style.display = 'flex';
}

function closeReject() {
  document.getElementById('rejectModalOverlay').style.display = 'none';
}

document.getElementById('rejectModalOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeReject();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
