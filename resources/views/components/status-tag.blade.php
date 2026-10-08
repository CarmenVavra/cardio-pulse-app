@props(['status', 'label' => null, 'short' => false])
@if ($status)
    <span {{ $attributes->class(['tag', 'st-'.$status->value]) }}>{{ $label ?? ($short ? $status->tagLabel() : $status->label()) }}</span>
@else
    <span {{ $attributes->class(['tag', 'st-none']) }}>Keine Daten</span>
@endif
