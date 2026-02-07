<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 20mm; size: A4; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { 
    margin: 0; 
    padding: 0;
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif; 
    font-size: 12pt;
    line-height: 1.6;
    color: #000;
  }
  .document-container {
    width: 100%;
    max-width: 100%;
  }
  .content {
    white-space: pre-wrap;
    word-wrap: break-word;
  }
  h1, h2, h3 {
    margin-top: 20px;
    margin-bottom: 10px;
  }
  p {
    margin-bottom: 10px;
  }
</style>
</head>
<body>
  <div class="document-container">
    <div class="content">
      {!! $content !!}
    </div>
  </div>
</body>
</html>
