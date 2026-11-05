<?php

namespace Tests\Unit;

use App\Domain\Operations\Services\AndroidBinaryManifest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\AndroidApkFixture;

class AndroidBinaryManifestTest extends TestCase
{
    #[DataProvider('encodings')]
    public function test_reads_literal_metadata_from_binary_string_pools(bool $utf8): void
    {
        $this->assertSame(['package' => 'tv.signage.player', 'versionCode' => 24, 'versionName' => '0.1.23'],
            (new AndroidBinaryManifest)->parse(AndroidApkFixture::manifest(utf8: $utf8)));
    }

    public static function encodings(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('invalidManifests')]
    public function test_rejects_malformed_incompatible_or_unsafe_metadata(string $data): void
    {
        $this->expectException(RuntimeException::class);
        (new AndroidBinaryManifest)->parse($data);
    }

    public static function invalidManifests(): array
    {
        $valid = AndroidApkFixture::manifest();
        $badSize = substr_replace($valid, pack('V', 0x7FFFFFFF), 12, 4);
        $badResource = str_replace(pack('V', 0x0101021B), pack('V', 0x01010000), $valid);

        return [
            'plain XML' => ['<manifest package="tv.signage.player"/>'],
            'truncated' => [substr($valid, 0, -4)],
            'chunk outside file' => [$badSize],
            'wrong Android resource ID' => [$badResource],
            'unsafe version path' => [AndroidApkFixture::manifest(version: '../release')],
            'zero version' => [AndroidApkFixture::manifest(code: 0)],
            'split APK' => [AndroidApkFixture::manifest(split: true)],
        ];
    }
}
