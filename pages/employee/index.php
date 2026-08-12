<?php
$page_title = 'Employees Management';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Server side pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_clause = " WHERE m.business_id = ? AND m.member_type = 'EMPLOYEE'";
$params = [$businessId];
$types = 'i';

if (!empty($search)) {
    $where_clause .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR ep.employee_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

// Count total
$count_query = "
    SELECT COUNT(*) as total 
    FROM business_memberships m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN employee_profiles ep ON m.id = ep.membership_id AND ep.business_id = m.business_id
    $where_clause
";
$cStmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($cStmt, $types, ...$params);
mysqli_stmt_execute($cStmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Fetch employee data
$query = "
    SELECT m.id as membership_id, m.status as membership_status, m.joined_at, 
           u.id as user_id, u.first_name, u.last_name, u.email, u.phone, u.status as user_status,
           ep.employee_number, ep.job_title, ep.department, ep.hire_date, ep.emergency_contact_name, ep.emergency_contact_phone,
           (
             SELECT GROUP_CONCAT(br.name SEPARATOR ', ') 
             FROM membership_roles mr
             JOIN business_roles br ON mr.business_role_id = br.id
             WHERE mr.membership_id = m.id
           ) as assigned_roles
    FROM business_memberships m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN employee_profiles ep ON m.id = ep.membership_id AND ep.business_id = m.business_id
    $where_clause
    ORDER BY ep.employee_number ASC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $query);
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Only the effective Super Admin may assign the protected Owner role.
$roleQuery = "SELECT id, name FROM business_roles WHERE business_id = ?";
if (!isEffectiveSuperAdmin()) {
    $roleQuery .= " AND UPPER(code) <> 'OWNER' AND LOWER(name) <> 'owner'";
}
$roleQuery .= " ORDER BY name ASC";
$rStmt = mysqli_prepare($conn, $roleQuery);
mysqli_stmt_bind_param($rStmt, 'i', $businessId);
mysqli_stmt_execute($rStmt);
$rolesResult = mysqli_stmt_get_result($rStmt);
$roles_list = [];
while ($rRow = mysqli_fetch_assoc($rolesResult)) {
    $roles_list[] = $rRow;
}

$csrfToken = generateCsrfToken();
$role_query = getRolePreviewQuery();
$canCreateEmployee = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['create']);
$canUpdateEmployee = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);
$canSuspendEmployee = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['suspend']);
?>
<div>
  <!-- Left: Roster List -->
  <div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <div class="card-title">Employee Roster Directory</div>
      <?php if ($canCreateEmployee): ?>
        <button class="btn-primary" onclick="openAddModal()">+ Add Employee</button>
      <?php endif; ?>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>No.</th>
          <th>Employee Name</th>
          <th>Roles</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr>
            <td colspan="5" style="text-align: center; color: var(--text3); padding: 30px;">
              No employee accounts registered yet.
            </td>
          </tr>
        <?php else: ?>
          <?php $displaySequence = $offset + 1; while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td><?php echo $displaySequence++; ?></td>
              <td class="td-name">
                <div style="font-weight: 500; color: var(--text);"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></div>
                <div style="font-size: 10px; color: var(--text3);"><?php echo e($row['email'] ?? 'No email'); ?></div>
              </td>
              <td><span style="font-size: 11px; color: var(--blue); font-weight: 500;"><?php echo e($row['assigned_roles'] ?? 'No Role'); ?></span></td>
              <td>
                <?php if ($row['membership_status'] === 'ACTIVE'): ?>
                  <span class="status-pill pill-green">Active</span>
                <?php elseif ($row['membership_status'] === 'PENDING'): ?>
                  <span class="status-pill pill-amber">Pending</span>
                <?php elseif ($row['membership_status'] === 'SUSPENDED'): ?>
                  <span class="status-pill pill-red" style="opacity: 0.6;">Suspended</span>
                <?php else: ?>
                  <span class="status-pill pill-red">Terminated</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <?php if ($canUpdateEmployee): ?>
                  <button class="btn-sm" onclick="showEdit(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                <?php endif; ?>
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
            <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page - 1); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Previous</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a class="btn-sm <?php echo ($i === $page) ? 'active' : ''; ?>" style="text-decoration:none;" href="index.php?page=<?php echo $i; ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page + 1); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ==========================================
     MODAL: ADD EMPLOYEE
     ========================================== -->
<!-- ==========================================
     MODAL: ADD EMPLOYEE
     ========================================== -->
