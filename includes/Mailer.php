<?php
/**
 * Mailer Class
 * Handles sending emails for the application.
 */

class Mailer {
    private array $config;

    public function __construct(array $config = []) {
        $this->config = array_merge([
            'mailDriver' => MAIL_DRIVER,
            'mailFromAddress' => MAIL_FROM_ADDRESS,
            'mailFromName' => MAIL_FROM_NAME,
            'smtpHost' => SMTP_HOST,
            'smtpPort' => SMTP_PORT,
            'smtpUsername' => SMTP_USERNAME,
            'smtpPassword' => SMTP_PASSWORD,
            'smtpEncryption' => SMTP_ENCRYPTION,
            'smtpTimeout' => SMTP_TIMEOUT
        ], $config);
    }

    public function send(string $to, string $subject, string $message, array $attachments = [], ?string $templateTitle = null, ?string $templateSubtitle = null): bool {
        $fromName = trim((string)($this->config['mailFromName'] ?? $this->config['businessName'] ?? getSiteName()));
        $fromEmail = trim((string)($this->config['mailFromAddress'] ?? $this->config['businessEmail'] ?? ''));
        if ($fromEmail === '') {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $fromEmail = 'noreply@' . preg_replace('/:\d+$/', '', $host);
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
            'From: ' . $this->formatMailbox($fromEmail, $fromName),
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];

        $htmlMessage = $this->wrapInTemplate($templateTitle ?? $subject, $templateSubtitle, $message);

        try {
            if ($this->shouldUseSmtp()) {
                return $this->sendViaSmtp($to, $subject, $htmlMessage, $headers, $fromEmail);
            }

            return mail($to, $subject, $htmlMessage, implode("\r\n", $headers));
        } catch (Exception $e) {
            error_log('Failed to send email: ' . $e->getMessage());
            return false;
        }
    }

    private function shouldUseSmtp(): bool {
        return strtolower((string)($this->config['mailDriver'] ?? 'mail')) === 'smtp'
            && trim((string)($this->config['smtpHost'] ?? '')) !== '';
    }

    private function resolveAppName(): string {
        $siteName = trim((string)($this->config['siteName'] ?? ''));
        if ($siteName !== '') {
            return $siteName;
        }

        return getPublicAppName();
    }

    private function resolveTemplateBrandName(): string {
        $businessName = trim((string)($this->config['businessName'] ?? ''));
        if ($businessName !== '') {
            return $businessName;
        }

        return $this->resolveAppName();
    }

    private function generateEmailReference(): string {
        return strtoupper(bin2hex(random_bytes(3)));
    }

