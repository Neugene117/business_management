<?php
$page_title = 'Roles & Permissions';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;

// Fetch business roles
$queryRoles = "SELECT * FROM business_roles WHERE business_id = ? ORDER BY id ASC";
$rStmt = mysqli_prepare($conn, $queryRoles);
mysqli_stmt_bind_param($rStmt, 'i', $businessId);
mysqli_stmt_execute($rStmt);
$rolesResult = mysqli_stmt_get_result($rStmt);

// Fetch all business scoped permissions
$queryPerms = "SELECT * FROM permissions WHERE scope = 'BUSINESS' ORDER BY module ASC, name ASC";
$permsResult = mysqli_query($conn, $queryPerms);
$all_perms = [];
while ($p = mysqli_fetch_assoc($permsResult)) {
    $all_perms[] = $p;
}

// Fetch business memberships (excluding super admins) for overrides section
$queryMems = "
    SELECT m.id as membership_id, u.first_name, u.last_name, u.email, m.member_type 
    FROM business_memberships m
    JOIN users u ON m.user_id = u.id
    WHERE m.business_id = ? AND m.status = 'ACTIVE'
    ORDER BY u.first_name ASC
";
$mStmt = mysqli_prepare($conn, $queryMems);
mysqli_stmt_bind_param($mStmt, 'i', $businessId);
mysqli_stmt_execute($mStmt);
$memsResult = mysqli_stmt_get_result($mStmt);
$members_list = [];
while ($m = mysqli_fetch_assoc($memsResult)) {
    $members_list[] = $m;
}

// Fetch existing membership overrides
$queryOverrides = "
    SELECT o.membership_id, o.permission_id, o.effect, p.name as permission_name, p.code as permission_code, u.first_name, u.last_name
    FROM membership_permission_overrides o
    JOIN permissions p ON o.permission_id = p.id
    JOIN business_memberships m ON o.membership_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE o.business_id = ?
    ORDER BY u.first_name ASC
";
$oStmt = mysqli_prepare($conn, $queryOverrides);
mysqli_stmt_bind_param($oStmt, 'i', $businessId);
mysqli_stmt_execute($oStmt);
$overridesResult = mysqli_stmt_get_result($oStmt);

$csrfToken = generateCsrfToken();
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';
?>
<div>
  <!-- Left: Roles & Assigned permissions -->
  <div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <div class="card-title">Authorization Roles &amp; Permissions</div>
      <button class="btn-primary" onclick="openAddRoleModal()">+ Create Custom Role</button>
    </div>
    
    <table class="data-table">
      <thead>
        <tr>
          <th>Role Code</th>
          <th>Role Name</th>
          <th>Description</th>
          <th>Scope privileges</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        mysqli_data_seek($rolesResult, 0);
        while ($row = mysqli_fetch_assoc($rolesResult)): 
            // Get count of permissions assigned to this role
            $cntQuery = "SELECT COUNT(*) as cnt FROM business_role_permissions WHERE business_id = ? AND business_role_id = ?";
            $cStmt = mysqli_prepare($conn, $cntQuery);
            mysqli_stmt_bind_param($cStmt, 'ii', $businessId, $row['id']);
            mysqli_stmt_execute($cStmt);
            $cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['cnt'] ?? 0;
        ?>
          <tr>
            <td><span class="code-badge"><?php echo e($row['code']); ?></span></td>
            <td class="td-name"><?php echo e($row['name']); ?></td>
            <td style="font-size: 11.5px; color: var(--text3);"><?php echo e($row['description'] ?? 'No description'); ?></td>
            <td><span style="font-weight: 600; color: var(--blue);"><?php echo (int)$cnt; ?></span> assigned permissions</td>
            <td style="text-align: right;">
              <div style="display:inline-flex; gap: 4px;">
                <button class="btn-sm" onclick="editRolePermissions(<?php echo (int)$row['id']; ?>, '<?php echo e($row['name']); ?>')">Manage Privileges</button>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  </div>
</div>

<!-- ==========================================
     MODAL: CREATE CUSTOM ROLE
     ========================================== -->
<!-- ==========================================
     MODAL: CREATE CUSTOM ROLE
     ========================================== -->
