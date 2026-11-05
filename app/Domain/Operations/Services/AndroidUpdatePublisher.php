<?php

namespace App\Domain\Operations\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class AndroidUpdatePublisher
{
    public function __construct(private AndroidApkMetadata $metadata) {}

    public function current(): ?array
    {
        $path = $this->directory().'/latest.json';
        if (! file_exists($path)) {
            return null;
        }
        if (is_link($path) || ! is_file($path) || filesize($path) > 65536) {
            throw new RuntimeException('El manifiesto publicado es inválido.');
        }
        $manifest = json_decode(file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        $keys = ['versionCode', 'versionName', 'apkUrl', 'sha256', 'forceUpdate', 'changelog'];
        if (! is_array($manifest) || count($manifest) !== count($keys) || array_diff($keys, array_keys($manifest)) ||
            ! is_int($manifest['versionCode']) || $manifest['versionCode'] < 1 ||
            ! is_string($manifest['versionName']) || ! $this->validVersion($manifest['versionName']) ||
            ! is_string($manifest['sha256']) || ! preg_match('/\A[a-f0-9]{64}\z/D', $manifest['sha256']) ||
            ! is_bool($manifest['forceUpdate']) || ! is_string($manifest['changelog']) ||
            strlen($manifest['changelog']) > 16384 || ! mb_check_encoding($manifest['changelog'], 'UTF-8') ||
            $manifest['apkUrl'] !== $this->url($manifest['versionName']) ||
            ! is_file($this->apkPath($manifest['versionName'])) || is_link($this->apkPath($manifest['versionName']))) {
            throw new RuntimeException('El manifiesto publicado es inválido; revise el almacenamiento antes de publicar.');
        }

        return $manifest;
    }

    public function publish(UploadedFile $upload, bool $forceUpdate, string $changelog): array
    {
        if (! $upload->isValid() || $upload->getSize() < 1 || $upload->getSize() > config('android_updates.max_upload_kb') * 1024 ||
            strlen($changelog) > 16384 || ! mb_check_encoding($changelog, 'UTF-8')) {
            throw new RuntimeException('El APK o las notas de la versión exceden los límites admitidos.');
        }
        $directory = $this->directory();
        $private = (string) config('android_updates.staging_directory');
        $this->ensureDirectory($directory);
        $this->ensureDirectory($private);
        $staged = $private.'/'.bin2hex(random_bytes(16)).'.upload';
        $apkTemp = null;
        $jsonTemp = null;
        $lock = null;
        try {
            $this->copyAndSync($upload->getPathname(), $staged);
            $metadata = $this->metadata->read($staged);
            $sha = hash_file('sha256', $staged);
            if ($sha === false) {
                throw new RuntimeException('No se pudo calcular el SHA-256 del APK.');
            }
            $lock = fopen($directory.'/.publication.lock', 'c');
            if ($lock === false) {
                throw new RuntimeException('No se pudo abrir el bloqueo de publicación.');
            }
            $deadline = microtime(true) + 5;
            while (! flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Hay otra publicación en curso; inténtelo nuevamente.');
                }
                usleep(50000);
            }
            $previous = $this->current();
            if ($previous !== null && ($metadata['versionCode'] < $previous['versionCode'] ||
                ($metadata['versionCode'] === $previous['versionCode'] &&
                    ($sha !== $previous['sha256'] || $metadata['versionName'] !== $previous['versionName'])))) {
                throw new RuntimeException('La versión debe ser posterior a la publicada; una versión idéntica requiere el mismo APK.');
            }
            $target = $this->apkPath($metadata['versionName']);
            if (is_link($target) || (file_exists($target) && (! is_file($target) || hash_file('sha256', $target) !== $sha))) {
                throw new RuntimeException('Este nombre de versión ya pertenece a otro APK; publique una versión nueva.');
            }
            $manifest = [
                'versionCode' => $metadata['versionCode'],
                'versionName' => $metadata['versionName'],
                'apkUrl' => $this->url($metadata['versionName']),
                'sha256' => $sha,
                'forceUpdate' => $forceUpdate,
                'changelog' => $changelog,
            ];
            $jsonTemp = $directory.'/.'.bin2hex(random_bytes(16)).'.json-stage';
            $this->writeAndSync($jsonTemp, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
            if (! file_exists($target)) {
                // The public directory can be a different filesystem from private staging.
                $apkTemp = $directory.'/.'.bin2hex(random_bytes(16)).'.apk-stage';
                $this->copyAndSync($staged, $apkTemp);
                if (hash_file('sha256', $apkTemp) !== $sha || ! rename($apkTemp, $target)) {
                    throw new RuntimeException('No se pudo publicar el APK completo.');
                }
                $apkTemp = null;
            }
            if (! rename($jsonTemp, $directory.'/latest.json')) {
                throw new RuntimeException('No se pudo publicar el manifiesto; la versión anterior sigue vigente.');
            }
            $jsonTemp = null;

            return ['previous' => $previous, 'manifest' => $manifest];
        } finally {
            foreach ([$staged, $apkTemp, $jsonTemp] as $path) {
                if ($path !== null && is_file($path)) {
                    unlink($path);
                }
            }
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public function directory(): string
    {
        return rtrim((string) config('android_updates.directory'), '/\\');
    }

    private function apkPath(string $version): string
    {
        return $this->directory().'/signage-'.$version.'.apk';
    }

    private function url(string $version): string
    {
        return rtrim((string) config('android_updates.base_url'), '/').'/signage-'.$version.'.apk';
    }

    private function validVersion(string $version): bool
    {
        return (bool) preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $version);
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0750, true) && ! is_dir($path)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento de actualizaciones.');
        }
    }

    private function copyAndSync(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($target, 'xb');
        try {
            if ($input === false || $output === false || stream_copy_to_stream($input, $output) !== filesize($source) ||
                ! fflush($output) || ! fsync($output)) {
                throw new RuntimeException('No se pudo guardar el APK completo.');
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    private function writeAndSync(string $path, string $content): void
    {
        $output = fopen($path, 'xb');
        try {
            if ($output === false || fwrite($output, $content) !== strlen($content) || ! fflush($output) || ! fsync($output)) {
                throw new RuntimeException('No se pudo guardar el manifiesto de actualización.');
            }
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }
}
