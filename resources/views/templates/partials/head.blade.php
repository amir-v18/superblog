{{-- Shared head of the site templates. The opening html element stays in each page, since it carries the theme. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- Only used as an admin preview for now, so it must never show up in search results. --}}
<meta name="robots" content="noindex, nofollow">
<title>{{ $superblog->name }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Every theme can be picked for every template, so each site loads the newspaper theme's fonts too. --}}
@fonts(['unifrakturmaguntia', 'playfair-display', 'old-standard-tt', 'im-fell-english-sc'])
