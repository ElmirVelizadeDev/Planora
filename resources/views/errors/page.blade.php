@php
    $errorCode = (int) ($code ?? 500);
    $errorScopes = isset($maintenanceScopes) && is_array($maintenanceScopes) ? $maintenanceScopes : null;
    $safeDestination = null;
    $safeDestinationLabel = null;
    $actor = auth()->user();

    if ($errorCode !== 503) {
        $safeDestination = url('/');
        $safeDestinationLabel = 'Go to Planora';
    } elseif ($errorScopes !== null) {
        $workspaceAvailable = ($errorScopes['dashboard_workspace'] ?? true) === false;
        $publicAvailable = ($errorScopes['public_website'] ?? true) === false;

        if ($actor?->status === 'active' && $actor->role === 'admin') {
            $safeDestination = url('/admin/dashboard');
            $safeDestinationLabel = 'Go to Admin dashboard';
        } elseif ($actor?->status === 'active' && $actor->role === 'user' && $workspaceAvailable) {
            $safeDestination = url('/dashboard');
            $safeDestinationLabel = 'Go to dashboard';
        } elseif ($actor?->status === 'active' && $actor->role === 'moderator' && $workspaceAvailable) {
            $canOpenAdminDashboard = collect(['users.view', 'activity.view', 'categories.view', 'projects.view', 'tasks.view', 'reports.view'])
                ->contains(fn (string $permission) => $actor->hasManagementPermission($permission));
            if ($canOpenAdminDashboard) {
                $safeDestination = url('/admin/dashboard');
                $safeDestinationLabel = 'Go to your management dashboard';
            }
        }

        if ($safeDestination === null && $publicAvailable) {
            $safeDestination = url('/');
            $safeDestinationLabel = 'Go to Planora';
        }
    }

    $errorTheme = request()->cookie('planora_guest_theme') === 'dark' ? 'dark' : 'light';
    $retrySeconds = max(15, min(86400, (int) ($retryAfter ?? 60)));
    $isMaintenance = $errorCode === 503;
