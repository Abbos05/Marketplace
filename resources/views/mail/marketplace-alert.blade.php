{{ config('app.name') }}

{{ $title }}

{{ $body }}

@if($actionUrl)
Открыть: {{ str_starts_with($actionUrl, 'http') ? $actionUrl : url($actionUrl) }}
@endif

—
Уведомление также в личном кабинете.
