<?php
$page_title = 'Products Catalog';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

// Server side pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_clause = " WHERE p.business_id = ?";
$params = [$businessId];
$types = 'i';

if (!empty($search)) {
    $where_clause .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($category_filter)) {
    $where_clause .= " AND p.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

// Count total
$count_query = "SELECT COUNT(*) as total FROM products p $where_clause";
$cStmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($cStmt, $types, ...$params);
mysqli_stmt_execute($cStmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Fetch data including total quantity on hand
$query = "
    SELECT p.*, 
           COALESCE((SELECT SUM(quantity_on_hand) FROM inventory_balances WHERE product_id = p.id AND business_id = p.business_id), 0.0) as total_qty
    FROM products p
    $where_clause
    ORDER BY p.name ASC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $query);
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch unique categories for dropdown filters
$catQuery = "SELECT DISTINCT category FROM products WHERE business_id = ? AND category IS NOT NULL AND category != '' ORDER BY category ASC";
$catStmt = mysqli_prepare($conn, $catQuery);
mysqli_stmt_bind_param($catStmt, 'i', $businessId);
mysqli_stmt_execute($catStmt);
$catResult = mysqli_stmt_get_result($catStmt);

// Fetch business currency
$bizQuery = "SELECT currency_code FROM businesses WHERE id = ? LIMIT 1";
$bStmt = mysqli_prepare($conn, $bizQuery);
mysqli_stmt_bind_param($bStmt, 'i', $businessId);
mysqli_stmt_execute($bStmt);
$bizCur = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt))['currency_code'] ?? 'RWF';

