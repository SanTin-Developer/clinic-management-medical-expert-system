@extends('emails.layout')

@section('content')
    <h1 class="headline" style="margin: 0 0 12px; color: #0f2444; font-size: 24px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.25;">Confirm your new email address</h1>
    <p class="greeting" style="margin: 0 0 20px; color: #0f2444; font-size: 16px; font-weight: 600;">Hello,</p>
    <p class="body-text" style="margin: 0 0 16px; color: #33475e; font-size: 16px; line-height: 1.6;">
        Use the code below to confirm {{ $newEmail }} as the new email address for your RoyalCare Clinic account.
    </p>

    <x-email.code-box :code="$code" label="Your one-time code" />

    <x-email.callout type="warning" icon="⏰">This code expires in <strong>{{ $expiresInMinutes }} minutes</strong>.</x-email.callout>

    <x-email.button :url="$url">Open account settings</x-email.button>
    <x-email.fallback-link :url="$url" />

    <x-email.callout type="security" icon="🔒">Didn't request this? You can ignore this email.</x-email.callout>
@stop
