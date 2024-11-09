<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyAddressController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/not-authorized', function (Request $request) {
    return response()->json([
        "error" => true,
        "message" => "Unauthorized. Please log in to access this resource.",
        "code" => 401
    ], 401);
})->name('not.authorized');

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user-logged', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/attendance', [AttendanceController::class, 'getAttendaces']);
    Route::post('/attendance/checkin', [AttendanceController::class, 'checkin']);
    Route::get('/attendance/check', [AttendanceController::class, 'checkStatus']);
    Route::patch('/attendance/checkout', [AttendanceController::class, 'checkout']);

    Route::get('/company-address', [CompanyAddressController::class, 'index']);
    Route::get('/attendance/get-user-detail', [AttendanceController::class, 'getUserCompanyDetails']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
