{{ $title }}

{{ $body }}

@if($actionUrl)
Ссылка: {{ str_starts_with($actionUrl, 'http') ? $actionUrl : url($actionUrl) }}
@endif

—
{{ config('app.name') }}
