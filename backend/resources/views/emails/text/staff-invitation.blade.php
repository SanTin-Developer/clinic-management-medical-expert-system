@extends('emails.text.layout')

@section('content')
You're invited to join RoyalCare Clinic

Hello {{ $name }},

An administrator has invited you to join the RoyalCare Clinic staff portal as {{ $article }} {{ $role }}.

Invitation details
Role: {{ $role }}
Expires in: {{ $expireHours }} hours

Accept your invitation:
Button not working? Copy and paste this link into your browser
{{ $url }}

This invitation expires in {{ $expireHours }} hours. After that, please ask your administrator to send a new one.

When you accept, you'll set your own password, enable two-factor authentication, and choose a username.

Didn't request this? You can safely ignore this email or contact support.
@stop
