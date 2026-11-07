@props([
    'code',
    'label' => 'Your one-time code',
])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="code-box" style="margin: 20px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
    <tr>
        <td align="center" style="padding: 20px 20px 22px;">
            <div class="code-label" style="font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; margin-bottom: 10px;">{{ $label }}</div>
            <div class="code-value" style="font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 32px; font-weight: 800; letter-spacing: 0.14em; color: #0f2444; line-height: 1.1;">{{ $code }}</div>
        </td>
    </tr>
</table>
