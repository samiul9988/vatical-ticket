<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vatican Ticket Operations</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><span class="brand-mark">✦</span><span>VATICAN<br><b>OPERATIONS</b></span></div>
        <nav><a class="active" href="{{ route('dashboard') }}">Overview <span>⌂</span></a><a href="#new-search">Availability watcher <span>◷</span></a><a href="#activity">Activity log <span>≋</span></a></nav>
        <div class="sidebar-foot">Official ticket workflow<br><small>Human checkout handoff enabled</small></div>
    </aside>
    <main class="main-content">
        <header class="topbar"><div><p class="eyebrow">TICKET OFFICE / ADMIN</p><h1>Booking operations</h1></div><div class="operator"><span class="online-dot"></span> System ready <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" style="border:0;background:transparent;color:#738092;cursor:pointer;font:inherit;padding:0" type="submit">Sign out</button></form></div></header>
        <section class="hero"><div><p class="eyebrow yellow">VATICAN MUSEUMS</p><h2>Reserve the moment<br><em>availability opens.</em></h2><p class="hero-copy">Set a visit date and one or more preferred schedules. The watcher checks at a controlled one-minute interval and alerts you when a manual checkout handoff is ready.</p></div><div class="hero-stat"><span>OFFICIAL CHECKOUT</span><strong>Manual handoff</strong><small>Payment and visitor details never leave your control.</small></div></section>
        <div id="toast-container" aria-live="polite"></div>
        @if (session('success'))<div class="flash success">✓ {{ session('success') }}</div>@endif
        @if (session('error'))<div class="flash error">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
        <section class="metrics"><div class="metric"><span class="metric-icon blue">◷</span><div><small>ACTIVE WATCHES</small><strong>{{ $watchingCount }}</strong></div></div><div class="metric"><span class="metric-icon green">✓</span><div><small>BOOKED HANDOFFS</small><strong>{{ $bookedCount }}</strong></div></div><div class="metric"><span class="metric-icon gold">↗</span><div><small>CHECK INTERVAL</small><strong>1 min</strong></div></div></section>
        <div class="content-grid">
            <section class="panel form-panel" id="new-search"><div class="panel-heading"><div><p class="eyebrow">STEP 01</p><h3>Start an availability watch</h3></div><span class="step-badge">CONFIGURE</span></div>
                <form method="POST" action="{{ route('booking-searches.store') }}" data-ajax-form>
                    @csrf
                    <div class="form-row"><label>Visit date<input type="date" name="visit_date" min="{{ now()->toDateString() }}" value="{{ old('visit_date', now()->addDay()->toDateString()) }}" required><small>Select the date from the Vatican calendar.</small></label><label>Visitors<input type="number" name="visitor_count" min="1" max="30" value="{{ old('visitor_count', 1) }}" required><small>Maximum 30 visitors per watch.</small></label></div>
                    <fieldset><legend>Preferred schedules</legend><p class="field-help">Choose one or more times. The watcher will evaluate them in this order.</p><div class="schedule-grid">@foreach(['09:00','09:30','10:00','10:30','11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30'] as $time)<label class="schedule-option"><input type="checkbox" name="schedules[]" value="{{ $time }}" @checked(in_array($time, old('schedules', []), true))><span>{{ $time }}</span></label>@endforeach</div></fieldset>
                    <div class="form-footer"><span>Next: availability only. Checkout stays manual.</span><button type="submit" class="button primary submit-button"><span class="button-label">Confirm watch <span>→</span></span><span class="button-spinner" aria-hidden="true"></span></button></div>
                </form>
            </section>
            <section class="panel safety-panel"><div class="panel-heading"><div><p class="eyebrow">AUTOMATION POLICY</p><h3>Safe by design</h3></div><span class="shield">◇</span></div><ul class="safety-list"><li><b>Rate controlled</b><span>One check per minute, not aggressive polling.</span></li><li><b>Session aware</b><span>No payment or CAPTCHA bypass is attempted.</span></li><li><b>Immediate alert</b><span>Stop watching after a successful handoff.</span></li></ul><div class="notice">Use only with an account and traffic permitted by the ticket office terms.</div></section>
        </div>
        <div id="availability-slot">
        @if ($availableSearches->isNotEmpty())
        <section class="panel availability-panel">
            <div class="panel-heading"><div><p class="eyebrow yellow">ACTION REQUIRED</p><h3>Booking availability detected</h3></div><span class="live-label">OPEN OFFICIAL FLOW</span></div>
            <div class="availability-list">
                @foreach ($availableSearches as $search)
                <div class="availability-item">
                    <div class="date-tile available-date"><strong>{{ $search->visit_date->format('d') }}</strong><span>{{ $search->visit_date->format('M Y') }}</span></div>
                    <div class="watch-details"><strong>{{ count($search->availability_items ?? []) }} available ticket{{ count($search->availability_items ?? []) === 1 ? '' : 's' }}</strong><span>{{ $search->visitor_count }} {{ Str::plural('visitor', $search->visitor_count) }} · Preferred: {{ implode(', ', $search->schedules) }}</span><small>Detected {{ $search->detected_at?->diffForHumans() ?? 'recently' }}</small><div class="detected-items">@foreach ($search->availability_items ?? [] as $item)<div><span>{{ $item['title'] }} <b>{{ str_replace('_', ' ', strtolower($item['availability'])) }}</b></span><a class="text-button" href="{{ route('booking-searches.availability', [$search, $item['id']]) }}" target="_blank" rel="noreferrer">Book ↗</a></div>@endforeach</div></div>
                    <form method="POST" action="{{ route('booking-searches.destroy', $search) }}" data-ajax-delete>@csrf @method('DELETE')<button type="submit" class="text-button">Remove</button></form>
                </div>
                @endforeach
            </div>
            <p class="availability-note">Book opens the official Vatican availability page with the selected date and visitor count. Select the matching ticket there to open its detail section, then complete checkout manually.</p>
        </section>
        @endif
        </div>
        <section class="panel activity-panel" id="activity"><div class="panel-heading"><div><p class="eyebrow">STEP 02</p><h3>Availability watches</h3></div><span class="live-label"><i></i> LIVE STATUS</span></div>
            @if ($searches->isEmpty())<div class="empty-state">No watches yet. Configure your first availability watch above.</div>@else<div class="watch-list">@foreach ($searches as $search)<div class="watch-item"><div class="date-tile"><strong>{{ $search->visit_date->format('d') }}</strong><span>{{ $search->visit_date->format('M Y') }}</span></div><div class="watch-details"><strong>{{ $search->visit_date->format('l, d F Y') }}</strong><span>{{ $search->visitor_count }} {{ Str::plural('visitor', $search->visitor_count) }} · Preferred: {{ implode(', ', $search->schedules) }}</span><small>Last check: {{ $search->last_checked_at?->diffForHumans() ?? 'Waiting for first check' }}</small></div><div class="watch-status"><span class="status {{ $search->status }}">{{ str_replace('_', ' ', $search->status) }}</span>@if ($search->status === 'watching')<form method="POST" action="{{ route('booking-searches.stop', $search) }}">@csrf @method('PATCH')<button type="submit" class="text-button">Stop</button></form>@endif<form method="POST" action="{{ route('booking-searches.destroy', $search) }}" onsubmit="return confirm('Remove this availability watch?');">@csrf @method('DELETE')<button type="submit" class="text-button">Remove</button></form></div></div>@endforeach</div>@endif
        </section>
        <footer>External booking: <a href="https://tickets.museivaticani.va/home/calendar/visit/MV-Biglietti/1" target="_blank" rel="noreferrer">tickets.museivaticani.va ↗</a><span>Admin console v1.0</span></footer>
    </main>
