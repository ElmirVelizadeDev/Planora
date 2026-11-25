@php
    $metadataByPath = [
        '' => [
            'title' => 'Planora — Plan tasks and projects',
            'description' => 'Planora helps you organize tasks, projects, categories, priorities, and progress in one workspace.',
            'robots' => 'index, follow',
        ],
        'terms-and-conditions' => [
            'title' => 'Terms & Conditions — Planora',
            'description' => 'Terms for creating an account and using Planora.',
            'robots' => 'index, follow',
        ],
        'privacy-policy' => [
            'title' => 'Privacy Policy — Planora',
            'description' => 'How Planora handles account, workspace, and security information.',
            'robots' => 'index, follow',
        ],
    ];
    $initialMetadata = $metadataByPath[trim(request()->path(), '/')]
        ?? [
            'title' => 'Planora',
            'description' => 'Planora account and workspace pages.',
            'robots' => 'noindex, nofollow',
        ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F5F7FB">
    <meta name="description" content="{{ $initialMetadata['description'] }}">
    <meta name="robots" content="{{ $initialMetadata['robots'] }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $initialMetadata['title'] }}">
    <meta property="og:description" content="{{ $initialMetadata['description'] }}">
    <meta property="og:image" content="{{ asset('images/branding/planora-master-512.png') }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $initialMetadata['title'] }}">
    <meta name="twitter:description" content="{{ $initialMetadata['description'] }}">
    <meta name="twitter:image" content="{{ asset('images/branding/planora-master-512.png') }}">
    <link rel="icon" href="/images/branding/planora-favicon.ico" sizes="any" type="image/vnd.microsoft.icon">
    <link rel="icon" href="/images/branding/planora-favicon-32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/images/branding/planora-favicon-16.png" sizes="16x16" type="image/png">
    <link rel="icon" href="/images/branding/planora-app-icon-192.png" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="/images/branding/planora-app-icon-192.png" sizes="192x192">
    <script>
        (() => {
            const accountId = @json(auth()->id());
            const guestKey = 'planora.guest-theme.v1';
            let theme = 'light';
            try {
                if (accountId !== null) {
                    const saved = JSON.parse(localStorage.getItem(`planora.appearance.v1.${accountId}`) || 'null');
                    if (saved?.theme === 'dark' || saved?.theme === 'light') theme = saved.theme;
                } else {
                    const saved = localStorage.getItem(guestKey);
                    const cookie = document.cookie.split('; ').find((item) => item.startsWith('planora_guest_theme='))?.slice('planora_guest_theme='.length);
                    const guestTheme = saved === 'dark' || saved === 'light' ? saved : (cookie || '');
                    if (guestTheme === 'dark' || guestTheme === 'light') theme = guestTheme;
                }
            } catch (_) {}
            document.documentElement.dataset.theme = theme;
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
    <title>{{ $initialMetadata['title'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app" data-planora-view="{{ ($appView ?? null) === 'admin-auth' ? 'admin-auth' : 'application' }}"></div>
</body>
</html>
