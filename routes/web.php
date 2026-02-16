<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MindMapController;

Route::get('/', [MindMapController::class, 'index']);
