<?php

use App\Livewire\ChileFashionRadar;
use App\Livewire\InstagramNetwork;
use App\Livewire\ProductDeepDive;
use App\Livewire\TrendDashboard;
use App\Livewire\TrendDetail;
use App\Livewire\ExperimentPanel;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fashion Intelligence — Web Routes
|--------------------------------------------------------------------------
*/

// ─── Auth ─────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'login'])->name('login.post');
});

Route::post('/logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ─── Authenticated app ────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/',             ChileFashionRadar::class)->name('dashboard');
    Route::get('/all-trends',   TrendDashboard::class)->name('all-trends');
    Route::get('/products',     ProductDeepDive::class)->name('products');
    Route::get('/trends/{slug}', TrendDetail::class)->name('trends.show');

    Route::get('/opportunities', function () {
        return redirect()->route('all-trends', ['sort' => 'commercial_opportunity_score']);
    })->name('opportunities');

    Route::get('/experiments', ExperimentPanel::class)->name('experiments');

    Route::get('/instagram-network', InstagramNetwork::class)->name('instagram-network');

    Route::get('/sources', function () {
        // Placeholder — full SourceManager Livewire component is Phase B+
        return view('placeholder', ['title' => 'Sources', 'message' => 'Source management coming in next phase.']);
    })->name('sources');

});
