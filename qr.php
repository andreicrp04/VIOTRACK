<?php
// qr.php
// Simple QR generator using goqr.me (QRServer) API

$defaultUrl = "https://yourwebsite.com";
$sizeOptions = [200, 300, 500];

$url  = $defaultUrl;
$size = 300;
$err  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = trim($_POST["url"] ?? "");
    $pick  = intval($_POST["size"] ?? 300);

    if (!filter_var($input, FILTER_VALIDATE_URL)) {
        $err = "Please enter a valid URL that starts with http:// or https://";
    } else {
        $url = $input;
    }

    if (!in_array($pick, $sizeOptions, true)) {
        $size = 300;
    } else {
        $size = $pick;
    }
}

$qrApi = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>QR Code Generator</title>
  <style>
    body { font-family: system-ui, Arial, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 16px; }
    .card { border: 1px solid #ddd; border-radius: 12px; padding: 20px; }
    .row { display: grid; gap: 12px; grid-template-columns: 1fr 140px; }
    label { font-weight: 600; }
    input, select, button { padding: 10px; font-size: 16px; }
    .error { color: #b00020; margin: 8px 0; }
    .qr-wrap { text-align: center; margin-top: 16px; }
    .actions { display:flex; gap:10px; justify-content:center; margin-top:12px;}
    .hint { color:#555; font-size: 14px; }
  </style>
</head>
<body>
  <h1>QR Code Generator</h1>

  <div class="card">
    <form method="POST">
      <div class="row">
        <div>
          <label for="url">Website URL</label><br/>
          <input type="url" id="url" name="url" placeholder="https://yourwebsite.com" value="<?php echo htmlspecialchars($url); ?>" required>
          <div class="hint">Include <code>http://</code> or <code>https://</code></div>
        </div>
        <div>
          <label for="size">Size (px)</label><br/>
          <select id="size" name="size">
            <?php foreach ($sizeOptions as $opt): ?>
              <option value="<?php echo $opt; ?>" <?php echo $size === $opt ? "selected" : ""; ?>><?php echo $opt . " x " . $opt; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php if ($err): ?>
        <div class="error"><?php echo htmlspecialchars($err); ?></div>
      <?php endif; ?>

      <div style="margin-top:12px;">
        <button type="submit">Generate</button>
      </div>
    </form>

    <div class="qr-wrap">
      <h2>Preview</h2>
      <img src="<?php echo $qrApi; ?>" alt="QR Code" width="<?php echo $size; ?>" height="<?php echo $size; ?>">
      <div class="actions">
        <a href="<?php echo $qrApi; ?>" download="qr.png">Download PNG</a>
        <a href="<?php echo $qrApi; ?>" target="_blank" rel="noopener">Open in new tab</a>
      </div>
      <div class="hint">Scan with your phone’s camera to test.</div>
    </div>
  </div>
</body>
</html>
