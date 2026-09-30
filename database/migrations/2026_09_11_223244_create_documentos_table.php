<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->enum('tipo_documento', ['ine', 'pasaporte', 'cartilla_militar', 'curp', 'licencia_conducir']);
            $table->string('numero_documento', 100)->nullable();
            $table->string('ruta_archivo', 255);
            $table->text('texto_extraido')->nullable();
            $table->timestamp('fecha_carga')->useCurrent();

            $table->unique(['persona_id', 'tipo_documento'], 'unico_doc_persona');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
