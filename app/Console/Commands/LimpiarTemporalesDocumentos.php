<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('documentos:limpiar-temporales')]
#[Description('Borra archivos temporales de reemplazos de documentos que quedaron huérfanos (nunca confirmados ni cancelados) hace más de 24 horas.')]
class LimpiarTemporalesDocumentos extends Command
{
    public function handle(): void
    {
        $disco = Storage::disk('local');
        $borrados = 0;

        foreach ($disco->files('temp') as $archivo) {
            $antiguedadHoras = (time() - $disco->lastModified($archivo)) / 3600;

            if ($antiguedadHoras > 24) {
                $disco->delete($archivo);
                $borrados++;
            }
        }

        $this->info("Archivos temporales eliminados: {$borrados}");
    }
}
