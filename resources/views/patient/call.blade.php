@php
    use App\Enums\CallDirection;
    use App\Enums\CallStatus;

    $incoming = $call->direction === CallDirection::ToPatient;
    $peer = $call->user?->displayName() ?? config('cardiopulse.clinic.hotline_label');
    $kicker = match (true) {
        $call->status === CallStatus::Ringing && $incoming => 'EINGEHENDER ANRUF',
        $call->status === CallStatus::Ringing => 'VERBINDE …',
        $call->status === CallStatus::Active => 'LAUFENDES GESPRÄCH · VERSCHLÜSSELT',
        $call->status === CallStatus::Declined => 'NICHT ANGENOMMEN',
        default => 'GESPRÄCH BEENDET',
    };
@endphp
<x-layouts.patient title="Anruf" theme="navy" :poll="false">
    <section class="p-call"
             data-call
             data-status-url="{{ route('patient.calls.status', $call) }}"
             data-status="{{ $call->status->value }}"
             data-elapsed="{{ $call->durationSeconds() }}"
             aria-labelledby="call-peer">
        <div style="font-weight:800;font-size:18px"><x-logo /> · Anruf</div>
        <div class="p-call__kicker" role="status">{{ $kicker }}</div>
        <h1 class="p-call__name" id="call-peer">{{ $peer }}</h1>
        <div style="font-size:17px;color:var(--sand)">{{ config('cardiopulse.clinic.name') }} · {{ config('cardiopulse.clinic.ward') }}</div>

        @if ($call->measurement)
            <div class="p-call__context">Bezug: Ihre Messung von {{ $call->measurement->measured_at->format('H:i') }} · <b style="color:#fff">{{ $call->measurement->reading() }}</b></div>
        @endif

        @if ($call->status === CallStatus::Active)
            <div class="p-call__timer" data-call-timer>{{ \App\Support\Format::duration($call->durationSeconds()) }}</div>
        @endif

        <div @class(['wave', 'is-idle' => $call->status !== CallStatus::Active]) data-wave aria-hidden="true">
            @for ($i = 0; $i < 24; $i++)
                <span style="height:{{ 4 + (($i * 29) % 26) }}px"></span>
            @endfor
        </div>

        <div class="p-actions">
            @if ($call->status === CallStatus::Ringing && $incoming)
                <form method="POST" action="{{ route('patient.calls.answer', $call) }}">
                    @csrf
                    <button type="submit" class="btn btn--success btn--block" style="min-height:64px;font-size:19px">Annehmen <x-icon name="phone" size="24" stroke="2.4" /></button>
                </form>
                <form method="POST" action="{{ route('patient.calls.decline', $call) }}">
                    @csrf
                    <button type="submit" class="btn btn--outline-red btn--lg btn--block" style="font-size:17px">Ablehnen</button>
                </form>
            @elseif ($call->status->isOpen())
                <form method="POST" action="{{ route('patient.calls.end', $call) }}">
                    @csrf
                    <button type="submit" class="btn btn--danger btn--lg btn--block" style="font-size:17px">Auflegen <span aria-hidden="true">→</span></button>
                </form>
            @else
                @if ($call->answered_at)
                    <p style="color:var(--sand)">Dauer: {{ $call->durationLabel() }}</p>
                @endif
                <a class="btn btn--primary btn--lg btn--block" href="{{ route('patient.home') }}">Zur Startseite <span aria-hidden="true">→</span></a>
                <a class="btn btn--outline-taupe btn--lg btn--block" href="{{ route('patient.doctor') }}">Mein Behandlungsteam</a>
            @endif
        </div>
    </section>
</x-layouts.patient>
