@php
       $code = 500;
      $title = 'Внутренняя ошибка сервера';
    $message = 'Мы уже разбираемся с проблемой. Попробуйте обновить страницу через несколько минут.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))
