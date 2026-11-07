@extends('emails.text.layout')

@section('content')
Confirm your new email address

Hello,

Use the code below to confirm {{ $newEmail }} as the new email address for your RoyalCare Clinic account.

Your one-time code: {{ $code }}

This code expires in {{ $expiresInMinutes }} minutes.

Open account settings:
Button not working? Copy and paste this link into your browser
{{ $url }}

Didn't request this? You can ignore this email.
@stop
