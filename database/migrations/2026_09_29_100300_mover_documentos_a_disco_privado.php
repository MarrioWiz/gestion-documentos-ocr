<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Antes los archivos vivían en el disco "public" (cualquiera con la URL
     * podía ver una INE). Se mueven al disco privado "local" y se calcula su
     * huella; desde ahora solo se sirven a usuarios con sesión iniciada.
     * En una instalación nueva no hay nada que mover.
     */
    public function up(): void
    {
        foreach (DB::table('documentos')->get() as $documento) {
            $publico = Storage::disk('public');

            if (! str_starts_with($documento->ruta_archivo, 'uploads/') || ! $publico->exists($documento->ruta_archivo)) {
                continue;
            }

            $rutaNueva = "documentos/{$documento->persona_id}/".basename($documento->ruta_archivo);
            Storage::disk('local')->put($rutaNueva, $publico->get($documento->ruta_archivo));
            $publico->delete($documento->ruta_archivo);

            DB::table('documentos')->where('id', $documento->id)->update([
                'ruta_archivo' => $rutaNueva,
                'archivo_hash' => hash_file('sha256', Storage::disk('local')->path($rutaNueva)),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Sin reversa: no se vuelven a exponer públicamente los documentos.
    }
};
