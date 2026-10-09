@props(['heading'])
<header class="p-head">
    <div class="p-head__bar"><x-logo /></div>
    <h1 class="p-greeting">{{ $heading }}</h1>
    <p class="p-connection">{{ config('cardiopulse.clinic.name') }} · {{ config('cardiopulse.clinic.ward') }}</p>
</header>
