@props([
    'url',
    'color' => 'primary',
])

@php
    $palette = [
        'primary' => '#1b6fd6',
        'success' => '#059669',
        'warning' => '#d97706',
        'security' => '#dc2626',
    ];
    $bg = $palette[$color] ?? $palette['primary'];
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="email-button-wrap">
                <tr>
                    <td align="center" bgcolor="{{ $bg }}" class="email-button-cell" style="border-radius: 10px; background-color: {{ $bg }};">
                        <!--[if mso]>
                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height: 50px; v-text-anchor: middle; width: 300px;" arcsize="20%" stroke="f" fillcolor="{{ $bg }}">
                            <w:anchorlock/>
                            <center style="color: #ffffff; font-family: sans-serif; font-size: 16px; font-weight: 700;">{{ $slot }}</center>
                        </v:roundrect>
                        <![endif]-->
                        <!--[if !mso]><!-- -->
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="email-button" style="display: inline-block; padding: 15px 32px; color: #ffffff; font-size: 16px; font-weight: 700; line-height: 1.2; border-radius: 10px; background-color: {{ $bg }};">{{ $slot }}</a>
                        <!--<![endif]-->
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
