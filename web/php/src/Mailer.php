<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;
use App\Log;

class Mailer
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Send a welcome email to a newly registered user.
     */
    public function sendWelcome(string $toEmail, string $toName): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = match ($this->config['encryption']) {
                'ssl'  => PHPMailer::ENCRYPTION_SMTPS,
                'tls'  => PHPMailer::ENCRYPTION_STARTTLS,
                default => throw new MailerException("Unsupported encryption: {$this->config['encryption']}"),
            };
            $mail->Port       = $this->config['port'];

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Bienvenue sur Unreal Game !';
            $mail->Body    = $this->buildWelcomeHtml($toName);
            $mail->AltBody = $this->buildWelcomePlain($toName);

            $mail->send();
            return true;
        } catch (MailerException $e) {
            Log::error('Email sending failed: ' . $mail->ErrorInfo, 'email');
            return false;
        }
    }

    private function buildWelcomeHtml(string $username): string
    {
        $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        return <<<HTML
        <html>
        <body style="font-family: Arial, sans-serif; color: #333;">
            <h2>Bienvenue, {$username} !</h2>
            <p>Votre compte a été créé avec succès sur <strong>Unreal Game</strong>.</p>
            <p>Vous pouvez maintenant vous connecter et commencer votre aventure.</p>
            <br>
            <p>À bientôt,<br>L'équipe Unreal Game</p>
        </body>
        </html>
        HTML;
    }

    private function buildWelcomePlain(string $username): string
    {
        return "Bienvenue, {$username} !\n\n"
            . "Votre compte a été créé avec succès sur Unreal Game.\n"
            . "Vous pouvez maintenant vous connecter et commencer votre aventure.\n\n"
            . "À bientôt,\nL'équipe Unreal Game";
    }
}
