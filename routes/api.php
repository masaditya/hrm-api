<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user-logged', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/attendance', [AttendanceController::class, 'getAttendaces']);
    Route::post('/attendance/checkin', [AttendanceController::class, 'checkin']);
    Route::get('/attendance/check', [AttendanceController::class, 'checkStatus']);
    Route::patch('/attendance/checkout', [AttendanceController::class, 'checkout']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
