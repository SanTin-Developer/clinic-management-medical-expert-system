@extends('emails.text.layout')

@section('content')
Your account status has changed

Hello {{ $name }},

Your RoyalCare Clinic account status is now {{ $statusLabel }}.

Account status
Status: {{ $statusLabel }}
@if ($reason)
Reason: {{ $reason }}
@endif

Contact support:
Button not working? Copy and paste this link into your browser
{{ $url }}

If you believe this is a mistake, contact your administrator.
@stop
