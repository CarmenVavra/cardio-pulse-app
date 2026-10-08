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
        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

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
            <h2 id="messages-title" class="meta" style="font-weight:600;margin:4px 0 8px">Nachrichten mit der Klinik</h2>
            <div class="card">
                <x-message-thread :messages="$messages" viewer="patient" empty="Noch keine Nachrichten. Schreiben Sie Ihrem Behandlungsteam, z. B. bei Fragen zur Medikation." />

                <form method="POST" action="{{ route('patient.messages.store') }}" class="chat-form" novalidate>
                    @csrf
                    <div class="field">
                        <label class="field__label" for="message-body">Nachricht an die Klinik</label>
                        <textarea class="textarea" id="message-body" name="body" rows="3" maxlength="2000" required
                                  aria-describedby="message-hint{{ $errors->has('body') ? ' message-error' : '' }}"
                                  @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                        @error('body')<div class="field-error" id="message-error" role="alert">{{ $message }}</div>@enderror
                        <p class="meta" id="message-hint">Antwort in der Regel innerhalb eines Werktags. Nicht für Notfälle – dann <b>112</b> wählen.</p>
                    </div>
                    <button type="submit" class="btn btn--navy btn--lg btn--block">Senden <span aria-hidden="true">→</span></button>
                </form>
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
