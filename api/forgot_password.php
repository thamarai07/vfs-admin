<?php
// ── 0. Error reporting ────────────────────────────────────────
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ── 1. CORS ───────────────────────────────────────────────────
$allowedOrigins = [
    'https://rooto.in',
    'https://www.rooto.in',
    'http://localhost:3000',
    'http://localhost:3001',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

error_log("[ForgotPassword] ===== REQUEST START =====");

// ── 2. Load DB ────────────────────────────────────────────────
try {
    require_once __DIR__ . '/../config/db.php';
    error_log("[ForgotPassword] DB loaded OK");
} catch (Throwable $e) {
    error_log("[ForgotPassword] DB load FAILED: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'DB config error', 'debug' => $e->getMessage()]);
    exit;
}

// ── 3. Read Input ─────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

error_log("[ForgotPassword] Raw input: " . $raw);

if (empty($data['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Email is required', 'debug' => 'missing_email']);
    exit;
}

$email = trim($data['email']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email', 'debug' => 'invalid_email_format']);
    exit;
}

// ── 4. Check DB connection ────────────────────────────────────
if (!isset($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed', 'debug' => 'conn_not_set']);
    exit;
}

// ── 5. Check table ────────────────────────────────────────────
try {
    $tableCheck  = $conn->query("SHOW TABLES LIKE 'password_resets'")->fetchAll();
    $tableExists = count($tableCheck) > 0;
    if (!$tableExists) {
        echo json_encode([
            'status' => 'error',
            'debug'  => 'table_password_resets_missing',
            'fix'    => 'Run the CREATE TABLE SQL from the setup guide',
        ]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'debug' => 'table_check_failed', 'error' => $e->getMessage()]);
    exit;
}

// ── 6. Lookup user ────────────────────────────────────────────
try {
    $stmt = $conn->prepare("SELECT id, name FROM customers WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("[ForgotPassword] User found: " . ($user ? "YES id=" . $user['id'] : "NO"));
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'debug' => 'user_lookup_failed', 'error' => $e->getMessage()]);
    exit;
}

if (!$user) {
    echo json_encode(['status' => 'success', 'message' => 'If this email is registered, a reset link has been sent.']);
    exit;
}

// ── 7. Generate & store token ─────────────────────────────────
try {
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $del = $conn->prepare("DELETE FROM password_resets WHERE customer_id = :id");
    $del->execute([':id' => $user['id']]);

    $ins = $conn->prepare("INSERT INTO password_resets (customer_id, token, expires_at) VALUES (:customer_id, :token, :expires_at)");
    $ins->execute([':customer_id' => $user['id'], ':token' => $token, ':expires_at' => $expiresAt]);
    error_log("[ForgotPassword] Token saved");
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'debug' => 'token_save_failed', 'error' => $e->getMessage()]);
    exit;
}

// ── 8. Build reset URL ────────────────────────────────────────
$frontendUrl = $_ENV['FRONTEND_URL'] ?? 'https://rooto.in';
$resetUrl    = "$frontendUrl/reset-password?token=$token";
$userName    = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
$userEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$currentYear = date('Y');

error_log("[ForgotPassword] Reset URL: " . $resetUrl);

