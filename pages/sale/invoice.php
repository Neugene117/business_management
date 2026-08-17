<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/tenant.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireActiveBusiness($conn);

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = (int)($_SESSION['membership_id'] ?? 0);
requirePermission($conn, $membershipId, $businessId, $permissions['view']);

$saleId = (int)($_GET['id'] ?? 0);
$downloadInvoice = ($_GET['download'] ?? '') === '1';
$role = getPreviewRole();
$roleSuffix = $role ? '&role=' . rawurlencode($role) : '';

$saleStmt = mysqli_prepare($conn, "
    SELECT s.*,
           c.customer_code,c.name customer_name,c.phone customer_phone,c.email customer_email,
           c.tax_number customer_tax,c.address customer_address,
           l.name location_name,l.code location_code,l.address location_address,
           b.business_name,b.legal_name,b.company_logo_path,b.phone business_phone,b.email business_email,
           b.registration_number,b.tax_number business_tax,b.address_line1,b.address_line2,b.city,
           b.state_region,b.postal_code,b.country_code,b.currency_code,b.timezone,
           TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) cashier_name
    FROM sales s
    JOIN businesses b ON b.id=s.business_id
    JOIN business_locations l ON l.id=s.location_id AND l.business_id=s.business_id
    LEFT JOIN customers c ON c.id=s.customer_id AND c.business_id=s.business_id
    LEFT JOIN business_memberships bm ON bm.id=s.cashier_membership_id AND bm.business_id=s.business_id
    LEFT JOIN users u ON u.id=bm.user_id
    WHERE s.id=? AND s.business_id=?
    LIMIT 1
");
mysqli_stmt_bind_param($saleStmt, 'ii', $saleId, $businessId);
mysqli_stmt_execute($saleStmt);
$sale = mysqli_fetch_assoc(mysqli_stmt_get_result($saleStmt));

if (!$sale) {
    setFlashMessage('error', 'Sales invoice not found.');
    header('Location: index.php?view=history' . $roleSuffix);
    exit();
}

$config = getBusinessInventoryConfig($conn, $businessId);
$timezone = (string)($sale['timezone'] ?: $config['timezone']);
$currency = (string)($sale['currency_code'] ?: 'RWF');

$itemStmt = mysqli_prepare($conn, "
    SELECT si.*,p.name product_name,p.sku,su.code sale_uom,
           COALESCE((
               SELECT SUM(sri.quantity)
               FROM sale_return_items sri
               JOIN sale_returns sr ON sr.id=sri.sale_return_id
               WHERE sri.sale_item_id=si.id AND sr.status='COMPLETED'
           ),0) returned_quantity
    FROM sale_items si
    JOIN products p ON p.id=si.product_id AND p.business_id=si.business_id
    JOIN units_of_measure su ON su.id=si.sale_uom_id
    WHERE si.sale_id=? AND si.business_id=?
    ORDER BY si.id
");
mysqli_stmt_bind_param($itemStmt, 'ii', $saleId, $businessId);
mysqli_stmt_execute($itemStmt);
$itemResult = mysqli_stmt_get_result($itemStmt);
$invoiceItems = [];
while ($item = mysqli_fetch_assoc($itemResult)) $invoiceItems[] = $item;

$paymentStmt = mysqli_prepare($conn, "
    SELECT sp.payment_method,sp.amount,sp.reference_number,sp.paid_at,
           TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) recorded_by
    FROM sale_payments sp
    LEFT JOIN business_memberships bm ON bm.id=sp.recorded_by_membership_id AND bm.business_id=sp.business_id
    LEFT JOIN users u ON u.id=bm.user_id
    WHERE sp.sale_id=? AND sp.business_id=?
    ORDER BY sp.paid_at,sp.id
");
mysqli_stmt_bind_param($paymentStmt, 'ii', $saleId, $businessId);
mysqli_stmt_execute($paymentStmt);
$paymentResult = mysqli_stmt_get_result($paymentStmt);
$invoicePayments = [];
while ($payment = mysqli_fetch_assoc($paymentResult)) $invoicePayments[] = $payment;

$returnStmt = mysqli_prepare($conn, "
    SELECT return_number,returned_at,reason,refund_amount,status
    FROM sale_returns
    WHERE sale_id=? AND business_id=? AND status='COMPLETED'
    ORDER BY returned_at,id
");
mysqli_stmt_bind_param($returnStmt, 'ii', $saleId, $businessId);
mysqli_stmt_execute($returnStmt);
$returnResult = mysqli_stmt_get_result($returnStmt);
$invoiceReturns = [];
$refundTotal = 0.0;
while ($return = mysqli_fetch_assoc($returnResult)) {
    $invoiceReturns[] = $return;
    $refundTotal += (float)$return['refund_amount'];
}

$amountDue = max(0, (float)$sale['total_amount'] - (float)$sale['amount_paid']);
$statusClass = strtolower(str_replace('_', '-', (string)$sale['status']));
$paymentStatus = $amountDue <= 0.00005 ? 'Paid' : ((float)$sale['amount_paid'] > 0 ? 'Partially paid' : 'Payment due');
if ($sale['status'] === 'VOIDED') $paymentStatus = 'Voided';
if ($sale['status'] === 'REFUNDED') $paymentStatus = 'Refunded';

$businessAddress = array_values(array_filter([
    trim((string)$sale['address_line1']),
    trim((string)$sale['address_line2']),
    trim(implode(', ', array_filter([(string)$sale['city'], (string)$sale['state_region'], (string)$sale['postal_code']]))),
    trim((string)$sale['country_code'])
]));
$sellerName = trim((string)($sale['legal_name'] ?: $sale['business_name']));
$brandWords = preg_split('/\s+/', trim((string)$sale['business_name'])) ?: [];
$brandInitials = '';
foreach (array_slice($brandWords, 0, 2) as $word) $brandInitials .= strtoupper(substr($word, 0, 1));
$brandInitials = $brandInitials ?: 'BM';
$logoUrl = getCompanyLogoUrl($sale['company_logo_path'] ?? null, '../../');
$logoDownloadData = null;
if ($downloadInvoice && $logoUrl) {
    $logoPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$sale['company_logo_path']);
    if (is_file($logoPath)) {
        $mime = mime_content_type($logoPath) ?: 'image/png';
        $logoDownloadData = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($logoPath));
    }
}
$invoiceLogo = $downloadInvoice ? $logoDownloadData : $logoUrl;
$safeInvoiceNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$sale['sale_number']);

