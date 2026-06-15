@php
       $code = 429;
      $title = 'Слишком много запросов';
    $message = 'Вы отправляете запросы слишком часто. Подождите немного и попробуйте снова.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))
