<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:600px;background-color:#ffffff;border-radius:8px;">
                    <tr>
                        <td style="padding:28px 32px 8px;font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:1.3;font-weight:700;color:#212529;">
                            {{ $title }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 28px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#333333;">
                            <p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#333333;">{{ $greeting }}</p>
                            <p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#333333;">{{ $intro }}</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;background-color:#f8f9fa;border-left:4px solid #007bff;">
                                <tr>
                                    <td style="padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#333333;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                            <tr>
                                                <td colspan="2" style="padding:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;font-weight:600;color:#333333;">{{ $membershipLine }}</td>
                                            </tr>
                                            @foreach($facts as $fact)
                                            <tr>
                                                <td valign="top" style="padding:0 16px {{ $loop->last ? '0' : '8px' }} 0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;font-weight:600;color:#333333;white-space:nowrap;">{{ $fact['label'] }}:</td>
                                                <td valign="top" style="padding:0 0 {{ $loop->last ? '0' : '8px' }} 0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#333333;">{{ $fact['value'] }}</td>
                                            </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;padding-top:16px;border-top:1px solid #dee2e6;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#495057;">
                                {{ $signOff }}@if($studioName !== '')<br>{{ $studioName }}@endif
                                @foreach($contacts as $contact)
                                <br><a href="{{ $contact['href'] }}" style="color:#0b5ed7;text-decoration:underline;">{{ $contact['label'] }}</a>
                                @endforeach
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
