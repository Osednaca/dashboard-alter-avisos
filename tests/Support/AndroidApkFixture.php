<?php

namespace Tests\Support;

class AndroidApkFixture
{
    public static function manifest(int $code = 24, string $version = '0.1.23', string $package = 'tv.signage.player', bool $utf8 = true, bool $split = false): string
    {
        $strings = ['versionCode', 'versionName', 'http://schemas.android.com/apk/res/android', 'manifest', 'package', $package, $version, 'split', 'feature'];
        $text = '';
        $offsets = [];
        foreach ($strings as $string) {
            $offsets[] = strlen($text);
            $utf16 = mb_convert_encoding($string, 'UTF-16LE', 'UTF-8');
            $text .= $utf8 ? chr(intdiv(strlen($utf16), 2)).chr(strlen($string)).$string."\0" : pack('v', intdiv(strlen($utf16), 2)).$utf16."\0\0";
        }
        $text .= str_repeat("\0", (4 - strlen($text) % 4) % 4);
        $pool = pack('vvVVVVVV', 1, 28, 28 + count($strings) * 4 + strlen($text), count($strings), 0, $utf8 ? 0x100 : 0, 28 + count($strings) * 4, 0);
        $pool .= pack('V*', ...$offsets).$text;
        $resources = pack('vvVVV', 0x180, 8, 16, 0x0101021B, 0x0101021C);
        $attributes = self::attribute(0xFFFFFFFF, 4, 3, 5).self::attribute(2, 0, 0x10, $code).self::attribute(2, 1, 3, 6);
        if ($split) {
            $attributes .= self::attribute(0xFFFFFFFF, 7, 3, 8);
        }
        // attrExt is namespace/name + attributeStart/size/count/id/class/style (20 bytes).
        $start = pack('vvVVVVVvvvvvv', 0x102, 16, 36 + strlen($attributes), 1, 0xFFFFFFFF, 0xFFFFFFFF, 3, 20, 20, $split ? 4 : 3, 0, 0, 0).$attributes;
        $end = pack('vvVVVVV', 0x103, 16, 24, 1, 0xFFFFFFFF, 0xFFFFFFFF, 3);
        $content = $pool.$resources.$start.$end;

        return pack('vvV', 3, 8, 8 + strlen($content)).$content;
    }

    public static function apk(string $manifest, string $extra = ''): string
    {
        $name = 'AndroidManifest.xml';
        $size = strlen($manifest);
        $crc = crc32($manifest);
        $local = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0).$name.$manifest;
        $central = pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, 0).$name;

        return $local.$central.pack('VvvvvVVv', 0x06054B50, 0, 0, 1, 1, strlen($central), strlen($local), strlen($extra)).$extra;
    }

    private static function attribute(int $namespace, int $name, int $type, int $value): string
    {
        return pack('VVVvCCV', $namespace, $name, $type === 3 ? $value : 0xFFFFFFFF, 8, 0, $type, $value);
    }
}
