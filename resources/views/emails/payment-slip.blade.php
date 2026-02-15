<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uplatnica</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            padding: 20px 0;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-row {
            margin: 10px 0;
        }
        .info-label {
            font-weight: 600;
            display: inline-block;
            min-width: 120px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; color: #212529;">Uplatnica</h1>
    </div>

    <div class="content">
        <p>Poštovani/na <strong>{{ $memberName }}</strong>,</p>

        <p>U prilogu Vam šaljemo uplatnicu za račun:</p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Broj računa:</span>
                <span>{{ $referenceCode }}</span>
            </div>
            @if(!empty($workshopName))
            <div class="info-row">
                <span class="info-label">Radionica:</span>
                <span>{{ $workshopName }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Iznos:</span>
                <span><strong>{{ $amount }} EUR</strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Rok plaćanja:</span>
                <span>{{ $dueDate }}</span>
            </div>
        </div>

        <p>Molimo Vas da uplatnicu ispunite i uplatite do datuma roka plaćanja.</p>

        <p>Uplatnica je priložena kao PDF dokument.</p>
    </div>

    <div class="footer">
        <p style="margin: 0;">Lijep pozdrav,<br>
        {{ config('mail.from.name') }}</p>
    </div>
</body>
</html>
