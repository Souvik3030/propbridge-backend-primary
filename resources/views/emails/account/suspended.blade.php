@extends('emails.auth.layouts.master')
@section('title', 'Account Deactivated')

@section('content')
    <h1 class="hero-title">Account Access Revoked</h1>
    <p class="hero-subtitle">
        Hello {{ $targetUser->name }}, your account has been deactivated by your organization's administrator. 
        Your current sessions have been terminated and platform access restricted.
    </p>

    <div class="company-badge">
        <span style="display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 800; margin-bottom: 4px;">Deactivated By</span>
        <span class="company-name">{{ $adminUser->name }}</span>
        <span style="display: block; margin-top: 4px; font-size: 12px; color: #64748b; font-weight: 500;">{{ $adminUser->email }}</span>
    </div>

    <div style="margin-bottom: 30px;">
        <a href="mailto:{{ $adminUser->email }}?subject=PropBridge%20Account%20Access" class="cta-button">Contact Administrator</a>
    </div>

    <p style="font-size: 11px; color: #cbd5e1; font-style: italic;">
        If you believe this is an error, please reach out directly to your administrator using the button above. Do not contact PropBridge support.
    </p>
@endsection