@php
    use App\Support\MailBrandLogo;

    $logoSize = (int) ($size ?? 48);
    $logoPath = MailBrandLogo::path();
@endphp

@if (isset($message) && MailBrandLogo::exists())
    <img
        src="{{ $message->embed($logoPath) }}"
        alt="{{ config('app.name') }}"
        width="{{ $logoSize }}"
        height="{{ $logoSize }}"
        style="display:block;width:{{ $logoSize }}px;height:{{ $logoSize }}px;border-radius:12px;border:0;margin:0 auto;"
    >
@endif
