<?php

namespace Tests\Feature;

use App\Domain\Operations\Models\AuditLog;
use App\Domain\Operations\Services\AndroidUpdatePublisher;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesUsers;
use Tests\Support\AndroidApkFixture;
use Tests\TestCase;

class AndroidUpdatePublicationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/android-update-test-'.bin2hex(random_bytes(10));
        config(['android_updates.directory' => $this->directory.'/public', 'android_updates.staging_directory' => $this->directory.'/private']);
        if (getenv('OTA_TEST_UNZIP')) {
            config(['android_updates.unzip_binary' => getenv('OTA_TEST_UNZIP')]);
        }
    }

    protected function tearDown(): void
    {
        $resolved = realpath($this->directory);
        if ($resolved !== false && dirname($resolved) === realpath(sys_get_temp_dir()) &&
            str_starts_with(basename($resolved), 'android-update-test-')) {
            File::deleteDirectory($resolved);
        }
        parent::tearDown();
    }

    public function test_admin_upload_publishes_six_fields_hash_public_delivery_and_audit(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get('/admin/android-updates')->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/AndroidUpdates/Index')->where('currentUpdate', null));
        $apk = AndroidApkFixture::apk(AndroidApkFixture::manifest());
        $this->actingAs($admin)->from('/admin/android-updates')->post('/admin/android-updates', [
            'apk' => UploadedFile::fake()->createWithContent('production.apk', $apk),
            'forceUpdate' => '1', 'changelog' => 'Actualización segura',
        ])->assertRedirect('/admin/android-updates')->assertSessionHasNoErrors()->assertSessionHas('success');
        $manifest = app(AndroidUpdatePublisher::class)->current();
        $this->assertSame(['versionCode', 'versionName', 'apkUrl', 'sha256', 'forceUpdate', 'changelog'], array_keys($manifest));
        $this->assertSame(24, $manifest['versionCode']);
        $this->assertSame(hash('sha256', $apk), $manifest['sha256']);
        $this->assertTrue($manifest['forceUpdate']);
        $this->assertSame('https://signage.finespublicidad.com/updates/android/signage-0.1.23.apk', $manifest['apkUrl']);
        $this->assertSame($apk, file_get_contents($this->directory.'/public/signage-0.1.23.apk'));
        $this->assertSame(1, AuditLog::query()->where('action', 'android_update.published')->count());
        $this->get('/updates/android/latest.json')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $this->get('/updates/android/latest.json')->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', $this->get('/updates/android/signage-0.1.23.apk')->headers->get('Cache-Control'));
        $this->get('/updates/android/.publication.lock')->assertNotFound();
        $this->get('/updates/android/other.apk')->assertNotFound();
        $this->assertSame([], glob($this->directory.'/private/*'));
        $this->assertSame([], glob($this->directory.'/public/.*-stage'));
    }

    public function test_publication_requires_admin_permission_and_valid_fields(): void
    {
        $this->get('/admin/android-updates')->assertRedirect('/login');
        $this->post('/admin/android-updates')->assertRedirect('/login');
        $this->actingAs($this->userWithRole(RoleEnum::Operator))->get('/admin/android-updates')->assertForbidden();
        $this->post('/admin/android-updates')->assertForbidden();
        $this->actingAs($this->superAdmin())->post('/admin/android-updates', [
            'apk' => $this->upload(), 'forceUpdate' => 'invalid', 'changelog' => str_repeat('é', 8193),
        ])->assertSessionHasErrors(['forceUpdate', 'changelog']);
        $this->assertDirectoryDoesNotExist($this->directory.'/public');
        $this->post('/admin/android-updates', ['apk' => $this->upload()->size(128 * 1024 + 1)])->assertSessionHasErrors('apk');
        $this->assertDirectoryDoesNotExist($this->directory.'/public');
    }

    public function test_same_apk_can_change_policy_but_downgrades_collisions_and_changed_bytes_preserve_previous_release(): void
    {
        $this->actingAs($this->superAdmin());
        $this->post('/admin/android-updates', ['apk' => $this->upload()])->assertSessionHasNoErrors();
        $this->post('/admin/android-updates', ['apk' => $this->upload(), 'forceUpdate' => true, 'changelog' => 'Obligatoria'])->assertSessionHasNoErrors();
        $before = file_get_contents($this->directory.'/public/latest.json');
        foreach ([$this->upload(23, '0.1.22'), $this->upload(extra: 'different bytes'), $this->upload(25, '0.1.23')] as $apk) {
            $this->post('/admin/android-updates', ['apk' => $apk])->assertSessionHasErrors('apk');
            $this->assertSame($before, file_get_contents($this->directory.'/public/latest.json'));
        }
        $this->post('/admin/android-updates', ['apk' => $this->upload(25, '0.1.24')])->assertSessionHasNoErrors();
        $this->assertSame(25, app(AndroidUpdatePublisher::class)->current()['versionCode']);
        $this->assertFileExists($this->directory.'/public/signage-0.1.23.apk');
    }

    public function test_wrong_package_split_or_truncated_apk_never_replaces_current_manifest(): void
    {
        $this->actingAs($this->superAdmin());
        $this->post('/admin/android-updates', ['apk' => $this->upload()])->assertSessionHasNoErrors();
        $before = file_get_contents($this->directory.'/public/latest.json');
        foreach ([AndroidApkFixture::apk(AndroidApkFixture::manifest(package: 'other.app')),
            AndroidApkFixture::apk(AndroidApkFixture::manifest(split: true)), 'PK incomplete'] as $data) {
            $this->post('/admin/android-updates', ['apk' => UploadedFile::fake()->createWithContent('release.apk', $data)])->assertSessionHasErrors('apk');
            $this->assertSame($before, file_get_contents($this->directory.'/public/latest.json'));
        }
    }

    public function test_storage_failure_preserves_previous_json_and_cleans_own_temporary_files(): void
    {
        $publisher = app(AndroidUpdatePublisher::class);
        $publisher->publish($this->upload(), false, '');
        $this->get('/updates/android/latest.json')->assertOk();
        $before = file_get_contents($this->directory.'/public/latest.json');
        // A directory at the immutable filename simulates publication storage failure.
        mkdir($this->directory.'/public/signage-0.1.24.apk');
        try {
            $publisher->publish($this->upload(25, '0.1.24'), false, '');
            $this->fail('Expected storage failure');
        } catch (\RuntimeException) {
            $this->assertSame($before, file_get_contents($this->directory.'/public/latest.json'));
            $this->assertSame([], glob($this->directory.'/private/*'));
        }
    }

    private function upload(int $code = 24, string $version = '0.1.23', string $extra = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('release.apk', AndroidApkFixture::apk(AndroidApkFixture::manifest($code, $version), $extra));
    }
}
