<?php

use App\Http\Controllers\API\ClothController;
use App\Http\Controllers\API\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

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

Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    // UserController Routes
    Route::get('user', [UserController::class, 'fetch']);
    Route::put('user/update', [UserController::class, 'updateProfile']);
    Route::post('logout', [UserController::class, 'logout']);

    // ClothController Routes
    Route::post('userCloth/create', [ClothController::class, 'createUserCloth']);
    Route::post('cloth/image/create', [ClothController::class, 'createClothImage']);
    Route::post('cloth/create', [ClothController::class, 'createCloth']);

    // TransactionController Routes
    Route::post('transaction/create', [TransactionController::class, 'createTransaction']);
    Route::post('transaction/item/create', [TransactionController::class, 'createTransactionItem']);
    Route::put('transaction/update', [TransactionController::class, 'updateTransaction']);
//    Route::get('transaction', [TransactionController::class, 'getTransaction']);
//    Route::get('transaction/details', [TransactionController::class, 'getTransactionDetail']);




});
