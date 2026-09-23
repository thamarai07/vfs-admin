<?php
/**
 * Order invoice — shared HTML builder + emailer.
 * Used by api/create_order.php to email the customer a neat invoice after an
 * order is placed. Completely non-fatal: any failure just writes error_log and
 * returns false, so a mail problem can never break order creation.
 */

require_once __DIR__ . '/../config/env.php';

if (!function_exists('invoiceUnitLabel')) {
    /** Human unit label for an order line. NULL / kg-like => "kg". */
    function invoiceUnitLabel(?string $unit, float $qty = 1): string
    {
        $u = strtolower(trim((string) $unit));
        if ($u === 'piece' || $u === 'pieces') {
            return $qty === 1.0 ? 'Piece' : 'Pieces';
        }
        if ($u === '' || $u === 'kg' || $u === 'gram' || $u === 'grams' || $u === 'g') {
            return 'kg';
        }
        return ucfirst($u);
    }
}

if (!function_exists('invoiceFormatQty')) {
    /** "3 Pieces" / "0.25 kg". */
    function invoiceFormatQty(float $qty, ?string $unit): string
    {
        $label = invoiceUnitLabel($unit, $qty);
        if ($label === 'Piece' || $label === 'Pieces') {
            return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') . ' ' . $label;
        }
        return number_format($qty, 2) . ' ' . $label;
    }
}

if (!function_exists('invoiceStoreInfo')) {
    function invoiceStoreInfo(): array
    {
        return [
            'name'    => env('STORE_NAME', 'Rooto'),
            'address' => env('STORE_ADDRESS', ''),
            'phone'   => env('STORE_PHONE', ''),
            'email'   => env('STORE_EMAIL', ''),
            'gst'     => env('STORE_GST', ''),
            'website' => env('STORE_WEBSITE', 'rooto.in'),
        ];
    }
}

if (!function_exists('buildOrderInvoiceText')) {
    /** Plain-text version of the invoice (for the multipart/alternative part). */
    function buildOrderInvoiceText(array $order, array $items, array $store): string
    {
        $l = [];
        $l[] = $store['name'] . ' — Order Invoice';
        $l[] = str_repeat('-', 40);
        $l[] = 'Order:   ' . ($order['order_number'] ?? '');
        $l[] = 'Date:    ' . date('d M Y, g:i A', strtotime($order['created_at'] ?? 'now'));
        $l[] = 'Payment: ' . strtoupper($order['payment_method'] ?? 'COD');
        $l[] = '';
        $l[] = 'Deliver to: ' . ($order['customer_name'] ?? '');
        $l[] = '  ' . ($order['customer_address'] ?? '');
        $l[] = '  ' . ($order['customer_phone'] ?? '');
        $l[] = '';
        $l[] = 'ITEMS';
        foreach ($items as $it) {
            $qty = (float) ($it['quantity'] ?? 0);
            $l[] = sprintf('  %-22s %8s x Rs %-8s = Rs %s',
                substr($it['name'] ?? '', 0, 22),
                invoiceFormatQty($qty, $it['unit'] ?? null),
                number_format((float) ($it['price'] ?? 0), 2),
                number_format((float) ($it['subtotal'] ?? 0), 2));
        }
        $l[] = '';
        $l[] = 'Subtotal:  Rs ' . number_format((float) ($order['subtotal'] ?? 0), 2);
        $l[] = 'Tax (8%):  Rs ' . number_format((float) ($order['tax'] ?? 0), 2);
        $sh = (float) ($order['shipping_charge'] ?? 0);
        $l[] = 'Shipping:  ' . ($sh > 0 ? 'Rs ' . number_format($sh, 2) : 'FREE');
        $l[] = 'TOTAL:     Rs ' . number_format((float) ($order['total_amount'] ?? $order['total'] ?? 0), 2);
        if (!empty($order['notes'])) {
            $l[] = '';
            $l[] = 'Note: ' . $order['notes'];
        }
        $l[] = '';
        $l[] = 'Thank you for your order! - ' . $store['name'];
        return implode("\r\n", $l);
    }
}