ob_start();
?>
<article class="sales-invoice" id="salesInvoice">
  <header class="invoice-document-header">
    <div class="invoice-logo-column">
      <div class="invoice-logo-frame"><?php if ($invoiceLogo): ?><img src="<?php echo e($invoiceLogo); ?>" alt="<?php echo e($sale['business_name']); ?> logo"><?php else: ?><span class="invoice-brand-mark" aria-hidden="true"><?php echo e($brandInitials); ?></span><?php endif; ?></div>
      <div class="invoice-document-identity"><span>Sales invoice</span><strong><?php echo e($sale['sale_number']); ?></strong><em class="invoice-status status-<?php echo e($statusClass); ?>"><?php echo e(str_replace('_', ' ', $sale['status'])); ?></em></div>
    </div>

    <div class="invoice-contact-column">
      <section class="invoice-contact-block invoice-company-information">
        <span class="invoice-section-label">Company information</span>
        <h1><?php echo e($sale['business_name']); ?></h1>
        <?php if ($sellerName !== $sale['business_name']): ?><p class="invoice-legal-name"><?php echo e($sellerName); ?></p><?php endif; ?>
        <?php if ($businessAddress): ?><p class="invoice-address"><?php echo implode('<br>', array_map('e', $businessAddress)); ?></p><?php endif; ?>
        <dl class="invoice-contact-grid">
          <?php if ($sale['business_phone']): ?><div><dt>Phone</dt><dd><?php echo e($sale['business_phone']); ?></dd></div><?php endif; ?>
          <?php if ($sale['business_email']): ?><div><dt>Email</dt><dd><?php echo e($sale['business_email']); ?></dd></div><?php endif; ?>
          <?php if ($sale['business_tax']): ?><div><dt>Tax ID</dt><dd><?php echo e($sale['business_tax']); ?></dd></div><?php endif; ?>
          <?php if ($sale['registration_number']): ?><div><dt>Registration</dt><dd><?php echo e($sale['registration_number']); ?></dd></div><?php endif; ?>
        </dl>
      </section>

      <section class="invoice-contact-block invoice-customer-information">
        <span class="invoice-section-label">Customer information</span>
        <h2><?php echo e($sale['customer_name'] ?: 'Walk-in customer'); ?></h2>
        <?php if ($sale['customer_address']): ?><p class="invoice-address"><?php echo nl2br(e($sale['customer_address'])); ?></p><?php elseif (!$sale['customer_id']): ?><p class="invoice-address">No customer account attached to this sale.</p><?php endif; ?>
        <dl class="invoice-contact-grid">
          <?php if ($sale['customer_code']): ?><div><dt>Customer ID</dt><dd><?php echo e($sale['customer_code']); ?></dd></div><?php endif; ?>
          <?php if ($sale['customer_phone']): ?><div><dt>Phone</dt><dd><?php echo e($sale['customer_phone']); ?></dd></div><?php endif; ?>
          <?php if ($sale['customer_email']): ?><div><dt>Email</dt><dd><?php echo e($sale['customer_email']); ?></dd></div><?php endif; ?>
          <?php if ($sale['customer_tax']): ?><div><dt>Tax ID</dt><dd><?php echo e($sale['customer_tax']); ?></dd></div><?php endif; ?>
        </dl>
      </section>
    </div>
  </header>

  <?php if ($sale['status'] === 'VOIDED'): ?><div class="invoice-alert invoice-alert-danger"><strong>Voided invoice</strong><span>This transaction was reversed and should not be treated as payable.</span></div><?php elseif ($invoiceReturns): ?><div class="invoice-alert"><strong>Return activity recorded</strong><span>This invoice has <?php echo count($invoiceReturns); ?> completed return<?php echo count($invoiceReturns) === 1 ? '' : 's'; ?> totaling <?php echo formatCurrency($refundTotal, $currency); ?>.</span></div><?php endif; ?>

  <section class="invoice-facts-card invoice-facts-wide">
    <div><span>Invoice date</span><strong><?php echo e(formatDate($sale['sold_at'], $timezone, 'd M Y')); ?></strong><small><?php echo e(formatDate($sale['sold_at'], $timezone, 'H:i')); ?> local time</small></div>
    <div><span>Payment status</span><strong><?php echo e($paymentStatus); ?></strong><small><?php echo e($currency); ?> transaction</small></div>
    <div><span>Sales location</span><strong><?php echo e($sale['location_name']); ?></strong><small><?php echo e($sale['location_code']); ?><?php echo $sale['location_address'] ? ' · ' . e($sale['location_address']) : ''; ?></small></div>
    <div><span>Prepared by</span><strong><?php echo e($sale['cashier_name'] ?: 'Authorized cashier'); ?></strong><small>Sales representative</small></div>
  </section>

  <section class="invoice-lines-section">
    <div class="invoice-section-heading"><div><span>Invoice items</span><h2>Products and charges</h2></div><small><?php echo count($invoiceItems); ?> line item<?php echo count($invoiceItems) === 1 ? '' : 's'; ?></small></div>
    <div class="invoice-table-scroll">
      <table class="invoice-lines">
        <thead><tr><th class="invoice-line-number">#</th><th>Product</th><th class="align-right">Quantity</th><th>Unit</th><th class="align-right">Unit price</th><th class="align-right">Discount</th><th class="align-right">Tax</th><th class="align-right">Total</th></tr></thead>
        <tbody>
          <?php foreach ($invoiceItems as $index => $item):
            $returnedEntered = convertBaseQuantityToTransaction($item['returned_quantity'], $item['conversion_factor_to_base']);
          ?>
          <tr>
            <td class="invoice-line-number"><?php echo $index + 1; ?></td>
            <td><strong><?php echo e($item['product_name']); ?></strong><small>SKU <?php echo e($item['sku']); ?></small></td>
            <td class="align-right"><strong><?php echo e(formatInventoryDecimal($item['sale_quantity'])); ?></strong><?php if ($returnedEntered > 0): ?><small class="invoice-returned-quantity"><?php echo e(formatInventoryDecimal($returnedEntered)); ?> returned</small><?php endif; ?></td>
            <td><span class="invoice-unit-badge"><?php echo e($item['sale_uom']); ?></span></td>
            <td class="align-right"><?php echo formatCurrency($item['sale_unit_price'], $currency); ?></td>
            <td class="align-right"><?php echo formatCurrency($item['discount_amount'], $currency); ?></td>
            <td class="align-right"><?php echo formatCurrency($item['tax_amount'], $currency); ?></td>
            <td class="align-right invoice-line-amount"><?php echo formatCurrency($item['line_total'], $currency); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="invoice-settlement">
    <div class="invoice-supporting-details">
      <?php if ($sale['notes']): ?><div class="invoice-note"><span class="invoice-section-label">Invoice notes</span><p><?php echo nl2br(e($sale['notes'])); ?></p></div><?php endif; ?>
      <div class="invoice-payment-summary">
        <span class="invoice-section-label">Payment history</span>
        <?php if (!$invoicePayments): ?><p class="invoice-empty">No payment has been recorded for this invoice.</p><?php else: ?>
          <?php foreach ($invoicePayments as $payment): ?><div class="invoice-payment-row"><span><strong><?php echo e(ucwords(strtolower(str_replace('_', ' ', $payment['payment_method'])))); ?></strong><small><?php echo e(formatDate($payment['paid_at'], $timezone, 'd M Y · H:i')); ?><?php echo $payment['reference_number'] ? ' · ' . e($payment['reference_number']) : ''; ?></small></span><b><?php echo formatCurrency($payment['amount'], $currency); ?></b></div><?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="invoice-totals">
      <div><span>Subtotal</span><strong><?php echo formatCurrency($sale['subtotal'], $currency); ?></strong></div>
      <?php if ((float)$sale['discount_amount'] > 0): ?><div><span>Discount</span><strong>-<?php echo formatCurrency($sale['discount_amount'], $currency); ?></strong></div><?php endif; ?>
      <div><span><?php echo e($sale['tax_name'] ?: 'Tax'); ?><?php if ($sale['tax_type'] === 'PERCENTAGE'): ?> (<?php echo e(formatInventoryDecimal($sale['tax_value'])); ?>%)<?php endif; ?></span><strong><?php echo formatCurrency($sale['tax_amount'], $currency); ?></strong></div>
      <div class="invoice-total-row"><span>Invoice total</span><strong><?php echo formatCurrency($sale['total_amount'], $currency); ?></strong></div>
      <div><span>Amount paid</span><strong><?php echo formatCurrency($sale['amount_paid'], $currency); ?></strong></div>
      <?php if ($refundTotal > 0): ?><div><span>Refunded</span><strong>-<?php echo formatCurrency($refundTotal, $currency); ?></strong></div><?php endif; ?>
      <div class="invoice-balance-row"><span>Amount due</span><strong><?php echo formatCurrency($amountDue, $currency); ?></strong></div>
    </div>
  </section>

  <?php if ($invoiceReturns): ?>
  <section class="invoice-returns-section">
    <div class="invoice-section-heading"><div><span>Adjustments</span><h2>Returns and refunds</h2></div><small><?php echo formatCurrency($refundTotal, $currency); ?> refunded</small></div>
    <div class="invoice-return-list"><?php foreach ($invoiceReturns as $return): ?><article><div><strong><?php echo e($return['return_number']); ?></strong><small><?php echo e(formatDate($return['returned_at'], $timezone, 'd M Y · H:i')); ?></small></div><p><?php echo e($return['reason'] ?: 'No reason supplied'); ?></p><b><?php echo formatCurrency($return['refund_amount'], $currency); ?></b></article><?php endforeach; ?></div>
  </section>
  <?php endif; ?>

  <footer class="invoice-footer">
    <div><strong>Thank you for your business.</strong><span>Please retain this invoice for your records.</span></div>
    <div><span>Invoice <?php echo e($sale['sale_number']); ?></span><small>Generated <?php echo e(formatDate(gmdate('Y-m-d H:i:s'), $timezone, 'd M Y · H:i')); ?></small></div>
  </footer>
