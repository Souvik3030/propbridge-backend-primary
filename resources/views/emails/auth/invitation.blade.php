@extends('emails.auth.layouts.master')
@section('title', 'Administrator Invitation')

@section('content')
    <h1 class="hero-title">Elevate Your Enterprise</h1>
    <p class="hero-subtitle">
        You have been invited to join the administrative board of our project ecosystem. 
        Gain exclusive access to entity management and analytics tools.
    </p>
    
    <div class="company-badge">
        <span style="display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 800; margin-bottom: 4px;">Partner Entity</span>
        <span class="company-name">{{ $companyName }}</span>
    </div>

    <div style="margin-bottom: 30px;">
        <a href="{{ $inviteUrl }}" class="cta-button">Accept Invitation</a>
    </div>

    <p style="font-size: 11px; color: #cbd5e1; font-style: italic;">
        This link will expire in 24 hours. If you did not expect this, please ignore.
    </p>
@endsection