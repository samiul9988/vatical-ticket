<aside class="sidebar">
    <div class="brand"><span class="brand-mark">✦</span><span>VATICAN<br><b>OPERATIONS</b></span></div>
    <nav>
        <a @class(['active' => $active === 'overview']) href="{{ route('dashboard') }}">Overview <span>⌂</span></a>
        <a href="{{ route('dashboard') }}#new-search">Availability watcher <span>◷</span></a>
        <a href="{{ route('dashboard') }}#activity">Activity log <span>≋</span></a>
        <a @class(['active' => $active === 'sounds']) href="{{ route('sounds.index') }}">Notification sounds <span>♪</span></a>
    </nav>
    <div class="sidebar-foot">Official ticket workflow<br><small>Human checkout handoff enabled</small></div>
</aside>
