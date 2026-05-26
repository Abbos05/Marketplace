@php
     = 503;
     = 'Сервис временно недоступен';
     = 'Сайт на техническом обслуживании. Скоро все снова заработает.';
@endphp

@include('errors.modern', compact('code', 'title', 'message'))
