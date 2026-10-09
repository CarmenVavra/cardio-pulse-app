@props(['title' => null, 'tab' => null, 'theme' => null, 'poll' => true])
@php
    $tabs = [
        'home' => ['Start', 'home', route('patient.home')],
        'measure' => ['Messen', 'activity', route('patient.measurements.create')],
        'month' => ['Monat', 'calendar', route('patient.month')],
        'doctor' => ['Arzt', 'phone', route('patient.doctor')],
    ];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    @include('partials.head', ['title' => $title, 'pwa' => true])
    @vite(['resources/css/app.css', 'resources/js/patient.js'])
</head>
<body class="p-body" @if ($poll) data-incoming-url="{{ route('patient.calls.active') }}" data-poll="4000" @endif>
<a class="skip-link" href="#main">Zum Inhalt springen</a>
<div @class(['p-app', 'p-app--'.$theme => $theme])>
    @if ($tab)
        <a class="sos-link" href="{{ route('patient.sos') }}"><x-icon name="siren" size="20" stroke="2.4" />Notfall · SOS</a>
    @endif
    {{ $header ?? '' }}

    <main id="main" class="p-main" tabindex="-1">
        {{ $slot }}
    </main>

    @if ($tab)
        <nav class="p-tabbar" aria-label="App-Navigation">
            <ul class="p-tabs">
                @foreach ($tabs as $key => [$label, $icon, $url])
                    <li><a href="{{ $url }}" @if ($tab === $key) aria-current="page" @endif><x-icon :name="$icon" size="22" />{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
</body>
</html>
