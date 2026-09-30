<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['success' => false, 'error' => 'send']);
if (!empty($_POST['website'] ?? '')) respond(200, ['success' => true]);

$limits = ['company'=>160,'name'=>120,'email'=>200,'phone'=>80,'product'=>180,'material'=>100,'dimensions'=>220,'quantity'=>120,'message'=>4000,'locale'=>8];
$data = [];
foreach ($limits as $key => $limit) {
    $value = trim((string)($_POST[$key] ?? ''));
    $data[$key] = mb_substr($value, 0, $limit, 'UTF-8');
}
$missing = [];
foreach (['company','name','email'] as $field) if ($data[$field] === '') $missing[] = $field;
if (empty($_POST['consent'])) $missing[] = 'consent';
if ($missing) respond(422, ['success'=>false,'error'=>'required','missing'=>$missing]);
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) respond(422, ['success'=>false,'error'=>'email','missing'=>['email']]);

$rateFile = sys_get_temp_dir() . '/silvotech-form-' . hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$now = time(); $hits = [];
$handle = @fopen($rateFile, 'c+');
if ($handle && flock($handle, LOCK_EX)) {
    $stored = stream_get_contents($handle);
    $hits = array_values(array_filter(array_map('intval', explode(',', (string)$stored)), fn($t) => $now - $t < 600));
    if (count($hits) >= 5) { flock($handle, LOCK_UN); fclose($handle); respond(429, ['success'=>false,'error'=>'send']); }
    $hits[] = $now; ftruncate($handle, 0); rewind($handle); fwrite($handle, implode(',', $hits)); fflush($handle); flock($handle, LOCK_UN);
}
if ($handle) fclose($handle);

$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
$attachments = [];
$finfo = new finfo(FILEINFO_MIME_TYPE);
foreach (['photo','drawing'] as $field) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) continue;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 4 * 1024 * 1024) respond(422, ['success'=>false,'error'=>'file']);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) respond(422, ['success'=>false,'error'=>'file']);
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$file['name'])) ?: ($field . '.' . $allowed[$mime]);
    $attachments[] = ['name'=>substr($name, -120),'type'=>$mime,'content'=>file_get_contents($file['tmp_name'])];
}

$labels = ['company'=>'Company','name'=>'Contact person','email'=>'E-mail','phone'=>'Phone','product'=>'Product','material'=>'Material','dimensions'=>'Dimensions','quantity'=>'Quantity','message'=>'Additional information','locale'=>'Language'];
$lines = [];
foreach ($labels as $key=>$label) if ($data[$key] !== '') $lines[] = $label . ': ' . $data[$key];
$text = implode("\r\n", $lines);
$boundary = '=_SilvoTech_' . bin2hex(random_bytes(12));
$body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$text}\r\n";
foreach ($attachments as $attachment) {
    $body .= "--{$boundary}\r\nContent-Type: {$attachment['type']}; name=\"{$attachment['name']}\"\r\nContent-Disposition: attachment; filename=\"{$attachment['name']}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($attachment['content'])) . "\r\n";
}
$body .= "--{$boundary}--\r\n";
$cleanName = preg_replace('/[\r\n]+/', ' ', $data['name']);
$cleanEmail = preg_replace('/[\r\n]+/', '', $data['email']);
$cleanCompany = preg_replace('/[\r\n]+/', ' ', $data['company']);
$subject = '=?UTF-8?B?' . base64_encode('Manufacturing enquiry: ' . $cleanCompany) . '?=';
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    'From: SilvoTech <sales@silvotech.eu>',
    'Reply-To: ' . $cleanName . ' <' . $cleanEmail . '>',
    'X-Mailer: SilvoTech Website'
];
$sent = @mail('sales@silvotech.eu', $subject, $body, implode("\r\n", $headers), '-fsales@silvotech.eu');
if (!$sent) respond(500, ['success'=>false,'error'=>'send']);
respond(200, ['success'=>true]);
