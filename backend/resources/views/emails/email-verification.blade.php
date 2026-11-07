@extends('emails.layout')

@section('content')
    <h1 class="headline" style="margin: 0 0 12px; color: #0f2444; font-size: 24px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.25;">Verify your email address</h1>
    <p class="greeting" style="margin: 0 0 20px; color: #0f2444; font-size: 16px; font-weight: 600;">Hello {{ $name }},</p>
    <p class="body-text" style="margin: 0 0 16px; color: #33475e; font-size: 16px; line-height: 1.6;">
        Please click the button below to verify your email address and activate your RoyalCare Clinic account.
    </p>

    <x-email.info-box title="Link details">
        <x-email.detail-row label="Expires in">{{ $expiresInMinutes }} minutes</x-email.detail-row>
    </x-email.info-box>

    <x-email.button :url="$url">Verify email address</x-email.button>
    <x-email.fallback-link :url="$url" />

    <x-email.callout type="security" icon="🔒">Didn't request this? If you did not create an account, no further action is required.</x-email.callout>
@stop