$csrfToken = generateCsrfToken();
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';
?>
<div>
  <!-- Left Column: Products List -->
  <div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <div class="card-title">Item Catalog Directory</div>
        <button class="btn-primary" onclick="openAddModal()">+ Add Product</button>
      </div>
      <form method="GET" action="index.php" style="display: flex; gap: 8px; align-items: center;">
        <?php if (isset($_GET['role'])): ?>
          <input type="hidden" name="role" value="<?php echo e($_GET['role']); ?>">
        <?php endif; ?>
        <select name="category" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
          <option value="">All Categories</option>
          <?php while ($cRow = mysqli_fetch_assoc($catResult)): ?>
            <option value="<?php echo e($cRow['category']); ?>" <?php echo ($category_filter === $cRow['category']) ? 'selected' : ''; ?>><?php echo e($cRow['category']); ?></option>
          <?php endwhile; ?>
        </select>
        <input type="text" name="search" placeholder="Search SKU/name..." value="<?php echo e($search); ?>" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px; min-width: 150px;">
        <button class="btn-sm" type="submit">Filter</button>
      </form>
    </div>
    
    <table class="data-table">
      <thead>
        <tr>
          <th>SKU</th>
          <th>Product Name</th>
          <th>Category</th>
          <th>Cost / Sale</th>
          <th>Stock Balance</th>
          <th>UOM</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr>
            <td colspan="8" style="text-align: center; color: var(--text3); padding: 30px;">
              No products registered in catalog.
            </td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): 
              $is_low = ($row['total_qty'] <= $row['reorder_level']);
          ?>
            <tr>
              <td><span class="code-badge"><?php echo e($row['sku']); ?></span></td>
              <td class="td-name"><?php echo e($row['name']); ?></td>
              <td><span style="font-size:11.5px; color:var(--text3);"><?php echo e($row['category'] ?? 'General'); ?></span></td>
              <td>
                <div style="font-size: 11px; color:var(--text3)">Cost: <?php echo formatCurrency($row['cost_price'], $bizCur); ?></div>
                <div style="font-weight: 600; color:var(--blue);">Sale: <?php echo formatCurrency($row['sale_price'], $bizCur); ?></div>
              </td>
              <td>
                <span style="font-weight: 700; color: <?php echo $is_low ? 'var(--red)' : 'var(--text)'; ?>;">
                  <?php echo (float)$row['total_qty']; ?>
                </span>
                <?php if ($is_low): ?>
                  <span class="status-pill pill-red" style="font-size: 8px; padding: 1px 4px; margin-left: 4px;" title="Reorder level: <?php echo (float)$row['reorder_level']; ?>">Low Stock</span>
                <?php endif; ?>
              </td>
              <td><span class="code-badge" style="background:var(--bg); border:1px solid var(--border);"><?php echo e($row['uom']); ?></span></td>
              <td>
                <?php if ($row['is_active']): ?>
                  <span class="status-pill pill-green">Active</span>
                <?php else: ?>
                  <span class="status-pill pill-red">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 4px;">
                  <button class="btn-sm" onclick="showEdit(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                  <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Toggle status of this catalog item?');">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="product_id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn-sm" style="background: var(--bg); border: 1px solid var(--border); color: var(--text);">Toggle</button>
                  </form>
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
            <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page - 1); ?>&category=<?php echo e($category_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Previous</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a class="btn-sm <?php echo ($i === $page) ? 'active' : ''; ?>" style="text-decoration:none;" href="index.php?page=<?php echo $i; ?>&category=<?php echo e($category_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page + 1); ?>&category=<?php echo e($category_filter); ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ==========================================
     MODAL: ADD PRODUCT
     ========================================== -->
<!-- ==========================================
     MODAL: ADD PRODUCT
     ========================================== -->
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        Add Catalog Product
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addProdForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create">

        <div class="field" style="margin-bottom: 12px;">
          <label for="p_sku">SKU Code <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="sku" id="p_sku" placeholder="e.g. GLD-24K-10G" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="p_name">Product Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="name" id="p_name" placeholder="e.g. 24K Gold Bar (10g)" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="p_cat">Category</label>
          <div class="field-wrap">
            <input type="text" name="category" id="p_cat" placeholder="e.g. Minerals, Machinery">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="p_uom">Unit of Measure (UOM) <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="uom" id="p_uom" value="GRAM" placeholder="e.g. GRAM, UNIT, KG" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="p_cost">Cost Price (<?php echo e($bizCur); ?>) <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="number" name="cost_price" id="p_cost" step="0.0001" min="0" placeholder="0.0000" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="p_sale">Sale Price (<?php echo e($bizCur); ?>) <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="number" name="sale_price" id="p_sale" step="0.0001" min="0" placeholder="0.0000" required>
          </div>
        </div>

        <div class="field">
          <label for="p_reorder">Reorder Threshold Quantity <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="number" name="reorder_level" id="p_reorder" step="0.0001" min="0" value="5.0000" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="addBtn">Save Catalog Product</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================
     MODAL: EDIT PRODUCT
     ========================================== -->
<div class="modal-overlay" id="editModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Modify Product Catalog Parameters
      </div>
      <button type="button" class="modal-close-btn" onclick="closeEdit()">✕</button>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="editProdForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="product_id" id="edit_prod_id" value="">
        
        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_sku">SKU Code <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="sku" id="edit_sku" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_name">Product Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="name" id="edit_name" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_cat">Category</label>
          <div class="field-wrap">
            <input type="text" name="category" id="edit_cat">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_uom">Unit of Measure (UOM) <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="uom" id="edit_uom" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_cost">Cost Price (<?php echo e($bizCur); ?>) <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="number" name="cost_price" id="edit_cost" step="0.0001" min="0" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="edit_sale">Sale Price (<?php echo e($bizCur); ?>) <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="number" name="sale_price" id="edit_sale" step="0.0001" min="0" required>
          </div>
        </div>

        <div class="field">
          <label for="edit_reorder">Reorder Threshold Quantity <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="number" name="reorder_level" id="edit_reorder" step="0.0001" min="0" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn-primary" id="updateBtn">Update Product</button>
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

function showEdit(prod) {
  document.getElementById('edit_prod_id').value = prod.id;
  document.getElementById('edit_sku').value = prod.sku;
  document.getElementById('edit_name').value = prod.name;
  document.getElementById('edit_cat').value = prod.category || '';
  document.getElementById('edit_uom').value = prod.uom;
  document.getElementById('edit_cost').value = parseFloat(prod.cost_price).toFixed(4);
  document.getElementById('edit_sale').value = parseFloat(prod.sale_price).toFixed(4);
  document.getElementById('edit_reorder').value = parseFloat(prod.reorder_level).toFixed(4);
  
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
document.getElementById('addProdForm').addEventListener('submit', function() {
  document.getElementById('addBtn').disabled = true;
  document.getElementById('addBtn').style.opacity = '0.7';
  document.getElementById('addBtn').textContent = 'Saving...';
});
document.getElementById('editProdForm').addEventListener('submit', function() {
  document.getElementById('updateBtn').disabled = true;
  document.getElementById('updateBtn').style.opacity = '0.7';
  document.getElementById('updateBtn').textContent = 'Updating...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
