@extends('emails.layout')

@section('content')
    <h1 class="headline" style="margin: 0 0 12px; color: #0f2444; font-size: 24px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.25;">Your account status has changed</h1>
    <p class="greeting" style="margin: 0 0 20px; color: #0f2444; font-size: 16px; font-weight: 600;">Hello {{ $name }},</p>
    <p class="body-text" style="margin: 0 0 16px; color: #33475e; font-size: 16px; line-height: 1.6;">
        Your RoyalCare Clinic account status is now <strong>{{ $statusLabel }}</strong>.
    </p>

    <x-email.info-box title="Account status">
        <x-email.detail-row label="Status">
            <x-email.badge :color="$statusColor">{{ $statusLabel }}</x-email.badge>
        </x-email.detail-row>
        @if ($reason)
            <x-email.detail-row label="Reason">{{ $reason }}</x-email.detail-row>
        @endif
    </x-email.info-box>

    <x-email.button :url="$url">Contact support</x-email.button>
    <x-email.fallback-link :url="$url" />

    <x-email.callout type="security" icon="🔒">If you believe this is a mistake, contact your administrator.</x-email.callout>
@stop
