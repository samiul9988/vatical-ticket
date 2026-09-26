<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification sounds | Vatican Operations</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <style>
        .sound-page { display: grid; gap: 24px; }
        .sound-page .panel { border-radius: 18px; padding: 28px; background: linear-gradient(145deg, #fdf4ff 0%, #fae8ff 100%); border: 1px solid #f5d0fe; border-top: 5px solid #d946ef; box-shadow: 0 10px 30px rgba(192, 38, 211, .12); }
        .sound-form { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
        .sound-form label { display: grid; gap: 6px; font-size: 12px; font-weight: 700; color: #86198f; text-transform: uppercase; letter-spacing: .05em; flex: 1 1 220px; min-width: 0; }
        .sound-form input { padding: 11px 14px; border: 1px solid #f0abfc; border-radius: 12px; background: #fff; font: inherit; font-size: 14px; }
        .sound-button { border: 0; border-radius: 999px; padding: 11px 22px; font: inherit; font-weight: 700; cursor: pointer; color: #fff; background: linear-gradient(135deg, #c026d3, #7c3aed); box-shadow: 0 8px 20px rgba(124, 58, 237, .3); }
        .sound-button.ghost { background: #fff; color: #86198f; border: 1px solid #f0abfc; box-shadow: none; }
        .sound-button.danger { background: #fff; color: #b91c1c; border: 1px solid #fecaca; box-shadow: none; }
        .sound-list { display: grid; gap: 12px; margin-top: 8px; }
        .sound-item { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 16px 18px; background: rgba(255, 255, 255, .85); border: 1px solid #f5d0fe; border-radius: 14px; }
        .sound-item.is-active { border: 2px solid #c026d3; box-shadow: 0 6px 18px rgba(192, 38, 211, .2); }
        .sound-item .name { flex: 1 1 200px; min-width: 0; font-weight: 700; overflow-wrap: anywhere; }
        .sound-item .name small { display: block; font-weight: 500; color: #86198f; font-size: 12px; }
        .sound-item form { margin: 0; }
        .active-pill { background: #c026d3; color: #fff; font-size: 11px; font-weight: 800; letter-spacing: .06em; border-radius: 999px; padding: 4px 12px; }
        .sound-flash { padding: 14px 18px; border-radius: 12px; font-weight: 600; }
        .sound-flash.success { background: #d1fae5; color: #065f46; }
        .sound-flash.error { background: #fee2e2; color: #b91c1c; }
        .sound-help { color: #86198f; font-size: 13px; margin: 0 0 18px; }
    </style>
</head>
<body>
<div class="app-shell">
    @include('partials.sidebar', ['active' => 'sounds'])
    <main class="main-content sound-page">
        <header class="topbar"><div><p class="eyebrow">TICKET OFFICE / ADMIN</p><h1>Notification sounds</h1></div></header>

        @if (session('success'))<div class="sound-flash success">✓ {{ session('success') }}</div>@endif
        @if (session('error'))<div class="sound-flash error">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="sound-flash error">{{ $errors->first() }}</div>@endif

        <section class="panel">
            <div class="panel-heading"><div><p class="eyebrow">ADD SOUND</p><h3>Upload a notification sound</h3></div></div>
            <p class="sound-help">Played on the dashboard when Admission Ticket or Guided Tours for Individuals Museums becomes available. MP3, WAV, OGG or M4A, up to 2 MB. Turn on "Sound" at the top of the dashboard once per browser.</p>
            <form class="sound-form" method="POST" action="{{ route('sounds.store') }}" enctype="multipart/form-data">
                @csrf
                <label>Name<input type="text" name="name" maxlength="60" placeholder="e.g. Church bell" required></label>
                <label>Audio file<input type="file" name="sound" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*" required></label>
                <button type="submit" class="sound-button">Add sound</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-heading"><div><p class="eyebrow">LIBRARY</p><h3>Available sounds</h3></div>
                <form method="POST" action="{{ route('sounds.test') }}">@csrf<button type="submit" class="sound-button ghost">Send test notification</button></form>
            </div>
            <div class="sound-list">
                <div @class(['sound-item', 'is-active' => $usingDefault])>
                    <div class="name">Built-in melody<small>Default chime, always available</small></div>
                    @if ($usingDefault)<span class="active-pill">ACTIVE</span>@endif
                    <button type="button" class="sound-button ghost" data-preview="">▶ Preview</button>
                    @unless ($usingDefault)<form method="POST" action="{{ route('sounds.default') }}">@csrf<button type="submit" class="sound-button">Use this</button></form>@endunless
                </div>
                @foreach ($sounds as $sound)
                <div @class(['sound-item', 'is-active' => $sound->is_active])>
                    <div class="name">{{ $sound->name }}<small>Uploaded {{ $sound->created_at->diffForHumans() }}</small></div>
                    @if ($sound->is_active)<span class="active-pill">ACTIVE</span>@endif
                    <button type="button" class="sound-button ghost" data-preview="{{ route('sounds.file', $sound) }}">▶ Preview</button>
                    @unless ($sound->is_active)<form method="POST" action="{{ route('sounds.activate', $sound) }}">@csrf @method('PATCH')<button type="submit" class="sound-button">Use this</button></form>@endunless
                    <form method="POST" action="{{ route('sounds.destroy', $sound) }}" >@csrf @method('DELETE')<button type="submit" class="sound-button danger">Remove</button></form>
                </div>
                @endforeach
            </div>
        </section>
    </main>
</div>
<script src="{{ asset('notification-sound.js') }}"></script>
<script>
    document.querySelectorAll('[data-preview]').forEach((button) => {
        button.addEventListener('click', () => {
            window.unlockNotificationAudio();
            window.playNotificationSound(button.dataset.preview || null);
        });
    });
</script>
</body>
</html>
