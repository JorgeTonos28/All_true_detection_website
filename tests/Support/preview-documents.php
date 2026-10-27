<?php

// Disposable, obviously synthetic image fixtures for local browser QA. No personal information.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'atd-document-qa-'.bin2hex(random_bytes(6));
mkdir($directory, 0700);
$files = [];
foreach (['identity_front', 'identity_back', 'degree_document', 'exequatur_document', 'selfie'] as $index => $purpose) {
    $image = imagecreatetruecolor(800, 500);
    $background = imagecolorallocate($image, 225 + $index * 4, 235, 240 - $index * 5);
    $ink = imagecolorallocate($image, 15, 42, 67);
    imagefill($image, 0, 0, $background);
    imagestring($image, 5, 80, 160, 'DOCUMENTO FICTICIO - SOLO PRUEBAS', $ink);
    imagestring($image, 5, 80, 220, $purpose, $ink);
    $files[$purpose] = $directory.DIRECTORY_SEPARATOR.$purpose.'.png';
    imagepng($image, $files[$purpose]);
    imagedestroy($image);
}
echo json_encode(['directory' => $directory, 'files' => $files], JSON_THROW_ON_ERROR).PHP_EOL;
