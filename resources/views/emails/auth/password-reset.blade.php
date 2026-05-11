@extends('emails.auth.layouts.master')
@section('title', 'Secure Password Reset')

@section('content')
    <h1 class="hero-title">Reset Your Password</h1>
    <p class="hero-subtitle">
        Hello {{ $user->name }}, we received a request to reset the password for your PropBridge account. 
        Security is our priority.
    </p>

    <div style="margin-bottom: 30px;">
        <a href="{{ $resetUrl }}" class="cta-button">Reset My Password</a>
    </div>

    <p style="font-size: 11px; color: #cbd5e1; font-style: italic;">
        This link is valid for 60 minutes. If you did not request this, no action is required.
    </p>
@endsection