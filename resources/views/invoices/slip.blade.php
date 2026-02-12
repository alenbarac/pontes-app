<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 2mm; size: A4; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { 
    margin: 0; 
    padding: 0;
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif; 
    font-size: 12pt;
  }
  .canvas {
    position: relative;
    width: 210mm;   /* A4 width */
    height: 80mm;   /* Slip area height; adjust as needed */
    border: 1px solid #fff;
    page-break-after: avoid; /* Prevent page breaks */
    page-break-inside: avoid;
  }
  .bg {
    position: absolute; left: 0; top: 0;
    width: 210mm; height: 80mm;
    object-fit: cover;
    opacity: 1;
  }
  .f { 
    position: absolute; 
    font-size: 3.7mm; 
    line-height: 1.2; 
    color: #000;
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
  }

  /* Approximate placements – tweak to align with your template */
  /* Top row */
  .valuta       { top: 6mm;  left: 76mm; width: 20mm; text-align: left; }
  .valuta.right { top: 7mm;  left: 152mm; width: 20mm; text-align: left; font-size: 2.5mm; }
  .iznos        { top: 5.5mm;  left: 131mm;  text-align: left;  font-size: 4mm; letter-spacing: 0.5mm; }
  .iznos.big-amount { left: 128mm; font-size: 4mm; letter-spacing: 0.5mm; top: 5.5mm; }
  .iznos.right { top: 7mm;  left: 159mm; width: 18mm; text-align: left; font-size: 2.5mm; letter-spacing: 0mm; }

  /* Platitelj (payer) block - left side */
  .platitelj_ime     { top: 10mm; left: 7mm;  width: 90mm; font-size: 2.7mm; }
  .platitelj_adresa  { top: 14mm; left: 7mm;  width: 90mm; font-size: 2.7mm; }

  /* Poziv na broj platitelja (optional—often empty) */
  .poziv_platitelja  { top: 28mm; left: 55mm; width: 43mm; }

  /* Primatelj (recipient) block - left middle */
  .primatelj_ime     { top: 32mm; left: 7mm;  width: 90mm; font-size: 2.7mm; }
  .primatelj_adresa  { top: 36mm; left: 7mm;  width: 90mm; font-size: 2.7mm; }
  .primatelj_postal  { top: 40mm; left: 7mm;  width: 90mm; font-size: 2.7mm; }

  /* IBAN primatelja */
  .iban_primatelja   { top: 23mm; left: 76mm; width: 80mm; letter-spacing: 0.7mm; font-size: 3.7mm; }
  .iban_primatelja.right   { top: 25mm; left: 151mm; font-size: 2.5mm; letter-spacing: 0mm; }

  /* Model i poziv na broj primatelja */
  .model             { top: 30mm; left: 52mm; width: 14mm; text-align: center; letter-spacing: 0.7mm; }
  .model.right       { top: 32mm; left: 148mm; width: 14mm; text-align: center; font-size: 2.5mm; letter-spacing: 0mm; }
  .poziv_primatelja  { top: 30.5mm; left: 73mm; width: 64mm; letter-spacing: 0.5mm; font-size: 3.5mm; }
  .poziv_primatelja.right  { top: 32mm; left: 160mm; font-size: 2.5mm; letter-spacing: 0mm; }

  /* Opis plaćanja - two lines: member name on top, notes below */
  .opis_ime         { top: 39mm; left: 85mm;  width: 178mm; font-size: 3.2mm; }
  .opis_ime.right   { top: 40mm; left: 152mm; font-size: 2.5mm; }
  .opis_napomena    { top: 43mm; left: 85mm;  width: 178mm; font-size: 3.2mm; }
  .opis_napomena.right { top: 44mm; left: 152mm; font-size: 2.5mm; }

  /* Datum izvršenja (we'll use due date) */
  .datum             { top: 56mm; left: 28mm; width: 26mm; text-align: center; }

  /* PDF417 Barcode (2D barcode for HUB3A - Croatian payment slip standard) */
  /* HUB3A requirements: max 58mm x 26mm, 1mm from left, 1mm from top */
  .barcode { position: absolute; top: 52mm; left: 9mm; width: 53mm; height: 22mm; z-index: 10; }
  .barcode img { 
    width: 100%; 
    height: 100%; 
    display: block; 
    object-fit: contain;
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
  }

  /* Right column mirror (Valuta i iznos, IBANs, opis) – optional to fill; left side is usually enough */
</style>
</head>
<body>
  <div class="canvas">
    <img class="bg" src="file://{{ $bgPath }}" alt="uplatnica">
    {{-- Currency + Amount --}}
    @php
        // Convert HR format (1.234,56) to float for comparison
        $amountValue = (float) str_replace(['.', ','], ['', '.'], $amount);
        $hasBigAmount = $amountValue > 60;
    @endphp
    <div class="f iznos{{ $hasBigAmount ? ' big-amount' : '' }}">{{ $amount }}</div>
    <div class="f right iznos{{ $hasBigAmount ? ' big-amount' : '' }}">{{ $amount }}</div>
    <div class="f valuta">{{ $currency }}</div>
    <div class="f right valuta">{{ $currency }}</div>
    

    {{-- Payer (Platitelj) --}}
    <div class="f platitelj_ime">{{ $member_name }}</div>
    @if(!empty($member_address))
    <div class="f platitelj_adresa">{{ $member_address }}</div>
    @endif

    {{-- Recipient (Primatelj) --}}
    <div class="f primatelj_ime">{{ $recipient_name }}</div>
    <div class="f primatelj_adresa">{{ $recipient_address }}</div>
    <div class="f primatelj_postal">{{ $recipient_postal }}</div>

    {{-- Recipient IBAN --}}
    <div class="f iban_primatelja">{{ $recipient_iban }}</div>
    <div class="f right iban_primatelja">{{ $recipient_iban }}</div>

    {{-- Model + Poziv na broj primatelja --}}
    <div class="f model">{{ $model }}</div>
    <div class="f right model">{{ $model }}</div>
    <div class="f poziv_primatelja">{{ $reference }}</div>
    <div class="f right poziv_primatelja">{{ $reference }}</div>

    {{-- Opis plaćanja - Member name on top row, notes on second row --}}
    <div class="f opis_ime">{{ $member_name_for_description }}</div>
    <div class="f opis_ime right">{{ $member_name_for_description }}</div>
    <div class="f opis_napomena">{{ $payment_notes }}</div>
    <div class="f opis_napomena right">{{ $payment_notes }}</div>

    {{-- PDF417 Barcode (2D barcode for HUB3 format - Croatian payment slip standard) --}}
    @if(isset($barcode_path) && !empty($barcode_path) && file_exists($barcode_path))
    <div class="barcode">
      <img src="file://{{ $barcode_path }}" alt="PDF417 Barcode" />
    </div>
    @endif

    {{-- Datum izvršenja (use due date) --}}
    {{-- <div class="f datum">{{ $due_date }}</div> --}}
  </div>
</body>
</html>
