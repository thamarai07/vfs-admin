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
    function sendOrderInvoiceEmail(string $toEmail, string $toName, array $order, array $items, array $bcc = []): bool
    {
        try {
            $toEmail = trim($toEmail);
            if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
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

            $autoload = __DIR__ . '/../vendor/autoload.php';
            $manual   = __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
            $havePhpMailer = file_exists($autoload) || file_exists($manual);

            // ── Path 1: PHPMailer + SMTP ────────────────────────────────────
            if ($havePhpMailer && $smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '') {
                if (file_exists($autoload)) {
                    require_once $autoload;
                } else {
                    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
                    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
                    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
                }

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpUser;
                $mail->Password   = $smtpPass;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
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
                $mail->send();
                error_log("[invoice_email] sent via SMTP to $toEmail for $orderNo");
                return true;
            }

            // ── Path 2: PHP mail() — multipart/alternative (text + HTML) ────
            $fromName = preg_replace('/[\r\n]/', '', $store['name'] ?: 'Rooto');
            $text     = buildOrderInvoiceText($order, $items, $store);
            $boundary = 'b_' . bin2hex(random_bytes(12));

            $headerLines = [
                "From: {$fromName} <{$mailFrom}>",
                "Reply-To: {$mailFrom}",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
                "X-Mailer: PHP",
            ];
            if (!empty($bcc)) {
                $headerLines[] = "Bcc: " . implode(', ', $bcc);
            }
            $headers = implode("\r\n", $headerLines);

            $bodyMime =
                "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $text . "\r\n\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $html . "\r\n\r\n"
                . "--{$boundary}--\r\n";

            // The 5th arg (-f) sets the envelope sender so it MATCHES the From
            // header. Without it hsendmail uses u<id>@srvXXXX.main-hosting.eu and
            // Gmail fails SPF alignment => the message is silently dropped.
            // (The raw mail() test that reached the inbox used exactly this.)
            $sent = @mail($toEmail, $subject, $bodyMime, $headers, "-f{$mailFrom}");
            error_log("[invoice_email] mail() from=$mailFrom to=$toEmail order=$orderNo => "
                . ($sent ? 'ACCEPTED (check inbox + spam)' : 'REJECTED by MTA')
                . ($havePhpMailer ? '' : ' [PHPMailer not on path]')
                . ($smtpHost === '' ? ' [no SMTP]' : ''));
            return (bool) $sent;

        } catch (\Throwable $e) {
            error_log('[invoice_email] FAILED: ' . $e->getMessage());
            return false;
        }
    }
}