@endphp
<!doctype html>
<html lang="en" data-theme="{{ $errorTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $errorTheme }}">
    <title>{{ $title ?? 'Something went wrong' }} · Planora</title>
    <style>
        :root{color-scheme:light;--bg:#f5f7fb;--surface:#fff;--text:#20283a;--muted:#748097;--border:#e5e9f1;--accent:#5b6cfa;--accent-soft:#eef0ff;--accent-fg:#fff;--danger:#c34758;--warning:#a86914;--shadow:0 18px 48px rgb(32 40 58 / 7%);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
        :root[data-theme="dark"]{color-scheme:dark;--bg:#101522;--surface:#182235;--text:#f1f5f9;--muted:#b1bdcf;--border:#2c3a50;--accent-soft:#262d50;--shadow:0 18px 44px rgb(0 0 0 / 26%)}
        *{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;color:var(--text);background:var(--bg)}
        .error-shell{width:min(100%,780px);overflow:hidden;border:1px solid var(--border);border-radius:20px;background:var(--surface);box-shadow:var(--shadow)}
        .error-brand{display:flex;align-items:center;gap:10px;padding:26px 32px 0;font-size:15px;font-weight:700;letter-spacing:-.01em}.error-mark{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;color:var(--accent-fg);background:var(--accent);font-size:17px}
        .error-content{display:grid;grid-template-columns:112px minmax(0,1fr);align-items:center;gap:30px;padding:42px 56px 48px}.error-visual{width:108px;height:108px;display:grid;place-items:center;border:1px solid var(--border);border-radius:26px;color:var(--accent);background:var(--accent-soft)}.error-visual svg{width:48px;height:48px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .error-code{display:inline-flex;align-items:center;gap:8px;color:var(--accent);font-size:11px;font-weight:750;letter-spacing:.12em;text-transform:uppercase}.error-code strong{font-size:13px}.error-code span{color:var(--muted)}h1{margin:12px 0 10px;color:var(--text);font-size:clamp(27px,4vw,37px);line-height:1.15;letter-spacing:-.035em}.error-copy{max-width:530px;margin:0;color:var(--muted);font-size:15px;line-height:1.7}.error-detail{margin:12px 0 0;color:var(--muted);font-size:13px;line-height:1.6}.error-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}.error-button{min-height:44px;display:inline-flex;align-items:center;justify-content:center;padding:0 17px;border:1px solid transparent;border-radius:11px;font:600 14px Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;text-decoration:none;cursor:pointer;transition:background-color .16s ease,border-color .16s ease,transform .16s ease}.error-button:hover{transform:translateY(-1px)}.error-button:focus-visible{outline:3px solid rgb(91 108 250 / 38%);outline-offset:3px}.error-button--primary{color:var(--accent-fg);background:var(--accent)}.error-button--primary:hover{background:#4d5ee9}.error-button--secondary{color:var(--text);border-color:var(--border);background:var(--surface)}.error-button--secondary:hover{background:var(--accent-soft)}.error-footer{display:flex;justify-content:space-between;gap:12px;padding:16px 32px;border-top:1px solid var(--border);color:var(--muted);font-size:12px}
        .is-warning .error-visual,.is-419 .error-visual,.is-429 .error-visual{color:var(--warning)}.is-danger .error-visual{color:var(--danger)}.error-live{min-height:20px;margin-top:12px;color:var(--muted);font-size:13px;line-height:1.5}
        @media(max-width:620px){body{padding:16px}.error-content{grid-template-columns:1fr;gap:20px;padding:30px 26px 34px}.error-visual{width:76px;height:76px;border-radius:20px}.error-visual svg{width:38px;height:38px}.error-brand{padding:22px 26px 0}.error-copy{max-width:none}.error-actions{align-items:stretch}.error-button{flex:1 1 100%}.error-footer{padding:14px 26px;font-size:11px}}
        @media(prefers-reduced-motion:reduce){.error-button{transition:none}.error-button:hover{transform:none}}
    </style>
</head>
<body @if($isMaintenance) data-retry-after="{{ $retrySeconds }}" @endif>
<main class="error-shell is-{{ $errorCode }}" aria-labelledby="error-title">
    <header class="error-brand"><span class="error-mark" aria-hidden="true">P</span><span>Planora</span></header>
    <section class="error-content">
        <div class="error-visual" aria-hidden="true">
            @if($errorCode === 401)<svg viewBox="0 0 48 48"><circle cx="18" cy="19" r="10"/><path d="m25 26 15 15m-7-7 5-5m-10 0 5-5"/></svg>
            @elseif($errorCode === 403)<svg viewBox="0 0 48 48"><path d="m24 5 15 6v12c0 10-6 17-15 21C15 40 9 33 9 23V11l15-6Z"/><path d="m18 24 4 4 8-9"/></svg>
            @elseif($errorCode === 404)<svg viewBox="0 0 48 48"><circle cx="20" cy="20" r="13"/><path d="m29 29 12 12M16 20h8"/></svg>
            @elseif($errorCode === 419)<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="18"/><path d="M24 13v12l8 5"/></svg>
            @elseif($errorCode === 429)<svg viewBox="0 0 48 48"><path d="M7 32a17 17 0 1 1 34 0"/><path d="m24 26 9-10M14 32h20"/></svg>
            @elseif($errorCode === 503)<svg viewBox="0 0 48 48"><path d="M24 5 44 41H4L24 5Z"/><path d="M24 17v11m0 6h.01"/></svg>
            @else<svg viewBox="0 0 48 48"><path d="M29 8a12 12 0 0 0-14 15L6 32a5 5 0 0 0 7 7l10-9a12 12 0 0 0 16-15l-7 7-8-8 5-6Z"/></svg>@endif
        </div>
        <div>
            <div class="error-code"><strong>{{ $errorCode }}</strong><span aria-hidden="true">/</span><span>{{ $isMaintenance ? 'Service status' : 'Request status' }}</span></div>
            <h1 id="error-title">{{ $title ?? 'Something went wrong' }}</h1>
            <p class="error-copy">{{ $description ?? 'Planora could not complete your request. Please try again.' }}</p>
            @if(!empty($message))<p class="error-detail">{{ $message }}</p>@endif
            @if(!empty($estimatedAt))<p class="error-detail">Estimated availability: <time>{{ $estimatedAt }}</time></p>@endif
            @if($isMaintenance)<p class="error-live" id="retry-note" role="status" aria-live="polite">We will check this page’s availability periodically.</p>@endif
            <div class="error-actions">
                @if($isMaintenance)
                    <a id="error-retry" class="error-button error-button--primary" href="{{ url()->current() }}">Check again</a>
                @elseif(!empty($login))
                    <a class="error-button error-button--primary" href="{{ url('/login') }}">Sign in</a>
                @else
                    <a class="error-button error-button--primary" href="{{ url()->current() }}">Try again</a>
                @endif
                @if($safeDestination)<a class="error-button error-button--secondary" href="{{ $safeDestination }}">{{ $safeDestinationLabel }}</a>@endif
            </div>
        </div>
    </section>
    <footer class="error-footer"><span>Thoughtful tools for focused work</span><span>© Planora</span></footer>
</main>
@if($isMaintenance)<script src="/js/error-page.js" defer></script>@endif
</body>
</html>
