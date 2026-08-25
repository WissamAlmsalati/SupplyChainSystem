<?php

use App\Http\Controllers\Api\DelegateDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', fn () => view('docs'))->name('docs.landing');
Route::get('/docs/admin', fn () => view('scalar', ['title' => 'Admin API', 'url' => '/docs/json']))->name('scalar.admin');
Route::get('/docs/cafe', fn () => view('scalar', ['title' => 'Cafe Mobile API', 'url' => '/docs/cafe/json']))->name('scalar.cafe');
Route::get('/docs/delegate', [DelegateDocsController::class, 'ui'])->name('swagger.delegate');
Route::get('/docs/delegate-scalar', fn () => view('scalar', ['title' => 'Delegate Mobile API', 'url' => route('swagger.delegate.json')]))->name('scalar.delegate');
Route::get('/docs/delegate.json', [DelegateDocsController::class, 'json'])->name('swagger.delegate.json');
Route::view('/qa/cafe', 'qa.cafe')->name('qa.cafe');
