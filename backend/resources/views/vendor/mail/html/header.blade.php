@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
            <img src="https://res.cloudinary.com/drercy9vt/image/upload/v1791806975/RoyalCare-removebg-preview_cthbmr.png" alt="{{ config('app.name') }}" class="logo">
            <span style="display: block; color: #172554; font-size: 19px; font-weight: 800; letter-spacing: -0.01em;">{!! $slot !!}</span>
        </a>
    </td>
</tr>