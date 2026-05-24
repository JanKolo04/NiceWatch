<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Alert;
use App\Models\Host;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HostOfflineMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Host $host, public readonly Alert $alert)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[NiceWatch] %s offline', $this->host->name),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.alerts.host-offline',
            with: [
                'host' => $this->host,
                'alert' => $this->alert,
            ],
        );
    }
}
