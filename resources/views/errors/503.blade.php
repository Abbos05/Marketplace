@php
      $code  = 503;
     $title  = 'Сервис временно недоступен';
   $message  = 'Сайт на техническом обслуживании. Скоро все снова заработает.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))
