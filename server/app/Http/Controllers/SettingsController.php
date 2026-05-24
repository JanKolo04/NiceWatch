<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SettingsUpdateRequest;
use App\Mail\SmtpTestMail;
use App\Services\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function edit(): View
    {
        return view('settings.edit', [
            'values' => $this->editableValues(),
            'hasMailPassword' => $this->settings->get('mail_password') !== null,
            'mailReady' => $this->settings->hasUsableMail() && $this->settings->hasAlertRecipient(),
        ]);
    }

    public function update(SettingsUpdateRequest $request): RedirectResponse
    {
        $data = $request->settingsPayload();

        // Skip password if blank — preserves existing stored secret.
        if ($data['mail_password'] === null || $data['mail_password'] === '') {
            unset($data['mail_password']);
        }

        $this->settings->setMany($data);
        $this->settings->applyToLaravelConfig();

        // Tell active queue workers to restart so they pick up new credentials.
        Artisan::call('queue:restart');

        return redirect()
            ->route('settings.edit')
            ->with('status', 'Ustawienia zapisane. Workerzy kolejki dostali sygnał restartu.');
    }

    public function test(Request $request): RedirectResponse
    {
        $recipient = $this->settings->get('alert_recipient');

        if (! $this->settings->hasUsableMail()) {
            return back()->with('error', 'SMTP nie skonfigurowany — uzupełnij host i adres nadawcy.');
        }
        if ($recipient === null) {
            return back()->with('error', 'Brak adresu odbiorcy alertów.');
        }

        // Apply current settings synchronously and send without the queue so feedback is immediate.
        $this->settings->applyToLaravelConfig();

        try {
            Mail::to($recipient)->send(new SmtpTestMail());
        } catch (Throwable $e) {
            return back()->with('error', 'Błąd SMTP: ' . $e->getMessage());
        }

        return back()->with('status', "Wysłano test maila na {$recipient}.");
    }

    /**
     * @return array<string, ?string>
     */
    private function editableValues(): array
    {
        $keys = [
            'mail_host', 'mail_port', 'mail_username', 'mail_encryption',
            'mail_from_address', 'mail_from_name',
            'alert_recipient',
            'disk_alert_percent', 'offline_threshold_seconds', 'alert_throttle_minutes',
        ];

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->settings->get($key);
        }
        // 'none' is the UI label for "no encryption" — translate empty string back.
        if ($values['mail_encryption'] === '') {
            $values['mail_encryption'] = 'none';
        }

        return $values;
    }
}
