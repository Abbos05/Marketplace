@php
     = 419;
     = 'Сессия истекла';
     = 'Сессия безопасности завершена. Обновите страницу и повторите действие.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))
