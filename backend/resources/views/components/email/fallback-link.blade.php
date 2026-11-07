@props([
    'url',
])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 20px;">
    <tr>
        <td style="border-top: 1px solid #e2e8f0; padding-top: 18px;">
            <div class="fallback-label" style="color: #64748b; font-size: 12px; line-height: 1.6; margin-bottom: 8px;">Button not working? Copy and paste this link into your browser</div>
            <div class="fallback-url" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; color: #33475e; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; line-height: 1.5; word-break: break-all; overflow-wrap: anywhere;">{{ $url }}</div>
        </td>
    </tr>
</table>
