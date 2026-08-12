<?php
$page_title = 'Business Approvals';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);
$approvalBusinessId = $_SESSION['active_business_id'] ?? null;
$canApproveBusiness = hasPermission($conn, $_SESSION['membership_id'] ?? null, $approvalBusinessId, $permissions['approve']);
$canRejectBusiness = hasPermission($conn, $_SESSION['membership_id'] ?? null, $approvalBusinessId, $permissions['reject']);
$canSuspendBusiness = hasPermission($conn, $_SESSION['membership_id'] ?? null, $approvalBusinessId, $permissions['suspend']);

// Fetch list of businesses (using server-side pagination)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clause = " WHERE 1=1";
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where_clause .= " AND b.approval_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $where_clause .= " AND (b.business_name LIKE ? OR b.email LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Count total
$count_query = "
    SELECT COUNT(*) as total 
    FROM businesses b
    LEFT JOIN users u ON b.created_by_user_id = u.id
    $where_clause
";
$cStmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($cStmt, $types, ...$params);
}
mysqli_stmt_execute($cStmt);
$count_result = mysqli_stmt_get_result($cStmt);
$total_rows = mysqli_fetch_assoc($count_result)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "
    SELECT b.*, u.first_name, u.last_name, u.email as owner_email, u.phone as owner_phone
    FROM businesses b
    LEFT JOIN users u ON b.created_by_user_id = u.id
    $where_clause
    ORDER BY b.submitted_at DESC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $query);
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Quick statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN approval_status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN approval_status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN approval_status = 'SUSPENDED' THEN 1 ELSE 0 END) as suspended
    FROM businesses
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$csrfToken = generateCsrfToken();
?>

