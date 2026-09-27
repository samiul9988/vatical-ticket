<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check interval | Vatican Operations</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <style>
        .watch-page { display: grid; gap: 24px; }
        .watch-page .panel { border-radius: 18px; padding: 28px; background: linear-gradient(145deg, #fff7ed 0%, #ffedd5 100%); border: 1px solid #fed7aa; border-top: 5px solid #f97316; box-shadow: 0 10px 30px rgba(234, 88, 12, .12); }
        .watch-help { color: #9a3412; font-size: 13px; margin: 0 0 18px; }
        .watch-form { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
        .watch-form label { display: grid; gap: 6px; font-size: 12px; font-weight: 700; color: #9a3412; text-transform: uppercase; letter-spacing: .05em; flex: 1 1 220px; min-width: 0; }
        .watch-form input { padding: 11px 14px; border: 1px solid #fdba74; border-radius: 12px; background: #fff; font: inherit; font-size: 14px; }
        .watch-button { border: 0; border-radius: 999px; padding: 11px 22px; font: inherit; font-weight: 700; cursor: pointer; color: #fff; background: linear-gradient(135deg, #ea580c, #f97316); box-shadow: 0 8px 20px rgba(234, 88, 12, .3); }
        .watch-flash { padding: 14px 18px; border-radius: 12px; font-weight: 600; }
        .watch-flash.success { background: #d1fae5; color: #065f46; }
        .watch-flash.error { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
<div class="app-shell">
    @include('partials.sidebar', ['active' => 'watches'])
    <main class="main-content watch-page">
        <header class="topbar"><div><p class="eyebrow">TICKET OFFICE / ADMIN</p><h1>Check interval</h1></div></header>

        @if (session('success'))<div class="watch-flash success">✓ {{ session('success') }}</div>@endif
        @if (session('error'))<div class="watch-flash error">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="watch-flash error">{{ $errors->first() }}</div>@endif

        <section class="panel">
            <div class="panel-heading"><div><p class="eyebrow">AUTOMATION</p><h3>How often should watches be checked?</h3></div></div>
            <p class="watch-help">Set the interval, in seconds, between availability checks. This applies to every active watch. For example, entering 40 checks each watch every 40 seconds instead of the default 60.</p>
            <form class="watch-form" method="POST" action="{{ route('watches.update') }}">
                @csrf
                @method('PATCH')
                <label>Interval (seconds)<input type="number" name="check_interval_seconds" min="5" max="3600" value="{{ old('check_interval_seconds', $checkIntervalSeconds) }}" required></label>
                <button type="submit" class="watch-button">Save</button>
            </form>
        </section>
    </main>
</div>
</body>
</html>
