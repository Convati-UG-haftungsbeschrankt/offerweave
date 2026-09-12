<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class MailTransport
{
    public static function configure($mailer, array $email, array $message): void
    {
        $mailer->AltBody = $message['text'];
        if ($email['from_email']) {
            $mailer->setFrom($email['from_email'], $email['from_name'], false);
        }
    }
    public static function send(
        string $to,
        array $message,
        array $identity,
        string $replyTo,
        ?array $transport = null,
    ): bool {
        $current = $transport ?? Config::get()['email'];
        $email = array_replace(
            $current,
            array_intersect_key($identity, array_flip(['from_email', 'from_name'])),
        );
        if (!is_email($to) || ($replyTo !== '' && !is_email($replyTo))) {
            return false;
        }
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        if ($email['from_email'] !== '') {
            $headers[] = 'From: ' . $email['from_email'];
        }
        $hook = static fn($mailer) => self::configure($mailer, $email, $message);
        // WordPress reuses a global mailer. Use a fresh instance and restore the original after this send.
        $previous = $GLOBALS['phpmailer'] ?? null;
        unset($GLOBALS['phpmailer']);
        add_action('phpmailer_init', $hook, PHP_INT_MAX);
        try {
            return (bool) wp_mail($to, $message['subject'], $message['html'], $headers);
        } catch (\Throwable $e) {
            return false;
        } finally {
            remove_action('phpmailer_init', $hook, PHP_INT_MAX);
            try {
                if (($GLOBALS['phpmailer'] ?? null) instanceof \PHPMailer\PHPMailer\PHPMailer) {
                    $GLOBALS['phpmailer']->smtpClose();
                }
            } catch (\Throwable $e) {
                // Cleanup must not replace the send result or leak transport credentials.
            } finally {
                if ($previous === null) {
                    unset($GLOBALS['phpmailer']);
                } else {
                    $GLOBALS['phpmailer'] = $previous;
                }
            }
        }
    }
}
