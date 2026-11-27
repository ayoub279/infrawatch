<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\ServiceController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('/csrf', fn () => response()->json(['ready' => true]));
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/health', function () {
        DB::select('SELECT 1');

        return ['status' => 'ok'];
    });
    Route::middleware(['auth', 'throttle:120,1'])->group(function () {
        Route::get('/me', fn (Request $r) => $r->user()->only(['id', 'name', 'email', 'role']));
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{id}/checks', [ServiceController::class, 'history'])->whereNumber('id');
        Route::get('/incidents', [IncidentController::class, 'index']);
        Route::patch('/incidents/{id}', [IncidentController::class, 'transition'])->whereNumber('id');
        Route::middleware('admin')->group(function () {
            Route::get('/allowed-hosts', fn () => config('infrawatch.hosts'));
            Route::post('/services', [ServiceController::class, 'store']);
            Route::put('/services/{id}', [ServiceController::class, 'update'])->whereNumber('id');
            Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->whereNumber('id');
            Route::get('/audit', fn () => DB::table('audit_logs')->latest('id')->limit(100)->get()->map(function ($a) {
                $a->created_at = Carbon::parse($a->created_at)->utc()->toISOString();
                $a->details = json_decode($a->details, true);

                return $a;
            }));
        });
    });
});
