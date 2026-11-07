@extends('emails.layout')

@section('content')
    <h1 class="headline" style="margin: 0 0 12px; color: #0f2444; font-size: 24px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.25;">Your email address was changed</h1>
    <p class="greeting" style="margin: 0 0 20px; color: #0f2444; font-size: 16px; font-weight: 600;">Hello,</p>
    <p class="body-text" style="margin: 0 0 16px; color: #33475e; font-size: 16px; line-height: 1.6;">
        The email address for a RoyalCare Clinic account was changed from {{ $oldEmail }} to {{ $newEmail }}.
    </p>

    <x-email.callout type="info" icon="ℹ">For security, all sessions and trusted devices were signed out.</x-email.callout>

    <x-email.button :url="$url">Review your account security</x-email.button>
    <x-email.fallback-link :url="$url" />

    <x-email.callout type="security" icon="🔒">Didn't make this change? Contact your administrator immediately.</x-email.callout>
@stop
