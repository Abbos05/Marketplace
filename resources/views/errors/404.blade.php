@php
    $code = 404;
    $title = 'Страница не найдена';
    $message = 'Похоже, ссылка устарела или введен неверный адрес. Проверьте URL или вернитесь на главную.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))