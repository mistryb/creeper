@extends('mail.layout')

@section('preheader')Your code is {{ $code }}. It expires in {{ $minutes }} minutes.@endsection

@section('content')
<p style="margin: 0 0 16px;">Your Creeper sign-in code is:</p>

{{-- Read off one screen and typed on another, so it is big and split in two.
     The gap is a margin, not a space, so copying it pastes six clean digits. --}}
<p style="margin: 0 0 24px; font-family: 'SFMono-Regular', Menlo, Consolas, 'Liberation Mono', monospace; font-size: 36px; line-height: 44px; font-weight: 600; letter-spacing: 4px; color: #14201a;">{{ substr($code, 0, 3) }}<span style="margin-left: 14px;">{{ substr($code, 3) }}</span></p>

<p style="margin: 0 0 16px; color: #3a4a41;">It expires in {{ $minutes }} minutes and works once.</p>

<p style="margin: 0 0 32px; color: #3a4a41;">Didn't ask to sign in? You can ignore this email. Nobody gets in without the code.</p>

<p style="margin: 0; color: #3a4a41;">— Creeper</p>
@endsection
