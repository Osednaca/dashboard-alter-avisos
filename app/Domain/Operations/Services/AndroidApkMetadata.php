<?php

namespace App\Domain\Operations\Services;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class AndroidApkMetadata
{
    public function __construct(private AndroidBinaryManifest $manifest) {}

    public function read(string $path): array
    {
        $process = new Process([config('android_updates.unzip_binary'), '-p', $path, 'AndroidManifest.xml']);
        $process->setTimeout(10)->disableOutput();
        $xml = '';
        $errors = 0;
        try {
            $process->run(function (string $type, string $bytes) use (&$xml, &$errors): void {
                if ($type === Process::OUT) {
                    if (strlen($xml) + strlen($bytes) > AndroidBinaryManifest::MAX_BYTES) {
                        throw new RuntimeException('El manifiesto del APK supera el límite permitido.');
                    }
                    $xml .= $bytes;
                } else {
                    $errors += strlen($bytes);
                    if ($errors > 16384) {
                        throw new RuntimeException('El archivo no es un APK válido.');
                    }
                }
            });
            if (! $process->isSuccessful()) {
                throw new RuntimeException('No se pudo leer AndroidManifest.xml. Sube un APK completo.');
            }
        } catch (Throwable $error) {
            $process->stop(0);
            throw new RuntimeException('No se pudo validar el manifiesto del APK. Verifica el archivo y la disponibilidad de unzip.', 0, $error);
        }

        $metadata = $this->manifest->parse($xml);
        if ($metadata['package'] !== config('android_updates.package')) {
            throw new RuntimeException('El APK debe corresponder al paquete de producción tv.signage.player.');
        }

        return $metadata;
    }
}
