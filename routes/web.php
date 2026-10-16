<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Support\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    // Fake article data shaped like the future Article (is_locked, source_type, article_links, media).
    Route::get('/demo/news/article', function (Request $request) {
        $theme = in_array($request->query('theme'), Theme::NAMES, true)
            ? $request->query('theme')
            : 'bondebladet';

        $article = [
            'title' => 'Så påverkar höstregnen skörden i år',
            'slug' => 'sa-paverkar-hostregnen-skorden-i-ar',
            'excerpt' => 'Ovanligt mycket regn under september har försenat skörden på många gårdar. Här är vad det betyder för odlare och konsumenter.',
            'author' => 'Demo Skribent',
            'published_at' => Carbon::parse('2026-10-14 08:30'),
            'source_type' => 'rss',
            'is_locked' => $request->boolean('locked'),
            'image' => [
                'src' => asset('images/demo/article.svg'),
                'alt' => 'Platshållarbild för artikelns huvudbild',
                'width' => 1200,
                'height' => 675,
                'caption' => 'Platshållarbild. Foto: Demo.',
            ],
            'body' => [
                ['type' => 'p', 'text' => 'Septembers regn kom tidigt och stannade länge. På flera håll har tröskorna fått stå still i veckor medan fälten torkar upp, och spannmålen som redan är mogen riskerar att tappa i kvalitet.'],
                ['type' => 'h2', 'text' => 'Därför blir skörden sen'],
                ['type' => 'p', 'text' => 'Fuktig spannmål måste torkas innan den kan lagras. Det tar tid och kostar energi, och när marken är blöt kan tunga maskiner inte köra ut utan att skada jorden.'],
                ['type' => 'h3', 'text' => 'Vilka grödor drabbas mest'],
                ['type' => 'p', 'text' => 'Höstvete och havre som stod kvar på fälten i slutet av augusti är mest utsatta. Raps skördas tidigare och har i regel klarat sig bättre.'],
                ['type' => 'h2', 'text' => 'Vad betyder det för priserna'],
                ['type' => 'p', 'text' => 'Det är för tidigt att säga hur priserna påverkas. Mycket beror på hur vädret blir under oktober och hur skörden har gått i andra länder.'],
                ['type' => 'p', 'text' => 'Odlare som vill följa läget kan hålla koll på lokala prognoser och rådgivning från sina branschorganisationer.'],
            ],
            // Keyed by article_links.type; both are required by the SEO rules.
            'links' => [
                'brand' => ['url' => 'https://example.com', 'anchor_text' => 'Exempelmärket'],
                'product' => ['url' => 'https://example.com/produkt', 'anchor_text' => 'Exempelprodukten'],
            ],
            'sources' => [
                ['name' => 'Exempelkällan: Regnet försenar skörden', 'url' => 'https://example.com/nyheter/regn-skord'],
                ['name' => 'Exempelinstitutet: Nederbörd i september', 'url' => 'https://example.org/statistik/nederbord'],
            ],
        ];

        return view('templates.news.article', ['article' => $article, 'theme' => $theme]);
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
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
