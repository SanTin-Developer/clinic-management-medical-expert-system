@props([
    'type' => 'info',
    'icon' => null,
])

@php
    $palette = [
        'info' => ['bg' => '#eef5ff', 'border' => '#1b6fd6', 'text' => '#0f3d7a', 'symbol' => 'ℹ'],
        'success' => ['bg' => '#ecfdf5', 'border' => '#059669', 'text' => '#065f46', 'symbol' => '✓'],
        'warning' => ['bg' => '#fffbeb', 'border' => '#d97706', 'text' => '#78350f', 'symbol' => '⏰'],
        'security' => ['bg' => '#fef2f2', 'border' => '#dc2626', 'text' => '#7f1d1d', 'symbol' => '🔒'],
    ];
    $p = $palette[$type] ?? $palette['info'];
    $symbol = $icon ?? $p['symbol'];
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="callout-{{ $type }}" style="margin: 16px 0; background-color: {{ $p['bg'] }}; border-left: 4px solid {{ $p['border'] }}; border-radius: 8px;">
    <tr>
        <td style="padding: 14px 16px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td valign="top" aria-hidden="true" style="width: 22px; padding-right: 10px; color: {{ $p['border'] }}; font-size: 16px; line-height: 1.6; text-align: center;">{{ $symbol }}</td>
                    <td valign="top" class="callout-text" style="color: {{ $p['text'] }}; font-size: 14px; line-height: 1.6;">{!! $slot !!}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