<?php if ($canCreateEmployee): ?>
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
        Add Employee Account
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addEmpForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create">

        <div class="field" style="margin-bottom: 12px;">
          <label for="f_name">First Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="first_name" id="f_name" placeholder="Eric" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="l_name">Last Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="last_name" id="l_name" placeholder="Habimana" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="emp_phone">Contact Phone <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="tel" name="phone" id="emp_phone" placeholder="+250788000000" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="emp_pw">Login Password <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="password" name="password" id="emp_pw" placeholder="Min 8 characters" required autocomplete="new-password">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="sel_role">Assign Business Role <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="business_role_id" id="sel_role" required>
              <option value="">-- Choose Role --</option>
              <?php foreach ($roles_list as $r): ?>
                <option value="<?php echo (int)$r['id']; ?>"><?php echo e($r['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_name">Emergency Contact Name</label>
          <div class="field-wrap">
            <input type="text" name="emergency_contact_name" id="e_name" placeholder="Alice Habimana">
          </div>
        </div>

        <div class="field">
          <label for="e_phone">Emergency Contact Phone</label>
          <div class="field-wrap">
            <input type="tel" name="emergency_contact_phone" id="e_phone" placeholder="+250788111111">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="addBtn">Create Employee</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==========================================
     MODAL: EDIT EMPLOYEE
     ========================================== -->
<?php if ($canUpdateEmployee): ?>
<div class="modal-overlay" id="editModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Modify Employee Profile
      </div>
      <button type="button" class="modal-close-btn" onclick="closeEdit()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="editEmpForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="membership_id" id="edit_membership_id" value="">
        
        <div class="field" style="margin-bottom: 12px;">
          <label>Employee Code</label>
          <div class="field-wrap">
            <input type="text" id="edit_emp_num" readonly style="background:var(--bg); border-color:var(--border);">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_f_name">First Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="first_name" id="edit_f_name" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_l_name">Last Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="last_name" id="edit_l_name" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_j_title">Job Title</label>
          <div class="field-wrap">
            <input type="text" name="job_title" id="edit_j_title">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_dept">Department</label>
          <div class="field-wrap">
            <input type="text" name="department" id="edit_dept">
          </div>
        </div>

        <?php if ($canSuspendEmployee): ?>
        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_status">Membership Status <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="status" id="edit_status" required>
              <option value="PENDING">Pending Approval</option>
              <option value="ACTIVE">Active</option>
              <option value="SUSPENDED">Suspended</option>
              <option value="TERMINATED">Terminated</option>
            </select>
          </div>
        </div>
        <?php else: ?>
          <input type="hidden" name="status" id="edit_status" value="ACTIVE">
        <?php endif; ?>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_role_id">Business Role <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="business_role_id" id="edit_role_id" required>
              <?php foreach ($roles_list as $r): ?>
                <option value="<?php echo (int)$r['id']; ?>"><?php echo e($r['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_e_name">Emergency Contact Name</label>
          <div class="field-wrap">
            <input type="text" name="emergency_contact_name" id="edit_e_name">
          </div>
        </div>

        <div class="field">
          <label for="edit_e_phone">Emergency Contact Phone</label>
          <div class="field-wrap">
            <input type="tel" name="emergency_contact_phone" id="edit_e_phone">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn-primary" id="updateBtn">Update Profile</button>
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

function showEdit(emp) {
  document.getElementById('edit_membership_id').value = emp.membership_id;
  document.getElementById('edit_emp_num').value = emp.employee_number || 'N/A';
  document.getElementById('edit_f_name').value = emp.first_name;
  document.getElementById('edit_l_name').value = emp.last_name || '';
  document.getElementById('edit_j_title').value = emp.job_title || '';
  document.getElementById('edit_dept').value = emp.department || '';
  document.getElementById('edit_status').value = emp.membership_status;
  document.getElementById('edit_e_name').value = emp.emergency_contact_name || '';
  document.getElementById('edit_e_phone').value = emp.emergency_contact_phone || '';
  
  // Try to find matching assigned role in select dropdown
  const roles = emp.assigned_roles ? emp.assigned_roles.split(', ') : [];
  const select = document.getElementById('edit_role_id');
  if (roles.length > 0) {
    for (let i = 0; i < select.options.length; i++) {
      if (roles.includes(select.options[i].text)) {
        select.selectedIndex = i;
        break;
      }
    }
  }

  document.getElementById('editModalOverlay').style.display = 'flex';
}

function closeEdit() {
  document.getElementById('editModalOverlay').style.display = 'none';
}

// Close modals when clicking outside
document.getElementById('addModalOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});
document.getElementById('editModalOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});

// Safeguard double submissions client-side
document.getElementById('addEmpForm')?.addEventListener('submit', function(e) {
  const pw = document.getElementById('emp_pw').value;
  if (pw.length < 8) {
    e.preventDefault();
    alert('Password must be at least 8 characters long.');
    return;
  }
  
  document.getElementById('addBtn').disabled = true;
  document.getElementById('addBtn').style.opacity = '0.7';
  document.getElementById('addBtn').textContent = 'Creating Account...';
});
document.getElementById('editEmpForm')?.addEventListener('submit', function() {
  document.getElementById('updateBtn').disabled = true;
  document.getElementById('updateBtn').style.opacity = '0.7';
  document.getElementById('updateBtn').textContent = 'Updating Account...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
