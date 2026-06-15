@php
      $code  = 419;
     $title  = 'Сессия истекла';
   $message  = 'Сессия безопасности завершена. Обновите страницу и повторите действие.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))
