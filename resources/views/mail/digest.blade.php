<x-mail::message>
# {{ $business->name }}: competitor digest

{{ $digest->period_start->toFormattedDateString() }} to {{ $digest->period_end->toFormattedDateString() }}

**{{ $digest->report['headline'] }}**

@foreach ($sections as $section)
## {{ $section['competitor']->name }}

{{ $section['summary'] }}

@foreach ($section['items'] as $item)
- **{{ $item['title'] }}**@if ($item['date']) ({{ $item['date'] }})@endif — {{ $item['detail'] }}@if ($item['url']) [Source]({{ $item['url'] }})@endif

@endforeach
@endforeach

<x-mail::button :url="$url">
Read it in Creeper
</x-mail::button>

@if ($digest->report['notes'])
_{{ $digest->report['notes'] }}_
@endif

You get this {{ strtolower($business->digest_frequency->label()) }}. Change that, or the competitors, from the company's page.
</x-mail::message>