// ── 9. Build Email HTML ───────────────────────────────────────
$emailHtml = <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>Reset your password — Rooto</title>
</head>
<body style="margin:0;padding:0;background-color:#f6f9f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

  <!-- Outer wrapper -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
         style="background-color:#f6f9f6;min-height:100vh;">
    <tr>
      <td align="center" style="padding:40px 16px;">

        <!-- Card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;
                      box-shadow:0 4px 24px rgba(0,0,0,0.07);">

          <!-- ── Top accent bar ── -->
          <tr>
            <td style="background:linear-gradient(135deg,#16a34a 0%,#22c55e 100%);height:5px;font-size:0;line-height:0;">&nbsp;</td>
          </tr>

          <!-- ── Logo / Brand ── -->
          <tr>
            <td align="center" style="padding:36px 40px 0 40px;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background:#f0fdf4;border-radius:12px;padding:10px 20px;">
                    <span style="font-size:22px;font-weight:800;color:#16a34a;letter-spacing:-0.5px;">
                      🌿 Rooto
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- ── Icon ── -->
          <tr>
            <td align="center" style="padding:28px 40px 0 40px;">
              <div style="width:64px;height:64px;background:#f0fdf4;border-radius:50%;
                          display:inline-flex;align-items:center;justify-content:center;
                          font-size:30px;line-height:64px;text-align:center;">
                🔑
              </div>
            </td>
          </tr>

          <!-- ── Heading ── -->
          <tr>
            <td align="center" style="padding:20px 40px 0 40px;">
              <h1 style="margin:0;font-size:24px;font-weight:700;color:#111827;letter-spacing:-0.3px;">
                Reset your password
              </h1>
            </td>
          </tr>

          <!-- ── Divider ── -->
          <tr>
            <td align="center" style="padding:16px 40px 0 40px;">
              <div style="width:40px;height:3px;background:#16a34a;border-radius:99px;margin:0 auto;"></div>
            </td>
          </tr>

          <!-- ── Body text ── -->
          <tr>
            <td style="padding:24px 40px 0 40px;">
              <p style="margin:0;font-size:15px;color:#4b5563;line-height:1.7;">
                Hi <strong style="color:#111827;">$userName</strong>,
              </p>
              <p style="margin:12px 0 0 0;font-size:15px;color:#4b5563;line-height:1.7;">
                We received a request to reset the password for your Rooto account.
                Click the button below to set a new password.
              </p>
            </td>
          </tr>

          <!-- ── Expiry notice ── -->
          <tr>
            <td style="padding:20px 40px 0 40px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
                              padding:12px 16px;">
                    <p style="margin:0;font-size:13px;color:#92400e;line-height:1.5;">
                      ⏰ &nbsp;<strong>This link expires in 1 hour.</strong>
                      If you don't reset your password within this time, you'll need to request a new link.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- ── CTA Button ── -->
          <tr>
            <td align="center" style="padding:32px 40px 0 40px;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="border-radius:10px;background:linear-gradient(135deg,#16a34a 0%,#22c55e 100%);
                              box-shadow:0 4px 14px rgba(22,163,74,0.35);">
                    <a href="$resetUrl"
                       target="_blank"
                       style="display:inline-block;padding:14px 36px;font-size:15px;
                              font-weight:700;color:#ffffff;text-decoration:none;
                              border-radius:10px;letter-spacing:0.2px;">
                      Reset My Password →
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- ── Fallback link ── -->
          <tr>
            <td style="padding:20px 40px 0 40px;">
              <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6;text-align:center;">
                Button not working? Copy and paste this link into your browser:
              </p>
              <p style="margin:6px 0 0 0;font-size:11px;text-align:center;">
                <a href="$resetUrl"
                   style="color:#16a34a;word-break:break-all;text-decoration:underline;">
                  $resetUrl
                </a>
              </p>
            </td>
          </tr>

          <!-- ── Security note ── -->
          <tr>
            <td style="padding:24px 40px 0 40px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background:#f9fafb;border-radius:10px;padding:14px 16px;">
                    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
                      🔒 &nbsp;<strong style="color:#374151;">Didn't request this?</strong>
                      You can safely ignore this email. Your password won't change unless you click the
                      button above and create a new one.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- ── Spacer ── -->
          <tr><td style="height:36px;">&nbsp;</td></tr>

          <!-- ── Footer ── -->
          <tr>
            <td style="background:#f9fafb;border-top:1px solid #f3f4f6;padding:24px 40px;border-radius:0 0 16px 16px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td align="center">
                    <p style="margin:0;font-size:13px;color:#9ca3af;">
                      This email was sent to <strong style="color:#6b7280;">$userEmail</strong>
                    </p>
                    <p style="margin:8px 0 0 0;font-size:12px;color:#d1d5db;">
                      © $currentYear Rooto · Fresh Groceries Delivered
                    </p>
                    <p style="margin:8px 0 0 0;font-size:12px;">
                      <a href="https://rooto.in" style="color:#16a34a;text-decoration:none;">rooto.in</a>
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
        <!-- /Card -->

      </td>
    </tr>
  </table>

</body>
</html>
HTML;

// ── Plain text version ────────────────────────────────────────
$emailText = "Hi {$user['name']},\r\n\r\n"
    . "We received a request to reset your Rooto password.\r\n\r\n"
    . "Reset your password here (link expires in 1 hour):\r\n"
    . "$resetUrl\r\n\r\n"
    . "If you didn't request this, ignore this email — your password won't change.\r\n\r\n"
    . "— The Rooto Team\r\n"
    . "rooto.in";

// ── 10. Send email ────────────────────────────────────────────
$autoloadPath   = __DIR__ . '/../vendor/autoload.php';
$manualMailPath = __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';

error_log("[ForgotPassword] autoload: "    . (file_exists($autoloadPath)   ? "EXISTS" : "MISSING"));
error_log("[ForgotPassword] manualPath: "  . (file_exists($manualMailPath) ? "EXISTS" : "MISSING"));

if (file_exists($autoloadPath) || file_exists($manualMailPath)) {

    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        error_log("[ForgotPassword] Loaded via autoload.php");
    } else {
        require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
        error_log("[ForgotPassword] Loaded via manual require");
    }

    $smtpHost = $_ENV['SMTP_HOST'] ?? '';
    $smtpUser = $_ENV['SMTP_USER'] ?? '';
    $smtpPass = $_ENV['SMTP_PASS'] ?? '';
    $mailFrom = $_ENV['MAIL_FROM'] ?? $smtpUser;

    error_log("[ForgotPassword] SMTP_HOST: " . ($smtpHost ?: 'NOT SET'));
    error_log("[ForgotPassword] SMTP_USER: " . ($smtpUser ?: 'NOT SET'));
    error_log("[ForgotPassword] SMTP_PASS: " . (!empty($smtpPass) ? 'SET' : 'NOT SET'));

    if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
        echo json_encode([
            'status'    => 'error',
            'message'   => 'Email config missing in .env',
            'debug'     => 'missing_smtp_config',
            'fix'       => 'Add SMTP_HOST, SMTP_USER, SMTP_PASS to your .env file',
            'token'     => $token,    // ← REMOVE IN PRODUCTION
            'reset_url' => $resetUrl, // ← REMOVE IN PRODUCTION
        ]);
        exit;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom($mailFrom, 'Rooto 🌿');
        $mail->addAddress($email, $user['name']);
        $mail->addReplyTo($mailFrom, 'Rooto Support');

        // Content
        $mail->isHTML(true);
        $mail->Subject = '🔑 Reset your Rooto password';
        $mail->Body    = $emailHtml;
        $mail->AltBody = $emailText;

        $mail->send();
        error_log("[ForgotPassword] ✅ Email sent successfully");

    } catch (\Exception $e) {
        error_log("[ForgotPassword] ❌ PHPMailer FAILED: " . $mail->ErrorInfo);
        echo json_encode([
            'status'    => 'error',
            'message'   => 'Failed to send email.',
            'debug'     => 'phpmailer_send_failed',
            'error'     => $mail->ErrorInfo,
            'token'     => $token,    // ← REMOVE IN PRODUCTION
            'reset_url' => $resetUrl, // ← REMOVE IN PRODUCTION
        ]);
        exit;
    }

} else {
    // Fallback: PHP mail()
    error_log("[ForgotPassword] PHPMailer not found — using mail()");

    $subject  = "Reset your Rooto password";
    $headers  = implode("\r\n", [
        "From: Rooto <no-reply@rooto.in>",
        "Reply-To: no-reply@rooto.in",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "X-Mailer: PHP/" . phpversion(),
    ]);

    $mailSent = mail($email, $subject, $emailHtml, $headers);
    error_log("[ForgotPassword] mail() result: " . ($mailSent ? "SUCCESS" : "FAILED"));

    if (!$mailSent) {
        echo json_encode([
            'status'    => 'error',
            'message'   => 'Could not send email.',
            'debug'     => 'no_phpmailer_and_mail_failed',
            'token'     => $token,    // ← REMOVE IN PRODUCTION
            'reset_url' => $resetUrl, // ← REMOVE IN PRODUCTION
        ]);
        exit;
    }
}

error_log("[ForgotPassword] ===== SUCCESS =====");
echo json_encode([
    'status'  => 'success',
    'message' => 'If this email is registered, a reset link has been sent.',
]);

$conn = null;