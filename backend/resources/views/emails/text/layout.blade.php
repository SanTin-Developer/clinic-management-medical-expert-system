{{--
    RoyalCare Clinic — shared plain-text layout.

    Mirrors emails.layout so every HTML email has a plain-text version with the
    same content. Each plain-text email extends this view.
--}}
@php
    $profile = \App\Support\ClinicProfile::contact();
    $clinicName = $profile['name'];
    $frontendUrl = $profile['website'];
    $supportEmail = $profile['email'];
    $supportPhone = $profile['phone'];
    $clinicAddress = $profile['address'];
    $year = date('Y');
    $creditLine = 'Designed and developed by SanTin Dev with a focus on quality, security, and usability.';
@endphp
{{ $clinicName }}


@yield('content')

--------------------
{{ $clinicName }}
@if ($clinicAddress)
{{ $clinicAddress }}
@endif
@if ($supportPhone)
Phone: {{ $supportPhone }}
@endif
Website: {{ $frontendUrl }}
Support: {{ $supportEmail }}
© {{ $year }} {{ $clinicName }}. All rights reserved.
{{ $creditLine }}