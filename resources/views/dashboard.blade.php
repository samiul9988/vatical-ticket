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
    @include('partials.sidebar', ['active' => 'overview'])
    <main class="main-content">
        <header class="topbar"><div><p class="eyebrow">TICKET OFFICE / ADMIN</p><h1>Booking operations</h1></div><div class="operator"><button type="button" id="sound-toggle" class="sound-toggle" title="Enable notification sound">🔔 Sound off</button><span class="online-dot"></span> System ready <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" style="border:0;background:transparent;color:#738092;cursor:pointer;font:inherit;padding:0" type="submit">Sign out</button></form></div></header>
        <section class="hero"><div><p class="eyebrow yellow">VATICAN MUSEUMS</p><h2>Reserve the moment<br><em>availability opens.</em></h2><p class="hero-copy">Set a visit date and one or more preferred schedules. The watcher checks at a controlled one-minute interval and alerts you when a manual checkout handoff is ready.</p></div><div class="hero-stat"><span>OFFICIAL CHECKOUT</span><strong>Manual handoff</strong><small>Payment and visitor details never leave your control.</small></div></section>
        <div id="sound-config" data-url="{{ $soundUrl }}" hidden></div>
        <div id="toast-container" aria-live="polite"></div>
        <div id="realtime-notifications" aria-live="assertive"></div>
        @if (session('success'))<div class="flash success">✓ {{ session('success') }}</div>@endif
        @if (session('error'))<div class="flash error">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
        <section class="metrics"><div class="metric"><span class="metric-icon blue">◷</span><div><small>ACTIVE WATCHES</small><strong>{{ $watchingCount }}</strong></div></div><div class="metric"><span class="metric-icon green">✓</span><div><small>BOOKED HANDOFFS</small><strong>{{ $bookedCount }}</strong></div></div><div class="metric"><span class="metric-icon gold">↗</span><div><small>CHECK INTERVAL</small><strong>{{ $checkIntervalSeconds >= 60 && $checkIntervalSeconds % 60 === 0 ? ($checkIntervalSeconds / 60).' min' : $checkIntervalSeconds.' sec' }}</strong></div></div></section>
        <section class="panel ticket-visibility-panel"><div><p class="eyebrow">TICKET FILTER</p><h3>Show all ticket types</h3><span class="field-help">Off shows only Admission Ticket and Guided Tours for Individuals Museums when available.</span></div><form method="POST" action="{{ route('settings.ticket-visibility.update') }}" data-ajax-toggle>@csrf @method('PATCH')<label class="toggle-switch"><input type="checkbox" name="show_all_tickets" value="1" @checked($showAllTickets)><span class="toggle-track"><span class="toggle-thumb"></span></span></label></form></section>
        <div class="content-grid">
            <section class="panel form-panel" id="new-search"><div class="panel-heading"><div><p class="eyebrow">STEP 01</p><h3>Start an availability watch</h3></div><span class="step-badge">CONFIGURE</span></div>
                <form method="POST" action="{{ route('booking-searches.store') }}" data-ajax-form>
                    @csrf
                    <div class="form-row"><label>Visit date<input type="date" name="visit_date" min="{{ now()->toDateString() }}" value="{{ old('visit_date', now()->addDay()->toDateString()) }}" required><small>Select the date from the Vatican calendar.</small></label><label>Visitors<input type="number" name="visitor_count" min="1" max="30" value="{{ old('visitor_count', 1) }}" required><small>Maximum 30 visitors per watch.</small></label></div>
                    @foreach(['09:00','09:30','10:00','10:30','11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30'] as $time)<input type="hidden" name="schedules[]" value="{{ $time }}">@endforeach
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
                    <div class="watch-details"><strong>{{ count($search->availability_items ?? []) }} available ticket{{ count($search->availability_items ?? []) === 1 ? '' : 's' }}</strong><span>{{ $search->visitor_count }} {{ Str::plural('visitor', $search->visitor_count) }} · Preferred: {{ implode(', ', $search->schedules) }}</span><small class="detected-at" data-detected-at="{{ $search->detected_at?->toIso8601String() }}">Detected {{ $search->detected_at?->diffForHumans() ?? 'recently' }}</small><small class="next-check" data-next-check-at="{{ $search->next_check_at?->toIso8601String() }}"></small><div class="handoff-options" data-max="{{ $search->visitor_count }}"><label>Participants 1<select data-handoff="full">@for($i = 0; $i <= $search->visitor_count; $i++)<option value="{{ $i }}" @selected($i === $search->visitor_count)>{{ $i }}</option>@endfor</select></label><label>Participants 2<select data-handoff="reduced">@for($i = 0; $i <= $search->visitor_count; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></label><label>Language<select data-handoff="lang"><option selected>English</option><option>Italiano</option><option>Español</option><option>Français</option><option>Deutsch</option></select></label></div><div class="detected-items">@foreach ($search->availability_items ?? [] as $item)@php $isPriority = \App\Support\PriorityTickets::matches($item['title']); @endphp<div id="ticket-{{ $search->id }}-{{ $item['id'] }}" @class(['priority-ticket' => $isPriority])><span>@if ($isPriority)<em class="priority-badge">★ PRIORITY</em> @endif{{ $item['title'] }} <b>{{ str_replace('_', ' ', strtolower($item['availability'])) }}</b></span><a class="text-button" href="{{ route('booking-searches.availability', [$search, $item['id']]) }}" target="_blank" rel="noreferrer">Book ↗</a>@php $uniqueSlots = collect($item['slots'] ?? [])->unique('time')->values(); @endphp @if ($uniqueSlots->isNotEmpty())<div class="slot-row">@foreach ($uniqueSlots as $slot)@php $slotOpen = in_array($slot['availability'], ['AVAILABLE', 'LOW_AVAILABILITY'], true); @endphp<button type="button" class="slot-chip {{ $slotOpen ? 'open' : 'sold' }}" data-time="{{ $slot['time'] }}" @disabled(! $slotOpen)><b>{{ $slot['time'] }}</b><small>{{ $slotOpen ? 'Available' : 'Sold out' }}</small></button>@endforeach</div>@endif</div>@endforeach</div></div>
                    <form method="POST" action="{{ route('booking-searches.destroy', $search) }}" data-ajax-delete>@csrf @method('DELETE')<button type="submit" class="text-button">Remove</button></form>
                </div>
                @endforeach
            </div>
            <p class="availability-note">Book opens the official Vatican availability page with the selected date and visitor count. Select the matching ticket there to open its detail section, then complete checkout manually.</p>
        </section>
        @endif
        </div>
        <section class="panel activity-panel" id="activity"><div class="panel-heading"><div><p class="eyebrow">STEP 02</p><h3>Availability watches</h3></div><span class="live-label"><i></i> LIVE STATUS</span></div>
            @if ($searches->isEmpty())<div class="empty-state">No watches yet. Configure your first availability watch above.</div>@else<div class="watch-list">@foreach ($searches as $search)<div class="watch-item"><div class="date-tile"><strong>{{ $search->visit_date->format('d') }}</strong><span>{{ $search->visit_date->format('M Y') }}</span></div><div class="watch-details"><strong>{{ $search->visit_date->format('l, d F Y') }}</strong><span>{{ $search->visitor_count }} {{ Str::plural('visitor', $search->visitor_count) }} · Preferred: {{ implode(', ', $search->schedules) }}</span><small>Last check: {{ $search->last_checked_at?->diffForHumans() ?? 'Waiting for first check' }}</small></div><div class="watch-status"><span class="status {{ $search->status }}">{{ str_replace('_', ' ', $search->status) }}</span>@if ($search->status === 'watching')<form method="POST" action="{{ route('booking-searches.stop', $search) }}">@csrf @method('PATCH')<button type="submit" class="text-button">Stop</button></form>@endif<form method="POST" action="{{ route('booking-searches.destroy', $search) }}" data-confirm="Remove this availability watch?">@csrf @method('DELETE')<button type="submit" class="text-button">Remove</button></form></div></div>@endforeach</div>@endif
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
    .main-content .panel.form-panel { background: linear-gradient(145deg, #eef2ff 0%, #e0e7ff 100%); border: 1px solid #c7d2fe; box-shadow: 0 10px 30px rgba(79, 70, 229, .12); border-radius: 18px; border-top: 5px solid #6366f1; }
    .main-content .panel.availability-panel { background: linear-gradient(145deg, #ecfdf5 0%, #d1fae5 100%); border: 1px solid #a7f3d0; box-shadow: 0 10px 30px rgba(5, 150, 105, .14); border-radius: 18px; border-top: 5px solid #10b981; }
    .main-content .panel.activity-panel { background: linear-gradient(145deg, #fff7ed 0%, #ffedd5 100%); border: 1px solid #fed7aa; box-shadow: 0 10px 30px rgba(234, 88, 12, .12); border-radius: 18px; border-top: 5px solid #f97316; }
    .form-panel .schedule-option span, .availability-item, .watch-item { background: rgba(255, 255, 255, .78); backdrop-filter: blur(6px); }
    .availability-item, .watch-item { border-radius: 14px; border: 1px solid rgba(255, 255, 255, .9); box-shadow: 0 2px 8px rgba(15, 23, 42, .06); }
    .availability-panel .detected-items > div { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 8px 0; }
    .availability-panel .detected-items a.text-button { display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; border-radius: 999px; background: linear-gradient(135deg, #059669, #10b981); color: #fff; font-weight: 700; font-size: 14px; text-decoration: none; letter-spacing: .02em; box-shadow: 0 6px 16px rgba(5, 150, 105, .35); transition: transform .15s, box-shadow .15s; white-space: nowrap; }
    .availability-panel .detected-items a.text-button:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(5, 150, 105, .45); }
    .form-panel .submit-button { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; border: 0; border-radius: 999px; padding: 12px 28px; font-weight: 700; box-shadow: 0 8px 20px rgba(79, 70, 229, .35); }
    .main-content { display: flex; flex-direction: column; gap: 24px; }
    .main-content > * { margin-top: 0; margin-bottom: 0; min-width: 0; }
    .main-content .metrics { margin: 0; }
    .content-grid { align-items: start; }
    .main-content .panel { padding: 28px; }
    .main-content .panel-heading { gap: 12px; flex-wrap: wrap; margin-bottom: 22px; }
    .availability-list, .watch-list { display: grid; gap: 16px; }
    .availability-item, .watch-item { display: flex; align-items: flex-start; gap: 18px; padding: 18px 20px; }
    .availability-item .date-tile, .watch-item .date-tile { flex: 0 0 60px; width: 60px; height: 60px; }
    .availability-item .watch-details, .watch-item .watch-details { flex: 1; min-width: 0; gap: 6px; overflow-wrap: anywhere; }
    .availability-item .watch-details span, .watch-item .watch-details span { font-size: 12.5px; line-height: 1.5; }
    .availability-panel .detected-items { display: grid; gap: 10px; margin-top: 10px; }
    .availability-panel .detected-items > div { padding: 10px 14px; background: rgba(255, 255, 255, .8); border-radius: 12px; border: 1px solid #d1fae5; }
    .availability-panel .detected-items > div > span { flex: 1 1 220px; min-width: 0; }
    .availability-note { margin: 20px 0 0; line-height: 1.6; }
    .watch-status { flex: 0 0 auto; }
    .schedule-grid { gap: 10px; }
    .ticket-visibility-panel { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 20px 28px; flex-wrap: wrap; }
    .ticket-visibility-panel h3 { font-size: 16px; margin: 4px 0 2px; }
    .ticket-visibility-panel .field-help { margin: 0; }
    .toggle-switch { position: relative; display: inline-flex; flex: 0 0 auto; cursor: pointer; }
    .toggle-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .toggle-track { display: block; width: 42px; height: 24px; border-radius: 999px; background: #d9e0e8; transition: background .15s; }
    .toggle-thumb { display: block; width: 18px; height: 18px; margin: 3px; border-radius: 50%; background: #fff; box-shadow: 0 2px 4px rgba(15, 23, 42, .25); transition: transform .15s; }
    .toggle-switch input:checked + .toggle-track { background: #6366f1; }
    .toggle-switch input:checked + .toggle-track .toggle-thumb { transform: translateX(18px); }
    @media (max-width: 900px) { .main-content { gap: 18px; } }
    @media (max-width: 640px) { .availability-item, .watch-item { flex-wrap: wrap; padding: 16px; gap: 14px; } .availability-item .watch-details, .watch-item .watch-details { flex: 1 1 calc(100% - 80px); } .availability-item > form, .watch-item .watch-status { width: 100%; display: flex; justify-content: flex-end; gap: 14px; align-items: center; } .main-content .panel { padding: 20px 16px; } }
    @media (max-width: 640px) { .availability-panel .detected-items a.text-button { width: 100%; justify-content: center; } .main-content .panel { border-radius: 14px; } }
    .confirm-overlay { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 16px; background: rgba(15, 23, 42, .45); backdrop-filter: blur(3px); animation: toast-in .2s ease-out; }
    .confirm-toast { width: min(380px, 100%); background: #fff; border-radius: 18px; padding: 28px 24px 22px; text-align: center; box-shadow: 0 24px 60px rgba(15, 23, 42, .3); }
    .confirm-icon { width: 48px; height: 48px; margin: 0 auto 14px; border-radius: 50%; background: #fee2e2; color: #dc2626; font-size: 24px; font-weight: 800; display: grid; place-items: center; }
    .confirm-toast p { margin: 0 0 20px; font-size: 16px; font-weight: 600; color: #17202b; }
    .confirm-actions { display: flex; gap: 10px; }
    .confirm-actions button { flex: 1; padding: 11px 14px; border-radius: 999px; border: 0; font: inherit; font-weight: 700; cursor: pointer; }
    .confirm-cancel { background: #eef1f5; color: #475569; }
    .confirm-ok { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; box-shadow: 0 6px 16px rgba(220, 38, 38, .35); }
    .availability-panel .detected-items > div.priority-ticket { background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #f59e0b; box-shadow: 0 6px 18px rgba(245, 158, 11, .35); }
    .availability-panel .detected-items > div.priority-ticket > span { color: #78350f; font-weight: 600; }
    .availability-panel .detected-items > div.priority-ticket a.text-button { background: linear-gradient(135deg, #d97706, #f59e0b); box-shadow: 0 6px 16px rgba(217, 119, 6, .45); }
    .priority-badge { display: inline-block; font-style: normal; font-size: 10px; font-weight: 800; letter-spacing: .08em; padding: 2px 8px; margin-right: 6px; border-radius: 999px; background: #b45309; color: #fff; }
    .handoff-options { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; }
    .handoff-options label { display: grid; gap: 4px; font-size: 11px; font-weight: 700; color: #065f46; text-transform: uppercase; letter-spacing: .05em; }
    .handoff-options select { padding: 8px 12px; border-radius: 10px; border: 1px solid #a7f3d0; background: #fff; font: inherit; font-size: 13px; }
    .slot-row { flex-basis: 100%; display: flex; flex-wrap: wrap; gap: 8px; }
    .slot-load { border: 1px dashed #059669; background: transparent; color: #047857; border-radius: 999px; padding: 6px 14px; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
    .slot-chip { display: grid; gap: 1px; min-width: 76px; padding: 7px 12px; border-radius: 12px; border: 2px solid transparent; font: inherit; text-align: center; cursor: pointer; }
    .slot-chip b { font-size: 14px; }
    .slot-chip small { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .slot-chip.open { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
    .slot-chip.open.picked { background: #059669; color: #fff; border-color: #047857; }
    .slot-chip.sold { background: #fee2e2; color: #b91c1c; border-color: #fecaca; cursor: not-allowed; opacity: .8; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes toast-in { from { transform: translateY(-8px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .sound-toggle { border: 1px solid #d9e0e8; background: #fff; border-radius: 999px; padding: 6px 12px; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; margin-right: 8px; }
    .sound-toggle.on { background: #d1fae5; border-color: #6ee7b7; color: #065f46; }
    #realtime-notifications { position: fixed; top: 84px; right: 0; z-index: 1500; display: grid; gap: 12px; padding-right: 16px; max-width: 100vw; }
    .rt-card { width: min(360px, calc(100vw - 32px)); background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border-radius: 16px 0 0 16px; padding: 16px 18px; box-shadow: 0 16px 40px rgba(217, 119, 6, .45); cursor: pointer; animation: rt-in .45s cubic-bezier(.2, .9, .3, 1.2); border-left: 6px solid #fff7ed; }
    .rt-card small { display: block; font-size: 10px; font-weight: 800; letter-spacing: .12em; opacity: .9; }
    .rt-card strong { display: block; font-size: 15px; margin: 4px 0; }
    .rt-card span { font-size: 12px; opacity: .95; }
    .rt-card em { display: block; margin-top: 8px; font-style: normal; font-size: 12px; font-weight: 700; }
    .rt-card.leaving { animation: rt-out .3s ease-in forwards; }
    .flash-target { animation: flash-target 2.4s ease-out 1; }
    @keyframes rt-in { from { transform: translateX(110%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes rt-out { to { transform: translateX(110%); opacity: 0; } }
    @keyframes flash-target { 0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); } 20%, 60% { box-shadow: 0 0 0 8px rgba(245, 158, 11, .55); } }
    @media (max-width: 640px) { #realtime-notifications { top: 70px; padding-right: 8px; } }
</style>
@if (config('broadcasting.connections.pusher.key'))
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
@endif
<script src="{{ asset('notification-sound.js') }}"></script>
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

    document.addEventListener('change', (event) => {
        const container = event.target.closest?.('.watch-details');
        if (!container || !event.target.dataset.handoff) return;
        const max = Number(container.querySelector('.handoff-options')?.dataset.max ?? 0);
        const key = event.target.dataset.handoff;
        const other = container.querySelector(`[data-handoff="${key === 'full' ? 'reduced' : 'full'}"]`);
        if (other && ['full', 'reduced'].includes(key)) other.value = String(Math.max(0, max - Number(event.target.value)));
        const params = new URLSearchParams();
        container.querySelectorAll('[data-handoff]').forEach((select) => params.set(select.dataset.handoff, select.value));
        container.querySelectorAll('.detected-items a').forEach((link) => {
            const time = new URL(link.href, location.href).searchParams.get('time');
            if (time) params.set('time', time);
            link.href = link.href.split('?')[0] + '?' + params.toString();
        });
    });

    document.addEventListener('click', (event) => {
        const chip = event.target.closest?.('.slot-chip.open');
        if (chip) {
            const wrapper = chip.closest('.detected-items > div');
            const link = wrapper.querySelector('a.text-button');
            const selected = !chip.classList.contains('picked');
            wrapper.querySelectorAll('.slot-chip').forEach((c) => c.classList.remove('picked'));
            const url = new URL(link.href, location.href);
            url.searchParams.delete('time');
            if (selected) { chip.classList.add('picked'); url.searchParams.set('time', chip.dataset.time); }
            link.href = url.toString();
        }
    });

    function confirmToast(message) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'confirm-overlay';
            overlay.innerHTML = '<div class="confirm-toast" role="alertdialog"><div class="confirm-icon">!</div><p></p><div class="confirm-actions"><button type="button" class="confirm-cancel">Cancel</button><button type="button" class="confirm-ok">Yes, remove</button></div></div>';
            overlay.querySelector('p').textContent = message;
            const close = (result) => { overlay.remove(); resolve(result); };
            overlay.querySelector('.confirm-cancel').onclick = () => close(false);
            overlay.querySelector('.confirm-ok').onclick = () => close(true);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
            document.body.appendChild(overlay);
            overlay.querySelector('.confirm-ok').focus();
        });
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest?.('form[data-confirm]');
        if (!form || form.dataset.confirmed) return;
        event.preventDefault();
        if (await confirmToast(form.dataset.confirm)) { form.dataset.confirmed = 'true'; form.submit(); }
    });

    async function refreshDashboard() {
        try {
            const response = await fetch('{{ route('dashboard') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, cache: 'no-store' });
            if (!response.ok) return;
            const html = await response.text();
            const documentFragment = new DOMParser().parseFromString(html, 'text/html');
            ['.metrics', '#availability-slot', '.activity-panel', '#sound-config'].forEach((selector) => {
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
                if (!await confirmToast('Remove this listing?')) return;
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

    const soundToggle = document.getElementById('sound-toggle');
    let soundEnabled = false;
    try { soundEnabled = localStorage.getItem('dashboardSound') === 'on'; } catch (error) { /* storage unavailable */ }

    function renderSoundToggle() {
        soundToggle.classList.toggle('on', soundEnabled);
        soundToggle.textContent = soundEnabled ? '🔔 Sound on' : '🔔 Sound off';
    }

    function playMelody() {
        if (!soundEnabled) return;
        window.playNotificationSound(document.getElementById('sound-config')?.dataset.url || null);
    }

    soundToggle.addEventListener('click', () => {
        soundEnabled = !soundEnabled;
        try { localStorage.setItem('dashboardSound', soundEnabled ? 'on' : 'off'); } catch (error) { /* storage unavailable */ }
        if (soundEnabled) { window.unlockNotificationAudio(); playMelody(); }
        renderSoundToggle();
    });
    document.addEventListener('click', () => { if (soundEnabled) window.unlockNotificationAudio(); }, { once: true });
    renderSoundToggle();

    function showTicketNotification(data) {
        const container = document.getElementById('realtime-notifications');
        const card = document.createElement('div');
        card.className = 'rt-card';
        card.innerHTML = '<small>PRIORITY TICKET AVAILABLE</small><strong></strong><span></span><em>Click to jump to the Book button →</em>';
        card.querySelector('strong').textContent = data.title;
        card.querySelector('span').textContent = `${data.visit_date} · ${data.visitor_count} ${data.visitor_count === 1 ? 'visitor' : 'visitors'}`;
        const dismiss = () => { card.classList.add('leaving'); window.setTimeout(() => card.remove(), 300); };
        card.addEventListener('click', () => {
            const target = document.getElementById(`ticket-${data.search_id}-${data.item_id}`);
            dismiss();
            if (!target) return;
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.remove('flash-target');
            void target.offsetWidth;
            target.classList.add('flash-target');
        });
        container.appendChild(card);
        window.setTimeout(dismiss, 30000);
    }

    @if (config('broadcasting.connections.pusher.key'))
    const pusher = new Pusher(@json(config('broadcasting.connections.pusher.key')), {
        cluster: @json(config('broadcasting.connections.pusher.options.cluster')),
        forceTLS: true,
        channelAuthorization: { endpoint: '/broadcasting/auth', headers: { 'X-CSRF-TOKEN': csrfToken } },
    });
    pusher.subscribe('private-admin-dashboard').bind('priority-ticket.available', async (data) => {
        await refreshDashboard();
        showTicketNotification(data);
        playMelody();
    });
    @endif

    function tickNextCheckCountdowns() {
        document.querySelectorAll('.next-check[data-next-check-at]').forEach((el) => {
            const nextCheckAt = el.dataset.nextCheckAt;
            if (!nextCheckAt) { el.textContent = ''; return; }
            const remaining = Math.max(0, Math.round((new Date(nextCheckAt).getTime() - Date.now()) / 1000));
            el.textContent = remaining > 0 ? `Next check in ${remaining}s` : 'Checking now…';
        });
    }

    document.addEventListener('change', async (event) => {
        const form = event.target.closest?.('form[data-ajax-toggle]');
        if (!form || event.target.type !== 'checkbox') return;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Unable to update ticket visibility.');
            await refreshDashboard();
        } catch (error) {
            showToast(error.message, 'error');
        }
    });

    bindAjaxActions();
    tickNextCheckCountdowns();
    window.setInterval(tickNextCheckCountdowns, 1000);
    window.setInterval(refreshDashboard, {{ max(5, $checkIntervalSeconds) * 1000 }});
</script>
</body>
</html>
