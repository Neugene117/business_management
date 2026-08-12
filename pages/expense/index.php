<?php
$page_title = 'Expenses Management';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;
$canCreateExpense = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['create']);
$canPostExpense = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['post']);
$canVoidExpense = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['void']);
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$cat_filter = isset($_GET['expense_category_id']) ? (int)$_GET['expense_category_id'] : 0;

// Server side pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_clause = " WHERE e.business_id = ?";
$params = [$businessId];
$types = 'i';

if (!empty($search)) {
    $where_clause .= " AND (e.expense_number LIKE ? OR e.payee LIKE ? OR e.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where_clause .= " AND e.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($cat_filter)) {
    $where_clause .= " AND e.expense_category_id = ?";
    $params[] = $cat_filter;
    $types .= 'i';
}

// Count total
$count_query = "SELECT COUNT(*) as total FROM expenses e $where_clause";
$cStmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($cStmt, $types, ...$params);
mysqli_stmt_execute($cStmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "
    SELECT e.*, c.name as category_name, u.first_name, u.last_name
    FROM expenses e
    JOIN expense_categories c ON e.expense_category_id = c.id
    LEFT JOIN business_memberships bm ON e.recorded_by_membership_id = bm.id
    LEFT JOIN users u ON bm.user_id = u.id
    $where_clause
    ORDER BY e.expense_date DESC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $query);
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch active categories
$catQuery = "SELECT id, name FROM expense_categories WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$catStmt = mysqli_prepare($conn, $catQuery);
mysqli_stmt_bind_param($catStmt, 'i', $businessId);
mysqli_stmt_execute($catStmt);
$catResult = mysqli_stmt_get_result($catStmt);
$categories_list = [];
while ($cRow = mysqli_fetch_assoc($catResult)) {
    $categories_list[] = $cRow;
}

// Fetch active locations
$locQuery = "SELECT id, name, code FROM business_locations WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$lStmt = mysqli_prepare($conn, $locQuery);
mysqli_stmt_bind_param($lStmt, 'i', $businessId);
mysqli_stmt_execute($lStmt);
$locResult = mysqli_stmt_get_result($lStmt);
$locations_list = [];
while ($lRow = mysqli_fetch_assoc($locResult)) {
    $locations_list[] = $lRow;
}

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
  <!-- Left Column: Table -->
  <div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <div class="card-title">Expense Transactions Ledger</div>
        <?php if ($canCreateExpense): ?>
          <button class="btn-primary" onclick="openAddModal()">+ Record Expense</button>
          <button class="btn-primary" style="background: var(--green);" onclick="openCatModal()">+ Add Category</button>
        <?php endif; ?>
      </div>      
      <form method="GET" action="index.php" style="display: flex; gap: 8px; align-items: center;">
        <?php if (isset($_GET['role'])): ?>
          <input type="hidden" name="role" value="<?php echo e($_GET['role']); ?>">
        <?php endif; ?>
        <select name="status" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
          <option value="">All Statuses</option>
          <option value="DRAFT" <?php echo ($status_filter === 'DRAFT') ? 'selected' : ''; ?>>Draft</option>
          <option value="POSTED" <?php echo ($status_filter === 'POSTED') ? 'selected' : ''; ?>>Posted</option>
          <option value="VOIDED" <?php echo ($status_filter === 'VOIDED') ? 'selected' : ''; ?>>Voided</option>
        </select>
        <select name="expense_category_id" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
          <option value="">All Categories</option>
          <?php foreach ($categories_list as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo ($cat_filter === (int)$cat['id']) ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="search" placeholder="Search payee/ref/num..." value="<?php echo e($search); ?>" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px; min-width: 130px;">
        <button class="btn-sm" type="submit">Filter</button>
      </form>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Expense Number</th>
          <th>Date</th>
          <th>Category</th>
          <th>Payee</th>
          <th>Tax / Total</th>
          <th>Status</th>
          <th>Recorded By</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr>
            <td colspan="8" style="text-align: center; color: var(--text3); padding: 30px;">
              No expenses recorded matching filters.
            </td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td><span class="code-badge"><?php echo e($row['expense_number']); ?></span></td>
              <td><?php echo e(date('Y-m-d', strtotime($row['expense_date']))); ?></td>
              <td><span class="status-pill pill-blue" style="font-size: 10px; font-weight:600;"><?php echo e($row['category_name']); ?></span></td>
              <td class="td-name"><?php echo e($row['payee'] ?? 'N/A'); ?></td>
              <td>
                <div style="font-size: 10px; color: var(--text3)">Tax: <?php echo formatCurrency($row['tax_amount'], $bizCur); ?></div>
                <div class="td-bold" style="color:var(--red);"><?php echo formatCurrency($row['total_amount'], $bizCur); ?></div>
              </td>
              <td>
                <?php if ($row['status'] === 'POSTED'): ?>
                  <span class="status-pill pill-green">Posted</span>
                <?php elseif ($row['status'] === 'DRAFT'): ?>
                  <span class="status-pill pill-amber">Draft</span>
                <?php else: ?>
                  <span class="status-pill pill-red" style="opacity: 0.6;">Voided</span>
                <?php endif; ?>
              </td>
              <td><?php echo e($row['first_name'] ? ($row['first_name'] . ' ' . $row['last_name']) : 'System'); ?></td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 4px;">
                  <?php if ($canPostExpense && $row['status'] === 'DRAFT'): ?>
                    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Post this expense transaction to accounts?');">
                      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                      <input type="hidden" name="action" value="post_expense">
                      <input type="hidden" name="expense_id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn-sm" style="background:var(--green); color:#fff; border:none;">Post</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canVoidExpense && $row['status'] !== 'VOIDED'): ?>
                    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('VOID this expense transaction? This cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                      <input type="hidden" name="action" value="void_expense">
                      <input type="hidden" name="expense_id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn-action reject" style="font-size:10px; padding:3px 6px;">Void</button>
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
            <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page - 1); ?>&status=<?php echo e($status_filter); ?>&expense_category_id=<?php echo $cat_filter; ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Previous</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a class="btn-sm <?php echo ($i === $page) ? 'active' : ''; ?>" style="text-decoration:none;" href="index.php?page=<?php echo $i; ?>&status=<?php echo e($status_filter); ?>&expense_category_id=<?php echo $cat_filter; ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a class="btn-sm" style="text-decoration:none;" href="index.php?page=<?php echo ($page + 1); ?>&status=<?php echo e($status_filter); ?>&expense_category_id=<?php echo $cat_filter; ?>&search=<?php echo e($search); ?><?php echo isset($_GET['role']) ? '&role='.e($_GET['role']) : ''; ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ==========================================
     MODAL: RECORD EXPENSE
     ========================================== -->
