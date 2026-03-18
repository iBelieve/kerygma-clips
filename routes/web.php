<?php

use App\Http\Controllers\YouTubeController;
use Illuminate\Support\Facades\Route;

Route::get('/youtube/callback', [YouTubeController::class, 'callback'])->middleware('auth');
