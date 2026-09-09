<?php
/**
 * Invoice-email diagnostic. Admin only. (Lives in the web root because tools/ is
 * blocked by tools/.htaccess "Deny from all".)
 *
 *   /vfs-admin/invoice_email_test.php                -> config check only
 *   /vfs-admin/invoice_email_test.php?to=you@x.com   -> also send a test invoice
 *
 * Tells you exactly why an order confirmation email did / didn't go out.
 */

session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$out = [];
$line = function ($k, $v) use (&$out) { $out[] = str_pad($k, 26) . ': ' . $v; };

$line('PHP version', PHP_VERSION);
$line('APP_ENV', $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '(not set)');

// ── 1. PHPMailer present? ────────────────────────────────────────────────
$autoload = __DIR__ . '/vendor/autoload.php';
$manual   = __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
$havePhpMailer = file_exists($autoload) || file_exists($manual);
$line('vendor/autoload.php', file_exists($autoload) ? 'FOUND' : 'missing');
$line('vendor/PHPMailer/src/', file_exists($manual) ? 'FOUND' : 'missing');
if (!$havePhpMailer) {
    $line('=> PHPMailer', 'not installed — will use PHP mail() fallback (same as Forgot Password)');
}

// ── 2. SMTP config present? (never prints the password) ──────────────────
$smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '';
$smtpUser = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
$smtpPass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
$mailFrom = $_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?: $smtpUser;
$line('SMTP_HOST', $smtpHost !== '' ? $smtpHost : '(NOT SET)');
$line('SMTP_USER', $smtpUser !== '' ? $smtpUser : '(NOT SET)');
$line('SMTP_PASS', $smtpPass !== '' ? 'set (' . strlen($smtpPass) . ' chars)' : '(NOT SET)');
$line('MAIL_FROM', $mailFrom !== '' ? $mailFrom : '(NOT SET)');
if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
    $line('=> SMTP', 'not set — OK: mail() fallback will be used. (Set SMTP_* for better deliverability.)');
}
$line('mail() available', function_exists('mail') ? 'yes' : 'NO — server has no MTA, cannot send at all');
$line('delivery path', ($havePhpMailer && $smtpHost && $smtpUser && $smtpPass) ? 'PHPMailer/SMTP' : 'PHP mail()');

// ── 2b. PHP mail transport settings ─────────────────────────────────────
$line('ini sendmail_path', ini_get('sendmail_path') ?: '(empty)');
$line('ini SMTP (win only)', ini_get('SMTP') ?: '(n/a)');
$line('ini smtp_port', ini_get('smtp_port') ?: '(n/a)');
$line('disable_functions', ini_get('disable_functions') ?: '(none)');
$line('STORE_EMAIL (.env)', $_ENV['STORE_EMAIL'] ?? getenv('STORE_EMAIL') ?: '(not set)');
$line('server HTTP_HOST', $_SERVER['HTTP_HOST'] ?? '(n/a)');

// ── 2c. Where does error_log go? ───────────────────────────────────────
$errLog = ini_get('error_log');
$line('ini error_log', $errLog ?: '(default — try hPanel error log, or ./error_log)');
foreach ([$errLog, __DIR__ . '/error_log', __DIR__ . '/logs/error.log', dirname(__DIR__) . '/error_log'] as $lp) {
    if ($lp && is_file($lp) && is_readable($lp)) {
        $line('found log', $lp . '  (' . round(filesize($lp) / 1024, 1) . ' KB)');
    }
}

// ── 3. Helper file present? ─────────────────────────────────────────────
$helper = __DIR__ . '/includes/invoice_email.php';
$line('includes/invoice_email.php', file_exists($helper) ? 'FOUND' : 'MISSING — upload it');

