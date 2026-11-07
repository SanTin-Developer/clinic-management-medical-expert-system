@extends('emails.layout')

@section('content')
    <h1 class="headline" style="margin: 0 0 12px; color: #0f2444; font-size: 24px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.25;">Security alert for your account</h1>
    <p class="greeting" style="margin: 0 0 20px; color: #0f2444; font-size: 16px; font-weight: 600;">Hello {{ $name }},</p>
    <p class="body-text" style="margin: 0 0 16px; color: #33475e; font-size: 16px; line-height: 1.6;">
        We detected an important security event on your RoyalCare Clinic account.
    </p>

    <x-email.info-box title="Event details">
        <x-email.detail-row label="Event">{{ $eventDescription }}</x-email.detail-row>
        <x-email.detail-row label="When">{{ $when }}</x-email.detail-row>
        @if ($ipAddress)
            <x-email.detail-row label="IP address">{{ $ipAddress }}</x-email.detail-row>
        @endif
    </x-email.info-box>

    <x-email.button :url="$url">Review your account security</x-email.button>
    <x-email.fallback-link :url="$url" />

    <x-email.callout type="security" icon="🔒">If this was not you, change your password and contact your administrator immediately.</x-email.callout>
@stop
