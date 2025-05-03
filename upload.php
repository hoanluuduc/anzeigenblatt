<?php
$accessToken = 'sl.u.AFtxdiLGTkbRZvyPXsOagxA1JPRVruQiTIdKwXk4HlewsTllNNWIYcnG8Kjw7dTOmu9yU2Ss8qpCXVpOmmYIxdOoS1K0CCbXQzuu7bmdmjQ-8ol7Q7-Il_A6RA7eXD7w0q9QCQ98Cq0HePDrPjY04z0_BVv8cKd-zfOIHMZLHgf3GlvzdecA46Wxbs2comjYKLXzb5iw_9WSNkENV7VaUmgZb1gkKmLR7V4k75g_rz9LnGn3hp2vrTOQFn0dZPdWKRQ93T3e5m9YL4MheLqkZnAYbwx0ZkluBTwa_Q9NbqzkoIixkw8NDcL8FgbTKWG28Xt01t_CAhVYoOJbrLI2LadLXQfb6vPFzN6KjhxFyz2iuENmPM5wCMfL-3dBYgSaqHflPQdUEmoKcDKz21drf3z0h48LBzrxkGgcA5MAndag7FBfqDzqBR4RHlsI-npwR4Xq7IQdfS_D5ve8O7Vqo1ed2jfHtYltdOi5gEf40yColAYn55uk6jhz3HKejXUVOE9mkNrSoh5j9cxWagW-1ih5yi0LNwMObwUje9mnHkUM_s_PrYQ12xUcIPo-WiKeMPOEj6c6MxFCNVxpGfOrRR-SLdhp1pXHlWdrLnEef089MjMC7gjw0haJgQ5vTVPO3N2JdaUF-_EvM4QeEcJ3ak1gLafUy-jOyKd8IKtkWkx3dtGQL5x3efwuI7HXpQRCRMyFJo7vEpOEuxgDh5VWL0KKZ2G9JRZyHL6Zqg77QkEAzQoum-5rTxSvlwvW3cpIrxsfL4iRZK7OAtaShAo0FZqJN1mQwufxUexVY7yEtoFrKRqtNAIRNDW3xWgPXObuJ562etzCTIotb93ay-eAHaVVTL-01hP3NbhRSkmQupMu2Z3v7D8vx7RSel6AFn0ORE8eEl9PpPIaqMpVkxm0LntpsY9oJ-HJqmeogsYcOfcBVumI-XOz9i9Pp2UhFJTaJSrkrLVdjOgYOYFgIjHRmlW4PggB57bq4V2uGOQMS8aKeoUw47HBTfDh6PIe4KlR2Xxtw7IDNtL76A5NYQwU92zJGleVY4R7p1YZpxl7ugLoyx2sb0OXmFssUsjwvD6Jl-pFBRBVprzOYxZLItKdwG6o9MVQDbX7q2wIYRBGoHhz-G7Yc-UAHgiDH5IcgVERo6oOcyErSVSeb5KlNQe2X35r1sTwO6vJjk9bYDsjnZLAsOTGr75PePRzlokF7YA4OXW4dUFJWnCYNzPIX4gK4BstwRf6CpNclGPqcZe6Oivooy8lGiCZcKiRfLAmI8kB-7MRPtdyQiTS0TAX3s0e2jf8';
$jsonPath = 'menu.json';
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  die('❌ Fehler beim Hochladen.');
}

$restaurant = $_POST['restaurant'] ?? '';
$kwRaw = $_POST['kw'] ?? '';
$tmpName = $_FILES['image']['tmp_name'];
$mimeType = mime_content_type($tmpName);
$extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);

if (!in_array($mimeType, $allowedTypes)) {
  die('❌ Ungültiges Dateiformat.');
}

try {
  $date = new DateTime($kwRaw . '-1');
  $year = $date->format('Y');
  $week = $date->format('W');
  $kalenderwoche = $year . 'W' . $week;
} catch (Exception $e) {
  die('❌ Ungültige Kalenderwoche.');
}

$filename = "{$kalenderwoche}_{$restaurant}_1.{$extension}";
$dropboxPath = "/Mittagstisch/{$restaurant}/{$filename}";
$fileContents = file_get_contents($tmpName);

// Upload zur Dropbox
$upload = curl_init('https://content.dropboxapi.com/2/files/upload');
curl_setopt($upload, CURLOPT_POST, true);
curl_setopt($upload, CURLOPT_HTTPHEADER, [
  'Authorization: Bearer ' . $accessToken,
  'Content-Type: application/octet-stream',
  'Dropbox-API-Arg: ' . json_encode([
    'path' => $dropboxPath,
    'mode' => 'overwrite'
  ])
]);
curl_setopt($upload, CURLOPT_POSTFIELDS, $fileContents);
curl_setopt($upload, CURLOPT_RETURNTRANSFER, true);
curl_exec($upload);
curl_close($upload);

// Freigabelink prüfen oder erstellen
$list = curl_init('https://api.dropboxapi.com/2/sharing/list_shared_links');
curl_setopt($list, CURLOPT_POST, true);
curl_setopt($list, CURLOPT_HTTPHEADER, [
  'Authorization: Bearer ' . $accessToken,
  'Content-Type: application/json'
]);
curl_setopt($list, CURLOPT_POSTFIELDS, json_encode([
  'path' => $dropboxPath,
  'direct_only' => true
]));
curl_setopt($list, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($list);
curl_close($list);

$data = json_decode($response, true);

if (isset($data['links'][0]['url'])) {
  $publicUrl = $data['links'][0]['url'];
} else {
  $create = curl_init('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings');
  curl_setopt($create, CURLOPT_POST, true);
  curl_setopt($create, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
  ]);
  curl_setopt($create, CURLOPT_POSTFIELDS, json_encode([
    'path' => $dropboxPath
  ]));
  curl_setopt($create, CURLOPT_RETURNTRANSFER, true);
  $createResponse = curl_exec($create);
  curl_close($create);
  $createData = json_decode($createResponse, true);

  if (!isset($createData['url'])) {
    die('❌ Freigabelink konnte nicht erstellt werden.');
  }

  $publicUrl = $createData['url'];
}

// GPT-kompatiblen Link generieren
$publicUrl = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $publicUrl);
if (strpos($publicUrl, '?') !== false) {
  $publicUrl = preg_replace('/([?&])dl=0/', '$1raw=1', $publicUrl);
} else {
  $publicUrl .= '?raw=1';
}

// menu.json aktualisieren
$menu = file_exists($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : [];
$menu[$kalenderwoche][$restaurant] = $publicUrl;
file_put_contents($jsonPath, json_encode($menu, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "✅ Upload erfolgreich!<br>";
echo "🔗 Bild-URL: <a href='$publicUrl' target='_blank'>$publicUrl</a><br>";
echo "📁 menu.json wurde aktualisiert.";
?>
