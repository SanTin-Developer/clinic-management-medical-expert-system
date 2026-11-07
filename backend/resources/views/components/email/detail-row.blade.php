@props([
    'label',
])

<div class="detail-row">
    <div class="detail-label" style="color: #64748b; font-size: 12px; letter-spacing: 0.04em; text-transform: uppercase; padding-bottom: 2px;">{{ $label }}</div>
    <div class="detail-value" style="color: #0f2444; font-size: 15px; font-weight: 600; line-height: 1.5; padding-bottom: 12px;">{!! $slot !!}</div>
</div>