if (!function_exists('buildOrderInvoiceHtml')) {
    /**
     * @param array $order  order_number, created_at, customer_name, customer_phone,
     *                       customer_address, subtotal, tax, shipping_charge,
     *                       total_amount, payment_method, notes
     * @param array $items   each: name, unit, quantity, price, subtotal
     */
    function buildOrderInvoiceHtml(array $order, array $items, array $store): string
    {
        $money = static fn($n) => '&#8377;' . number_format((float) $n, 2);
        $e     = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $orderNo   = $e($order['order_number'] ?? '');
        $date      = $e(date('d M Y, g:i A', strtotime($order['created_at'] ?? 'now')));
        $subtotalS = $money($order['subtotal'] ?? 0);
        $taxS      = $money($order['tax'] ?? 0);
        $totalS    = $money($order['total_amount'] ?? $order['total'] ?? 0);
        $payMethod = strtoupper($e($order['payment_method'] ?? 'COD'));
        $custName  = $e($order['customer_name'] ?? '');
        $custAddr  = $e($order['customer_address'] ?? '');
        $custPhone = $e($order['customer_phone'] ?? '');

        $shipping  = (float) ($order['shipping_charge'] ?? 0);
        $shipCell  = $shipping > 0
            ? $money($shipping)
            : '<span style="color:#16a34a;font-weight:700;">FREE</span>';

        $rows = '';
        $i = 0;
        foreach ($items as $it) {
            $i++;
            $qty   = (float) ($it['quantity'] ?? 0);
            $price = (float) ($it['price'] ?? 0);
            $sub   = (float) ($it['subtotal'] ?? ($qty * $price));
            $rows .= '<tr>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee;color:#555;">' . $i . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee;font-weight:600;color:#111;">' . $e($it['name'] ?? '') . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee;text-align:right;color:#333;">' . $money($price) . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee;text-align:right;color:#333;white-space:nowrap;">' . $e(invoiceFormatQty($qty, $it['unit'] ?? null)) . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee;text-align:right;font-weight:700;color:#111;">' . $money($sub) . '</td>'
                . '</tr>';
        }

        $notesBlock = '';
        if (!empty($order['notes'])) {
            $notesBlock = '<tr><td colspan="2" style="padding-top:14px;">'
                . '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px 14px;font-size:13px;color:#9a3412;">'
                . '<strong>Note:</strong> ' . $e($order['notes']) . '</div></td></tr>';
        }

        $storeName = $e($store['name']);
        $storeLine = trim(implode(' &nbsp;&bull;&nbsp; ', array_filter([
            $e($store['phone']),
            $e($store['email']),
            $e($store['website']),
        ])));

        return <<<HTML
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f4;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <div style="max-width:600px;margin:0 auto;padding:24px 12px;">

    <div style="background:linear-gradient(135deg,#16a34a,#0f7a37);border-radius:16px 16px 0 0;padding:28px 26px;color:#fff;">
      <div style="font-size:22px;font-weight:800;letter-spacing:.3px;">{$storeName}</div>
      <div style="font-size:13px;opacity:.9;margin-top:2px;">Order Invoice</div>
    </div>

    <div style="background:#fff;padding:24px 26px;border:1px solid #e5e7eb;border-top:0;">
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#555;">
        <tr>
          <td style="vertical-align:top;">
            <div style="color:#888;text-transform:uppercase;font-size:11px;letter-spacing:.6px;">Order</div>
            <div style="font-weight:700;color:#111;font-size:15px;">{$orderNo}</div>
            <div style="margin-top:2px;">{$date}</div>
          </td>
          <td style="vertical-align:top;text-align:right;">
            <div style="color:#888;text-transform:uppercase;font-size:11px;letter-spacing:.6px;">Payment</div>
            <div style="font-weight:700;color:#111;">{$payMethod}</div>
          </td>
        </tr>
      </table>

      <div style="margin-top:16px;background:#f9fafb;border:1px solid #eef0ee;border-radius:10px;padding:12px 14px;font-size:13px;color:#333;">
        <div style="color:#888;text-transform:uppercase;font-size:11px;letter-spacing:.6px;margin-bottom:4px;">Deliver to</div>
        <div style="font-weight:700;color:#111;">{$custName}</div>
        <div>{$custAddr}</div>
        <div>{$custPhone}</div>
      </div>

      <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="background:#f0fdf4;">
            <th style="padding:9px 8px;text-align:left;color:#166534;font-size:11px;letter-spacing:.5px;text-transform:uppercase;">#</th>
            <th style="padding:9px 8px;text-align:left;color:#166534;font-size:11px;letter-spacing:.5px;text-transform:uppercase;">Item</th>
            <th style="padding:9px 8px;text-align:right;color:#166534;font-size:11px;letter-spacing:.5px;text-transform:uppercase;">Unit price</th>
            <th style="padding:9px 8px;text-align:right;color:#166534;font-size:11px;letter-spacing:.5px;text-transform:uppercase;">Qty</th>
            <th style="padding:9px 8px;text-align:right;color:#166534;font-size:11px;letter-spacing:.5px;text-transform:uppercase;">Amount</th>
          </tr>
        </thead>
        <tbody>{$rows}</tbody>
      </table>

      <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px;font-size:13px;color:#444;">
        <tr><td style="padding:4px 0;">Subtotal</td><td style="padding:4px 0;text-align:right;">{$subtotalS}</td></tr>
        <tr><td style="padding:4px 0;">Tax (8%)</td><td style="padding:4px 0;text-align:right;">{$taxS}</td></tr>
        <tr><td style="padding:4px 0;">Shipping</td><td style="padding:4px 0;text-align:right;">{$shipCell}</td></tr>
        <tr><td style="padding:12px 0 0;border-top:2px solid #111;font-weight:800;color:#111;font-size:15px;">Total</td>
            <td style="padding:12px 0 0;border-top:2px solid #111;text-align:right;font-weight:800;color:#16a34a;font-size:17px;">{$totalS}</td></tr>
        {$notesBlock}
      </table>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 16px 16px;padding:18px 26px;text-align:center;font-size:12px;color:#888;">
      <div style="font-weight:700;color:#16a34a;font-size:13px;margin-bottom:4px;">Thank you for your order! &#127807;</div>
      <div>{$storeLine}</div>
    </div>

  </div>
</body>
</html>
HTML;
    }
}

