{{-- Shared head of the site templates. The opening html element stays in each page, since it carries the theme. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- The builder's preview must never show up in search results; the public page may. A page that does not say
     which one it is gets noindex, so a forgotten flag can only hide a page, never expose a preview. --}}
@if ($isPreview ?? true)
    <meta name="robots" content="noindex, nofollow">
@endif
<title>{{ $superblog->name }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Every theme can be picked for every template, so each site loads the newspaper theme's fonts too. --}}
@fonts(['unifrakturmaguntia', 'playfair-display', 'old-standard-tt', 'im-fell-english-sc'])
