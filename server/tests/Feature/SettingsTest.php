<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\SmtpTestMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_settings(): void
    {
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_form_with_warning_when_unconfigured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('SMTP')
            ->assertSee('Alerty mailowe nie są jeszcze gotowe');
    }

    public function test_update_persists_smtp_and_encrypts_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'mail_host' => 'smtp.example.com',
                'mail_port' => 587,
                'mail_username' => 'apikey',
                'mail_password' => 'super-secret',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'nicewatch@example.com',
                'mail_from_name' => 'NiceWatch',
                'alert_recipient' => 'admin@example.com',
                'disk_alert_percent' => 85,
                'offline_threshold_seconds' => 120,
                'alert_throttle_minutes' => 60,
            ])
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('status');

        $pwRow = Setting::where('key', 'mail_password')->firstOrFail();
        $this->assertTrue($pwRow->is_encrypted);
        $this->assertNotSame('super-secret', $pwRow->value, 'Password must be stored encrypted, not plaintext');
        $this->assertSame('super-secret', Crypt::decryptString($pwRow->value));

        $repo = app(SettingsRepository::class);
        $repo->flushCache();
        $this->assertSame('smtp.example.com', $repo->get('mail_host'));
        $this->assertSame('super-secret', $repo->get('mail_password'));
        $this->assertTrue($repo->hasUsableMail());
        $this->assertTrue($repo->hasAlertRecipient());
    }

    public function test_blank_password_field_preserves_existing_secret(): void
    {
        $user = User::factory()->create();
        $repo = app(SettingsRepository::class);
        $repo->setMany([
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
            'mail_username' => 'apikey',
            'mail_password' => 'original-secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'nicewatch@example.com',
            'mail_from_name' => 'NiceWatch',
            'alert_recipient' => 'admin@example.com',
            'disk_alert_percent' => '85',
            'offline_threshold_seconds' => '120',
            'alert_throttle_minutes' => '60',
        ]);

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'mail_host' => 'smtp.changed.com',
                'mail_port' => 465,
                'mail_username' => 'apikey',
                'mail_password' => '',  // intentionally blank
                'mail_encryption' => 'ssl',
                'mail_from_address' => 'nicewatch@example.com',
                'mail_from_name' => 'NiceWatch',
                'alert_recipient' => 'admin@example.com',
                'disk_alert_percent' => 90,
                'offline_threshold_seconds' => 60,
                'alert_throttle_minutes' => 30,
            ])
            ->assertRedirect(route('settings.edit'));

        $repo->flushCache();
        $this->assertSame('original-secret', $repo->get('mail_password'));
        $this->assertSame('smtp.changed.com', $repo->get('mail_host'));
    }

    public function test_apply_to_laravel_config_overrides_mail_settings(): void
    {
        $repo = app(SettingsRepository::class);
        $repo->setMany([
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
            'mail_username' => 'apikey',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'nicewatch@example.com',
            'mail_from_name' => 'NiceWatch Prod',
        ]);

        $repo->applyToLaravelConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('secret', config('mail.mailers.smtp.password'));
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
        $this->assertSame('nicewatch@example.com', config('mail.from.address'));
        $this->assertSame('NiceWatch Prod', config('mail.from.name'));
    }

    public function test_send_test_mail_dispatches_via_mailer(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        app(SettingsRepository::class)->setMany([
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
            'mail_from_address' => 'nicewatch@example.com',
            'alert_recipient' => 'admin@example.com',
        ]);

        $this->actingAs($user)
            ->post(route('settings.test'))
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertSent(SmtpTestMail::class, function (SmtpTestMail $mailable): bool {
            return $mailable->hasTo('admin@example.com');
        });
    }

    public function test_test_mail_refuses_when_smtp_not_configured(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.test'))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }
}
