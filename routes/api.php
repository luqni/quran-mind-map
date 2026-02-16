<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MindMapController;

Route::get('/surah/{id}', [MindMapController::class, 'getSurahData']);
