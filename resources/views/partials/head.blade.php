<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0F2C59">
<title>{{ $title ? $title.' · ' : '' }}CardioPulse</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@if ($pwa ?? false)
    {{-- Patienten-App als installierbare Web-App (Startbildschirm, Vollbild). --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="CardioPulse">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap">
