@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
            <img src="{{ asset('royalcare-logo.png') }}" alt="{{ config('app.name') }} logo" class="logo">
            <span style="display: block; color: #172554; font-size: 19px; font-weight: 800; letter-spacing: -0.01em;">{!! $slot !!}</span>
        </a>
    </td>
</tr>