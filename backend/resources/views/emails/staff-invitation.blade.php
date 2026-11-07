@extends('emails.layout')

@section('content')
    <h1 class="headline" style="margin: 0 0 12px; color: #0f2444; font-size: 24px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.25;">You're invited to join RoyalCare Clinic</h1>
    <p class="greeting" style="margin: 0 0 20px; color: #0f2444; font-size: 16px; font-weight: 600;">Hello {{ $name }},</p>
    <p class="body-text" style="margin: 0 0 16px; color: #33475e; font-size: 16px; line-height: 1.6;">
        An administrator has invited you to join the RoyalCare Clinic staff portal as {{ $article }} {{ $role }}.
    </p>

    <x-email.info-box title="Invitation details">
        <x-email.detail-row label="Role">
            <x-email.badge color="blue">{{ $role }}</x-email.badge>
        </x-email.detail-row>
        <x-email.detail-row label="Expires in">{{ $expireHours }} hours</x-email.detail-row>
    </x-email.info-box>

    <x-email.button :url="$url">Accept your invitation</x-email.button>
    <x-email.fallback-link :url="$url" />

    <x-email.callout type="warning" icon="⏰">This invitation expires in <strong>{{ $expireHours }} hours</strong>. After that, please ask your administrator to send a new one.</x-email.callout>
    <x-email.callout type="info" icon="ℹ">When you accept, you'll set your own password, enable two-factor authentication, and choose a username.</x-email.callout>
    <x-email.callout type="security" icon="🔒">Didn't request this? You can safely ignore this email or contact support.</x-email.callout>
@stop
