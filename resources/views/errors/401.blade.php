@php
       $code = 401;
      $title = 'Требуется авторизация';
    $message = 'Для доступа к этой странице нужно войти в аккаунт.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))
