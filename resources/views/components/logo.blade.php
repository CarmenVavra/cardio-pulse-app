@props(['light' => false, 'href' => null])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['logo', 'logo--light' => $light]) }} aria-label="CardioPulse – Startseite"><span class="logo__cardio">Cardio</span>Pulse</a>
@else
    <span {{ $attributes->class(['logo', 'logo--light' => $light]) }}><span class="logo__cardio">Cardio</span>Pulse</span>
@endif
