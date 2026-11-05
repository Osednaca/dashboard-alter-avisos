<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessSettingsSimplificationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    #[DataProvider('hiddenPreferences')]
    public function test_contact_only_save_preserves_hidden_settings_exactly(?array $metadata): void
    {
        $business = Business::factory()->create([
            'timezone' => 'America/Lima',
            'logo_path' => 'business/retained-logo.jpg',
            'metadata' => $metadata,
        ]);

        $this->actingAs($this->businessUser($business))
            ->put('/business/settings', [
                'name' => 'Negocio actualizado',
                'contact_name' => 'Contacto actualizado',
                'contact_email' => 'contacto@example.com',
                'contact_phone' => '+57 300 123 4567',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $business->refresh();
        $this->assertSame('Negocio actualizado', $business->name);
        $this->assertSame('Contacto actualizado', $business->contact_name);
        $this->assertSame('contacto@example.com', $business->contact_email);
        $this->assertSame('+57 300 123 4567', $business->contact_phone);
        $this->assertSame('America/Lima', $business->timezone);
        $this->assertSame('business/retained-logo.jpg', $business->logo_path);
        $this->assertSame($metadata, $business->metadata);
    }

    public static function hiddenPreferences(): array
    {
        return [
            'mixed preferences' => [['audio_volume' => 37, 'notify_email' => true, 'notify_offline' => false, 'custom' => ['retained' => 1]]],
            'missing preferences' => [['custom' => 'retained']],
            'null metadata' => [null],
            'empty metadata' => [[]],
            'null preferences' => [['audio_volume' => null, 'notify_email' => null, 'notify_offline' => null]],
        ];
    }

    public function test_explicit_legacy_settings_remain_supported_without_resetting_omitted_preferences(): void
    {
        $business = Business::factory()->create([
            'metadata' => ['audio_volume' => 37, 'notify_email' => true, 'notify_offline' => true, 'custom' => 'retained'],
        ]);

        $this->actingAs($this->businessUser($business))
            ->put('/business/settings', [
                'name' => $business->name,
                'timezone' => 'America/Lima',
                'audio_volume' => 0,
                'notify_email' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $business->refresh();
        $this->assertSame('America/Lima', $business->timezone);
        $this->assertSame(['audio_volume' => 0, 'notify_email' => false, 'notify_offline' => true, 'custom' => 'retained'], $business->metadata);
    }

    public function test_explicit_invalid_timezone_is_still_rejected(): void
    {
        $business = Business::factory()->create();

        $this->actingAs($this->businessUser($business))
            ->put('/business/settings', ['name' => $business->name, 'timezone' => 'not-a-timezone'])
            ->assertSessionHasErrors('timezone');

        $this->assertSame('America/Bogota', $business->fresh()->timezone);
    }
}