</div>
<style>
    #toast-container { position: fixed; top: 24px; right: 24px; z-index: 1000; display: grid; gap: 10px; }
    .toast { min-width: 280px; padding: 15px 18px; border-radius: 12px; color: #fff; box-shadow: 0 12px 30px rgba(18, 31, 48, .2); animation: toast-in .25s ease-out; }
    .toast.success { background: #16805c; }
    .toast.error { background: #c84343; }
    .button-spinner { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
    .submit-button.is-loading .button-label { display: none; }
    .submit-button.is-loading .button-spinner { display: inline-block; }
    .submit-button.is-loading { pointer-events: none; opacity: .75; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes toast-in { from { transform: translateY(-8px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
    const toastContainer = document.getElementById('toast-container');

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = `${type === 'success' ? '✓ ' : '⚠ '}${message}`;
        toastContainer.appendChild(toast);
        window.setTimeout(() => toast.remove(), 4500);
    }

    async function refreshDashboard() {
        try {
            const response = await fetch('{{ route('dashboard') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, cache: 'no-store' });
            if (!response.ok) return;
            const html = await response.text();
            const documentFragment = new DOMParser().parseFromString(html, 'text/html');
            ['.metrics', '#availability-slot', '.activity-panel'].forEach((selector) => {
                const current = document.querySelector(selector);
                const updated = documentFragment.querySelector(selector);
                if (current && updated) current.replaceWith(updated);
            });
            bindAjaxActions();
        } catch (error) {
            console.warn('Dashboard refresh failed', error);
        }
    }

    function bindAjaxActions() {
        document.querySelectorAll('[data-ajax-delete]').forEach((form) => {
            if (form.dataset.bound) return;
            form.dataset.bound = 'true';
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!window.confirm('Remove this listing?')) return;
                const button = form.querySelector('button');
                button.disabled = true;
                try {
                    const response = await fetch(form.action, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' } });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'Unable to remove listing.');
                    showToast(result.message);
                    await refreshDashboard();
                } catch (error) { button.disabled = false; showToast(error.message, 'error'); }
            });
        });
    }

    document.querySelector('[data-ajax-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('.submit-button');
        button.classList.add('is-loading');
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Please check the form values.');
            form.reset();
            showToast(result.message);
            await refreshDashboard();
        } catch (error) { showToast(error.message, 'error'); }
        finally { button.classList.remove('is-loading'); }
    });

    bindAjaxActions();
    window.setInterval(refreshDashboard, 60000);
</script>
</body>
</html>
