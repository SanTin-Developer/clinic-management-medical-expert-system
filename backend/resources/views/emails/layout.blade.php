{{--
    RoyalCare Clinic — shared transactional email layout.

    Every transactional email extends this view so the header band, content
    card, callouts and footer stay consistent. The plain-text equivalent lives
    at emails.text.layout.
--}}
@php
    $profile = \App\Support\ClinicProfile::contact();
    $clinicName = $clinicName ?? $profile['name'];
    $logoUrl = $logoUrl ?? asset('royalcare-logo.png');
    $frontendUrl = $profile['website'];
    $supportEmail = $supportEmail ?? $profile['email'];
    $supportPhone = $supportPhone ?? $profile['phone'];
    $clinicAddress = $clinicAddress ?? $profile['address'];
    $preheader = $preheader ?? '';
    $year = date('Y');
    $creditLine = 'Designed and developed by SanTin Dev with a focus on quality, security, and usability.';
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" dir="ltr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title>{{ $clinicName }}</title>
    <style type="text/css">
        body { margin: 0; padding: 0; width: 100%; }
        img { border: 0; }
        a { text-decoration: none; }
        .headline, .greeting, .body-text, .detail-value { overflow-wrap: break-word; }
        .detail-row:last-child .detail-value { padding-bottom: 0; }

        @media only screen and (max-width: 600px) {
            .email-width { width: 100% !important; }
            .content-cell { padding: 28px 24px !important; }
            .email-button-wrap { width: 100% !important; }
            .email-button-cell { width: 100% !important; }
            .email-button { display: block !important; width: 100% !important; box-sizing: border-box !important; }
            .header-band { border-radius: 12px 12px 0 0 !important; }
            .card { border-radius: 0 0 12px 12px !important; }
        }

        @media (prefers-color-scheme: dark) {
            body { background-color: #0b1220 !important; }
            .email-outer { background-color: #0b1220 !important; }
            .card { background-color: #111a2e !important; box-shadow: none !important; }
            .headline { color: #f1f5f9 !important; }
            .greeting { color: #e2e8f0 !important; }
            .body-text { color: #cbd5e1 !important; }
            .info-box { background-color: #0f172a !important; border-color: #1e293b !important; }
            .info-box-title { color: #94a3b8 !important; }
            .detail-label { color: #94a3b8 !important; }
            .detail-value { color: #e2e8f0 !important; }
            .code-box { background-color: #0f172a !important; border-color: #1e293b !important; }
            .code-label { color: #94a3b8 !important; }
            .code-value { color: #f8fafc !important; }
            .callout-info { background-color: #0f172a !important; }
            .callout-success { background-color: #052e22 !important; }
            .callout-warning { background-color: #2a1a05 !important; }
            .callout-security { background-color: #2a0a0a !important; }
            .callout-text { color: #e2e8f0 !important; }
            .fallback-label { color: #94a3b8 !important; }
            .fallback-url { background-color: #0f172a !important; border-color: #1e293b !important; color: #cbd5e1 !important; }
            .footer-name { color: #e2e8f0 !important; }
            .footer-line { color: #94a3b8 !important; }
            .footer-small { color: #94a3b8 !important; }
            .footer-link { color: #cbd5e1 !important; }
        }
    </style>
</head>
<body class="email-outer" style="margin: 0; padding: 0; width: 100%; background-color: #f4f7fb; color: #33475e; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-text-size-adjust: none; text-size-adjust: none;">

    {{-- Preview / preheader text (hidden) --}}
    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all;">
        {{ $preheader }} &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="email-outer" style="background-color: #f4f7fb;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="email-width" style="width: 600px; max-width: 600px;">

                    {{-- Header band --}}
                    <tr>
                        <td align="center" class="header-band" bgcolor="#1b6fd6" style="background-color: #1b6fd6; background-image: linear-gradient(135deg, #1b6fd6 0%, #0f766e 100%); border-radius: 16px 16px 0 0; padding: 28px 24px 26px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="background-color: #ffffff; border-radius: 16px; padding: 8px; line-height: 0; font-size: 0;">
                                        <img src="{{ $logoUrl }}" alt="{{ $clinicName }} logo" width="56" height="52" style="display: block; width: 56px; height: 52px; border-radius: 10px;" />
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 14px; color: #ffffff; font-size: 22px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.2;">{{ $clinicName }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Content card --}}
                    <tr>
                        <td>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="card" style="background-color: #ffffff; border-radius: 0 0 16px 16px; box-shadow: 0 16px 40px -24px rgba(15, 36, 68, 0.35);">
                                <tr>
                                    <td class="content-cell" style="padding: 36px 40px; color: #33475e; font-size: 16px; line-height: 1.6;">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding: 24px 24px 12px; font-size: 12px; line-height: 1.7;">
                            <div class="footer-name" style="font-weight: 700; font-size: 13px; margin-bottom: 2px; color: #475569;">{{ $clinicName }}</div>
                            @if ($clinicAddress)
                                <div class="footer-line" style="color: #64748b;">{{ $clinicAddress }}</div>
                            @endif
                            @if ($supportPhone)
                                <div class="footer-line" style="color: #64748b;">
                                    Phone: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}" class="footer-link" style="color: #475569; text-decoration: underline;">{{ $supportPhone }}</a>
                                </div>
                            @endif
                            <div class="footer-line" style="color: #64748b;">
                                <a href="{{ $frontendUrl }}" class="footer-link" style="color: #64748b; text-decoration: underline;">Visit our website</a>
                            </div>
                            <div class="footer-line" style="color: #64748b;">Support: <a href="mailto:{{ $supportEmail }}" class="footer-link" style="color: #475569; text-decoration: underline;">{{ $supportEmail }}</a></div>
                            <div class="footer-small" style="margin-top: 10px; color: #94a3b8;">&copy; {{ $year }} {{ $clinicName }}. All rights reserved.</div>
                            <div class="footer-small" style="margin-top: 4px; color: #94a3b8;">{{ $creditLine }}</div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>