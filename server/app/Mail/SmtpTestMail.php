<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[NiceWatch] Test SMTP',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.smtp-test',
        );
    }
}
