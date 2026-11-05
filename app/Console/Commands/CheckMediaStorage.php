<?php

namespace App\Console\Commands;

use App\Domain\Media\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckMediaStorage extends Command
{
    protected $signature = 'signage:media-check';

    protected $description = 'Comprueba los originales y vistas previas sin modificar ni borrar archivos';

    public function handle(): int
    {
        $name = config('signage.media_disk');
        $disk = Storage::disk($name);
        $this->line("Disco: {$name}");
        if (config("filesystems.disks.{$name}.driver") === 'local') {
            $this->line('Carpeta de archivos: '.$disk->path(''));
            $this->line('En Docker/EasyPanel esta carpeta debe estar en un volumen persistente.');
        }
        $missing = 0;
        $checked = 0;
        foreach (MediaAsset::query()->whereIn('type', ['image', 'video'])->lazyById(200) as $asset) {
            $checked++;
            if (! $disk->exists($asset->storage_path)) {
                $this->error("Original ausente: archivo #{$asset->id} ({$asset->storage_path})");
                $missing++;
            }
            if ($asset->thumbnail_path && $asset->thumbnail_path !== $asset->storage_path && ! $disk->exists($asset->thumbnail_path)) {
                $this->error("Vista previa ausente: archivo #{$asset->id} ({$asset->thumbnail_path})");
                $missing++;
            }
        }
        $this->line("Archivos registrados: {$checked}. Rutas ausentes: {$missing}.");
        if ($missing > 0) {
            $this->warn('Recupera los archivos del contenedor anterior o de una copia de seguridad. No se modificaron registros ni archivos.');

            return self::FAILURE;
        }
        $this->info('Los archivos registrados están disponibles en el disco.');
        $this->line('Si el navegador devuelve 404, comprueba public/storage, APP_URL y el montaje del volumen.');

        return self::SUCCESS;
    }
}
