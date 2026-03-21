<?php

use App\Livewire\AiChat;
use App\Livewire\BotLogs;
use App\Livewire\ErrorFixes;
use App\Livewire\Dashboard as TradebotDashboard;
use App\Livewire\Settings;
use App\Livewire\Sources;
use App\Livewire\TradeHistory;
use App\Livewire\AnalysisViewer;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('tradebot.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->prefix('tradebot')->name('tradebot.')->group(function () {
    Route::get('/', TradebotDashboard::class)->name('dashboard');
    Route::get('/sources', Sources::class)->name('sources');
    Route::get('/trades', TradeHistory::class)->name('trades');
    Route::get('/analysis', AnalysisViewer::class)->name('analysis');
    Route::get('/logs', BotLogs::class)->name('logs');
    Route::get('/settings', Settings::class)->name('settings');
    Route::get('/chat', AiChat::class)->name('chat');
    Route::get('/fixes', ErrorFixes::class)->name('fixes');
});
