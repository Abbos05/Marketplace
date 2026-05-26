@php
    $code = 403;
    $title = 'Доступ запрещен';
    $message = 'У вас нет прав для просмотра этой страницы. Если это ошибка, обратитесь к администратору.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))