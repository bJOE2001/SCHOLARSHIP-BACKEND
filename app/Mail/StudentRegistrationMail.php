<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

class StudentRegistrationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('app.name').' account is ready',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtmlContent()->toHtml(),
        );
    }

    /**
     * Build the HTML body for the welcome email.
     */
    private function buildHtmlContent(): HtmlString
    {
        $appName = e((string) config('app.name'));
        $userName = e($this->user->name);
        $userEmail = e($this->user->email);

        return new HtmlString(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to {$appName}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;padding:24px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;">Your {$appName} account is ready</h1>
                            <p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;">Hi {$userName},</p>
                            <p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;">
                                Your student account has been created. Use the password you chose during registration to sign in:
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
                                <tr>
                                    <td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f9fafb;font-size:14px;color:#4b5563;width:34%;">Email</td>
                                    <td style="padding:10px 12px;border:1px solid #e5e7eb;font-size:14px;"><strong>{$userEmail}</strong></td>
                                </tr>
                            </table>
                            <p style="margin:0;font-size:15px;line-height:1.6;">Keep your password secure and do not share it with anyone.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML);
    }
}
