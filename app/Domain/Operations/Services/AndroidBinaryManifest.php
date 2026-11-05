<?php

namespace App\Domain\Operations\Services;

use RuntimeException;

/** Bounded AXML reader; layouts follow AOSP androidfw/ResourceTypes.h. */
class AndroidBinaryManifest
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    private const ANDROID = 'http://schemas.android.com/apk/res/android';

    public function parse(string $data): array
    {
        $length = strlen($data);
        if ($length < 8 || $length > self::MAX_BYTES || $this->u16($data, 0) !== 3 ||
            $this->u16($data, 2) !== 8 || $this->u32($data, 4) !== $length) {
            $this->invalid();
        }
        $strings = null;
        $resources = [];
        $stack = [];
        $root = null;
        $closed = false;
        for ($offset = 8; $offset < $length; $offset += $size) {
            $type = $this->u16($data, $offset);
            $header = $this->u16($data, $offset + 2);
            $size = $this->u32($data, $offset + 4);
            if ($header < 8 || $size < $header || $size % 4 !== 0 || $size > $length - $offset) {
                $this->invalid();
            }
            $chunk = substr($data, $offset, $size);
            if ($type === 1) {
                if ($strings !== null || $root !== null) {
                    $this->invalid();
                }
                $strings = $this->stringPool($chunk, $header);

                continue;
            }
            if ($strings === null) {
                $this->invalid();
            }
            if ($type === 0x180) {
                if ($header !== 8 || $root !== null || $resources !== [] || ($size - 8) / 4 > count($strings)) {
                    $this->invalid();
                }
                for ($i = 8; $i < $size; $i += 4) {
                    $resources[] = $this->u32($chunk, $i);
                }

                continue;
            }
            if ($header !== 16 || $size < 24) {
                $this->invalid();
            }
            $namespace = $this->string($strings, $this->u32($chunk, 16));
            $name = $this->string($strings, $this->u32($chunk, 20));
            if (in_array($type, [0x100, 0x101], true)) {
                continue;
            }
            if ($type === 0x103) {
                if ($stack === [] || array_pop($stack) !== [$namespace, $name]) {
                    $this->invalid();
                }
                $closed = $stack === [];

                continue;
            }
            if ($type !== 0x102 || $size < 36 || $closed || $name === null || count($stack) >= 128) {
                $this->invalid();
            }
            $start = $this->u16($chunk, 24);
            $stride = $this->u16($chunk, 26);
            $count = $this->u16($chunk, 28);
            if ($start < 20 || $stride < 20 || $count > 1024 || 16 + $start + $count * $stride > $size) {
                $this->invalid();
            }
            $attributes = [];
            for ($i = 0; $i < $count; $i++) {
                $at = 16 + $start + $i * $stride;
                $ns = $this->string($strings, $this->u32($chunk, $at));
                $index = $this->u32($chunk, $at + 4);
                $key = $this->string($strings, $index);
                $raw = $this->string($strings, $this->u32($chunk, $at + 8));
                if ($key === null || $this->u16($chunk, $at + 12) !== 8 || ord($chunk[$at + 14]) !== 0) {
                    $this->invalid();
                }
                $kind = ord($chunk[$at + 15]);
                $value = $this->u32($chunk, $at + 16);
                $decoded = match ($kind) {
                    3 => $this->string($strings, $value),
                    0x10, 0x11 => $value,
                    0x12 => $value !== 0,
                    default => null, // Resource references cannot supply literal release metadata.
                };
                if ($kind === 3 && ($decoded === null || ($raw !== null && $raw !== $decoded))) {
                    $this->invalid();
                }
                $qualified = ($ns ?? '').'|'.$key;
                if (array_key_exists($qualified, $attributes)) {
                    $this->invalid();
                }
                $attributes[$qualified] = $decoded;
                if (in_array($key, ['split', 'configForSplit', 'isFeatureSplit', 'isSplitRequired', 'requiredSplitTypes'], true) &&
                    $decoded !== null && $decoded !== false && $decoded !== '' && $decoded !== 0) {
                    throw new RuntimeException('Se requiere un APK base universal, sin splits.');
                }
                $expected = ['versionCode' => 0x0101021B, 'versionName' => 0x0101021C, 'versionCodeMajor' => 0x01010576][$key] ?? null;
                if ($stack === [] && $ns === self::ANDROID && $expected !== null && ($resources[$index] ?? null) !== $expected) {
                    $this->invalid();
                }
            }
            if ($name === 'uses-split') {
                throw new RuntimeException('Se requiere un APK base universal, sin splits.');
            }
            if ($stack === []) {
                if ($root !== null || $namespace !== null || $name !== 'manifest') {
                    $this->invalid();
                }
                $root = $attributes;
            }
            $stack[] = [$namespace, $name];
        }
        $package = $root['|package'] ?? null;
        $code = $root[self::ANDROID.'|versionCode'] ?? null;
        $major = $root[self::ANDROID.'|versionCodeMajor'] ?? 0;
        $version = $root[self::ANDROID.'|versionName'] ?? null;
        if (! $closed || $stack !== [] || ! is_string($package) || ! is_int($code) || $code < 1 || $code > 0x7FFFFFFF ||
            ! is_int($major) || $major < 0 || $major > 0x7FFFFFFF || PHP_INT_SIZE < 8 || ! is_string($version) ||
            ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $version)) {
            $this->invalid();
        }

        return ['package' => $package, 'versionCode' => ($major << 32) | $code, 'versionName' => $version];
    }

    private function stringPool(string $chunk, int $header): array
    {
        if ($header < 28) {
            $this->invalid();
        }
        $count = $this->u32($chunk, 8);
        $start = $this->u32($chunk, 20);
        if ($count > 4096 || $this->u32($chunk, 12) !== 0 || $this->u32($chunk, 24) !== 0 ||
            $start < $header + $count * 4 || $start > strlen($chunk)) {
            $this->invalid();
        }
        $utf8 = ($this->u32($chunk, 16) & 0x100) !== 0;
        $strings = [];
        $decodedBytes = 0;
        for ($i = 0; $i < $count; $i++) {
            $at = $start + $this->u32($chunk, $header + $i * 4);
            $characters = $this->stringLength($chunk, $at, $utf8);
            $bytes = $utf8 ? $this->stringLength($chunk, $at, true) : $characters * 2;
            $terminator = $utf8 ? 1 : 2;
            if ($bytes > 65536 || $at + $bytes + $terminator > strlen($chunk) ||
                substr($chunk, $at + $bytes, $terminator) !== str_repeat("\0", $terminator)) {
                $this->invalid();
            }
            $text = substr($chunk, $at, $bytes);
            if (! mb_check_encoding($text, $utf8 ? 'UTF-8' : 'UTF-16LE')) {
                $this->invalid();
            }
            $text = $utf8 ? $text : mb_convert_encoding($text, 'UTF-8', 'UTF-16LE');
            $decodedBytes += strlen($text);
            if ($decodedBytes > self::MAX_BYTES || str_contains($text, "\0") ||
                intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2) !== $characters) {
                $this->invalid();
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function stringLength(string $data, int &$at, bool $utf8): int
    {
        if ($utf8) {
            $this->bounds($data, $at, 1);
            $value = ord($data[$at++]);
            if (($value & 0x80) !== 0) {
                $this->bounds($data, $at, 1);
                $value = (($value & 0x7F) << 8) | ord($data[$at++]);
            }
        } else {
            $value = $this->u16($data, $at);
            $at += 2;
            if (($value & 0x8000) !== 0) {
                $value = (($value & 0x7FFF) << 16) | $this->u16($data, $at);
                $at += 2;
            }
        }

        return $value;
    }

    private function string(array $strings, int $index): ?string
    {
        if ($index === 0xFFFFFFFF) {
            return null;
        }
        if (! array_key_exists($index, $strings)) {
            $this->invalid();
        }

        return $strings[$index];
    }

    private function u16(string $data, int $at): int
    {
        $this->bounds($data, $at, 2);

        return unpack('v', substr($data, $at, 2))[1];
    }

    private function u32(string $data, int $at): int
    {
        $this->bounds($data, $at, 4);

        return unpack('V', substr($data, $at, 4))[1];
    }

    private function bounds(string $data, int $at, int $size): void
    {
        if ($at < 0 || $at > strlen($data) - $size) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw new RuntimeException('El AndroidManifest.xml del APK es inválido o no contiene metadatos literales compatibles.');
    }
}
