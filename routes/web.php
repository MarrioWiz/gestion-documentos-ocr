<?php

use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\PersonaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/personas');

Route::get('/personas', [PersonaController::class, 'index'])->name('personas.index');
Route::get('/personas/{persona}', [PersonaController::class, 'show'])->name('personas.show');
Route::delete('/personas/{persona}', [PersonaController::class, 'destroy'])->name('personas.destroy');

Route::get('/documentos/crear', [DocumentoController::class, 'create'])->name('documentos.create');
Route::get('/documentos/carga-masiva', [DocumentoController::class, 'cargaMasivaForm'])->name('documentos.carga-masiva');
Route::post('/documentos/carga-masiva', [DocumentoController::class, 'cargaMasiva'])->name('documentos.carga-masiva.procesar');
Route::post('/documentos/ocr', [DocumentoController::class, 'ocr'])->name('documentos.ocr');
Route::post('/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
Route::post('/documentos/confirmar-reemplazo', [DocumentoController::class, 'confirmarReemplazo'])->name('documentos.confirmar-reemplazo');
Route::post('/documentos/cancelar-reemplazo', [DocumentoController::class, 'cancelarReemplazo'])->name('documentos.cancelar-reemplazo');
Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy'])->name('documentos.destroy');
