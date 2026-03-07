<?php
/**
 * Mailer Class
 * Handles sending emails for the application
 */

class Mailer {
    private array $config;

    public function __construct(array $config = []) {
        $this->config = $config;
    }

    /**
     * Send a plain text or HTML email
     */
    public function send(string $to, string $subject, string $message, array $attachments = []): bool {
        $fromName = $this->config['businessName'] ?? APP_NAME;
        $fromEmail = $this->config['businessEmail'] ?? 'noreply@' . $_SERVER['HTTP_HOST'];

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            'X-Mailer: PHP/' . phpversion()
        ];

        // Format HTML message
        $htmlMessage = $this->wrapInTemplate($subject, $message);

        // In a real self-hosted environment, this uses mail() 
        // or a library like PHPMailer for SMTP.
        // For simplicity, we use mail().
        try {
            return mail($to, $subject, $htmlMessage, implode("\r\n", $headers));
        } catch (Exception $e) {
            error_log("Failed to send email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Wrap message in a professional monochrome template
     */
    private function wrapInTemplate(string $title, string $content): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
        .header { background: #000; color: #fff; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #999; }
        .btn { display: inline-block; padding: 10px 20px; background: #000; color: #fff; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$title}</h1>
        </div>
        <div class="content">
            {$content}
        </div>
        <div class="footer">
            <p>&copy; {date('Y')} {$this->config['businessName']}. All rights reserved.</p>
            <p>Sent via LazyMan Tools</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Send an invoice to a client
     */
    public function sendInvoice(array $client, array $invoice): bool {
        $subject = "Invoice #{$invoice['invoiceNumber']} from " . ($this->config['businessName'] ?? APP_NAME);
        
        $message = "
            <p>Dear {$client['name']},</p>
            <p>Please find attached your invoice <strong>#{$invoice['invoiceNumber']}</strong>.</p>
            <p>Total Amount: <strong>" . formatCurrency($invoice['total'], $invoice['currency']) . "</strong><br>
            Due Date: " . formatDate($invoice['dueDate']) . "</p>
            <p>Thank you for your business!</p>
        ";
        
        return $this->send($client['email'], $subject, $message);
    }
}
