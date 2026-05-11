@extends('emails.auth.layouts.master')
@section('title', 'Company Account Suspended')

@section('content')
    <h1 class="hero-title">Account Suspended</h1>
    
    <p class="hero-subtitle">
        Hello {{ $admin->name }}, this is an urgent automated notice. The account for {{ $company->name }} has been temporarily suspended by the platform administration.
    </p>

    <div style="text-align: left; background-color: #fff1f2; border: 1px solid #ffe4e6; border-radius: 12px; padding: 24px; margin-bottom: 30px;">
        <p style="margin-top: 0; font-weight: 700; color: #9f1239; font-size: 15px;">
            As a result of this suspension:
        </p>
        <ul style="color: #be123c; font-size: 14px; line-height: 1.6; margin-bottom: 0; padding-left: 20px;">
            <li>All active sessions for your team members have been securely terminated.</li>
            <li>API access, including external portal syncs, has been paused.</li>
            <li>Your team will not be able to log in until the account is reactivated.</li>
        </ul>
    </div>

    <div class="company-badge">
        <span style="display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 800; margin-bottom: 4px;">Platform Admin Contact</span>
        <span class="company-name" style="text-transform: lowercase;">{{ $superAdmin->email }}</span>
    </div>

    <div style="margin-bottom: 30px;">
        <a href="mailto:{{ $superAdmin->email }}?subject=Urgent:%20Account%20Suspension%20-%20{{ urlencode($company->name) }}" class="cta-button">
            Contact Administration
        </a>
    </div>

    <p style="font-size: 11px; color: #cbd5e1; font-style: italic;">
        If you believe this suspension is an error, or to resolve any outstanding billing or compliance issues, please contact the platform administration immediately. We are here to help you get your team back online.
    </p>
@endsection