<!-- ==========================================
     MODAL: LOG EXPENSE
     ========================================== -->
<?php if ($canCreateExpense): ?>
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        Log Expense Ticket
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addExpForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create_expense">

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_num">Expense Number <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="expense_number" id="e_num" placeholder="e.g. EXP-2026-0004" value="EXP-<?php echo date('YmdHis'); ?>" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_date">Expense Date <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="datetime-local" name="expense_date" id="e_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_cat">Expense Category <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="expense_category_id" id="e_cat" required>
              <option value="">-- Choose Category --</option>
              <?php foreach ($categories_list as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_loc">Target Location (Branch/Office)</label>
          <div class="field-wrap">
            <select name="location_id" id="e_loc">
              <option value="">-- None / General Business --</option>
              <?php foreach ($locations_list as $loc): ?>
                <option value="<?php echo $loc['id']; ?>"><?php echo e($loc['name']); ?> [<?php echo e($loc['code']); ?>]</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_payee">Payee Name</label>
          <div class="field-wrap">
            <input type="text" name="payee" id="e_payee" placeholder="e.g. MTN Rwanda / Landlord">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_amount">Subtotal Amount (<?php echo e($bizCur); ?>) <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="number" name="amount" id="e_amount" step="0.0001" min="0.0001" placeholder="0.0000" oninput="calcTotals()" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_tax">Tax Amount (VAT) (<?php echo e($bizCur); ?>)</label>
          <div class="field-wrap">
            <input type="number" name="tax_amount" id="e_tax" step="0.0001" min="0" value="0.0000" oninput="calcTotals()">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_total">Total Amount (<?php echo e($bizCur); ?>)</label>
          <div class="field-wrap">
            <input type="number" name="total_amount" id="e_total" step="0.0001" min="0" placeholder="0.0000" readonly style="opacity: 0.7; background: var(--bg);">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_method">Payment Method</label>
          <div class="field-wrap">
            <select name="payment_method" id="e_method">
              <option value="CASH">Cash</option>
              <option value="BANK_TRANSFER">Bank Transfer</option>
              <option value="MOBILE_MONEY">Mobile Money (MoMo)</option>
              <option value="CARD">Card Payment</option>
              <option value="CHEQUE">Cheque</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_ref">Receipt Reference / Invoice No</label>
          <div class="field-wrap">
            <input type="text" name="receipt_reference" id="e_ref" placeholder="e.g. MTN-92837">
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="e_status">Initial status</label>
          <div class="field-wrap">
            <select name="status" id="e_status">
              <option value="POSTED">POSTED (Active immediately)</option>
              <option value="DRAFT">DRAFT (Requires review)</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="e_desc">Description / Particulars</label>
          <div class="field-wrap">
            <input type="text" name="description" id="e_desc" placeholder="e.g. Office internet subscription">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="addBtn">Save Expense</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==========================================
     MODAL: ADD EXPENSE CATEGORY
     ========================================== -->
<?php if ($canCreateExpense): ?>
<div class="modal-overlay" id="catModalOverlay">
  <div class="modal-content-card modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
        Add Expense Category
      </div>
      <button type="button" class="modal-close-btn" onclick="closeCatModal()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addCatForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create_category">
        
        <div class="field" style="margin-bottom: 12px;">
          <label for="c_name">Category Name <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="name" id="c_name" placeholder="e.g. Utilities, Rent, Salaries" required>
          </div>
        </div>

        <div class="field">
          <label for="c_desc">Description</label>
          <div class="field-wrap">
            <input type="text" name="description" id="c_desc" placeholder="Brief outline of expenses under this category">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeCatModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="addCatBtn">Save Category</button>
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

function openCatModal() {
  document.getElementById('catModalOverlay').style.display = 'flex';
}

function closeCatModal() {
  document.getElementById('catModalOverlay').style.display = 'none';
}

// Close modals when clicking outside
document.getElementById('addModalOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});
document.getElementById('catModalOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeCatModal();
});

function calcTotals() {
  const amt = parseFloat(document.getElementById('e_amount').value) || 0;
  const tax = parseFloat(document.getElementById('e_tax').value) || 0;
  document.getElementById('e_total').value = (amt + tax).toFixed(4);
}

// Safeguard double submissions client-side
document.getElementById('addExpForm')?.addEventListener('submit', function() {
  document.getElementById('addBtn').disabled = true;
  document.getElementById('addBtn').style.opacity = '0.7';
  document.getElementById('addBtn').textContent = 'Posting...';
});
document.getElementById('addCatForm')?.addEventListener('submit', function() {
  document.getElementById('addCatBtn').disabled = true;
  document.getElementById('addCatBtn').style.opacity = '0.7';
  document.getElementById('addCatBtn').textContent = 'Saving...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
