<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 10mm; }
  body { margin: 0; font-family: DejaVu Sans, sans-serif; }
  .canvas {
    position: relative;
    width: 190mm;   /* A4 width minus margins */
    height: 80mm;   /* Slip area height; adjust as needed */
    border: 1px solid #fff;
  }
  .bg {
    position: absolute; left: 0; top: 0;
    width: 190mm; height: 80mm;
    object-fit: cover;
    opacity: 1;
  }
  .f { position: absolute; font-size: 3.4mm; line-height: 1.2; color: #000; }

  /* Approximate placements – tweak to align with your template */
  /* Top row */
  .valuta       { top: 7mm;  left: 150mm; width: 20mm; text-align: left; }
  .iznos        { top: 7mm;  left: 170mm; width: 18mm; text-align: right; }

  /* Platitelj (payer) block - left side */
  .platitelj_ime     { top: 15mm; left: 8mm;  width: 90mm; }
  .platitelj_adresa  { top: 21mm; left: 8mm;  width: 90mm; }

  /* Poziv na broj platitelja (optional—often empty) */
  .poziv_platitelja  { top: 28mm; left: 55mm; width: 43mm; }

  /* Primatelj (recipient) block - left middle */
  .primatelj_ime     { top: 35mm; left: 8mm;  width: 90mm; }
  .primatelj_adresa  { top: 41mm; left: 8mm;  width: 90mm; }

  /* IBAN primatelja */
  .iban_primatelja   { top: 35mm; left: 106mm; width: 80mm; letter-spacing: 0.5mm; }

  /* Model i poziv na broj primatelja */
  .model             { top: 41mm; left: 106mm; width: 14mm; text-align: center; }
  .poziv_primatelja  { top: 41mm; left: 122mm; width: 64mm; }

  /* Opis plaćanja */
  .opis              { top: 49mm; left: 8mm;  width: 178mm; }

  /* Datum izvršenja (we’ll use due date) */
  .datum             { top: 56mm; left: 28mm; width: 26mm; text-align: center; }

  /* Right column mirror (Valuta i iznos, IBANs, opis) – optional to fill; left side is usually enough */
</style>
</head>
<body>
  <div class="canvas">
    <img class="bg" src="file://{{ $bgPath }}" alt="uplatnica">
    {{-- Currency + Amount --}}
    <div class="f valuta">{{ $currency }}</div>
    <div class="f iznos">{{ $amount }}</div>

    {{-- Payer (Platitelj) --}}
    <div class="f platitelj_ime">{{ $member_name }}</div>
    @if(!empty($member_address))
    <div class="f platitelj_adresa">{{ $member_address }}</div>
    @endif

    {{-- Recipient (Primatelj) --}}
    <div class="f primatelj_ime">{{ $recipient_name }}</div>
    <div class="f primatelj_adresa">{{ $recipient_address }}</div>

    {{-- Recipient IBAN --}}
    <div class="f iban_primatelja">{{ $recipient_iban }}</div>

    {{-- Model + Poziv na broj primatelja --}}
    <div class="f model">{{ $model }}</div>
    <div class="f poziv_primatelja">{{ $reference }}</div>

    {{-- Opis plaćanja --}}
    <div class="f opis">{{ $description }}</div>

    {{-- Datum izvršenja (use due date) --}}
    <div class="f datum">{{ $due_date }}</div>
  </div>
</body>
</html>