<div class="modal-overlay" id="addRoleModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Create Custom Role
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddRoleModal()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addRoleForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create_role">

        <div class="field" style="margin-bottom: 12px;">
          <label for="r_code">Role Code <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="code" id="r_code" placeholder="e.g. CASHIER, AUDITOR" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="r_name">Role Display Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="name" id="r_name" placeholder="e.g. Chief Cashier" required>
          </div>
        </div>

        <div class="field">
          <label for="r_desc">Description</label>
          <div class="field-wrap">
            <input type="text" name="description" id="r_desc" placeholder="Brief outline of role duties">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddRoleModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="addRoleBtn">Create Role</button>
      </div>
    </form>
  </div>
</div>

<!-- Bottom: Direct Overrides Management -->
<div class="card">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div class="card-title">Platform Direct Permission Overrides</div>
    <button class="btn-primary" onclick="openOverrideModal()">+ Configure Direct Override</button>
  </div>
  
  <div style="padding: 20px;">
    <!-- Active overrides table -->
    <div style="margin-bottom: 24px;">
      <h3 style="font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text);">Active Security Overrides</h3>
      <table class="data-table">
        <thead>
          <tr>
            <th>Employee Membership</th>
            <th>Permission Code</th>
            <th>Rule Effect</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($overridesResult) === 0): ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text3); padding: 15px; font-size:12px;">No direct overrides configured.</td>
            </tr>
          <?php else: ?>
            <?php while ($oRow = mysqli_fetch_assoc($overridesResult)): ?>
              <tr>
                <td class="td-name"><?php echo e($oRow['first_name'] . ' ' . $oRow['last_name']); ?></td>
                <td><span class="code-badge"><?php echo e($oRow['permission_code']); ?></span></td>
                <td>
                  <?php if ($oRow['effect'] === 'ALLOW'): ?>
                    <span class="status-pill pill-green">ALLOW (Force Access)</span>
                  <?php else: ?>
                    <span class="status-pill pill-red">DENY (Lock Out)</span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Remove this permission override?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="delete_override">
                    <input type="hidden" name="membership_id" value="<?php echo (int)$oRow['membership_id']; ?>">
                    <input type="hidden" name="permission_id" value="<?php echo (int)$oRow['permission_id']; ?>">
                    <button type="submit" class="btn-action reject" style="font-size:10px; padding:3px 6px;">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ==========================================
     MODAL: CONFIGURE DIRECT OVERRIDE
     ========================================== -->