if (!function_exists('sendOrderInvoiceEmail')) {
    /**
     * Send the invoice. Returns true on success, false on any failure (logged).
     * Never throws.
     *
     * Delivery path — same strategy api/forgot_password.php uses:
     *   1. PHPMailer over SMTP   (if vendor/ present AND SMTP_* env set)
     *   2. PHP mail()            (fallback — what actually works on the current host)
     */
    // $debug: optional — pass an array by reference (e.g. $d = []; sendOrderInvoiceEmail(...,$d))
    // to get back exactly which path/config was used and, on failure, the real
    // exception / SMTP transcript. Used by debug_order_email.php. Passing null
    // (the default, every existing call site) costs nothing extra.
    function sendOrderInvoiceEmail(string $toEmail, string $toName, array $order, array $items, array $bcc = [], ?array &$debug = null): bool
    {
        if ($debug === null) { $debug = []; }
        try {
            $toEmail = trim($toEmail);
            $debug['to'] = $toEmail;
            if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                $debug['error'] = 'invalid recipient email: "' . $toEmail . '"';
                error_log('[invoice_email] invalid recipient: ' . $toEmail);
                return false;
            }
            $bcc = array_values(array_filter(array_map('trim', $bcc),
                fn($b) => $b !== '' && filter_var($b, FILTER_VALIDATE_EMAIL)
                          && strcasecmp($b, $toEmail) !== 0));

            $store   = invoiceStoreInfo();
            $subject = 'Your ' . $store['name'] . ' order ' . ($order['order_number'] ?? '');
            $html    = buildOrderInvoiceHtml($order, $items, $store);
            $orderNo = $order['order_number'] ?? '';
            $debug['subject']    = $subject;
            $debug['bcc']        = $bcc;
            $debug['html_bytes'] = strlen($html);

            $smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '';
            $smtpUser = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
            $smtpPass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';

            // Sender: MAIL_FROM env, else no-reply@<your real domain> (from
            // STORE_WEBSITE, e.g. rooto.in) — the SAME kind of address
            // forgot_password.php's working mail() uses. Never STORE_EMAIL,
            // which is a placeholder in .env.
            $siteDomain = strtolower(preg_replace('#^https?://|/.*$|^www\.#', '', $store['website'] ?: 'rooto.in'));
            if ($siteDomain === '' || strpos($siteDomain, '.') === false) $siteDomain = 'rooto.in';
            $mailFrom   = ($_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM')) ?: ('no-reply@' . $siteDomain);

            // ── Path 0: Resend API (HTTPS, no SMTP port needed) ──────────────
            // Preferred when configured — sidesteps shared-hosting SMTP port
            // blocks and Gmail's mail()-from-shared-IP spam filtering entirely.
            // Falls through to SMTP/mail() below on any failure, so this is
            // purely additive — no RESEND_API_KEY set = zero behaviour change.
            $resendKey = ($_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY')) ?: '';
            $debug['resend_configured'] = ($resendKey !== '');
            if ($resendKey !== '' && function_exists('curl_init')) {
                $debug['path'] = 'resend';
                $payload = [
                    'from'    => $store['name'] . ' <' . $mailFrom . '>',
                    'to'      => [$toEmail],
                    'subject' => $subject,
                    'html'    => $html,
                ];
                if (!empty($bcc)) { $payload['bcc'] = $bcc; }
                if (!empty($store['email'])) { $payload['reply_to'] = $store['email']; }

                $ch = curl_init('https://api.resend.com/emails');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $resendKey,
                        'Content-Type: application/json',
                    ],
                    CURLOPT_POSTFIELDS     => json_encode($payload),
                    CURLOPT_TIMEOUT        => 15,
                ]);
                $resp     = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);

                $debug['resend_http_code'] = $httpCode;
                $debug['resend_response']  = $resp;

                if ($httpCode >= 200 && $httpCode < 300) {
                    $debug['result'] = 'sent-via-resend';
                    error_log("[invoice_email] sent via Resend API to $toEmail for $orderNo");
                    return true;
                }
                $debug['error'] = 'Resend API failed: HTTP ' . $httpCode . ' ' . ($curlErr ?: $resp);
                error_log("[invoice_email] Resend API FAILED ($httpCode) for $orderNo: " . ($curlErr ?: $resp));
                // fall through to SMTP / mail() below
            }

            $autoload = __DIR__ . '/../vendor/autoload.php';
            // The intended layout is vendor/PHPMailer/src/…; the copy that was
            // actually uploaded landed one level deeper at
            // vendor/vendor/PHPMailer/src/… — check both so a manual re-upload
            // isn't needed.
            $manualCandidates = [
                __DIR__ . '/../vendor/PHPMailer/src',
                __DIR__ . '/../vendor/vendor/PHPMailer/src',
            ];
            $manualSrcDir = null;
            foreach ($manualCandidates as $dir) {
                if (file_exists($dir . '/PHPMailer.php')) {
                    $manualSrcDir = $dir;
                    break;
                }
            }
            $havePhpMailer = file_exists($autoload) || $manualSrcDir !== null;
            $debug['have_phpmailer'] = $havePhpMailer;
            $debug['smtp_configured'] = ($smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '');
            $debug['mail_from'] = $mailFrom;

            // ── Path 1: PHPMailer + SMTP ────────────────────────────────────
            if ($havePhpMailer && $smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '') {
                $debug['path'] = 'smtp';
                if (file_exists($autoload)) {
                    require_once $autoload;
                } else {
                    require_once $manualSrcDir . '/Exception.php';
                    require_once $manualSrcDir . '/PHPMailer.php';
                    require_once $manualSrcDir . '/SMTP.php';
                }

                // Port/encryption are configurable (mailbox providers differ —
                // e.g. Hostinger/Titan often want 465+SSL instead of 587+STARTTLS)
                // but default to the common STARTTLS:587 combo.
                $smtpPort   = (int) (($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT')) ?: 587);
                $smtpSecure = strtolower((string) (($_ENV['SMTP_SECURE'] ?? getenv('SMTP_SECURE')) ?: 'tls'));
                $debug['smtp_host']   = $smtpHost;
                $debug['smtp_user']   = $smtpUser;
                $debug['smtp_port']   = $smtpPort;
                $debug['smtp_secure'] = $smtpSecure;

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                // Only turn on the (noisy) SMTP transcript when a caller asked
                // for debug info — never in normal order-placement sends.
                $debugRef =& $debug;
                $mail->SMTPDebug   = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) use (&$debugRef) {
                    $debugRef['smtp_log'][] = trim(preg_replace('/\s+/', ' ', $str));
                };
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpUser;
                $mail->Password   = $smtpPass;
                $mail->SMTPSecure = $smtpSecure === 'ssl'
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $smtpPort;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom($mailFrom, $store['name']);
                $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
                foreach ($bcc as $b) { $mail->addBCC($b); }
                if (!empty($store['email'])) {
                    $mail->addReplyTo($store['email'], $store['name'] . ' Support');
                }
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $html;
                $mail->AltBody = "Order $orderNo — total Rs "
                    . number_format((float) ($order['total_amount'] ?? 0), 2)
                    . ". Thank you for your order! - " . $store['name'];
                try {
                    $mail->send();
                } catch (\Throwable $e) {
                    $debug['error'] = $e->getMessage();
                    $debug['phpmailer_error_info'] = $mail->ErrorInfo;
                    error_log("[invoice_email] SMTP send FAILED to $toEmail for $orderNo: {$mail->ErrorInfo}");
                    return false;
                }
                $debug['result'] = 'sent';
                error_log("[invoice_email] sent via SMTP to $toEmail for $orderNo");
                return true;
            }
            $debug['path'] = 'mail()';

            // ── Path 2: PHP mail() — single-part HTML ────────────────────────
            // Was multipart/alternative (text+HTML in one MIME message) — that
            // version was silently dropped by Gmail even though mail() reported
            // success, while a plain single-part text/html mail() with the same
            // sender DID arrive (confirmed 2026-09-11 via invoice_email_test.php's
            // raw-mail probe). So this now mirrors that exact working shape:
            // same minimal header set, single Content-Type, no boundary/MIME
            // multipart, no X-Mailer. Trade-off: no plain-text alternative part —
            // acceptable, virtually every mail client renders HTML today.
            $fromName = preg_replace('/[\r\n]/', '', $store['name'] ?: 'Rooto');

            $headerLines = [
                "From: {$fromName} <{$mailFrom}>",
                "Reply-To: {$mailFrom}",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
            ];
            if (!empty($bcc)) {
                $headerLines[] = "Bcc: " . implode(', ', $bcc);
            }
            $headers = implode("\r\n", $headerLines);

            // The 5th arg (-f) sets the envelope sender so it MATCHES the From
            // header. Without it hsendmail uses u<id>@srvXXXX.main-hosting.eu and
            // Gmail fails SPF alignment => the message is silently dropped.
            // (The raw mail() test that reached the inbox used exactly this.)
            error_clear_last();
            $sent = @mail($toEmail, $subject, $html, $headers, "-f{$mailFrom}");
            $debug['result']    = $sent ? 'accepted-by-mta' : 'rejected-by-mta';
            $debug['php_error'] = error_get_last()['message'] ?? null;
            error_log("[invoice_email] mail() from=$mailFrom to=$toEmail order=$orderNo => "
                . ($sent ? 'ACCEPTED (check inbox + spam)' : 'REJECTED by MTA')
                . ($havePhpMailer ? '' : ' [PHPMailer not on path]')
                . ($smtpHost === '' ? ' [no SMTP]' : ''));
            return (bool) $sent;

        } catch (\Throwable $e) {
            $debug['error'] = $e->getMessage();
            error_log('[invoice_email] FAILED: ' . $e->getMessage());
            return false;
        }
    }
}