</article>
<?php
$invoiceDocument = ob_get_clean();

if ($downloadInvoice) {
    $invoiceCss = (string)file_get_contents(__DIR__ . '/../../src/css/sale-invoice.css');
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="Sales-Invoice-' . $safeInvoiceNumber . '.html"');
    header('X-Content-Type-Options: nosniff');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sales Invoice ' . e($sale['sale_number']) . '</title><style>' . $invoiceCss . '</style></head><body class="invoice-download-body"><main class="invoice-download-shell">' . $invoiceDocument . '</main></body></html>';
    exit();
}

$page_title = 'Sales Invoice ' . $sale['sale_number'];
$extra_css = ['sale-invoice.css'];
require_once __DIR__ . '/../../includes/header.php';
?>
<main class="invoice-page">
  <nav class="invoice-toolbar" aria-label="Invoice actions">
    <a class="invoice-back-link" href="index.php?view=history<?php echo e($roleSuffix); ?>"><span aria-hidden="true">←</span> Sales history</a>
    <div class="invoice-toolbar-copy"><strong>Sales invoice</strong><span><?php echo e($sale['sale_number']); ?></span></div>
    <div class="invoice-toolbar-actions"><a class="invoice-action-button" href="invoice?id=<?php echo $saleId; ?>&download=1<?php echo e($roleSuffix); ?>" download>Download invoice</a><button type="button" class="invoice-action-button invoice-print-button" onclick="window.print()">Print / Save PDF</button></div>
  </nav>
  <?php echo $invoiceDocument; ?>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
