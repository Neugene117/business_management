<?php
$isReportWorkerRequest = PHP_SAPI === 'cli' && in_array('--run-due', $argv ?? [], true);
require_once __DIR__ . '/../../config/database.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
require_once __DIR__ . '/../../includes/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!$isReportWorkerRequest) {
    require_once __DIR__ . '/../../config/session.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/tenant.php';
    require_once __DIR__ . '/../../includes/permission_helper.php';
    require_once __DIR__ . '/../../includes/csrf.php';
    require_once __DIR__ . '/../../includes/flash.php';
    require_once __DIR__ . '/../../includes/audit.php';
}

function reportPeriodForSchedule(array $schedule, DateTimeImmutable $runAtUtc): array {
    $end = $runAtUtc;
    if ($schedule['frequency'] === 'DAILY') {
        $start = $end->modify('-1 day');
    } elseif ($schedule['frequency'] === 'WEEKLY') {
        $start = $end->modify('-7 days');
    } else {
        $start = $end->modify('-1 month');
    }
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function queryReportTotal(mysqli $conn, string $sql, int $businessId, string $start, string $end): float {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iss', $businessId, $start, $end);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (float)($row['total'] ?? 0);
}

function queryReportRows(mysqli $conn, string $sql, string $types, array $values): array {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$values);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

function generateScheduledReport(mysqli $conn, array $schedule, DateTimeImmutable $runAtUtc): array {
    $businessId = (int)$schedule['business_id'];
    [$periodStart, $periodEnd] = reportPeriodForSchedule($schedule, $runAtUtc);

    $returnedQuantity = "COALESCE((SELECT SUM(sri.quantity) FROM sale_return_items sri JOIN sale_returns sr ON sr.id=sri.sale_return_id WHERE sri.sale_item_id=si.id AND sr.status='COMPLETED'),0)";
    $totalSales = queryReportTotal($conn, "SELECT COALESCE(SUM((si.quantity-$returnedQuantity)*si.unit_price + CASE WHEN si.quantity>0 THEN ((si.quantity-$returnedQuantity)/si.quantity)*si.tax_amount ELSE 0 END),0) total FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.business_id=si.business_id WHERE si.business_id=? AND s.status<>'VOIDED' AND s.sold_at>=? AND s.sold_at<?", $businessId, $periodStart, $periodEnd);
    $totalRevenue = queryReportTotal($conn, "SELECT COALESCE(SUM((si.quantity-$returnedQuantity)*si.unit_price),0) total FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.business_id=si.business_id WHERE si.business_id=? AND s.status<>'VOIDED' AND s.sold_at>=? AND s.sold_at<?", $businessId, $periodStart, $periodEnd);
    $totalCogs = queryReportTotal($conn, "SELECT COALESCE(SUM((si.quantity-$returnedQuantity)*si.unit_cost_at_sale),0) total FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.business_id=si.business_id WHERE si.business_id=? AND s.status<>'VOIDED' AND s.sold_at>=? AND s.sold_at<?", $businessId, $periodStart, $periodEnd);
    $totalExpenses = queryReportTotal($conn, "SELECT COALESCE(SUM(total_amount), 0) total FROM expenses WHERE business_id = ? AND status = 'POSTED' AND expense_date BETWEEN ? AND ?", $businessId, $periodStart, $periodEnd);
    $totalPurchases = queryReportTotal($conn, "SELECT COALESCE(SUM(quantity_delta*unit_cost),0) total FROM inventory_movements WHERE business_id=? AND movement_type='PURCHASE_RECEIPT' AND occurred_at>=? AND occurred_at<?", $businessId, $periodStart, $periodEnd);
    $inventoryLoss = queryReportTotal($conn, 'SELECT COALESCE(SUM(loss_value),0) total FROM v_inventory_loss_movements WHERE business_id=? AND occurred_at BETWEEN ? AND ?', $businessId, $periodStart, $periodEnd);
    $grossProfit = $totalRevenue - $totalCogs;
    $netProfit = $grossProfit - $totalExpenses - $inventoryLoss;

    $currentStock = queryReportRows($conn, "SELECT p.sku,p.name product_name,l.name location_name,ib.quantity_on_hand,ib.available_quantity,ib.average_unit_cost,ib.stock_value FROM inventory_balances ib JOIN products p ON p.business_id=ib.business_id AND p.id=ib.product_id JOIN business_locations l ON l.business_id=ib.business_id AND l.id=ib.location_id WHERE ib.business_id=? ORDER BY p.name,l.name", 'i', [$businessId]);
    $purchasedItems = queryReportRows($conn, "SELECT pu.purchase_number,m.occurred_at purchase_date,pu.status,p.name product_name,m.quantity_delta quantity,m.unit_cost,m.quantity_delta*m.unit_cost line_total FROM inventory_movements m JOIN purchase_items pi ON pi.id=m.purchase_item_id AND pi.business_id=m.business_id JOIN purchases pu ON pu.id=pi.purchase_id AND pu.business_id=pi.business_id JOIN products p ON p.id=m.product_id AND p.business_id=m.business_id WHERE m.business_id=? AND m.movement_type='PURCHASE_RECEIPT' AND m.occurred_at>=? AND m.occurred_at<? ORDER BY m.occurred_at,p.name", 'iss', [$businessId,$periodStart,$periodEnd]);
    $soldItems = queryReportRows($conn, "SELECT s.sale_number,s.sold_at,p.name product_name,(si.quantity-$returnedQuantity) quantity,si.unit_price,(si.quantity-$returnedQuantity)*si.unit_price line_total,(si.quantity-$returnedQuantity)*si.unit_cost_at_sale cogs_total FROM sale_items si JOIN sales s ON s.business_id=si.business_id AND s.id=si.sale_id JOIN products p ON p.business_id=si.business_id AND p.id=si.product_id WHERE si.business_id=? AND s.status<>'VOIDED' AND s.sold_at>=? AND s.sold_at<? AND (si.quantity-$returnedQuantity)>0 ORDER BY s.sold_at,p.name", 'iss', [$businessId,$periodStart,$periodEnd]);
    $expenseItems = queryReportRows($conn, "SELECT e.expense_number,e.expense_date,c.name category,e.payee,e.description,e.total_amount FROM expenses e JOIN expense_categories c ON c.business_id=e.business_id AND c.id=e.expense_category_id WHERE e.business_id=? AND e.status='POSTED' AND e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date", 'iss', [$businessId,$periodStart,$periodEnd]);
    $lossItems = queryReportRows($conn, "SELECT lm.occurred_at,p.name product_name,l.name location_name,lm.movement_type,ABS(lm.quantity_delta) quantity,lm.unit_cost,lm.loss_value FROM v_inventory_loss_movements lm JOIN products p ON p.business_id=lm.business_id AND p.id=lm.product_id JOIN business_locations l ON l.business_id=lm.business_id AND l.id=lm.location_id WHERE lm.business_id=? AND lm.occurred_at BETWEEN ? AND ? ORDER BY lm.occurred_at", 'iss', [$businessId,$periodStart,$periodEnd]);
    $transactions = queryReportRows($conn, "SELECT 'Sale payment' transaction_type,sp.paid_at transaction_date,s.sale_number reference_number,sp.payment_method,sp.amount FROM sale_payments sp JOIN sales s ON s.business_id=sp.business_id AND s.id=sp.sale_id WHERE sp.business_id=? AND s.status<>'VOIDED' AND sp.paid_at>=? AND sp.paid_at<? UNION ALL SELECT 'Sale refund',sr.returned_at,sr.return_number,'REFUND',-sr.refund_amount FROM sale_returns sr WHERE sr.business_id=? AND sr.status='COMPLETED' AND sr.returned_at>=? AND sr.returned_at<? UNION ALL SELECT 'Purchase payment',pp.paid_at,pu.purchase_number,pp.payment_method,pp.amount FROM purchase_payments pp JOIN purchases pu ON pu.business_id=pp.business_id AND pu.id=pp.purchase_id WHERE pp.business_id=? AND pp.paid_at>=? AND pp.paid_at<? ORDER BY transaction_date", 'issississ', [$businessId,$periodStart,$periodEnd,$businessId,$periodStart,$periodEnd,$businessId,$periodStart,$periodEnd]);

    $insert = "INSERT INTO generated_reports
        (business_id, report_schedule_id, report_type, period_start, period_end, status, total_purchases, total_sales, total_revenue, total_cogs, total_expenses, inventory_loss_value, gross_profit, net_profit_loss, generated_at)
        VALUES (?, ?, 'BUSINESS_SUMMARY', ?, ?, 'READY', ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(6))";
    $stmt = mysqli_prepare($conn, $insert);
    $scheduleId = (int)$schedule['id'];
    mysqli_stmt_bind_param($stmt, 'iissdddddddd', $businessId, $scheduleId, $periodStart, $periodEnd, $totalPurchases, $totalSales, $totalRevenue, $totalCogs, $totalExpenses, $inventoryLoss, $grossProfit, $netProfit);
    mysqli_stmt_execute($stmt);

    return [
        'id' => mysqli_insert_id($conn),
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'total_purchases' => $totalPurchases,
        'total_sales' => $totalSales,
        'total_revenue' => $totalRevenue,
        'total_cogs' => $totalCogs,
        'total_expenses' => $totalExpenses,
        'inventory_loss_value' => $inventoryLoss,
        'gross_profit' => $grossProfit,
        'net_profit_loss' => $netProfit,
        'current_stock' => $currentStock,
        'purchased_items' => $purchasedItems,
        'sold_items' => $soldItems,
        'expenses' => $expenseItems,
        'losses' => $lossItems,
        'transactions' => $transactions
    ];
}

function reportEmailTable(string $title, array $headers, array $rows): string {
    $html = '<h3 style="margin:26px 0 10px;color:#1a2332;font-size:16px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    if (!$rows) return $html . '<p style="margin:0;color:#718096;font-size:13px">No records for this section.</p>';
    $html .= '<div style="overflow-x:auto"><table role="presentation" style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr>';
    foreach ($headers as $label) $html .= '<th style="padding:9px 8px;text-align:left;background:#eef3f5;color:#405261;border-bottom:1px solid #dfe7eb">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) $html .= '<td style="padding:9px 8px;color:#273642;border-bottom:1px solid #edf1f3;vertical-align:top">' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function buildScheduledReportEmail(array $business, array $report): string {
    $currency = $business['currency_code'];
    $money = static fn($value) => number_format((float)$value, 2) . ' ' . $currency;
    $stockRows = array_map(static fn($r) => [$r['sku'],$r['product_name'],$r['location_name'],(float)$r['quantity_on_hand'],(float)$r['available_quantity'],$money($r['stock_value'])], $report['current_stock']);
    $purchaseRows = array_map(static fn($r) => [$r['purchase_number'],substr($r['purchase_date'],0,10),$r['product_name'],(float)$r['quantity'],$money($r['unit_cost']),$money($r['line_total'])], $report['purchased_items']);
    $saleRows = array_map(static fn($r) => [$r['sale_number'],substr($r['sold_at'],0,10),$r['product_name'],(float)$r['quantity'],$money($r['line_total']),$money($r['cogs_total'])], $report['sold_items']);
    $expenseRows = array_map(static fn($r) => [$r['expense_number'],substr($r['expense_date'],0,10),$r['category'],$r['payee'],$r['description'],$money($r['total_amount'])], $report['expenses']);
    $lossRows = array_map(static fn($r) => [substr($r['occurred_at'],0,10),$r['product_name'],$r['location_name'],ucwords(strtolower(str_replace('_',' ',$r['movement_type']))),(float)$r['quantity'],$money($r['loss_value'])], $report['losses']);
    $transactionRows = array_map(static fn($r) => [$r['transaction_type'],substr($r['transaction_date'],0,16),$r['reference_number'],ucwords(strtolower(str_replace('_',' ',$r['payment_method']))),$money($r['amount'])], $report['transactions']);
    $totalProfit = max(0, (float)$report['net_profit_loss']);
    $totalLoss = (float)$report['inventory_loss_value'] + max(0, -(float)$report['net_profit_loss']);

    $html = '<!doctype html><html><body style="margin:0;background:#f2f5f7;font-family:Arial,sans-serif;color:#1a2332"><div style="max-width:900px;margin:0 auto;padding:24px"><div style="padding:24px;background:#1b4b5a;color:#fff;border-radius:10px 10px 0 0"><h1 style="margin:0;font-size:23px">' . htmlspecialchars($business['business_name'], ENT_QUOTES, 'UTF-8') . '</h1><p style="margin:7px 0 0;color:#d9e9ed;font-size:13px">Complete automated business report · ' . htmlspecialchars(substr($report['period_start'],0,16) . ' to ' . substr($report['period_end'],0,16), ENT_QUOTES, 'UTF-8') . '</p></div><div style="padding:22px;background:#fff">';
    $html .= reportEmailTable('Current stock', ['SKU','Product','Location','On hand','Available','Stock value'], $stockRows);
    $html .= reportEmailTable('Purchased items', ['Purchase','Date','Product','Quantity','Unit cost','Total'], $purchaseRows);
    $html .= reportEmailTable('Sold items', ['Sale','Date','Product','Quantity','Revenue','COGS'], $saleRows);
    $html .= reportEmailTable('Expenses', ['Expense','Date','Category','Payee','Description','Total'], $expenseRows);
    $html .= reportEmailTable('Losses', ['Date','Product','Location','Type','Quantity','Loss value'], $lossRows);
    $html .= reportEmailTable('Other transactions', ['Type','Date','Reference','Method','Amount'], $transactionRows);
    $html .= '<div style="margin-top:28px;padding:18px;background:#eef3f5;border-radius:8px"><h2 style="margin:0 0 14px;font-size:18px">Summary</h2><table role="presentation" style="width:100%;border-collapse:collapse"><tr><td style="padding:8px;color:#5a6b7f">Total revenue</td><td style="padding:8px;text-align:right;font-weight:bold">'.$money($report['total_revenue']).'</td></tr><tr><td style="padding:8px;color:#5a6b7f">Total expenses</td><td style="padding:8px;text-align:right;font-weight:bold">'.$money($report['total_expenses']).'</td></tr><tr><td style="padding:8px;color:#5a6b7f">Total profit</td><td style="padding:8px;text-align:right;font-weight:bold;color:#0f6e56">'.$money($totalProfit).'</td></tr><tr><td style="padding:8px;color:#5a6b7f">Total loss</td><td style="padding:8px;text-align:right;font-weight:bold;color:#a32d2d">'.$money($totalLoss).'</td></tr><tr><td style="padding:10px 8px;border-top:1px solid #d6e0e4;font-weight:bold">Net result</td><td style="padding:10px 8px;border-top:1px solid #d6e0e4;text-align:right;font-weight:bold">'.$money($report['net_profit_loss']).'</td></tr></table></div></div><div style="padding:14px;text-align:center;color:#718096;font-size:11px">Generated automatically by Business Management.</div></div></body></html>';
    return $html;
}

function sendScheduledReport(array $setting, string $destination, array $business, array $report): array {
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('PHPMailer is not installed.');
    require_once $autoload;
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $setting['smtp_host'];
    $mail->Port = (int)$setting['smtp_port'];
    $mail->SMTPAuth = true;
    $mail->Username = decryptReportCredential($setting['smtp_username_encrypted']);
    $mail->Password = decryptReportCredential($setting['smtp_password_encrypted']);
    if ($setting['smtp_encryption'] === 'TLS') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    elseif ($setting['smtp_encryption'] === 'SMTPS') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    else { $mail->SMTPSecure = ''; $mail->SMTPAutoTLS = false; }
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 30;
    $mail->setFrom($setting['from_email'], $setting['from_name'] ?: $business['business_name']);
    if (!empty($setting['reply_to_email'])) $mail->addReplyTo($setting['reply_to_email']);
    $mail->addAddress($destination);
    $mail->isHTML(true);
    $mail->Subject = $business['business_name'] . ' complete business report';
    $mail->Body = buildScheduledReportEmail($business, $report);
    $mail->AltBody = sprintf('%s report from %s to %s. Revenue: %.2f %s. Expenses: %.2f %s. Net result: %.2f %s.', $business['business_name'], $report['period_start'], $report['period_end'], $report['total_revenue'], $business['currency_code'], $report['total_expenses'], $business['currency_code'], $report['net_profit_loss'], $business['currency_code']);
    $mail->send();
    return ['message_id' => substr((string)$mail->getLastMessageID(), 0, 255)];
}

function runDueReportSchedules(mysqli $conn): int {
    mysqli_query($conn, "SET time_zone = '+00:00'");
    $lockResult = mysqli_query($conn, "SELECT GET_LOCK('business_management_report_scheduler', 0) AS acquired");
    $lock = mysqli_fetch_assoc($lockResult);
    if ((int)($lock['acquired'] ?? 0) !== 1) {
        return 0;
    }

    $processed = 0;
    try {
        $sql = "SELECT rs.*, b.business_name, b.currency_code
                FROM report_schedules rs
                JOIN businesses b ON b.id = rs.business_id
                WHERE rs.is_active = 1
                  AND b.approval_status = 'APPROVED'
                  AND rs.frequency IN ('DAILY','WEEKLY','MONTHLY')
                  AND rs.next_run_at IS NOT NULL
                  AND rs.next_run_at <= UTC_TIMESTAMP(6)
                  AND EXISTS (
                    SELECT 1 FROM report_schedule_recipients rr
                    JOIN report_delivery_settings ds ON ds.business_id = rr.business_id AND ds.is_active = 1
                    WHERE rr.business_id = rs.business_id AND rr.report_schedule_id = rs.id AND rr.channel = 'EMAIL'
                  )
                ORDER BY rs.next_run_at ASC LIMIT 25";
        $result = mysqli_query($conn, $sql);
        while ($schedule = mysqli_fetch_assoc($result)) {
            $runAtUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $businessId = (int)$schedule['business_id'];
            $scheduleId = (int)$schedule['id'];
            try {
                $report = generateScheduledReport($conn, $schedule, $runAtUtc);
                $recipientStmt = mysqli_prepare($conn, "SELECT rr.channel,rr.destination,ds.smtp_host,ds.smtp_port,ds.smtp_encryption,ds.smtp_username_encrypted,ds.smtp_password_encrypted,ds.from_email,ds.from_name,ds.reply_to_email
                    FROM report_schedule_recipients rr
                    JOIN report_delivery_settings ds ON ds.business_id = rr.business_id
                    WHERE rr.business_id = ? AND rr.report_schedule_id = ? AND rr.channel='EMAIL' AND ds.is_active = 1");
                mysqli_stmt_bind_param($recipientStmt, 'ii', $businessId, $scheduleId);
                mysqli_stmt_execute($recipientStmt);
                $recipients = mysqli_stmt_get_result($recipientStmt);
                while ($recipient = mysqli_fetch_assoc($recipients)) {
                    $deliveryInsert = mysqli_prepare($conn, "INSERT INTO report_deliveries (business_id, generated_report_id, channel, destination, status) VALUES (?, ?, ?, ?, 'PENDING')");
                    $reportId = (int)$report['id'];
                    mysqli_stmt_bind_param($deliveryInsert, 'iiss', $businessId, $reportId, $recipient['channel'], $recipient['destination']);
                    mysqli_stmt_execute($deliveryInsert);
                    $deliveryId = mysqli_insert_id($conn);
                    try {
                        $deliveryResult = sendScheduledReport($recipient, $recipient['destination'], $schedule, $report);
                        $deliveryUpdate = mysqli_prepare($conn, "UPDATE report_deliveries SET status='SENT', provider_message_id=?, attempted_at=UTC_TIMESTAMP(6), sent_at=UTC_TIMESTAMP(6), error_message=NULL WHERE id=? AND business_id=?");
                        mysqli_stmt_bind_param($deliveryUpdate, 'sii', $deliveryResult['message_id'], $deliveryId, $businessId);
                    } catch (Throwable $deliveryError) {
                        $deliveryMessage = substr($deliveryError->getMessage(), 0, 1000);
                        $deliveryUpdate = mysqli_prepare($conn, "UPDATE report_deliveries SET status='FAILED', attempted_at=UTC_TIMESTAMP(6), error_message=? WHERE id=? AND business_id=?");
                        mysqli_stmt_bind_param($deliveryUpdate, 'sii', $deliveryMessage, $deliveryId, $businessId);
                    }
                    mysqli_stmt_execute($deliveryUpdate);
                }
            } catch (Throwable $generationError) {
                error_log('Scheduled report ' . $schedule['id'] . ' failed: ' . $generationError->getMessage());
            }

            $nextRun = calculateReportNextRun($schedule['frequency'], $schedule['send_time'], $schedule['weekday'] === null ? null : (int)$schedule['weekday'], $schedule['day_of_month'] === null ? null : (int)$schedule['day_of_month'], $schedule['timezone'], $runAtUtc);
            $update = mysqli_prepare($conn, "UPDATE report_schedules SET last_run_at=UTC_TIMESTAMP(6), next_run_at=? WHERE id=? AND business_id=?");
            mysqli_stmt_bind_param($update, 'sii', $nextRun, $scheduleId, $businessId);
            mysqli_stmt_execute($update);
            $processed++;
        }
    } finally {
        mysqli_query($conn, "SELECT RELEASE_LOCK('business_management_report_scheduler')");
    }
    return $processed;
}

if ($isReportWorkerRequest) {
    try {
        $count = runDueReportSchedules($conn);
        fwrite(STDOUT, 'Processed ' . $count . " due report schedule(s).\n");
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, 'Report scheduler failed: ' . $error->getMessage() . "\n");
        exit(1);
    }
}


requireLogin();
header('Location: index.php');
exit();
