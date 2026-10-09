<x-layouts.hospital title="Überwachung" active="board" page="board" :locked="$locked">
    @if ($locked)
        <div class="lock-grid">
            @include('hospital.partials.lock-panel')
            <div class="detail">
    @endif

    <div data-alarm-banner>
        @include('hospital.partials.alarm-banner')
    </div>

    @unless ($locked)
        <section class="counter-strip" aria-label="Übersicht nach Ampelfarbe">
            @foreach (\App\Enums\BloodPressureStatus::cases() as $status)
                <div class="counter st-{{ $status->value }}">
                    <div class="counter__label">{{ $status->label() }}</div>
                    <div class="counter__value" data-count="{{ $status->value }}">{{ $counts[$status->value] }}</div>
                </div>
            @endforeach
            <div class="counter-strip__search">
                <div class="search">
                    <x-icon name="search" size="16" />
                    <label class="sr-only" for="board-search">Patient oder ID suchen</label>
                    <input id="board-search" type="search" placeholder="Patient oder ID suchen" autocomplete="off" data-board-search>
                </div>
                <div class="counter-strip__meta">
                    <span>Heute <b data-uploads-today>{{ $uploadsToday }}</b> Uploads · <span data-patients-total>{{ $patientsTotal }}</span> Patienten zu Hause</span>
                    @if ($demo)
                        <form method="POST" action="{{ route('demo.upload') }}" data-demo-form>
                            @csrf
                            <button type="submit" class="link-button">Demo: Upload simulieren</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    @endunless

    <div class="board" id="board" data-board>
        <table class="board-table" aria-describedby="board-caption">
            <caption id="board-caption" class="sr-only">Live-Board: Patientinnen und Patienten zu Hause, sortiert nach Dringlichkeit (Rot, Gelb-Orange, Blau, Grün)</caption>
            <colgroup>
                <col style="width:10px">
                <col style="width:150px">
                <col>
                <col style="width:160px">
                @unless ($locked)
                    <col class="col-pulse" style="width:70px">
                    <col class="col-trend" style="width:120px">
                    <col class="col-symptoms">
                @endunless
                <col class="col-upload" style="width:150px">
                @unless ($locked)
                    <col style="width:170px">
                @endunless
            </colgroup>
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">Ampelfarbe</span></th>
                    <th scope="col" class="col-pad">Ampel</th>
                    <th scope="col" class="col-pad">Patient</th>
                    <th scope="col">RR mmHg</th>
                    @unless ($locked)
                        <th scope="col" class="col-pulse">Puls</th>
                        <th scope="col" class="col-trend">7 Tage</th>
                        <th scope="col" class="col-symptoms">Symptome</th>
                    @endunless
                    <th scope="col" class="col-upload">Upload</th>
                    @unless ($locked)
                        <th scope="col"><span class="sr-only">Aktion</span></th>
                    @endunless
                </tr>
            </thead>
            <tbody data-board-rows aria-live="polite" aria-relevant="additions">
                @include('hospital.partials.board-rows')
            </tbody>
        </table>
    </div>

    @if ($locked)
            </div>
        </div>
    @endif

    <div class="alarm-layer" data-alarm-layer @if ($openAlarms->isEmpty() || $locked || $openAlarms->first()->isClaimedByOther(auth()->user())) hidden @endif>
        @if ($openAlarms->isNotEmpty() && ! $locked)
            @include('hospital.partials.alarm-modal', ['alarm' => $openAlarms->first(), 'more' => $openAlarms->count() - 1])
        @endif
    </div>
</x-layouts.hospital>
