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
            subject: 'Welcome to '.config('app.name'),
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
                            <h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;">Welcome to {$appName}</h1>
                            <p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;">Hi {$userName},</p>
                            <p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;">
                                Your student account has been successfully created using this email address:
                                <strong>{$userEmail}</strong>.
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.6;">
                                You can now sign in and start applying for scholarship programs.
                            </p>
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
