@if ($chart->isEmpty())
    <p class="meta">Keine Messungen im Zeitraum.</p>
@else
    <svg class="chart" viewBox="0 0 1030 {{ $chart->height }}" role="img" aria-label="{{ $title }}: {{ $stats->count }} Messungen, Durchschnitt {{ $stats->average() }} mmHg">
        <rect x="30" y="0" width="1000" height="{{ $chart->y180 }}" fill="#F6DCD8"/>
        <rect x="30" y="{{ $chart->y180 }}" width="1000" height="{{ $chart->y130 - $chart->y180 }}" fill="#FBE3CF"/>
        <rect x="30" y="{{ $chart->y90 }}" width="1000" height="{{ $chart->height - $chart->y90 }}" fill="#FFFFFF"/>
        <line x1="30" x2="1030" y1="{{ $chart->y130 }}" y2="{{ $chart->y130 }}" stroke="#ED6C02" stroke-dasharray="4 4"/>
        <text x="0" y="{{ $chart->y180 + 4 }}" font-size="12" fill="#5C5045" font-family="Archivo, sans-serif">180</text>
        <text x="0" y="{{ $chart->y130 + 4 }}" font-size="12" fill="#5C5045" font-family="Archivo, sans-serif">130</text>
        <text x="0" y="{{ $chart->y90 + 4 }}" font-size="12" fill="#5C5045" font-family="Archivo, sans-serif">90</text>
        <g transform="translate(30 0)">
            <path d="{{ $chart->diastolicPath }}" fill="none" stroke="#867666" stroke-width="2"/>
            <path d="{{ $chart->systolicPath }}" fill="none" stroke="#0F2C59" stroke-width="2"/>
            @foreach ($chart->dots as $dot)
                @php $size = $dot['status'] === 'red' ? 10 : 8; @endphp
                <rect class="dot-{{ $dot['status'] }}" x="{{ $dot['x'] - $size / 2 }}" y="{{ $dot['y'] - $size / 2 }}" width="{{ $size }}" height="{{ $size }}"><title>{{ $dot['label'] }}</title></rect>
            @endforeach
        </g>
    </svg>
    <div class="chart-ticks" aria-hidden="true">
        @foreach ($chart->ticks as $tick)
            <span>{{ $tick }}</span>
        @endforeach
    </div>
@endif
