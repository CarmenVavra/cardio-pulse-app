@props(['title' => null, 'active' => null, 'page' => 'other', 'locked' => false])
@php
    $user = auth()->user();
    $tabs = [
        'board' => ['Überwachung', route('board')],
        'patients' => ['Patienten', route('patients.index')],
        'reports' => ['Monatsberichte', route('reports.index')],
        'calls' => ['Anrufe', route('calls.index')],
    ];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    @include('partials.head', ['title' => $title])
    @vite(['resources/css/app.css', 'resources/js/hospital.js'])
</head>
<body
    data-page="{{ $page }}"
    data-live-url="{{ route('board.live') }}"
    data-lock-url="{{ route('lock') }}"
    data-lock-after="{{ config('cardiopulse.privacy_lock_seconds') }}"
    data-poll="{{ config('cardiopulse.poll_interval_ms') }}"
    data-sound="{{ session('sound_enabled') ? 1 : 0 }}"
    data-locked="{{ $locked ? 1 : 0 }}"
>
<a class="skip-link" href="#main">Zum Inhalt springen</a>
<div class="h-app">
    <header class="h-top">
        <x-logo :href="route('board')" />
        @unless ($locked)
            <nav aria-label="Hauptnavigation">
                <ul class="h-tabs">
                    @foreach ($tabs as $key => [$label, $url])
                        <li><a href="{{ $url }}" @if ($active === $key) aria-current="page" @endif>{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endunless
        <div class="h-top__right">
            @if ($locked)
                <span class="badge-anon">ANONYM-MODUS</span>
            @else
                <a href="{{ route('board') }}" class="badge-alarm" data-alarm-badge hidden>
                    <span data-alarm-badge-count>0</span> Alarm aktiv
                </a>
                <span class="h-top__context">{{ session('department_label', 'Telemonitoring') }} · Patienten zu Hause</span>
                <span class="live tabular" aria-live="off">LIVE <span data-clock>{{ now()->format('H:i:s') }}</span></span>
                <span class="user-chip"><span class="avatar" aria-hidden="true">{{ $user->initials() }}</span><span>{{ $user->shortName() }}</span></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="h-top__logout" title="Abmelden"><x-icon name="log-out" size="18" /><span class="sr-only">Abmelden</span></button>
                </form>
            @endif
        </div>
    </header>

    <div class="call-toast" data-call-toast hidden role="alertdialog" aria-labelledby="call-toast-name" aria-live="assertive">
        <div class="call-toast__kicker">EINGEHENDER ANRUF · APP</div>
        <div class="call-toast__name" id="call-toast-name" data-call-toast-name></div>
        <form method="POST" data-call-toast-form>
            @csrf
            <button type="submit" class="btn btn--success btn--block">Annehmen <x-icon name="phone" size="18" stroke="2.4" /></button>
        </form>
    </div>

    <main id="main" class="h-main" tabindex="-1">
        @if (session('status'))
            <div class="h-flash"><div class="alert alert--success" role="status">{{ session('status') }}</div></div>
        @endif
        {{ $slot }}
    </main>

    <x-disclaimer />
</div>
</body>
</html>