// ── 4. Latest order + whether it was emailed ────────────────────────────
try {
    $hasCol = (bool) $conn->query("SHOW COLUMNS FROM orders LIKE 'invoice_emailed_at'")->fetch();
    $line('orders.invoice_emailed_at', $hasCol ? 'column exists' : 'column missing (run migration to track it)');
    $o = $conn->query("SELECT o.id, o.order_number, o.created_at, o.customer_id,
                              " . ($hasCol ? 'o.invoice_emailed_at,' : "NULL as invoice_emailed_at,") . "
                              c.email
                       FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
                       ORDER BY o.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($o) {
        $line('latest order', $o['order_number'] . '  (' . $o['created_at'] . ')');
        $line('  customer email', $o['email'] ?: '(NO EMAIL ON CUSTOMER — nothing to send to)');
        $line('  invoice_emailed_at', $o['invoice_emailed_at'] ?: '(not recorded)');
    }
} catch (Throwable $e) {
    $line('latest order check', 'error: ' . $e->getMessage());
}

// ── 5. Actually try a send ─────────────────────────────────────────────
$to   = trim($_GET['to'] ?? '');
$from = trim($_GET['from'] ?? '');   // optional: test different sender addresses

if ($to !== '') {
    $out[] = '';
    $out[] = '── SEND TEST → ' . $to . ($from ? "  (from: $from)" : '') . ' ─────────────';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $line('result', 'invalid recipient address');
    } else {
        // 5a. RAW mail() test — bypasses our helper so we see the transport's
        //     own verdict + error_get_last().
        $rawFrom = $from ?: (($_ENV['STORE_EMAIL'] ?? getenv('STORE_EMAIL'))
                   ?: 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $rawHdr  = "From: Rooto Test <{$rawFrom}>\r\nReply-To: {$rawFrom}\r\n"
                 . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8";
        error_clear_last();
        $raw = @mail($to, 'Rooto raw mail() test ' . date('H:i:s'),
                     '<p>Raw <b>mail()</b> test from invoice_email_test.php at ' . date('c') . '</p>',
                     $rawHdr, "-f{$rawFrom}");
        $lastErr = error_get_last();
        $line('RAW mail() from', $rawFrom);
        $line('RAW mail() returned', $raw ? 'true (accepted by MTA)' : 'FALSE (MTA rejected it)');
        if ($lastErr) $line('RAW mail() php error', $lastErr['message']);

        // 5b. Full helper send (SMTP if configured, else mail())
        if (file_exists($helper)) {
            require_once $helper;
            $sample = [
                'order_number'     => 'TEST-' . date('His'),
                'created_at'       => date('Y-m-d H:i:s'),
                'customer_name'    => 'Test Customer',
                'customer_phone'   => '9999999999',
                'customer_address' => '12 Test Street, Chennai, 600001',
                'subtotal'         => 160, 'tax' => 12.8, 'shipping_charge' => 0,
                'total_amount'     => 172.8, 'payment_method' => 'cod',
                'notes'            => 'Diagnostic test email.',
            ];
            $items = [
                ['name' => 'Coconut', 'unit' => 'piece', 'quantity' => 3, 'price' => 40, 'subtotal' => 120],
                ['name' => 'Apple',   'unit' => null,    'quantity' => 0.5, 'price' => 80, 'subtotal' => 40],
            ];
            if ($from) { putenv("MAIL_FROM=$from"); $_ENV['MAIL_FROM'] = $from; }
            $ok = sendOrderInvoiceEmail($to, 'Test Customer', $sample, $items);
            $line('helper send result', $ok ? 'SENT (accepted)' : 'FAILED');
        }
    }
}

// ── 6. Tail the error log (?log=1) ─────────────────────────────────────
if (isset($_GET['log'])) {
    $out[] = '';
    $out[] = '── ERROR LOG (last ' . (int)($_GET['log'] ?: 40) . ' lines with mail/invoice/hsendmail) ──';
    $lp = ini_get('error_log');
    if ($lp && is_readable($lp)) {
        $lines = @file($lp, FILE_IGNORE_NEW_LINES);
        if ($lines) {
            $keep = array_slice(array_values(array_filter($lines, fn($l) =>
                stripos($l, 'invoice_email') !== false
                || stripos($l, 'hsendmail') !== false
                || stripos($l, 'mail(') !== false
                || stripos($l, 'ForgotPassword') !== false
            )), -1 * max(5, (int)($_GET['log'] ?: 40)));
            $out = array_merge($out, $keep ?: ['(no matching lines)']);
        }
    } else {
        $out[] = '(cannot read ' . $lp . ')';
    }
}

$out[] = '';
$out[] = 'READING THE RESULT:';
$out[] = '  - RAW mail() returned FALSE      => the server MTA is refusing mail. mail() is dead here — use SMTP.';
$out[] = '  - RAW mail() true but no inbox   => mail leaves the server but Gmail drops it (bad From / no SPF).';
$out[] = '      Try ?to=<a NON-gmail addr>  and  ?from=<an address on this hosting domain>.';
$out[] = '  - Fix for good: set SMTP_HOST/USER/PASS + upload vendor/PHPMailer (see chat).';
$out[] = '';
$out[] = '  Usage: ?to=you@x.com            ?to=you@x.com&from=orders@yourdomain';
$out[] = '  (Delete this file once diagnosed.)';
echo implode("\n", $out) . "\n";
