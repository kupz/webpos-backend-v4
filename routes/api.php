<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:api'])->group(function () {
    Route::get('userInfo', [App\Http\Controllers\api\AuthController::class, 'userInfo']);
    Route::delete('logout', [App\Http\Controllers\api\AuthController::class, 'logout']);
});

Route::prefix('products')->middleware(['auth:api'])->group(function () {
    Route::get('/', [App\Http\Controllers\api\ProductController::class, 'index']);
    Route::post('/', [App\Http\Controllers\api\ProductController::class, 'store']);
    Route::patch('/{product}', [App\Http\Controllers\api\ProductController::class, 'update']);
    Route::get('/reports/sales/price', [App\Http\Controllers\api\ProductController::class, 'top5Price']);
    Route::get('/reports/sales/quantity', [App\Http\Controllers\api\ProductController::class, 'top5Quantity']);
    Route::get('/reports/sales/dailysales', [App\Http\Controllers\api\ProductController::class, 'dailySales']);
    Route::get('/reports/sales/dailyquantity', [App\Http\Controllers\api\ProductController::class, 'dailyQuantity']);
});

Route::prefix('transactions')->middleware(['auth:api'])->group(function () {
    Route::get('/', [App\Http\Controllers\api\TransactionController::class, 'index']);
    Route::post('/', [App\Http\Controllers\api\TransactionController::class, 'store']);
    Route::delete('/{transaction}', [App\Http\Controllers\api\TransactionController::class, 'void']);
});

Route::middleware(['guest:api'])->group(function () {
    Route::post('register', [App\Http\Controllers\api\AuthController::class, 'register']);
    Route::post('login', [App\Http\Controllers\api\AuthController::class, 'login']);
});