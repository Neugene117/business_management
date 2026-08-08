<?php
$page_title = 'Locations Management';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clause = " WHERE business_id = ?";
$params = [$businessId];
$types = 'i';

if (!empty($search)) {
    $where_clause .= " AND (code LIKE ? OR name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$query = "SELECT * FROM business_locations $where_clause ORDER BY code ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$csrfToken = generateCsrfToken();
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';
?>


    <div>
  <!-- Left Side: Table List -->
  <div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <div class="card-title">Business Branch &amp; Warehouse Locations</div>
      <button class="btn-primary" onclick="openAddModal()">+ Add Location</button>
    </div>
    
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Location Type</th>
          <th>Address</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr>
            <td colspan="6" style="text-align: center; color: var(--text3); padding: 30px;">
              No business locations registered yet.
            </td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td><span class="code-badge"><?php echo e($row['code']); ?></span></td>
              <td class="td-name"><?php echo e($row['name']); ?></td>
              <td>
                <span class="status-pill pill-blue" style="font-size: 10px; font-weight: 600;">
                  <?php echo e($row['location_type']); ?>
                </span>
              </td>
              <td><?php echo e($row['address'] ?? 'N/A'); ?></td>
              <td>
                <?php if ($row['is_active']): ?>
                  <span class="status-pill pill-green">Active</span>
                <?php else: ?>
                  <span class="status-pill pill-red">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 6px;">
                  <button class="btn-sm" onclick="showEdit(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                  <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Change status of this location?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="location_id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn-sm" style="background: var(--bg); border: 1px solid var(--border); color: var(--text);">Toggle Status</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ==========================================
     MODAL: ADD LOCATION
     ========================================== -->
<!-- ==========================================
     MODAL: ADD LOCATION
     ========================================== -->
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Add Location
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addLocForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create">

        <div class="field" style="margin-bottom: 12px;">
          <label for="code">Location Code <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="code" id="code" placeholder="e.g. WH1, STORE2" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="name">Location Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="name" id="name" placeholder="e.g. Kigali Warehouse" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="location_type">Location Type <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="location_type" id="location_type" required>
              <option value="STORE">Store</option>
              <option value="WAREHOUSE">Warehouse</option>
              <option value="OFFICE">Office</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="address">Full Address</label>
          <div class="field-wrap">
            <input type="text" name="address" id="address" placeholder="e.g. KG 11 Avenue, Kigali">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="addBtn">Save Location</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================
     MODAL: EDIT LOCATION
     ========================================== -->
<div class="modal-overlay" id="editModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Modify Location Details
      </div>
      <button type="button" class="modal-close-btn" onclick="closeEdit()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="editLocForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="location_id" id="edit_loc_id" value="">
        
        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_code">Location Code <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="code" id="edit_code" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_name">Location Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="name" id="edit_name" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_location_type">Location Type <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="location_type" id="edit_location_type" required>
              <option value="STORE">Store</option>
              <option value="WAREHOUSE">Warehouse</option>
              <option value="OFFICE">Office</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="edit_address">Full Address</label>
          <div class="field-wrap">
            <input type="text" name="address" id="edit_address">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn-primary" id="updateBtn">Update Location</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('addModalOverlay').style.display = 'flex';
}

function closeAddModal() {
  document.getElementById('addModalOverlay').style.display = 'none';
}

function showEdit(loc) {
  document.getElementById('edit_loc_id').value = loc.id;
  document.getElementById('edit_code').value = loc.code;
  document.getElementById('edit_name').value = loc.name;
  document.getElementById('edit_location_type').value = loc.location_type;
  document.getElementById('edit_address').value = loc.address || '';
  
  document.getElementById('editModalOverlay').style.display = 'flex';
}

function closeEdit() {
  document.getElementById('editModalOverlay').style.display = 'none';
}

// Close modals when clicking outside
document.getElementById('addModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});
document.getElementById('editModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});

// Safeguard double submissions client-side
document.getElementById('addLocForm').addEventListener('submit', function() {
  document.getElementById('addBtn').disabled = true;
  document.getElementById('addBtn').style.opacity = '0.7';
  document.getElementById('addBtn').textContent = 'Saving...';
});
document.getElementById('editLocForm').addEventListener('submit', function() {
  document.getElementById('updateBtn').disabled = true;
  document.getElementById('updateBtn').style.opacity = '0.7';
  document.getElementById('updateBtn').textContent = 'Updating...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
