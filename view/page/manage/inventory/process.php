<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . "/../../../../controller/cInventory.php");
include_once(__DIR__ . "/../../../../controller/cReceipt.php");

$cInventory = new CInventory();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'bins':
            $key = $_GET['key'] ?? '';
            $from = $_GET['from'] ?? '';
            $to = $_GET['to'] ?? '';
            $rows = [];
            if ($key) {
                $data = $cInventory->getBinDistributionForProduct($key, ['from' => $from, 'to' => $to]);

                // Preload latest import date (qty > 0) per bin from receipt.created_at
                $importMap = [];
                $receiptCache = [];
                try {
                    $details = $cInventory->getInventoryDetailsForProduct($key, [
                        'from' => $from,
                        'to' => $to,
                        'page' => 1,
                        'limit' => 500,
                    ]);
                    $items = isset($details['items']) && is_array($details['items']) ? $details['items'] : [];
                    foreach ($items as $it) {
                        $qty = isset($it['qty']) ? (float)$it['qty'] : 0.0;
                        if ($qty <= 0) continue; // only inbound (nhập)
                        $k = ($it['zone_id'] ?? '') . '|' . ($it['rack_id'] ?? '') . '|' . ($it['bin_id'] ?? '');
                        // Prefer receipt created_at
                        $rc = $it['receipt_code'] ?? ($it['receipt_id'] ?? '');
                        $dt = null;
                        if ($rc) {
                            if (!array_key_exists($rc, $receiptCache)) {
                                try {
                                    $r = (new CReceipt())->getReceiptById($rc);
                                } catch (Throwable $e) { $r = null; }
                                $receiptCache[$rc] = $r;
                            }
                            $r = $receiptCache[$rc];
                            $ts = $r['created_at'] ?? null;
                            if ($ts instanceof MongoDB\BSON\UTCDateTime) { $dt = $ts->toDateTime(); }
                            elseif ($ts instanceof DateTime) { $dt = $ts; }
                            elseif (!empty($ts) && is_numeric($ts)) { $dt = (new DateTime())->setTimestamp((int)$ts); }
                            elseif (!empty($ts)) { $dt = new DateTime((string)$ts); }
                        }
                        // Fallback to inventory timestamps
                        if (!$dt) {
                            $ts2 = $it['received_at'] ?? ($it['created_at'] ?? null);
                            if ($ts2 instanceof MongoDB\BSON\UTCDateTime) { $dt = $ts2->toDateTime(); }
                            elseif ($ts2 instanceof DateTime) { $dt = $ts2; }
                            elseif (!empty($ts2) && is_numeric($ts2)) { $dt = (new DateTime())->setTimestamp((int)$ts2); }
                            elseif (!empty($ts2)) { $dt = new DateTime((string)$ts2); }
                        }
                        if ($dt) {
                            if (!isset($importMap[$k]) || $dt > $importMap[$k]) {
                                $importMap[$k] = $dt;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // ignore fallback
                }

                // Normalize BSON types and attach importDate
                foreach ($data as $r) {
                    $last = '';
                    if (!empty($r['lastTime'])) {
                        if ($r['lastTime'] instanceof MongoDB\BSON\UTCDateTime) {
                            $dt = $r['lastTime']->toDateTime();
                            $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
                            $last = $dt->format('Y-m-d H:i:s');
                        } else if ($r['lastTime'] instanceof DateTime) {
                            $dt = $r['lastTime'];
                            $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
                            $last = $dt->format('Y-m-d H:i:s');
                        } else if (is_array($r['lastTime']) && isset($r['lastTime']['$date'])) {
                            $last = $r['lastTime']['$date'];
                        } else {
                            $last = (string)$r['lastTime'];
                        }
                    }

                    $k = ($r['zone_id'] ?? '') . '|' . ($r['rack_id'] ?? '') . '|' . ($r['bin_id'] ?? '');
                    $importStr = '';
                    if (isset($importMap[$k])) {
                        $dt = $importMap[$k];
                        if ($dt instanceof DateTime) {
                            $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
                            $importStr = $dt->format('Y-m-d H:i:s');
                        }
                    }

                    $rows[] = [
                        'warehouse_id' => $r['warehouse_id'] ?? '',
                        'zone_id' => $r['zone_id'] ?? '',
                        'rack_id' => $r['rack_id'] ?? '',
                        'bin_id' => $r['bin_id'] ?? '',
                        'bin_code' => $r['bin_code'] ?? '',
                        'qty' => (float)($r['qty'] ?? 0),
                        'lastTime' => $last,           // keep for backward-compat
                        'importDate' => $importStr,     // Ngày nhập hàng (mới nhất)
                    ];
                }
            }
            echo json_encode(['ok' => true, 'data' => $rows]);
            break;
        case 'product':
            $key = $_GET['key'] ?? '';
            $from = $_GET['from'] ?? '';
            $to = $_GET['to'] ?? '';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
            $rows = [];
            if ($key) {
                $list = $cInventory->getInventoryDetailsForProduct($key, [
                    'from' => $from,
                    'to' => $to,
                    'page' => $page,
                    'limit' => $limit,
                ]);
                foreach ($list['items'] as $it) {
                    // Format time string
                    $timeStr = '';
                    $ts = $it['received_at'] ?? ($it['created_at'] ?? null);
                    if ($ts instanceof MongoDB\BSON\UTCDateTime) {
                        $dt = $ts->toDateTime();
                        $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
                        $timeStr = $dt->format('d/m/Y H:i');
                    } elseif ($ts instanceof DateTime) {
                        $dt = $ts; $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
                        $timeStr = $dt->format('d/m/Y H:i');
                    } elseif (!empty($ts)) {
                        $timeStr = (string)$ts;
                    }
                    $rows[] = [
                        'time' => $timeStr,
                        'zone_id' => (string)($it['zone_id'] ?? ''),
                        'rack_id' => (string)($it['rack_id'] ?? ''),
                        'bin_id' => (string)($it['bin_id'] ?? ''),
                        'bin_code' => (string)($it['bin_code'] ?? ''),
                        'receipt' => (string)($it['receipt_code'] ?? ($it['receipt_id'] ?? '')),
                        'qty' => (float)($it['qty'] ?? 0),
                        'note' => (string)($it['note'] ?? ''),
                    ];
                }
            }
            echo json_encode(['ok' => true, 'data' => $rows, 'total' => count($rows)]);
            break;
        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}