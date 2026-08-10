<?php
$page_title = 'Customers Directory';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;
$canCreateCustomer = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['create']);
$canUpdateCustomer = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Server side pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_clause = " WHERE business_id = ?";
$params = [$businessId];
$types = 'i';

if (!empty($search)) {
    $where_clause .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Count total
$count_query = "SELECT COUNT(*) as total FROM customers $where_clause";
$cStmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($cStmt, $types, ...$params);
mysqli_stmt_execute($cStmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "SELECT * FROM customers $where_clause ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $query);
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$csrfToken = generateCsrfToken();
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';
?>

<div>
  <!-- Left Column: Table List -->
  <div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <div class="card-title">Customers Registry Logs</div>
      <?php if ($canCreateCustomer): ?>
        <button class="btn-primary" onclick="openAddModal()">+ Add Customer</button>
      <?php endif; ?>
    </div>
    
    <table class="data-table">
      <thead>
        <tr>
          <th>Customer Name</th>
          <th>Phone / Email</th>
          <th>TIN</th>
          <th>Address</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr>
            <td colspan="6" style="text-align: center; color: var(--text3); padding: 30px;">
              No customers registered yet.
            </td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td class="td-name"><?php echo e($row['name']); ?></td>
              <td>
                <div style="font-weight:500;"><?php echo e($row['phone'] ?? 'N/A'); ?></div>
                <div style="font-size:10px; color:var(--text3);"><?php echo e($row['email'] ?? ''); ?></div>
              </td>
              <td><code><?php echo e($row['tax_number'] ?? 'N/A'); ?></code></td>
              <td style="font-size:11.5px; color:var(--text3);"><?php echo e($row['address'] ?? 'N/A'); ?></td>
              <td>
                <?php if ($row['is_active']): ?>
                  <span class="status-pill pill-green">Active</span>
                <?php else: ?>
                  <span class="status-pill pill-red">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 6px;">
                  <?php if ($canUpdateCustomer): ?>
                  <button class="btn-sm" onclick="showEdit(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                  <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Toggle status of this customer?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="customer_id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn-sm" style="background: var(--bg); border: 1px solid var(--border); color: var(--text);">Toggle Status</button>
                  </form>
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
     MODAL: ADD CUSTOMER
     ========================================== -->
<?php if ($canCreateCustomer): ?>
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Add Customer
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addCustForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create">

        <div class="field" style="margin-bottom: 12px;">
          <label for="c_name">Customer Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="name" id="c_name" placeholder="e.g. John Doe / Gold Buyers" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="c_phone">Phone Number</label>
          <div class="field-wrap">
            <input type="tel" name="phone" id="c_phone" placeholder="e.g. +250788777777">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="c_email">Email address</label>
          <div class="field-wrap">
            <input type="email" name="email" id="c_email" placeholder="e.g. customer@company.com">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="c_tax">TIN / Tax Number</label>
          <div class="field-wrap">
            <input type="text" name="tax_number" id="c_tax" placeholder="e.g. 109837264">
          </div>
        </div>

        <div class="field">
          <label for="c_addr">Full Address</label>
          <div class="field-wrap">
            <input type="text" name="address" id="c_addr" placeholder="e.g. Nyarugenge, Kigali">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="addBtn">Save Customer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==========================================
     MODAL: EDIT CUSTOMER
     ========================================== -->
<?php if ($canUpdateCustomer): ?>
<div class="modal-overlay" id="editModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Modify Customer Parameters
      </div>
      <button type="button" class="modal-close-btn" onclick="closeEdit()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="editCustForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="customer_id" id="edit_cust_id" value="">
        
        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_name">Customer Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="name" id="edit_name" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_phone">Phone Number</label>
          <div class="field-wrap">
            <input type="tel" name="phone" id="edit_phone">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_email">Email address</label>
          <div class="field-wrap">
            <input type="email" name="email" id="edit_email">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_tax">TIN / Tax Number</label>
          <div class="field-wrap">
            <input type="text" name="tax_number" id="edit_tax">
          </div>
        </div>

        <div class="field">
          <label for="edit_addr">Full Address</label>
          <div class="field-wrap">
            <input type="text" name="address" id="edit_addr">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn-primary" id="updateBtn">Update Customer</button>
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

function showEdit(cust) {
  document.getElementById('edit_cust_id').value = cust.id;
  document.getElementById('edit_name').value = cust.name;
  document.getElementById('edit_phone').value = cust.phone || '';
  document.getElementById('edit_email').value = cust.email || '';
  document.getElementById('edit_tax').value = cust.tax_number || '';
  document.getElementById('edit_address').value = cust.address || '';
  
  document.getElementById('editModalOverlay').style.display = 'flex';
}

// Close edit modal
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
document.getElementById('addCustForm')?.addEventListener('submit', function() {
  document.getElementById('addBtn').disabled = true;
  document.getElementById('addBtn').style.opacity = '0.7';
  document.getElementById('addBtn').textContent = 'Saving...';
});
document.getElementById('editCustForm')?.addEventListener('submit', function() {
  document.getElementById('updateBtn').disabled = true;
  document.getElementById('updateBtn').style.opacity = '0.7';
  document.getElementById('updateBtn').textContent = 'Updating...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
