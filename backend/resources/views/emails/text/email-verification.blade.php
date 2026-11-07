@extends('emails.text.layout')

@section('content')
Verify your email address

Hello {{ $name }},

Please click the button below to verify your email address and activate your RoyalCare Clinic account.

Link details
Expires in: {{ $expiresInMinutes }} minutes

Verify email address:
Button not working? Copy and paste this link into your browser
{{ $url }}

Didn't request this? If you did not create an account, no further action is required.
@stop
