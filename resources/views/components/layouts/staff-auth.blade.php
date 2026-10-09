@props(['title'])
<!DOCTYPE html>
<html lang="de">
<head>
    @include('partials.head', ['title' => $title])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login">
    <section class="login__hero" aria-label="CardioPulse">
        <x-logo />
        <div>
            <div class="login__kicker">Krankenhaus · Überwachung</div>
            <p class="login__claim" aria-label="Messen. Übertragen. Schützen.">Messen.<br>Übertragen.<br><span>Schützen.</span></p>
            <p class="login__slogan">Lückenlose Blutdruck-Überwachung in Echtzeit zur Vermeidung kardiologischer Notfälle.</p>
        </div>
        <div class="login__compliance"><span>EN ISO 13485</span><span>HL7 FHIR R4</span><span>TLS 1.3 · E2E</span></div>
    </section>

    <main class="login__form">
        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>

    <x-disclaimer />
</div>
</body>
</html>
