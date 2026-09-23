<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Vatican Operations</title>
    <link rel="stylesheet" href="{{ asset('login.css') }}">
</head>
<body>
<div class="login-shell">
    <div class="login-art">
        <div class="brand"><span class="brand-mark">✦</span><span>VATICAN<br><b>OPERATIONS</b></span></div>
        <div class="art-copy"><p class="eyebrow">OFFICIAL TICKET WORKFLOW</p><h1>Make every<br><em>visit count.</em></h1><p>Monitor Vatican Museums availability and take control of the final booking handoff.</p></div>
        <div class="art-footer">Secure administrator access <span>✦</span></div>
    </div>
    <main class="login-card">
        <div class="mobile-brand"><span class="brand-mark">✦</span> VATICAN OPERATIONS</div>
        <p class="eyebrow">ADMIN PORTAL</p><h2>Welcome back</h2><p class="intro">Sign in to manage your availability watches.</p>
        @if ($errors->any())<div class="login-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}">@csrf
            <label for="email">Email address<input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="admin@vatican.local" required autofocus></label>
            <label for="password">Password<input id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required></label>
            <label class="remember"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in</span></label>
            <button type="submit">Sign in <span>→</span></button>
        </form>
        <p class="login-note">Access is restricted to authorized ticket administrators.</p>
    </main>
</div>
</body>
</html>
