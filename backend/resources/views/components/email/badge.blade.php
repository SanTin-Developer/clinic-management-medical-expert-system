@props([
    'color' => 'blue',
])

@php
    $palette = [
        'blue' => ['bg' => '#eef5ff', 'text' => '#1b6fd6', 'border' => '#bfdbfe'],
        'green' => ['bg' => '#ecfdf5', 'text' => '#059669', 'border' => '#a7f3d0'],
        'amber' => ['bg' => '#fffbeb', 'text' => '#d97706', 'border' => '#fde68a'],
        'red' => ['bg' => '#fef2f2', 'text' => '#dc2626', 'border' => '#fecaca'],
        'slate' => ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#e2e8f0'],
    ];
    $p = $palette[$color] ?? $palette['blue'];
@endphp

<span class="badge badge-{{ $color }}" style="display: inline-block; padding: 3px 12px; border-radius: 999px; background-color: {{ $p['bg'] }}; border: 1px solid {{ $p['border'] }}; color: {{ $p['text'] }}; font-size: 12px; font-weight: 700; line-height: 1.5;">{{ $slot }}</span>
