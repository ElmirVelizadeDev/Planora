@php($errorTheme = request()->cookie('planora_guest_theme') === 'dark' ? 'dark' : 'light')
<!doctype html>
<html lang="en" data-theme="{{ $errorTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $errorTheme }}">
    @if(($code ?? 500) === 503)<meta http-equiv="refresh" content="30">@endif
    <title>{{ $title ?? 'Something went wrong' }} · Planora</title>
    <style>
        :root{color-scheme:light;--bg:#f5f7fb;--surface:#fff;--text:#20283a;--muted:#68758c;--border:#e6eaf2;--accent:#5b6cfa;--accent-fg:#fff;--soft:#f4f6fb;--shadow:0 12px 36px rgb(32 40 58 / 8%);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
        :root[data-theme="dark"]{color-scheme:dark;--bg:#101522;--surface:#182235;--text:#f1f5f9;--muted:#c0cad8;--border:#2c3a50;--soft:#202d42;--shadow:0 12px 32px rgb(0 0 0 / 25%)}
        *{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;color:var(--text);background:var(--bg)}main{width:min(100%,680px);overflow:hidden;border:1px solid var(--border);border-radius:16px;background:var(--surface);box-shadow:var(--shadow)}header{display:flex;align-items:center;gap:10px;padding:28px 34px 0;font-weight:700}.mark{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:var(--accent);color:var(--accent-fg);font-size:17px}.content{padding:36px 34px 40px}.code{color:var(--accent);font-size:13px;font-weight:700;letter-spacing:.1em}h1{margin:12px 0;color:var(--text);font-size:clamp(26px,5vw,34px);line-height:1.2;letter-spacing:-.025em}p{max-width:540px;margin:0;color:var(--muted);font-size:15px;line-height:1.7}.detail{margin-top:12px;font-size:13px}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}a,button{min-height:42px;display:inline-flex;align-items:center;justify-content:center;padding:0 16px;border:1px solid transparent;border-radius:10px;font:600 14px Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;text-decoration:none;cursor:pointer}a.primary,button.primary{color:var(--accent-fg);background:var(--accent)}a.secondary,button.secondary{color:var(--text);border-color:var(--border);background:var(--surface)}a:hover,button:hover{filter:brightness(.96)}a:focus-visible,button:focus-visible{outline:3px solid rgb(91 108 250 / 38%);outline-offset:2px}footer{display:flex;justify-content:space-between;gap:12px;padding:16px 34px;border-top:1px solid var(--border);color:var(--muted);font-size:12px}@media(max-width:520px){body{padding:16px}header{padding:22px 22px 0}.content{padding:30px 22px}.actions a,.actions button{flex:1 1 100%}footer{padding:15px 22px}}
    </style>
</head>
<body @if(($code ?? 500) === 503) data-maintenance-scope="{{ $scope ?? '' }}" data-retry-after="{{ max(15, min(300, (int) ($retryAfter ?? 60))) }}" @endif>
<main aria-labelledby="error-title">
    <header><span class="mark" aria-hidden="true">P</span><span>Planora</span></header>
    <section class="content">
        <span class="code">{{ $code ?? 500 }}</span>
        <h1 id="error-title">{{ $title ?? 'Something went wrong' }}</h1>
        <p>{{ $description ?? 'Planora could not complete your request. Please try again.' }}</p>
        @if(!empty($message))<p class="detail">{{ $message }}</p>@endif
        @if(!empty($estimatedAt))<p class="detail">Estimated availability: <time>{{ $estimatedAt }}</time></p>@endif
        @if(($code ?? 500) === 503)<p class="detail" id="retry-note">We will check availability automatically.</p>@endif
        <div class="actions">
            @if(!empty($login))<a class="primary" href="{{ url('/login') }}">Sign in</a>@else<button class="primary" type="button" onclick="window.location.reload()">{{ ($code ?? 500) === 503 ? 'Check again' : 'Try again' }}</button>@endif
            <a class="secondary" href="{{ url('/') }}">{{ !empty($login) ? 'Go home' : 'Go to Planora' }}</a>
        </div>
    </section>
    <footer><span>Thoughtful tools for focused work</span><span>© Planora</span></footer>
</main>
@if(($code ?? 500) === 503)<script src="/js/error-page.js" defer></script>@endif
</body>
</html>
