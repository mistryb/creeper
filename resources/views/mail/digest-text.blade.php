Here's your competitor digest for {{ $business->name }}, {{ $digest->period_start->format('M j') }} to {{ $digest->period_end->format('M j') }}.

{{ $digest->report['headline'] }}

@foreach ($sections as $section)
{{ $section['competitor']->name }}@if ($section['host']) · {{ $section['host'] }}@endif

{{ $section['summary'] }}

@foreach ($section['items'] as $item)
- {{ $item['title'] }}@if ($item['date']) ({{ $item['date'] }})@endif. {{ $item['detail'] }}@if ($item['url'])

  Source: {{ $item['url'] }}@endif

@endforeach

@endforeach
@if ($digest->report['notes'])
{{ $digest->report['notes'] }}

@endif
Read this digest in Creeper: {{ $url }}

— Creeper

--
You get this {{ Str::lcfirst($business->digest_frequency->label()) }}. Change the schedule or competitors: {{ $settingsUrl }}
