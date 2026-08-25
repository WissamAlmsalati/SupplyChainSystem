<?php

use App\Http\Controllers\Api\DelegateDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs/delegate', [DelegateDocsController::class, 'ui'])->name('swagger.delegate');
Route::get('/docs/delegate.json', [DelegateDocsController::class, 'json'])->name('swagger.delegate.json');
