@extends('emails.layout')

@section('content')
    <h1 class="headline" style="margin: 0 0 12px; color: #0f2444; font-size: 24px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.25;">Reset your password</h1>
    <p class="greeting" style="margin: 0 0 20px; color: #0f2444; font-size: 16px; font-weight: 600;">Hello {{ $name }},</p>
    <p class="body-text" style="margin: 0 0 16px; color: #33475e; font-size: 16px; line-height: 1.6;">
        We received a request to reset the password for your account. Use the one-time code below to continue.
    </p>

    <x-email.code-box :code="$code" label="Your one-time code" />

    <x-email.callout type="warning" icon="⏰">This code expires in <strong>{{ $expiresInMinutes }} minutes</strong> and can only be used once.</x-email.callout>

    <x-email.button :url="$url">Open the password reset page</x-email.button>
    <x-email.fallback-link :url="$url" />

    <x-email.callout type="security" icon="🔒">Didn't request this? You can safely ignore this email or contact support.</x-email.callout>
@stop