<!-- Platform statistics summary -->
<div class="stats-grid-8" style="margin-bottom: 24px;">
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-trend trend-blue">Total</span>
    </div>
    <div class="stat-val"><?php echo (int)$stats['total']; ?></div>
    <div class="stat-card-desc">Registered companies</div>
  </div>
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-trend trend-warn">Pending</span>
    </div>
    <div class="stat-val"><?php echo (int)$stats['pending']; ?></div>
    <div class="stat-card-desc">Awaiting approval review</div>
  </div>
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-trend trend-up">Approved</span>
    </div>
    <div class="stat-val"><?php echo (int)$stats['approved']; ?></div>
    <div class="stat-card-desc">Active business tenants</div>
  </div>
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-trend trend-down">Suspended</span>
    </div>
    <div class="stat-val"><?php echo (int)$stats['suspended']; ?></div>
    <div class="stat-card-desc">Access blocked temporarily</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
    <div class="card-title">Business Registration Log</div>
    
    <!-- Filter form (GET) -->
    <form method="GET" action="index.php" style="display: flex; gap: 10px; align-items: center;">
      <?php if (isset($_GET['role'])): ?>
        <input type="hidden" name="role" value="<?php echo e($_GET['role']); ?>">
      <?php endif; ?>
      <select name="status" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
        <option value="">All Statuses</option>
        <option value="PENDING" <?php echo ($status_filter === 'PENDING') ? 'selected' : ''; ?>>Pending</option>
        <option value="APPROVED" <?php echo ($status_filter === 'APPROVED') ? 'selected' : ''; ?>>Approved</option>
        <option value="REJECTED" <?php echo ($status_filter === 'REJECTED') ? 'selected' : ''; ?>>Rejected</option>
        <option value="SUSPENDED" <?php echo ($status_filter === 'SUSPENDED') ? 'selected' : ''; ?>>Suspended</option>
      </select>
      <input type="text" name="search" placeholder="Search business/owner..." value="<?php echo e($search); ?>" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px; min-width: 180px;">
      <button class="btn-sm" type="submit">Apply</button>
    </form>
  </div>

  <table class="data-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Business Name</th>
        <th>Owner</th>
        <th>TIN</th>
        <th>Country</th>
        <th>Submitted At</th>
        <th>Status</th>
        <th style="text-align: right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (mysqli_num_rows($result) === 0): ?>
        <tr>
          <td colspan="8" style="text-align: center; color: var(--text3); padding: 30px;">
            No business registrations found matching filters.
          </td>
        </tr>
      <?php else: ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <tr>
            <td><span class="code-badge"><?php echo e(substr($row['public_id'], 0, 8)); ?></span></td>
            <td class="td-name"><?php echo e($row['business_name']); ?></td>
            <td>
              <div style="font-weight:500; color: var(--text);"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></div>
              <div style="font-size:10px; color: var(--text3);"><?php echo e($row['owner_email']); ?></div>
            </td>
            <td><?php echo e($row['tax_number'] ?? 'N/A'); ?></td>
            <td><span style="font-weight: 600; font-size:11px;"><?php echo e($row['country_code']); ?></span> (<?php echo e($row['currency_code']); ?>)</td>
            <td><?php echo formatDate($row['submitted_at']); ?></td>
            <td>
              <?php if ($row['approval_status'] === 'APPROVED'): ?>
                <span class="status-pill pill-green">Approved</span>
              <?php elseif ($row['approval_status'] === 'PENDING'): ?>
                <span class="status-pill pill-amber">Pending</span>
              <?php elseif ($row['approval_status'] === 'REJECTED'): ?>
                <span class="status-pill pill-red">Rejected</span>
              <?php else: ?>
                <span class="status-pill pill-red" style="opacity: 0.6;">Suspended</span>
              <?php endif; ?>
            </td>
            <td style="text-align: right;">
              <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                <!-- View Details Button -->
                <button class="btn-sm" 
                        onclick="showDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                        title="View Registration Details">
                  Inspect
                </button>

                <!-- Status Action Buttons (only show permitted actions) -->
                <?php if ($row['approval_status'] === 'PENDING'): ?>
                  <?php if ($canApproveBusiness): ?>
                  <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to APPROVE this business?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="business_id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn-action approve" style="padding: 4px 8px; font-size: 11px;">Approve</button>
                  </form>
                  <?php endif; ?>
                  <?php if ($canRejectBusiness): ?>
                  <button class="btn-action reject" style="padding: 4px 8px; font-size: 11px;" onclick="triggerReject(<?php echo (int)$row['id']; ?>)">Reject</button>
                  <?php endif; ?>
                <?php elseif ($row['approval_status'] === 'APPROVED'): ?>
                  <?php if ($canSuspendBusiness): ?>
                  <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to SUSPEND this business?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="business_id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn-action reject" style="padding: 4px 8px; font-size: 11px; opacity: 0.8;">Suspend</button>
                  </form>
                  <?php endif; ?>
                <?php elseif ($row['approval_status'] === 'SUSPENDED'): ?>
                  <?php if ($canSuspendBusiness): ?>
                  <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to REACTIVATE this suspended business?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="reactivate">
                    <input type="hidden" name="business_id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn-action approve" style="padding: 4px 8px; font-size: 11px;">Reactivate</button>
                  </form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Pagination links -->
  <?php if ($total_pages > 1): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px; border-top: 1px solid var(--table-border);">
      <span style="font-size:12px; color: var(--text3);">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_rows; ?> entries)</span>
      <div style="display: flex; gap: 4px;">
        <?php if ($page > 1): ?>
          <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page - 1); ?>&status=<?php echo e($status_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Previous</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a class="btn-sm <?php echo ($i === $page) ? 'active' : ''; ?>" style="text-decoration:none;" href="index.php?page=<?php echo $i; ?>&status=<?php echo e($status_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
          <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page + 1); ?>&status=<?php echo e($status_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ==========================================
     MODAL: INSPECT BUSINESS DETAILS
     ========================================== -->
<div class="modal-overlay" id="inspectModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Registration Parameters Details
      </div>
      <button class="modal-close-btn" onclick="closeInspect()">✕</button>
    </div>
    <div class="modal-body" style="font-size:12.5px;">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom: 16px;">
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Business Name</strong>
          <div id="m_biz_name" style="font-size:14px; font-weight:600; color:var(--text);"></div>
        </div>
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Legal Name</strong>
          <div id="m_legal_name" style="font-weight:500;"></div>
        </div>
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Phone Number</strong>
          <div id="m_biz_phone"></div>
        </div>
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Business Email</strong>
          <div id="m_biz_email"></div>
        </div>
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">TIN / Reg Number</strong>
          <div>TIN: <span id="m_tax"></span> | Reg: <span id="m_reg"></span></div>
        </div>
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Location parameters</strong>
          <div><span id="m_city"></span>, <span id="m_country"></span> (<span id="m_tz"></span>)</div>
        </div>
        <div style="grid-column: span 2;">
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Business Summary</strong>
          <div id="m_summary" style="background:var(--bg); padding:10px; border-radius: var(--radius); margin-top:4px;"></div>
        </div>
      </div>
      
      <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
        <h3 style="font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text);">Primary Owner Onboarding Info</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div>
            <strong style="color:var(--text3); text-transform:uppercase; font-size:9px;">Full Name</strong>
            <div id="m_owner_name"></div>
          </div>
          <div>
            <strong style="color:var(--text3); text-transform:uppercase; font-size:9px;">Owner Contact</strong>
            <div>Email: <span id="m_owner_email"></span><br>Phone: <span id="m_owner_phone"></span></div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-sm" onclick="closeInspect()">Close</button>
    </div>
  </div>
</div>

<!-- ==========================================
     MODAL: REJECTION REASON
     ========================================== -->
<?php if ($canRejectBusiness): ?>
<div class="modal-overlay" id="rejectModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--red);">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        Reject Registration
      </div>
      <button class="modal-close-btn" onclick="closeReject()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="business_id" id="reject_biz_id" value="">
        
        <div class="field" style="margin-bottom: 16px;">
          <label for="reject_reason" style="font-weight: 500; font-size:12px;">Reason for Rejection <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <textarea name="reason" id="reject_reason" required placeholder="Enter reasons for rejecting this business registration (e.g. invalid tax documents, verification failed)..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeReject()">Cancel</button>
        <button type="submit" class="btn-action reject" style="padding: 6px 14px;">Reject Business</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function showDetails(biz) {
  document.getElementById('m_biz_name').textContent = biz.business_name;
  document.getElementById('m_legal_name').textContent = biz.legal_name || biz.business_name;
  document.getElementById('m_biz_phone').textContent = biz.phone;
  document.getElementById('m_biz_email').textContent = biz.email || 'N/A';
  document.getElementById('m_tax').textContent = biz.tax_number || 'N/A';
  document.getElementById('m_reg').textContent = biz.registration_number || 'N/A';
  document.getElementById('m_city').textContent = biz.city || 'N/A';
  document.getElementById('m_country').textContent = biz.country_code;
  document.getElementById('m_tz').textContent = biz.timezone;
  document.getElementById('m_summary').textContent = biz.summary || 'No summary details.';
  
  document.getElementById('m_owner_name').textContent = biz.first_name + ' ' + (biz.last_name || '');
  document.getElementById('m_owner_email').textContent = biz.owner_email || 'N/A';
  document.getElementById('m_owner_phone').textContent = biz.owner_phone || 'N/A';
  
  document.getElementById('inspectModalOverlay').style.display = 'flex';
}

function closeInspect() {
  document.getElementById('inspectModalOverlay').style.display = 'none';
}

function triggerReject(bizId) {
  document.getElementById('reject_biz_id').value = bizId;
  document.getElementById('rejectModalOverlay').style.display = 'flex';
}

function closeReject() {
  document.getElementById('rejectModalOverlay').style.display = 'none';
}

// Click outside close helpers
document.getElementById('inspectModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeInspect();
});
document.getElementById('rejectModalOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeReject();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
