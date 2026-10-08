@props(['messages', 'viewer', 'empty' => 'Noch keine Nachrichten.'])
{{-- Chat-Verlauf; viewer = 'patient' oder 'clinic' bestimmt, welche Nachrichten "eigene" sind. --}}
@if ($messages->isEmpty())
    <p class="meta chat-empty">{{ $empty }}</p>
@else
    <ol class="chat" aria-label="Nachrichtenverlauf">
        @foreach ($messages as $message)
            @php
                $own = $viewer === 'patient' ? $message->from_patient : ! $message->from_patient;
                $sender = $viewer === 'patient' && $message->from_patient ? 'Sie' : $message->senderName();
            @endphp
            <li @class(['chat__msg', 'chat__msg--own' => $own, 'is-unread' => ! $own && $message->read_at === null])>
                <div class="chat__meta">
                    <b>{{ $sender }}</b> · {{ \App\Support\Format::day($message->created_at) }}
                    @if (! $own && $message->read_at === null)
                        <span class="chat__new">Neu</span>
                    @endif
                </div>
                <div class="chat__body">{{ $message->body }}</div>
                @if ($own)
                    <div class="chat__state">{{ $message->read_at ? 'Gelesen '.$message->read_at->format('d.m. H:i') : 'Gesendet' }}</div>
                @endif
            </li>
        @endforeach
    </ol>
@endif
