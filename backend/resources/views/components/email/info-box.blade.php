@props([
    'title' => null,
])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="info-box" style="margin: 20px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
    <tr>
        <td style="padding: 18px 20px;">
            @if ($title)
                <div class="info-box-title" style="font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #64748b; margin-bottom: 12px;">{{ $title }}</div>
            @endif
            {!! $slot !!}
        </td>
    </tr>
</table>
