<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailApiController;
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

Route::middleware('secure.api')->prefix('email')->group(function () {
    Route::post('/create', [EmailApiController::class, 'create']);
    Route::post('/reset-password', [EmailApiController::class, 'resetPassword']);
    Route::post('/get-user', [EmailApiController::class, 'getUser']);
    Route::post('/get-info', [EmailApiController::class, 'getinfo']);
    Route::post('/update-info', [EmailApiController::class, 'updateUserinfo']);  
    Route::post('/delete-user', [EmailApiController::class, 'deleteGoogleAccount']);  
    Route::get('/updateUserinfoAll', [EmailApiController::class, 'updateUserinfoAll']);  
    
    
});