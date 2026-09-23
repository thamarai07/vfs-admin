<?php
/**
 * Order-invoice-email debugger. Admin only.
 *
 * Unlike invoice_email_test.php (which sends a FAKE sample invoice), this
 * script re-runs the EXACT same steps create_order.php / resend_invoice.php
 * take for a REAL order — same DB lookups, same $order/$items shape — so it
 * can show you precisely where a real order's email differs or breaks.
 *
 *   /vfs-admin/debug_order_email.php                    -> list of recent orders
 *   /vfs-admin/debug_order_email.php?order_id=41         -> inspect that order (dry run, no email sent)
 *   /vfs-admin/debug_order_email.php?order_id=41&send=1  -> actually attempt to send + show full debug info
 *   /vfs-admin/debug_order_email.php?smtp_probe=1&pass=X -> raw SMTP AUTH test with a password typed
 *                                                            here directly, bypassing .env entirely
 *                                                            (optionally &host=&port=&user= to override)
 *
 * (Delete this file once you're done diagnosing — same rule as invoice_email_test.php.)
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/invoice_email.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$out  = [];
$line = function ($k, $v) use (&$out) { $out[] = str_pad($k, 24) . ': ' . $v; };
$hr   = function ($title = '') use (&$out) { $out[] = ''; $out[] = $title !== '' ? "── {$title} " . str_repeat('─', max(0, 60 - strlen($title))) : str_repeat('─', 62); };

// ── Raw SMTP AUTH probe — completely bypasses .env / PHPMailer / this app's
// config caching. Tests EXACTLY the string you put in ?pass=, right now,
// against the real server. Use this to rule out ".env didn't actually save
// what I think it saved" as a variable. ────────────────────────────────────
if (isset($_GET['smtp_probe'])) {
    $hr('RAW SMTP PROBE');
    $probeHost = trim((string) ($_GET['host'] ?? (env('SMTP_HOST', 'smtp.titan.email'))));
    $probePort = (int) ($_GET['port'] ?? (env('SMTP_PORT', 465)));
    $probeUser = trim((string) ($_GET['user'] ?? (env('SMTP_USER', ''))));
    $probePass = (string) ($_GET['pass'] ?? '');

    $line('host:port', "{$probeHost}:{$probePort}");
    $line('user', $probeUser);
    $line('pass typed here', $probePass !== '' ? '(' . strlen($probePass) . ' chars — not shown)' : '(none — add &pass=...)');

    if ($probePass === '' || $probeUser === '') {
        $out[] = '';
        $out[] = 'Usage: ?smtp_probe=1&pass=<password to test>  (optionally &user=&host=&port=)';
        echo implode("\n", $out) . "\n";
        exit;
    }

    $transcript = [];
    $log = function (string $side, string $text) use (&$transcript) { $transcript[] = "{$side} {$text}"; };

    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $fp = @stream_socket_client("ssl://{$probeHost}:{$probePort}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);

    if (!$fp) {
        $log('!!', "Connection FAILED: [{$errno}] {$errstr}");
        $out = array_merge($out, $transcript);
        $out[] = '';
        $out[] = '=> Could not even open a TCP+SSL connection to this host:port. That points to the';
        $out[] = '   hosting server BLOCKING outbound SMTP, not a password problem.';
        echo implode("\n", $out) . "\n";
        exit;
    }

    stream_set_timeout($fp, 10);
    $read = function () use ($fp, $log) {
        $line = fgets($fp, 2048);
        $log('S:', rtrim((string) $line));
        return (string) $line;
    };
    $write = function (string $cmd, ?string $label = null) use ($fp, $log) {
        fwrite($fp, $cmd . "\r\n");
        $log('C:', $label ?? $cmd);
    };

    $read(); // banner
    $write('EHLO debug-probe');
    do { $l = $read(); } while (isset($l[3]) && $l[3] === '-'); // drain multiline EHLO
    $write('AUTH LOGIN');
    $read(); // "334 Username:"
    $write(base64_encode($probeUser), '[base64 username]');
    $read(); // "334 Password:"
    $write(base64_encode($probePass), '[base64 password — value hidden]');
    $final = $read();
    $write('QUIT');
    $read();
    fclose($fp);

    $out = array_merge($out, $transcript);
    $out[] = '';
    if (str_starts_with(trim($final), '235')) {
        $out[] = '=> AUTH SUCCESS — this exact user+password IS accepted by the server right now.';
        $out[] = '   If .env still fails with the "same" password, the .env value differs from what';
        $out[] = '   you typed here (upload didn\'t take, stray whitespace, wrong file uploaded, etc).';
        $out[] = '   Re-copy this EXACT password into SMTP_PASS and re-upload .env.';
    } else {
        $out[] = '=> AUTH FAILED — the server itself rejected this exact password just now (see the';
        $out[] = '   S: response line above for its reason). This confirms the password is wrong for';
        $out[] = '   this account — reset it again on GoDaddy and try a freshly-copied value here.';
    }
    echo implode("\n", $out) . "\n";
    exit;
}

// ── Mail config snapshot (same checks as invoice_email_test.php) ───────────
$hr('MAIL CONFIG');
$autoload = __DIR__ . '/vendor/autoload.php';
$manual   = __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
$manualFallback = __DIR__ . '/vendor/vendor/PHPMailer/src/PHPMailer.php';
$havePhpMailer = file_exists($autoload) || file_exists($manual) || file_exists($manualFallback);
$smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '';
$smtpUser = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
$smtpPass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
$smtpPort = $_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: '(default 587)';
$smtpSecure = $_ENV['SMTP_SECURE'] ?? getenv('SMTP_SECURE') ?: '(default tls)';
$mailFrom = $_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?: '(not set — falls back to no-reply@<domain>)';
$resendKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?: '';

$line('RESEND_API_KEY', $resendKey !== '' ? '(set, ' . strlen($resendKey) . ' chars)' : '(not set)');
$line('curl available', function_exists('curl_init') ? 'yes' : 'NO — Resend needs curl');

$line('PHPMailer', $havePhpMailer
    ? 'FOUND (' . (file_exists($autoload) ? 'autoload' : (file_exists($manual) ? 'vendor/PHPMailer' : 'vendor/vendor/PHPMailer')) . ')'
    : 'NOT installed');
$line('SMTP_HOST', $smtpHost !== '' ? $smtpHost : '(not set)');
$line('SMTP_USER', $smtpUser !== '' ? $smtpUser : '(not set)');
if ($smtpPass !== '') {
    // Never print the real password — but DO show enough to catch the classic
    // copy-paste corruption: trailing/leading whitespace, a hidden character
    // (curly quote, zero-width space, CR) that looks invisible on screen, or
    // a byte-length mismatch (mb_strlen vs strlen) meaning a multi-byte char
    // snuck in from a rich-text copy.
    $rawLen  = strlen($smtpPass);
    $mbLen   = mb_strlen($smtpPass, 'UTF-8');
    $hasWs   = ($smtpPass !== trim($smtpPass, " \t\n\r\0\x0B"));
    $isAscii = mb_check_encoding($smtpPass, 'ASCII');
    $masked  = $rawLen > 4
        ? substr($smtpPass, 0, 2) . str_repeat('•', $rawLen - 4) . substr($smtpPass, -2)
        : str_repeat('•', $rawLen);
    $firstByteHex = bin2hex(substr($smtpPass, 0, 1));
    $lastByteHex  = bin2hex(substr($smtpPass, -1));
    $line('SMTP_PASS', "{$masked}  (byte-length {$rawLen}, char-length {$mbLen}" . ($rawLen !== $mbLen ? ' <- MISMATCH, multi-byte char present!' : '') . ')');
    $line('  leading/trailing whitespace?', $hasWs ? 'YES — this is very likely the bug, re-copy without extra spaces' : 'no');
    $line('  pure ASCII?', $isAscii ? 'yes' : 'NO — contains a non-ASCII character (curly quote / dash / accented letter?) — retype it manually instead of copy-pasting');
    $line('  first/last byte (hex)', "{$firstByteHex} / {$lastByteHex}");
} else {
    $line('SMTP_PASS', '(not set)');
}
$line('SMTP_PORT', (string) $smtpPort);
$line('SMTP_SECURE', (string) $smtpSecure);
$line('MAIL_FROM', $mailFrom);
$line('=> delivery path that will be used', $resendKey !== '' && function_exists('curl_init')
    ? 'Resend API (falls back to SMTP/mail() if it errors)'
    : (($havePhpMailer && $smtpHost && $smtpUser && $smtpPass) ? 'PHPMailer / SMTP' : 'PHP mail() fallback'));

$orderId = (int) ($_GET['order_id'] ?? 0);
$doSend  = isset($_GET['send']) && $_GET['send'] == '1';

// ── No order_id: list recent orders so you can pick one ────────────────────
if ($orderId <= 0) {
    $hr('RECENT ORDERS — add ?order_id=<id> to inspect one, &send=1 to actually send');
    $hasInvoiceCol = false;
    try { $hasInvoiceCol = (bool) $conn->query("SHOW COLUMNS FROM orders LIKE 'invoice_emailed_at'")->fetch(); } catch (Throwable $e) {}

    $sql = "SELECT o.id, o.order_number, o.customer_id, o.customer_name, o.created_at"
         . ($hasInvoiceCol ? ", o.invoice_emailed_at" : "")
         . " FROM orders o ORDER BY o.id DESC LIMIT 15";
    $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $custEmail = '(no customer_id)';
        if (!empty($r['customer_id'])) {
            $cs = $conn->prepare("SELECT email FROM customers WHERE id = ?");
            $cs->execute([$r['customer_id']]);
            $e = $cs->fetchColumn();
            $custEmail = $e !== false && $e !== null && $e !== '' ? $e : '(NO EMAIL ON FILE — this is why it fails)';
        }
        $itemCount = (int) $conn->query("SELECT COUNT(*) FROM order_items WHERE order_id = " . (int) $r['id'])->fetchColumn();
        $sent = $hasInvoiceCol ? (!empty($r['invoice_emailed_at']) ? 'sent ' . $r['invoice_emailed_at'] : 'NOT sent') : '(tracking off)';

        $out[] = sprintf(
            '#%-4d %-20s %-12s items:%-3d %-40s %s',
            $r['id'],
            $r['order_number'],
            $r['created_at'],
            $itemCount,
            $custEmail,
            $sent
        );
    }
    $out[] = '';
    $out[] = 'Pick one: ?order_id=<id>   Then to actually send: ?order_id=<id>&send=1';

    echo implode("\n", $out) . "\n";
    exit;
}

// ── order_id given: deep-dive that specific order ───────────────────────────
$hr("ORDER #{$orderId}");
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $line('FOUND', 'NO — no order with this id');
    echo implode("\n", $out) . "\n";
    exit;
}
$line('order_number', $order['order_number']);
$line('created_at', $order['created_at']);
$line('customer_id', $order['customer_id'] ?? '(NULL)');
$line('customer_name (on order)', $order['customer_name'] ?? '');
$line('status', $order['status'] . ' / ' . $order['payment_status']);
$line('subtotal/tax/shipping/total', "{$order['subtotal']} / {$order['tax']} / {$order['shipping_charge']} / " . ($order['total_amount'] ?? $order['total']));
$line('invoice_emailed_at', $order['invoice_emailed_at'] ?? '(column missing or NULL)');

$hr('CUSTOMER LOOKUP');
if (empty($order['customer_id'])) {
    $line('RESULT', 'FAIL — this order has no customer_id, so no email can be sent to anyone. (Guest checkout order?)');
    echo implode("\n", $out) . "\n";
    exit;
}
$custStmt = $conn->prepare("SELECT id, name, email FROM customers WHERE id = ?");
$custStmt->execute([$order['customer_id']]);
$cust = $custStmt->fetch(PDO::FETCH_ASSOC);

if (!$cust) {
    $line('RESULT', "FAIL — customer_id {$order['customer_id']} does not exist in `customers` (deleted account?)");
    echo implode("\n", $out) . "\n";
    exit;
}
$line('customer name', $cust['name'] ?? '(blank)');
$line('customer email (raw)', var_export($cust['email'], true));
$emailValid = !empty($cust['email']) && filter_var($cust['email'], FILTER_VALIDATE_EMAIL);
$line('email valid?', $emailValid ? 'yes' : 'NO — THIS IS WHY THE EMAIL NEVER SENDS');
if (!$emailValid) {
    echo implode("\n", $out) . "\n";
    exit;
}

$hr('ORDER ITEMS');
$itemsStmt = $conn->prepare("SELECT product_name, unit, quantity, price, subtotal FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$orderId]);
$rows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
$line('item count', (string) count($rows));
if (!$rows) {
    $line('RESULT', 'FAIL — this order has zero rows in order_items (nothing to put in the invoice)');
    echo implode("\n", $out) . "\n";
    exit;
}
foreach ($rows as $r) {
    $out[] = '  - ' . $r['product_name'] . ' x' . $r['quantity'] . ' (' . ($r['unit'] ?? 'kg') . ') @ ' . $r['price'] . ' = ' . $r['subtotal'];
}

$invoiceItems = array_map(static function (array $r): array {
    return [
        'name' => $r['product_name'], 'unit' => $r['unit'] ?? null,
        'quantity' => (float) $r['quantity'], 'price' => (float) $r['price'], 'subtotal' => (float) $r['subtotal'],
    ];
}, $rows);

$adminEmail = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: '';
$bcc = ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) && strcasecmp($adminEmail, $cust['email']) !== 0)
    ? [$adminEmail] : [];

$orderForEmail = [
    'order_number'     => $order['order_number'],
    'created_at'       => $order['created_at'],
    'customer_name'    => $order['customer_name'],
    'customer_phone'   => $order['customer_phone'],
    'customer_address' => $order['customer_address'],
    'subtotal'         => (float) $order['subtotal'],
    'tax'              => (float) $order['tax'],
    'shipping_charge'  => (float) $order['shipping_charge'],
    'total_amount'     => (float) ($order['total_amount'] ?: $order['total']),
    'payment_method'   => $order['payment_method'],
    'notes'            => $order['notes'],
];

$hr('SEND');
$line('would send to', $cust['email']);
$line('would BCC', $bcc ? implode(', ', $bcc) : '(none)');

if (!$doSend) {
    $line('mode', 'DRY RUN — everything above checks out. Nothing was sent.');
    $line('to actually send', "add &send=1 to this URL");
} else {
    $debug = [];
    $ok = sendOrderInvoiceEmail($cust['email'], $cust['name'] ?? $order['customer_name'], $orderForEmail, $invoiceItems, $bcc, $debug);
    $line('RESULT', $ok ? 'sendOrderInvoiceEmail() returned TRUE (accepted)' : 'sendOrderInvoiceEmail() returned FALSE (failed)');
    $hr('DEBUG DETAIL');
    foreach ($debug as $k => $v) {
        if ($k === 'smtp_log' && is_array($v)) {
            $out[] = 'smtp_log:';
            foreach ($v as $l) { $out[] = '    ' . $l; }
            continue;
        }
        if (is_array($v)) { $v = json_encode($v); }
        $line($k, (string) $v);
    }
    $hr('WHAT THIS MEANS');
    if ($ok) {
        $out[] = 'Accepted by the transport. If it still never arrives:';
        $out[] = '  - path=resend => Resend accepted it — check the Resend dashboard "Logs" tab for delivery status.';
        $out[] = '  - path=smtp   => real inbox delivery is now up to the mailbox provider (GoDaddy) — check Sent folder there.';
        $out[] = '  - path=mail() => shared-hosting IP reputation issue with Gmail; SMTP/Resend is the fix, not more retries.';
    } else {
        $out[] = 'It failed BEFORE even leaving the server — see "error" / "phpmailer_error_info" / "resend_response" above';
        $out[] = 'for the exact reason (common: wrong SMTP password, domain not verified yet in Resend, wrong API key).';
    }
}

echo implode("\n", $out) . "\n";
