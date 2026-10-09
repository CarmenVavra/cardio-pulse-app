@props(['signalUrl', 'role', 'iceServers', 'peerName'])
{{-- Videosprechstunde: Bild und Ton direkt zwischen den Browsern (resources/js/video-call.js). --}}
<div {{ $attributes->class(['video-call']) }}
     data-video
     data-signal-url="{{ $signalUrl }}"
     data-role="{{ $role }}"
     data-ice="{{ json_encode($iceServers) }}">
    <div class="video-call__stage">
        <video class="video-call__remote" data-remote-video autoplay playsinline aria-label="Video von {{ $peerName }}"></video>
        <video class="video-call__local" data-local-video autoplay playsinline muted aria-label="Ihr eigenes Kamerabild"></video>
        <button type="button" class="btn btn--primary video-call__unmute" data-video-unmute hidden>
            <span class="btn__lead"><x-icon name="volume-2" size="18" />Ton einschalten</span>
        </button>
    </div>
    <p class="video-call__state" data-video-state role="status" aria-live="polite">Videoverbindung wird vorbereitet …</p>
    <div class="video-call__controls">
        <button type="button" class="btn btn--outline-taupe" aria-pressed="false" data-video-mic>
            <span class="btn__lead"><x-icon name="mic-off" size="18" /><span data-label>Stumm</span></span>
        </button>
        <button type="button" class="btn btn--outline-taupe" aria-pressed="false" data-video-camera>
            <span class="btn__lead"><x-icon name="camera" size="18" /><span data-label>Kamera aus</span></span>
        </button>
    </div>
</div>
