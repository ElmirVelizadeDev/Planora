<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F5F7FB">
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
    <title>{{ config('app.name', 'Planora') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app" data-planora-view="{{ ($appView ?? null) === 'admin-auth' ? 'admin-auth' : 'application' }}"></div>
</body>
</html>
