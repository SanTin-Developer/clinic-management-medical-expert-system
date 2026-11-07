@extends('emails.text.layout')

@section('content')
Security alert for your account

Hello {{ $name }},

We detected an important security event on your RoyalCare Clinic account.

Event details
Event: {{ $eventDescription }}
When: {{ $when }}
@if ($ipAddress)
IP address: {{ $ipAddress }}
@endif

Review your account security:
Button not working? Copy and paste this link into your browser
{{ $url }}

If this was not you, change your password and contact your administrator immediately.
@stop
