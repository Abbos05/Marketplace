@php
    $appName = config('app.name', 'Marketplace');
    $brand = '#FF2E63';
    $brandDark = '#d30035';
    $bg = '#F5F7FA';
    $card = '#ffffff';
    $text = '#0f172a';
    $muted = '#64748b';
    $border = '#e2e8f0';
    $preheaderText = trim($__env->yieldContent('preheader'));
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', $appName)</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        a { color: {{ $brand }}; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .email-pad { padding-left: 16px !important; padding-right: 16px !important; }
            .email-code { font-size: 32px !important; letter-spacing: 8px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:{{ $bg }};font-family:'Montserrat',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
@if($preheaderText !== '')
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
        {{ $preheaderText }}
    </div>
@endif
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ $bg }};">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" class="email-container" width="560" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;width:100%;">
                <tr>
                    <td align="center" style="padding-bottom:20px;">
                        <span style="display:inline-block;font-size:22px;font-weight:700;color:{{ $brand }};letter-spacing:-0.02em;">{{ $appName }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:{{ $card }};border-radius:16px;border:1px solid {{ $border }};overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.06);">
                            <tr>
                                <td style="height:4px;background:linear-gradient(90deg,{{ $brand }},{{ $brandDark }});font-size:0;line-height:0;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td class="email-pad" style="padding:32px 36px 28px;">
                                    @yield('content')
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:24px 12px 8px;font-size:12px;line-height:1.5;color:{{ $muted }};">
                        @hasSection('footer')
                            @yield('footer')
                        @else
                            Письмо отправлено автоматически. Отвечать на него не нужно.
                        @endif
                        <br>
                        <span style="color:#94a3b8;">© {{ date('Y') }} {{ $appName }}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
