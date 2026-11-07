@extends('emails.text.layout')

@section('content')
Reset your password

Hello {{ $name }},

We received a request to reset the password for your account. Use the one-time code below to continue.

Your one-time code: {{ $code }}

This code expires in {{ $expiresInMinutes }} minutes and can only be used once.

Open the password reset page:
Button not working? Copy and paste this link into your browser
{{ $url }}

Didn't request this? You can safely ignore this email or contact support.
@stop
