<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RssSourceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SuperblogController;
use App\Http\Controllers\Admin\SuperblogThemeController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthenticationController;
use App\Http\Middleware\AllowPasswordLoginInDevelopment;
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
    Route::get('/auth/google/redirect', [GoogleAuthenticationController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthenticationController::class, 'callback'])->name('auth.google.callback');
    Route::post('/auth/google/create-account', [GoogleAuthenticationController::class, 'createAccount'])
        ->middleware('throttle:5,1')->name('auth.google.create-account');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware([AllowPasswordLoginInDevelopment::class, 'throttle:5,1'])
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
        Route::prefix('superblogs/{superblog}/rss-sources')
            ->name('admin.superblogs.rss-sources.')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', [RssSourceController::class, 'index'])->name('index');
                Route::get('create', [RssSourceController::class, 'create'])->name('create');
                Route::post('/', [RssSourceController::class, 'store'])->name('store');
                Route::get('{rssSource}/edit', [RssSourceController::class, 'edit'])->name('edit');
                Route::put('{rssSource}', [RssSourceController::class, 'update'])->name('update');
                Route::get('{rssSource}/delete', [RssSourceController::class, 'confirmDeletion'])->name('delete');
                Route::delete('{rssSource}', [RssSourceController::class, 'destroy'])->name('destroy');
                Route::post('{rssSource}/fetch', [RssSourceController::class, 'fetch'])->name('fetch');
                Route::get('{rssSource}/items', [RssSourceController::class, 'items'])->name('items.index');
                Route::get('{rssSource}/items/clear', [RssSourceController::class, 'confirmClearItems'])->name('items.clear');
                Route::delete('{rssSource}/items', [RssSourceController::class, 'clearItems'])->name('items.clear.destroy');
                Route::get('{rssSource}/items/{rssItem}/delete', [RssSourceController::class, 'confirmItemDeletion'])->name('items.delete');
                Route::delete('{rssSource}/items/{rssItem}', [RssSourceController::class, 'destroyItem'])->name('items.destroy');
            });
        Route::put('superblogs/{superblog}/theme', SuperblogThemeController::class)->name('admin.superblogs.theme.update');
        Route::get('superblogs/{superblog}/delete', [SuperblogController::class, 'confirmDeletion'])->name('admin.superblogs.delete');
        Route::resource('superblogs', SuperblogController::class)->except(['index', 'show'])->names('admin.superblogs');
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
