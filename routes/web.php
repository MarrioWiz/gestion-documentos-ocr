<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    // Máximo 5 intentos por minuto para frenar ataques de fuerza bruta.
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.intentar');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::redirect('/', '/panel');
    Route::get('/panel', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/personas', [PersonaController::class, 'index'])->name('personas.index');
    Route::get('/personas/reporte', [PersonaController::class, 'reporte'])->name('personas.reporte');
    Route::get('/personas/{persona}', [PersonaController::class, 'show'])->name('personas.show');
    Route::delete('/personas/{persona}', [PersonaController::class, 'destroy'])->name('personas.destroy');

    Route::get('/documentos/crear', [DocumentoController::class, 'create'])->name('documentos.create');
    Route::get('/documentos/carga-masiva', [DocumentoController::class, 'cargaMasivaForm'])->name('documentos.carga-masiva');
    Route::post('/documentos/carga-masiva', [DocumentoController::class, 'cargaMasiva'])->name('documentos.carga-masiva.procesar');
    Route::post('/documentos/ocr', [DocumentoController::class, 'ocr'])->name('documentos.ocr');
    Route::post('/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
    Route::post('/documentos/confirmar-reemplazo', [DocumentoController::class, 'confirmarReemplazo'])->name('documentos.confirmar-reemplazo');
    Route::post('/documentos/cancelar-reemplazo', [DocumentoController::class, 'cancelarReemplazo'])->name('documentos.cancelar-reemplazo');
    Route::get('/documentos/{documento}/archivo', [DocumentoController::class, 'archivo'])->name('documentos.archivo');
    Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy'])->name('documentos.destroy');

    Route::middleware('admin')->group(function () {
        Route::get('/historial', [HistorialController::class, 'index'])->name('historial.index');

        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
    });
});
