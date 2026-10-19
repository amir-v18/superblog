<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SuperblogController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Support\DemoArticle;
use App\Support\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Demo page for previewing the public layout; only registered locally so it is never public or indexed.
if (app()->environment('local')) {
    Route::view('/demo/layout', 'demo.layout')->name('demo.layout');

    Route::get('/demo/news', function (Request $request) {
        $theme = in_array($request->query('theme'), Theme::NAMES, true)
            ? $request->query('theme')
            : 'bondebladet';

        return view('demo.news', ['theme' => $theme]);
    })->name('demo.news');

    Route::get('/demo/news/article', function (Request $request) {
        $theme = in_array($request->query('theme'), Theme::NAMES, true)
            ? $request->query('theme')
            : 'bondebladet';

        return view('templates.news.article', [
            'article' => DemoArticle::make($request->boolean('locked')),
            'theme' => $theme,
        ]);
    })->name('demo.news.article');
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::prefix('admin')->middleware('can:access-admin')->group(function () {
        Route::get('/', DashboardController::class)->name('admin.dashboard');
        Route::prefix('staff')->name('admin.staff.')->middleware('can:manage-staff')->group(function () {
            Route::get('/', [StaffController::class, 'index'])->name('index');
            Route::post('{user}', [StaffController::class, 'update'])->name('update');
        });
        Route::get('templates/{template}/delete', [TemplateController::class, 'confirmDeletion'])->name('admin.templates.delete');
        Route::resource('templates', TemplateController::class)->except(['show'])->names('admin.templates');
        Route::get('superblogs/{superblog}/delete', [SuperblogController::class, 'confirmDeletion'])->name('admin.superblogs.delete');
        Route::resource('superblogs', SuperblogController::class)->except(['index', 'show'])->names('admin.superblogs');
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
