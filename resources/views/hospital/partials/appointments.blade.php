@php
    /** @var \App\Support\PatientOverview $overview */
    $patient = $overview->patient;
    $defaults = $overview->appointmentDefaults;
    $bag = $errors->appointment;
@endphp
<section class="detail__section" id="termine" aria-labelledby="appointments-title" tabindex="-1">
    <h2 id="appointments-title">Videosprechstunden</h2>
    @if ($bag->any())
        <div class="alert alert--error" role="alert">{{ $bag->first() }}</div>
    @endif

    <div class="detail__chat-grid">
        <div class="appointment-list">
            @forelse ($overview->appointments as $appointment)
                <div @class(['appointment-row', 'is-due' => $appointment->canStart()])>
                    <div>
                        <b>{{ $appointment->when() }}</b>
                        <div class="meta">
                            {{ $appointment->doctor?->displayName() ?? 'Arzt gelöscht' }}
                            @if ($appointment->reason) · {{ $appointment->reason }} @endif
                        </div>
                    </div>
                    <div class="appointment-row__actions">
                        @if ($appointment->canStart())
                            <form method="POST" action="{{ route('appointments.start', $appointment) }}">
                                @csrf
                                <button type="submit" class="btn btn--primary btn--sm"><span class="btn__lead"><x-icon name="video" size="16" />Videosprechstunde starten</span></button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('appointments.cancel', $appointment) }}"
                              data-confirm="Termin am {{ $appointment->when() }} absagen? {{ $patient->fullName() }} bekommt eine E-Mail.">
                            @csrf
                            <button type="submit" class="btn btn--outline btn--sm">Absagen<span class="sr-only">: {{ $appointment->when() }}</span></button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="meta">Keine Videosprechstunde geplant.</p>
            @endforelse

            @if ($overview->pastAppointments->isNotEmpty())
                <ul class="appointment-history" aria-label="Frühere Termine">
                    @foreach ($overview->pastAppointments as $past)
                        <li class="meta">
                            {{ $past->starts_at->format('d.m.Y H:i') }} ·
                            {{ $past->status === \App\Enums\AppointmentStatus::Booked ? 'Verpasst' : $past->status->label() }}{{ $past->cancelled_by_patient ? ' (vom Patienten)' : '' }}
                            @if ($past->doctor) · {{ $past->doctor->shortName() }} @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <form method="POST" action="{{ route('patients.appointments.store', $patient) }}" class="stack stack--sm" novalidate>
            @csrf
            <b>Neuen Termin vereinbaren</b>
            <div class="appointment-form">
                <div class="field">
                    <label class="field__label" for="appointment-date">Datum</label>
                    <input class="input" id="appointment-date" name="date" type="date" required min="{{ now()->toDateString() }}"
                           value="{{ old('date', $defaults['date']) }}" @if ($bag->has('date')) aria-invalid="true" @endif>
                </div>
                <div class="field">
                    <label class="field__label" for="appointment-time">Uhrzeit</label>
                    <input class="input" id="appointment-time" name="time" type="time" step="300" required
                           value="{{ old('time', $defaults['time']) }}" @if ($bag->has('time')) aria-invalid="true" @endif>
                </div>
                <div class="field">
                    <label class="field__label" for="appointment-duration">Dauer</label>
                    <select class="select" id="appointment-duration" name="duration">
                        @foreach (\App\Models\Appointment::DURATIONS as $minutes)
                            <option value="{{ $minutes }}" @selected((int) old('duration', 15) === $minutes)>{{ $minutes }} Min</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="appointment-doctor">Mit</label>
                    <select class="select" id="appointment-doctor" name="doctor_id">
                        @foreach ($overview->doctors as $doctor)
                            <option value="{{ $doctor->id }}" @selected((int) old('doctor_id', $defaults['doctor_id']) === $doctor->id)>{{ $doctor->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field">
                <label class="field__label" for="appointment-reason">Anlass (optional, steht nicht in der E-Mail)</label>
                <input class="input" id="appointment-reason" name="reason" type="text" maxlength="200" placeholder="z. B. Besprechung Monatsbericht, Medikation anpassen"
                       value="{{ old('reason') }}" @if ($bag->has('reason')) aria-invalid="true" @endif>
            </div>
            <div><button type="submit" class="btn btn--primary"><span class="btn__lead"><x-icon name="calendar" size="16" />Termin vereinbaren</span></button></div>
        </form>
    </div>
</section>
