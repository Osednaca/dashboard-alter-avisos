<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessSettingsSimplificationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_removed_settings_endpoints_cannot_modify_legacy_data(): void
    {
        $business = Business::factory()->create(['name' => 'Original', 'metadata' => ['audio_volume' => 55]]);
        $this->actingAs($this->businessUser($business));
        $this->get('/business/settings')->assertNotFound();
        $this->put('/business/settings', ['name' => 'Changed'])->assertNotFound();
        $this->post('/business/settings/locations', ['name' => 'New'])->assertNotFound();
        $this->put('/business/settings/locations/1', ['name' => 'Changed'])->assertNotFound();
        $this->delete('/business/settings/locations/1')->assertNotFound();
        $this->assertSame('Original', $business->fresh()->name);
        $this->assertSame(55, $business->fresh()->metadata['audio_volume']);
    }
}
