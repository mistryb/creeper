@extends('mail.layout')

@section('preheader'){{ $digest->report['headline'] }}@endsection

@section('content')
<p style="margin: 0 0 16px;">Here's your competitor digest for {{ $business->name }}, {{ $digest->period_start->format('M j') }} to {{ $digest->period_end->format('M j') }}.</p>

<p style="margin: 0 0 32px; font-size: 20px; line-height: 28px; font-weight: 600;">{{ $digest->report['headline'] }}</p>

@foreach ($sections as $section)
<h2 style="margin: 0 0 4px; font-size: 17px; line-height: 26px; font-weight: 600; color: #14201a;">
    {{ $section['competitor']->name }}@if ($section['host'])<span style="font-weight: 400; font-size: 14px; color: #5d6b62;"> · {{ $section['host'] }}</span>@endif
</h2>
<p style="margin: 0 0 12px; color: #3a4a41;">{{ $section['summary'] }}</p>
<ul style="margin: 0 0 32px; padding: 0 0 0 20px; color: #3a4a41;">
    @foreach ($section['items'] as $item)
    <li style="margin: 0 0 8px; padding: 0;"><strong style="color: #14201a; font-weight: 600;">{{ $item['title'] }}</strong>@if ($item['date']) ({{ $item['date'] }})@endif. {{ $item['detail'] }}@if ($item['url'])&nbsp;<a href="{{ $item['url'] }}" style="color: #1c6547;">Source</a>@endif</li>
    @endforeach
</ul>
@endforeach

@if ($digest->report['notes'])
<p style="margin: 0 0 32px; color: #3a4a41;">{{ $digest->report['notes'] }}</p>
@endif

<p style="margin: 0 0 32px;"><a href="{{ $url }}" style="color: #1c6547; font-weight: 600;">Read this digest in Creeper →</a></p>

<p style="margin: 0; color: #3a4a41;">— Creeper</p>
@endsection

@section('footer')
You get this {{ Str::lcfirst($business->digest_frequency->label()) }}. <a href="{{ $settingsUrl }}" style="color: #5d6b62;">Change the schedule or competitors</a>.
@endsection
