@extends('emails.text.layout')

@section('content')
Complete your sign-in

Hello {{ $name }},

A sign-in attempt was made from a new device. Use the one-time code below to continue.

Your one-time code: {{ $code }}

This code expires in {{ $expiresInMinutes }} minutes and can only be used once.

Continue signing in:
Button not working? Copy and paste this link into your browser
{{ $url }}

Didn't attempt this? Change your password immediately.
@stop
