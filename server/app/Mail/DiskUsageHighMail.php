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

class DiskUsageHighMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Host $host, public readonly Alert $alert)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[NiceWatch] %s — dysk %s pełny w %.1f%%',
                $this->host->name,
                $this->alert->payload['mount'] ?? '?',
                (float) ($this->alert->payload['used_percent'] ?? 0)
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.alerts.disk-usage-high',
            with: [
                'host' => $this->host,
                'alert' => $this->alert,
            ],
        );
    }
}
