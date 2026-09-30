<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De enum cerrado a texto para admitir "acta_nacimiento" (y futuros
     * tipos sin otra migración; la lista válida vive en Persona::TIPOS_DOCUMENTO).
     * Además: nombre original del archivo, huella SHA-256 para detectar el
     * mismo archivo subido dos veces y quién lo subió.
     */
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('tipo_documento', 40)->change();
            $table->string('nombre_original', 255)->nullable()->after('ruta_archivo');
            $table->string('archivo_hash', 64)->nullable()->index()->after('nombre_original');
            $table->foreignId('subido_por')->nullable()->after('texto_extraido')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subido_por');
            $table->dropIndex(['archivo_hash']);
            $table->dropColumn(['nombre_original', 'archivo_hash']);
        });
    }
};
