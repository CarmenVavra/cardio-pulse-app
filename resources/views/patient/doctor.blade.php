@php
    $doctor = $patient->doctor;
@endphp
<x-layouts.patient title="Arzt kontaktieren" tab="doctor">
    <x-slot:header>
        <header class="p-head">
            <h1 style="font-size:24px">Mein Behandlungsteam</h1>
            <p style="font-size:14px;color:var(--sand)">{{ config('cardiopulse.clinic.name') }} · {{ config('cardiopulse.clinic.ward') }}</p>
        </header>
    </x-slot:header>

    <div class="p-content">
        @if ($doctor)
            <section class="card" style="padding:16px" aria-label="Behandelnde Ärztin / behandelnder Arzt">
                <div class="doctor-card">
                    <span class="doctor-card__avatar" aria-hidden="true">{{ $doctor->initials() }}</span>
                    <div>
                        <b style="font-size:18px">{{ $doctor->displayName() }}</b>
                        <div class="availability">Erreichbar bis {{ $doctor->available_until ?? '16:00' }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('patient.calls.store') }}" style="margin-top:14px">
                    @csrf
                    <input type="hidden" name="target" value="doctor">
                    <button type="submit" class="btn btn--primary btn--lg btn--block btn--start" style="font-size:17px;min-height:58px"><x-icon name="phone" size="22" stroke="2.4" />Anrufen</button>
                </form>
            </section>
        @endif

        <section class="card card__pad" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
            <div>
                <b style="font-size:16px">{{ config('cardiopulse.clinic.hotline_label') }}</b>
                <div class="meta">rund um die Uhr</div>
            </div>
            <form method="POST" action="{{ route('patient.calls.store') }}">
                @csrf
                <input type="hidden" name="target" value="hotline">
                <button type="submit" class="btn btn--outline">Anrufen</button>
            </form>
        </section>

        <section id="nachrichten" aria-labelledby="messages-title">
            <h2 id="messages-title" class="meta" style="font-weight:600;margin:4px 0 8px">Nachrichten der Klinik</h2>
            <div class="card">
                @forelse ($messages as $message)
                    <div @class(['message', 'is-unread' => $message->read_at === null])>
                        <div class="meta">{{ $message->user?->displayName() ?? 'Klinik' }} · {{ \App\Support\Format::day($message->created_at) }}</div>
                        <div>{{ $message->body }}</div>
                    </div>
                @empty
                    <p class="meta" style="padding:12px 16px">Keine Nachrichten.</p>
                @endforelse
            </div>
        </section>

        <section aria-labelledby="history-title">
            <h2 id="history-title" class="meta" style="font-weight:600;margin:4px 0 8px">Letzte Gespräche</h2>
            <div class="card">
                @forelse ($recentCalls as $call)
                    @php $incoming = $call->direction === \App\Enums\CallDirection::ToPatient; @endphp
                    <div class="history-row">
                        <span>
                            <b>{{ $incoming ? ($call->user?->displayName() ?? 'Klinik').' hat angerufen' : 'Sie haben angerufen' }}</b><br>
                            <span class="muted">{{ \App\Support\Format::day($call->created_at) }} · {{ $call->answered_at ? $call->durationLabel() : $call->status->label() }}</span>
                        </span>
                        <span class="muted" aria-label="{{ $incoming ? 'eingehend' : 'ausgehend' }}"><x-icon :name="$incoming ? 'arrow-down-left' : 'arrow-up-right'" size="18" /></span>
                    </div>
                @empty
                    <p class="meta" style="padding:12px 16px">Noch keine Gespräche.</p>
                @endforelse
            </div>
        </section>

        <p class="meta push-down">Im Notfall immer zuerst <b class="emergency-number">112</b> wählen.</p>
    </div>
</x-layouts.patient>
