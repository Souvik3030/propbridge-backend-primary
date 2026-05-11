@extends('emails.auth.layouts.master')
@section('title', 'Account Reactivated')

@section('content')
    <h1 class="hero-title">Account Reactivated</h1>
    <p class="hero-subtitle">
        Hello {{ $targetUser->name }}, your account has been successfully reactivated by your organization's administrator. 
        You can now log in and securely access the platform.
    </p>

    <div class="company-badge">
        <span style="display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 800; margin-bottom: 4px;">Reactivated By</span>
        <span class="company-name">{{ $adminUser->name }}</span>
        <span style="display: block; margin-top: 4px; font-size: 12px; color: #64748b; font-weight: 500;">{{ $adminUser->email }}</span>
    </div>

    <div style="margin-bottom: 30px;">
        <a href="{{ config('app.frontend_url') }}/login" class="cta-button">Go to Dashboard</a>
    </div>

    <p style="font-size: 11px; color: #cbd5e1; font-style: italic;">
        Welcome back! If you experience any issues logging in, please contact your administrator using the details above.
    </p>
@endsection
