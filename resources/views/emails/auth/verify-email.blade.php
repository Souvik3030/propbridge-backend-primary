@extends('emails.auth.layouts.master')
@section('title', 'Verify Your Identity')

@section('content')
    <h1 class="hero-title">Welcome to PropBridge</h1>
    <p class="hero-subtitle">
        Your account is almost ready. Before you can log in and start managing listings, 
        please verify your email address.
    </p>

    <div style="margin-bottom: 30px;">
        <a href="{{ $url }}" class="cta-button">Verify Email Address</a>
    </div>

    <p style="font-size: 11px; color: #cbd5e1; font-style: italic;">
        Link expires in 24 hours.
    </p>
@endsection