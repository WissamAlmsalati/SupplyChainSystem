<?php

use App\Http\Controllers\Api\DelegateDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', fn () => view('docs'))->name('docs.landing');
Route::get('/docs/admin', fn () => view('scalar', ['title' => 'Admin API', 'url' => '/docs/json']))->name('scalar.admin');
Route::get('/docs/customer', fn () => view('scalar', ['title' => 'Customer Mobile API', 'url' => '/docs/customer/json']))->name('scalar.customer');
Route::get('/docs/delegate', [DelegateDocsController::class, 'ui'])->name('swagger.delegate');
Route::get('/docs/delegate-scalar', fn () => view('scalar', ['title' => 'Delegate Mobile API', 'url' => route('swagger.delegate.json')]))->name('scalar.delegate');
Route::get('/docs/delegate.json', [DelegateDocsController::class, 'json'])->name('swagger.delegate.json');
Route::view('/qa/customer', 'qa.customer')->name('qa.customer');
