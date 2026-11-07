@extends('emails.text.layout')

@section('content')
Your email address was changed

Hello,

The email address for a RoyalCare Clinic account was changed from {{ $oldEmail }} to {{ $newEmail }}.

For security, all sessions and trusted devices were signed out.

Review your account security:
Button not working? Copy and paste this link into your browser
{{ $url }}

Didn't make this change? Contact your administrator immediately.
@stop