    private function wrapInTemplate(string $title, ?string $subtitle, string $content): string {
        $siteName = $this->resolveTemplateBrandName();
        $year = date('Y');
        $subtitleHtml = $subtitle !== null && trim($subtitle) !== ''
            ? '<p style="margin: 0; color: #9ca3af; font-size: 16px; line-height: 24px;">' . e($subtitle) . '</p>'
            : '';
        $privacyUrl = APP_URL . '?page=privacy';
        $termsUrl = APP_URL . '?page=terms';
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; background: #f7f7f7; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0f172a; }
        .wrapper { width: 100%; padding: 32px 12px; background: #f7f7f7; box-sizing: border-box; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e5e7eb; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08); }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 22px 28px; border-bottom: 1px solid #f1f5f9; background: #ffffff; }
        .brand { display: flex; align-items: center; gap: 10px; color: #0f172a; }
        .brand-name { font-size: 18px; font-weight: 700; letter-spacing: -0.01em; }
        .brand-icon { width: 28px; height: 28px; color: #0f172a; }
        .hero { background: #000000; color: #ffffff; text-align: center; padding: 48px 24px; position: relative; }
        .hero-pattern { position: absolute; inset: 0; opacity: 0.2; background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.8) 1px, transparent 0); background-size: 24px 24px; }
        .hero-content { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 12px; }
        .hero-icon { width: 64px; height: 64px; border-radius: 999px; background: rgba(255,255,255,0.12); display: flex; align-items: center; justify-content: center; }
        .hero-icon svg { width: 30px; height: 30px; }
        .hero-title { margin: 0; font-size: 34px; font-weight: 700; letter-spacing: -0.02em; }
        .content { padding: 40px 32px 24px; text-align: center; }
        .content h2 { margin: 0 0 12px; font-size: 22px; font-weight: 700; color: #0f172a; }
        .content p { margin: 0 0 16px; font-size: 14px; color: #475569; line-height: 22px; }
        .btn { display: inline-block; width: 100%; max-width: 320px; padding: 14px 18px; background: #000000; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 14px; letter-spacing: 0.12em; text-transform: uppercase; border-radius: 10px; }
        .pill { display: inline-flex; align-items: center; gap: 8px; background: #f8fafc; color: #64748b; font-size: 12px; padding: 8px 14px; border-radius: 999px; }
        .code { font-family: 'Courier New', monospace; background: #f8fafc; padding: 12px 14px; border-radius: 10px; font-size: 12px; color: #0f172a; word-break: break-all; }
        .footer { background: #f8fafc; padding: 28px 24px; border-top: 1px solid #e2e8f0; text-align: center; }
        .footer-links { display: inline-flex; gap: 18px; margin: 10px 0 18px; }
        .footer-links a { color: #64748b; font-size: 11px; text-decoration: none; letter-spacing: 0.12em; text-transform: uppercase; }
        .footer small { display: block; font-size: 11px; color: #94a3b8; line-height: 16px; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="brand">
                    <svg class="brand-icon" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd"></path>
                    </svg>
                    <span class="brand-name">{$siteName}</span>
                </div>
                <svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#94a3b8" stroke-width="2">
                    <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd"></path>
                </svg>
            </div>
            <div class="hero">
                <div class="hero-pattern"></div>
                <div class="hero-content">
                    <div class="hero-icon">
                        <svg class="brand-icon" style="color: white; width: 30px; height: 30px;" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                            <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h1 class="hero-title">{$title}</h1>
                    {$subtitleHtml}
                </div>
            </div>
            <div class="content">
                {$content}
            </div>
            <div class="footer">
                <div class="footer-links">
                    <a href="{$privacyUrl}">Privacy Policy</a>
                    <a href="{$termsUrl}">Terms of Service</a>
                </div>
                <small>© {$year} {$siteName}. All rights reserved.</small>
                <small style="margin-top: 12px;">This is an automated message. Please do not reply. If you did not request this email, you can ignore it.</small>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    public function sendInvoice(array $client, array $invoice): bool {
        $subject = 'Invoice #' . ($invoice['invoiceNumber'] ?? '') . ' from ' . $this->resolveTemplateBrandName();
        $message = "
            <h2>Invoice ready</h2>
            <p>Dear " . e($client['name'] ?? 'there') . ", your invoice is ready for review.</p>
            <p><strong>Invoice #:</strong> " . e((string)($invoice['invoiceNumber'] ?? '')) . "<br>
            <strong>Total:</strong> " . formatCurrency((float)($invoice['total'] ?? 0), (string)($invoice['currency'] ?? 'USD')) . "<br>
            <strong>Due date:</strong> " . formatDate((string)($invoice['dueDate'] ?? date('Y-m-d'))) . "</p>
            <p>Thank you for your business.</p>
        ";

        return $this->send((string)($client['email'] ?? ''), $subject, $message, [], 'Invoice Ready', 'A fresh invoice is waiting for you.');
    }

    public function sendVerificationEmail(array $user, string $token): bool {
        $verifyUrl = APP_URL . '?page=verify-email&token=' . urlencode($token);
        $appName = $this->resolveAppName();
        $reference = $this->generateEmailReference();
        $subject = 'Welcome to the club — verify your email for ' . $appName;
        $message = '
            <h2>Verify your email address</h2>
            <p>Thanks for joining ' . e($appName) . '! To complete your registration and unlock the dashboard, please verify your email.</p>
            <p><a class="btn" href="' . e($verifyUrl) . '">Verify Email</a></p>
            <p class="pill">
                <span style="display:inline-flex;width:16px;height:16px;align-items:center;justify-content:center;">
                    <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"></path>
                        <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                    </svg>
                </span>
                This link expires in 24 hours.
            </p>
            <p style="margin-top: 22px;">If the button does not work, copy and paste this link into your browser:</p>
            <p class="code">' . e($verifyUrl) . '</p>
            <p style="margin-top: 18px; font-size: 12px; color: #94a3b8;">Reference: ' . e($reference) . '</p>
        ';

        return $this->send((string)($user['email'] ?? ''), $subject, $message, [], 'Welcome', 'We are excited to have you on board.');
    }

    public function sendPasswordResetEmail(array $user, string $token): bool {
        $resetUrl = APP_URL . '?page=reset-password&token=' . urlencode($token);
        $reference = $this->generateEmailReference();
        $subject = 'Reset your password for ' . $this->resolveAppName();
        $message = '
            <h2>Reset your password</h2>
            <p>We received a request to reset the password for your account. Use the button below to continue.</p>
            <p><a class="btn" href="' . e($resetUrl) . '">Reset Password</a></p>
            <p class="pill">
                <span style="display:inline-flex;width:16px;height:16px;align-items:center;justify-content:center;">
                    <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"></path>
                        <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                    </svg>
                </span>
                This link expires in 1 hour.
            </p>
            <p style="margin-top: 22px;">If the button does not work, copy and paste this link into your browser:</p>
            <p class="code">' . e($resetUrl) . '</p>
            <p style="margin-top: 18px; font-size: 12px; color: #94a3b8;">Reference: ' . e($reference) . '</p>
        ';

        return $this->send((string)($user['email'] ?? ''), $subject, $message, [], 'Password Reset', 'Secure access to your workspace.');
    }

    public function sendWelcomeEmail(array $user): bool {
        $loginUrl = APP_URL . '?page=login';
        $appName = $this->resolveAppName();
        $subject = 'Welcome to ' . $appName;
        $message = '
            <h2>You are all set</h2>
            <p>Your account is ready. Sign in to access your dashboard and start working in ' . e($appName) . '.</p>
            <p><a class="btn" href="' . e($loginUrl) . '">Sign In</a></p>
            <p>If you need help getting started, reply to this email and we will guide you.</p>
        ';

        return $this->send((string)($user['email'] ?? ''), $subject, $message, [], 'Welcome', 'Your account is ready.');
    }

    private function sendViaSmtp(string $to, string $subject, string $htmlMessage, array $headers, string $fromEmail): bool {
        $host = trim((string)($this->config['smtpHost'] ?? ''));
        $port = (int)($this->config['smtpPort'] ?? 587);
        $username = trim((string)($this->config['smtpUsername'] ?? ''));
        $password = (string)($this->config['smtpPassword'] ?? '');
        $encryption = strtolower(trim((string)($this->config['smtpEncryption'] ?? 'tls')));
        $timeout = max(5, (int)($this->config['smtpTimeout'] ?? 15));

        if ($host === '') {
            throw new Exception('SMTP host is not configured');
        }

        $scheme = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = stream_socket_client($scheme . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new Exception("SMTP connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->expectSmtpCode($socket, [220]);
            $helloName = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            $this->writeSmtpCommand($socket, 'EHLO ' . $helloName, [250]);

            if ($encryption === 'tls') {
                $this->writeSmtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('SMTP STARTTLS negotiation failed');
                }
                $this->writeSmtpCommand($socket, 'EHLO ' . $helloName, [250]);
            }

            if ($username !== '' || $password !== '') {
                $this->writeSmtpCommand($socket, 'AUTH LOGIN', [334]);
                $this->writeSmtpCommand($socket, base64_encode($username), [334]);
                $this->writeSmtpCommand($socket, base64_encode($password), [235]);
            }

            $this->writeSmtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->writeSmtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->writeSmtpCommand($socket, 'DATA', [354]);

            $subjectHeader = function_exists('mb_encode_mimeheader')
                ? mb_encode_mimeheader($subject, 'UTF-8')
                : $subject;
            $message = implode("\r\n", array_merge($headers, [
                'To: ' . $to,
                'Subject: ' . $subjectHeader,
                ''
            ])) . "\r\n" . $this->dotStuff($htmlMessage) . "\r\n.";

            fwrite($socket, $message . "\r\n");
            $this->expectSmtpCode($socket, [250]);
            $this->writeSmtpCommand($socket, 'QUIT', [221]);
            fclose($socket);
            return true;
        } catch (Exception $e) {
            fclose($socket);
            throw $e;
        }
    }

    private function writeSmtpCommand($socket, string $command, array $expectedCodes): void {
        fwrite($socket, $command . "\r\n");
        $this->expectSmtpCode($socket, $expectedCodes);
    }

    private function expectSmtpCode($socket, array $expectedCodes): void {
        $response = $this->readSmtpResponse($socket);
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new Exception('Unexpected SMTP response: ' . trim($response));
        }
    }

    private function readSmtpResponse($socket): string {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }

    private function dotStuff(string $message): string {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $normalized = preg_replace('/^\./m', '..', $normalized);
        return str_replace("\n", "\r\n", $normalized);
    }

    private function formatMailbox(string $email, string $name): string {
        $safeName = trim(str_replace(['\r', '\n'], '', $name));
        if ($safeName === '') {
            return $email;
        }

        return sprintf('%s <%s>', $safeName, $email);
    }
}
