@php
     = 401;
     = 'Требуется авторизация';
     = 'Для доступа к этой странице нужно войти в аккаунт.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))
