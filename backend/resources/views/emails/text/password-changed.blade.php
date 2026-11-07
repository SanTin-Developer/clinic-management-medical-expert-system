@extends('emails.text.layout')

@section('content')
Your password was changed

Hello {{ $name }},

This is a security notice that the password for your account was just changed.

Your password was changed successfully.
All other sessions and trusted devices have been signed out.

Review your account security:
Button not working? Copy and paste this link into your browser
{{ $url }}

Didn't make this change? Contact your administrator immediately.
@stop
