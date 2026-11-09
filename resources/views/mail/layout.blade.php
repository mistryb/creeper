{{--
    The shell every Creeper email is written in: a plain note, not a newsletter.
    White, one column, the reader's own system font, no card and no images, so
    it reads the same in every client and looks like mail from a person.

    Spacing runs on an 8px rhythm: 16px text on a 26px line, 16px between
    paragraphs, 32px before a new section, 40px above the footer. Every rule is
    inline because Gmail and Outlook ignore most of a <style> block; the one
    media query only tightens the side margins on a phone.

    Sections: `preheader` (the line an inbox shows after the subject),
    `content`, and optionally `footer`.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ config('app.name') }}</title>
    <style>
        @media only screen and (max-width: 600px) {
            .shell { padding: 24px 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #ffffff; -webkit-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff;">
        <tr>
            <td class="shell" align="left" style="padding: 40px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px;">
                    <tr>
                        <td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 16px; line-height: 26px; color: #14201a;">
                            @yield('content')

                            @hasSection('footer')
                                <div style="margin-top: 40px; padding-top: 16px; border-top: 1px solid #e3e5dc; font-size: 13px; line-height: 20px; color: #5d6b62;">
                                    @yield('footer')
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
