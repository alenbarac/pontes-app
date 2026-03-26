<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 24mm 18mm 26mm 18mm; size: A4; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 12pt;
    line-height: 1.5;
    color: #000;
  }

  .document-container {
    min-height: 250mm;
    position: relative;
    padding: 8mm 8mm 34mm 8mm;
  }

  .header {
    position: relative;
    min-height: 20mm;
    margin-bottom: 10mm;
  }

  .header-logo {
    position: absolute;
    left: 0;
    top: 0;
    width: 34mm;
    max-height: 12mm;
  }

  .header-logo img {
    width: 100%;
    height: auto;
  }

  .header-title {
    text-align: center;
    font-size: 17pt;
    font-weight: bold;
    margin-top: 8mm;
  }

  .content-container {
    padding: 6mm 7mm;
  }

  .content {
    white-space: pre-wrap;
    word-wrap: break-word;
    font-size: 12pt;
    margin-top: 1mm;
    margin-bottom: 26mm;
  }

  .content p {
    margin-bottom: 3mm;
  }

  .sign-row {
    width: 100%;
    margin-top: 16mm;
    margin-bottom: 10mm;
    padding: 0 7mm;
  }

  .city-date {
    float: left;
    width: 55%;
    font-size: 11pt;
    padding-top: 10mm;
  }

  .signature-block {
    float: right;
    width: 45%;
    text-align: left;
    padding-left: 8mm;
  }

  .signature-block img {
    width: 66mm;
    max-height: 35mm;
    object-fit: contain;
  }

  .clearfix {
    clear: both;
  }

  .footer {
    position: absolute;
    left: 8mm;
    right: 8mm;
    bottom: 0;
    text-align: left;
    font-size: 10pt;
    line-height: 1.4;
    border-top: 0.3mm solid #000;
    padding-top: 4mm;
    padding-bottom: 8mm;
  }

  .footer-left {
    float: left;
    width: 55%;
    padding-right: 4mm;
  }

  .footer-right {
    float: right;
    width: 45%;
    text-align: right;
  }
</style>
</head>
<body>
  <div class="document-container">
    <div class="header">
      @if(!empty($logoPath) && file_exists($logoPath))
        <div class="header-logo">
          <img src="file://{{ $logoPath }}" alt="Pontes logo">
        </div>
      @endif
      <div class="header-title">{{ $documentTitle }}</div>
    </div>

    <div class="content-container">
      <div class="content">
        {!! $content !!}
      </div>
    </div>

    <div class="sign-row">
      <div class="city-date">U Rijeci, {{ $todayDate }}</div>
      <div class="signature-block">
        @if(!empty($signaturePath) && file_exists($signaturePath))
          <img src="file://{{ $signaturePath }}" alt="Potpis">
        @endif
      </div>
      <div class="clearfix"></div>
    </div>

    <div class="footer">
      <div class="footer-left">
        <div>{{ $footerRecipientName }}</div>
        <div>{{ $footerRecipientAddress }}, {{ $footerRecipientPostal }}</div>
      </div>
      <div class="footer-right">
        <div>{{ $footerWebUrl }}</div>
        <div>{{ $footerEmail }}</div>
        <div>{{ $footerPhone }}</div>
      </div>
      <div class="clearfix"></div>
    </div>
  </div>
</body>
</html>
