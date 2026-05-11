@extends('emails.auth.layouts.master')
@section('title', 'Company Account Reactivated')

@section('content')
    <h1 class="hero-title">Account Reactivated</h1>
    
    <p class="hero-subtitle">
        Hello {{ $admin->name }}, great news! The account for {{ $company->name }} has been successfully reactivated by the platform administration.
    </p>

    <div style="text-align: left; background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 24px; margin-bottom: 30px;">
        <p style="margin-top: 0; font-weight: 700; color: #166534; font-size: 15px;">
            Service Fully Restored:
        </p>
        <ul style="color: #15803d; font-size: 14px; line-height: 1.6; margin-bottom: 0; padding-left: 20px;">
            <li>Your team members can now securely log in to the dashboard.</li>
            <li>API access and external portal syncs are fully active.</li>
            <li>All platform features are restored.</li>
        </ul>
    </div>

    <div class="company-badge">
        <span style="display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 800; margin-bottom: 4px;">Platform Admin Contact</span>
        <span class="company-name" style="text-transform: lowercase;">{{ $superAdmin->email }}</span>
    </div>

    <div style="margin-bottom: 30px;">
        <a href="{{ config('app.frontend_url') }}/login" class="cta-button">
            Go to Dashboard
        </a>
    </div>

    <p style="font-size: 11px; color: #cbd5e1; font-style: italic;">
        Welcome back! We are thrilled to continue supporting your business. If you have any further questions, please reach out to the platform administration.
    </p>
@endsection
