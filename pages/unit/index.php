<?php
$page_title = 'Units of Measure';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = (int)($_SESSION['membership_id'] ?? 0);
requirePermission($conn, $membershipId, $businessId, $permissions['view']);
$canCreate = hasPermission($conn, $membershipId, $businessId, $permissions['create']);
$canUpdate = hasPermission($conn, $membershipId, $businessId, $permissions['update']);
$csrfToken = generateCsrfToken();
$roleQuery = getRolePreviewQuery();
$stmt = mysqli_prepare($conn, 'SELECT u.id,u.business_id,u.code,u.name,u.symbol,COUNT(p.id) product_count FROM units_of_measure u LEFT JOIN products p ON p.uom_id=u.id AND p.business_id=? WHERE u.business_id IS NULL OR u.business_id=? GROUP BY u.id,u.business_id,u.code,u.name,u.symbol ORDER BY u.business_id IS NULL DESC,u.name');
mysqli_stmt_bind_param($stmt, 'ii', $businessId, $businessId);
mysqli_stmt_execute($stmt);
$units = [];$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) $units[] = $row;
$customCount = count(array_filter($units, static fn($unit) => $unit['business_id'] !== null));
?>
<div class="catalog-page-head"><div><h1>Units of Measure</h1><p>Create clear, reusable units for product quantities and transactions.</p></div><a class="btn-sm" href="../product/index.php<?php echo e($roleQuery); ?>">Back to Products</a></div>
<div class="unit-summary"><div class="card"><span>Available Units</span><strong><?php echo count($units); ?></strong></div><div class="card"><span>Custom Units</span><strong><?php echo $customCount; ?></strong></div></div>
<div class="unit-layout">
  <?php if ($canCreate): ?>
  <section class="card unit-create-card"><div class="card-header"><div><div class="card-title">Create Unit</div><p>Define a short code, readable name, and optional symbol.</p></div></div><form action="backend.php<?php echo e($roleQuery); ?>" method="POST" class="unit-form"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="create"><label>Unit Code<span>Used in product labels</span><input name="code" maxlength="32" placeholder="PACK" required></label><label>Unit Name<span>Full display name</span><input name="name" maxlength="100" placeholder="Pack" required></label><label>Symbol<span>Optional short symbol</span><input name="symbol" maxlength="20" placeholder="pk"></label><button class="btn-primary" type="submit">Create Unit</button></form></section>
  <?php endif; ?>
  <section class="card unit-list-card"><div class="card-header"><div><div class="card-title">Available Units</div><p>Default units are shared and protected. Company units can be edited or removed.</p></div></div><div class="unit-list">
    <?php foreach ($units as $unit): ?>
      <?php if ($unit['business_id'] === null): ?><article class="unit-item default-unit"><div class="unit-identity"><span class="unit-code"><?php echo e($unit['code']); ?></span><div><strong><?php echo e($unit['name']); ?></strong><small><?php echo e($unit['symbol'] ?: 'No symbol'); ?> · <?php echo (int)$unit['product_count']; ?> product(s)</small></div></div><span class="status-pill pill-green">Default</span></article>
      <?php else: ?><form class="unit-item editable-unit" action="backend.php<?php echo e($roleQuery); ?>" method="POST"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="unit_id" value="<?php echo (int)$unit['id']; ?>"><div class="unit-edit-grid"><label>Code<input name="code" maxlength="32" value="<?php echo e($unit['code']); ?>" required></label><label>Name<input name="name" maxlength="100" value="<?php echo e($unit['name']); ?>" required></label><label>Symbol<input name="symbol" maxlength="20" value="<?php echo e($unit['symbol']); ?>"></label></div><div class="unit-item-actions"><small><?php echo (int)$unit['product_count']; ?> product(s)</small><?php if ($canUpdate): ?><button class="btn-sm" name="action" value="update">Save</button><button class="btn-sm danger-button" name="action" value="delete" formnovalidate onclick="return confirm('Delete this unit of measure?');">Delete</button><?php endif; ?></div></form><?php endif; ?>
    <?php endforeach; ?>
  </div></section>
</div>
<style>
.catalog-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:16px}.catalog-page-head h1{margin:0;color:var(--text);font-size:21px}.catalog-page-head p,.card-header p{margin:5px 0 0;color:var(--text3);font-size:11px}.unit-summary{display:grid;grid-template-columns:repeat(2,minmax(0,180px));gap:10px;margin-bottom:14px}.unit-summary .card{padding:13px 15px;box-shadow:none}.unit-summary span{display:block;color:var(--text3);font-size:9px;text-transform:uppercase}.unit-summary strong{display:block;margin-top:6px;font-size:18px}.unit-layout{display:grid;grid-template-columns:minmax(250px,.7fr) minmax(480px,1.3fr);gap:14px;align-items:start}.unit-create-card,.unit-list-card{overflow:hidden;box-shadow:none}.unit-form{display:grid;gap:13px;padding:17px}.unit-form label,.unit-edit-grid label{display:flex;flex-direction:column;gap:5px;color:var(--text2);font-size:10px;font-weight:650}.unit-form label span{color:var(--text3);font-size:9px;font-weight:400}.unit-form input,.unit-edit-grid input{width:100%;min-height:38px;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);color:var(--text);outline:none}.unit-form input:focus,.unit-edit-grid input:focus{border-color:var(--border-hover)}.unit-list{padding:0 17px 14px}.unit-item{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 0;border-bottom:1px solid var(--table-border)}.unit-item:last-child{border-bottom:0}.unit-identity{display:flex;align-items:center;gap:12px}.unit-code{min-width:58px;padding:7px 8px;border-radius:var(--radius);background:var(--bg);text-align:center;color:var(--text);font-size:10px;font-weight:700}.unit-identity strong,.unit-identity small{display:block}.unit-identity strong{font-size:11px}.unit-identity small,.unit-item-actions small{margin-top:4px;color:var(--text3);font-size:9px}.editable-unit{align-items:end}.unit-edit-grid{display:grid;grid-template-columns:100px minmax(150px,1fr) 100px;gap:9px;flex:1}.unit-item-actions{display:flex;align-items:center;gap:6px;white-space:nowrap}.danger-button{color:var(--red)!important}@media(max-width:900px){.unit-layout{grid-template-columns:1fr}}@media(max-width:650px){.catalog-page-head,.editable-unit{align-items:stretch;flex-direction:column}.unit-summary{grid-template-columns:1fr 1fr}.unit-edit-grid{grid-template-columns:1fr}.unit-item-actions{justify-content:flex-end}}
</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
