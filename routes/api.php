<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PigeonController;
use App\Http\Controllers\CoupleController;
use App\Http\Controllers\ReproductionController;
use App\Http\Controllers\CageController;
use App\Http\Controllers\SortieController;
use App\Http\Controllers\DashboardController;

// Routes publiques (sans authentification)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Routes protégées (avec authentification)
Route::middleware('auth:sanctum')->group(function () {

    // Utilisateur connecté
    Route::get('/user', [AuthController::class, 'user']);

    // Profil utilisateur
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'updatePassword']);

    // Déconnexion
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard - Statistiques globales
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Pigeons
    Route::apiResource('pigeons', PigeonController::class);
    Route::get('/pigeons/{pigeon}/history', [PigeonController::class, 'history']);
    Route::get('/pigeons-tous', [PigeonController::class, 'tous']);
    Route::get('/pigeons-disponibles', [PigeonController::class, 'disponibles']);

    // Couples
    Route::apiResource('couples', CoupleController::class);
    Route::get('/couples/{couple}/history', [CoupleController::class, 'history']);
    Route::post('/couples/{couple}/rompre', [CoupleController::class, 'rompre']);

    // Reproductions
    Route::apiResource('reproductions', ReproductionController::class);
    Route::post('/reproductions/{reproduction}/pigeonneaux', [ReproductionController::class, 'creerPigeonneaux']);

    // Cages
    Route::apiResource('cages', CageController::class);
    Route::get('/cages-visualisation', [CageController::class, 'visualisation']);
    Route::get('/cages/{cage}/history', [CageController::class, 'history']);
    Route::get('/cages-pigeons-disponibles', [CageController::class, 'pigeonsDisponibles']);
    Route::get('/cages-couples-disponibles', [CageController::class, 'couplesDisponibles']);
    Route::post('/cages/{cage}/affecter', [CageController::class, 'affecter']);
    Route::post('/cages/{cage}/liberer', [CageController::class, 'liberer']);

    // Sorties
    Route::apiResource('sorties', SortieController::class);
});