<div class="modal-overlay" id="overrideModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Configure Direct Override
      </div>
      <button type="button" class="modal-close-btn" onclick="closeOverrideModal()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="overrideForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create_override">
        
        <div class="field" style="margin-bottom: 12px;">
          <label for="ov_mem">Select Employee <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="membership_id" id="ov_mem" required style="font-size:12px;">
              <option value="">-- Choose Member --</option>
              <?php foreach ($members_list as $m): ?>
                <option value="<?php echo (int)$m['membership_id']; ?>"><?php echo e($m['first_name'] . ' ' . $m['last_name']); ?> (<?php echo e($m['member_type']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="ov_perm">Select Privilege <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="permission_id" id="ov_perm" required style="font-size:12px;">
              <option value="">-- Choose Privilege --</option>
              <?php foreach ($all_perms as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['module'] . ' - ' . $p['name']); ?> (<?php echo e($p['code']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="ov_effect">Security Effect <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="effect" id="ov_effect" required style="font-size:12px;">
              <option value="ALLOW">ALLOW (Force Access)</option>
              <option value="DENY">DENY (Lock Out)</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeOverrideModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="overrideBtn">Apply Override Rule</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================
     MODAL: MANAGE ROLE PRIVILEGES
     ========================================== -->
<div class="modal-overlay" id="editModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 11 12 14 22 4"/></svg>
        Manage Privileges for: <span id="role_title_span" style="color:var(--orange);"></span>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeEdit()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="rolePermsForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="update_role_permissions">
        <input type="hidden" name="role_id" id="edit_role_id" value="">
        
        <div style="font-size:12px; margin-bottom: 14px; color:var(--text3);">
          Check checkboxes to grant privileges to this role. Clear checkbox to remove.
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
          <?php 
          // Group by module
          $grouped = [];
          foreach ($all_perms as $p) {
              $grouped[$p['module']][] = $p;
          }
          foreach ($grouped as $module => $items):
          ?>
            <div>
              <h4 style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--text3); border-bottom:1px solid var(--border); padding-bottom:4px; margin-bottom:8px;"><?php echo e($module); ?> Module</h4>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <?php foreach ($items as $item): ?>
                  <label class="checkbox-wrap" style="font-size:12px;">
                    <input type="checkbox" name="permissions[]" value="<?php echo (int)$item['id']; ?>" class="perm-cb-item" id="cb-perm-<?php echo (int)$item['id']; ?>">
                    <span><?php echo e($item['name']); ?> <span style="font-size:10px; color:var(--text3)">[<?php echo e($item['code']); ?>]</span></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn-primary" id="saveRolePermsBtn">Save Role Privileges</button>
      </div>
    </form>
  </div>
</div>

<script>
// Retrieve role permission mapping from DB dynamically
var rolePermissionsMap = {
  <?php
  mysqli_data_seek($rolesResult, 0);
  while ($rRow = mysqli_fetch_assoc($rolesResult)) {
      $roleId = $rRow['id'];
      $assignedQ = "SELECT permission_id FROM business_role_permissions WHERE business_role_id = ?";
      $asStmt = mysqli_prepare($conn, $assignedQ);
      mysqli_stmt_bind_param($asStmt, 'i', $roleId);
      mysqli_stmt_execute($asStmt);
      $asResult = mysqli_stmt_get_result($asStmt);
      $arr = [];
      while ($a = mysqli_fetch_assoc($asResult)) {
          $arr[] = (int)$a['permission_id'];
      }
      echo (int)$roleId . ": " . json_encode($arr) . ",\n";
  }
  ?>
};

function openAddRoleModal() {
  document.getElementById('addRoleModalOverlay').style.display = 'flex';
}

function closeAddRoleModal() {
  document.getElementById('addRoleModalOverlay').style.display = 'none';
}

function openOverrideModal() {
  document.getElementById('overrideModalOverlay').style.display = 'flex';
}

function closeOverrideModal() {
  document.getElementById('overrideModalOverlay').style.display = 'none';
}

function editRolePermissions(roleId, roleName) {
  document.getElementById('edit_role_id').value = roleId;
  document.getElementById('role_title_span').textContent = roleName;
  
  // Clear all checkboxes
  const cbs = document.querySelectorAll('.perm-cb-item');
  cbs.forEach(cb => cb.checked = false);
  
  // Set checkboxes matching map
  const assigned = rolePermissionsMap[roleId] || [];
  assigned.forEach(id => {
    const cb = document.getElementById('cb-perm-' + id);
    if (cb) cb.checked = true;
  });

  document.getElementById('editModalOverlay').style.display = 'flex';
}

function closeEdit() {
  document.getElementById('editModalOverlay').style.display = 'none';
}

// Close modals when clicking outside
document.getElementById('addRoleModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeAddRoleModal();
});
document.getElementById('overrideModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeOverrideModal();
});
document.getElementById('editModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});

// Safeguard double submissions client-side
document.getElementById('addRoleForm').addEventListener('submit', function() {
  document.getElementById('addRoleBtn').disabled = true;
  document.getElementById('addRoleBtn').style.opacity = '0.7';
  document.getElementById('addRoleBtn').textContent = 'Creating...';
});
document.getElementById('overrideForm').addEventListener('submit', function() {
  document.getElementById('overrideBtn').disabled = true;
  document.getElementById('overrideBtn').style.opacity = '0.7';
  document.getElementById('overrideBtn').textContent = 'Applying...';
});
document.getElementById('rolePermsForm').addEventListener('submit', function() {
  document.getElementById('saveRolePermsBtn').disabled = true;
  document.getElementById('saveRolePermsBtn').style.opacity = '0.7';
  document.getElementById('saveRolePermsBtn').textContent = 'Saving privileges...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
