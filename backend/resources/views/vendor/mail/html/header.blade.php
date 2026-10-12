@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; color: #db2777; font-weight: 700; font-size: 20px; letter-spacing: 0.02em; text-decoration: none;">
{{ $slot }}
</a>
</td>
</tr>
