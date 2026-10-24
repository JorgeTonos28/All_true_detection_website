<?php

// Mechanical PNG -> WebP optimization only. Artwork is generated with the built-in image tool.
if (PHP_SAPI !== 'cli' || ! function_exists('imagewebp')) {
    throw new RuntimeException('CLI con GD/WebP requerido.');
}
$source = $argv[1] ?? throw new InvalidArgumentException('Indique el directorio de PNG originales.');
$target = __DIR__.'/../public/assets/images/art';
if (! is_dir($target)) {
    mkdir($target, 0755, true);
}
$files = [
    'exec-aaec0f5f-575f-427a-a693-2bd02af80c15.png' => ['hero-lattice-v1.webp', 1536],
    'exec-82bd8e5e-de93-494f-92f1-de813cb72141.png' => ['services-evidence-v1.webp', 1100],
    'exec-6a6893f0-36f8-406a-a1f4-2c4b1f2e6cba.png' => ['risk-topography-v1.webp', 1536],
    'exec-69cf8be3-6134-4c22-bec9-7f3c56f33dc2.png' => ['sector-network-v1.webp', 1536],
];
foreach ($files as $file => [$name, $max]) {
    $destination = $target.'/'.$name;
    if (file_exists($destination)) {
        throw new RuntimeException('No se sobrescriben recursos existentes: '.$name);
    }
    $input = imagecreatefrompng($source.'/'.$file);
    $ratio = min(1, $max / max(imagesx($input), imagesy($input)));
    $output = imagecreatetruecolor((int) round(imagesx($input) * $ratio), (int) round(imagesy($input) * $ratio));
    imagealphablending($output, false);
    imagesavealpha($output, true);
    imagecopyresampled($output, $input, 0, 0, 0, 0, imagesx($output), imagesy($output), imagesx($input), imagesy($input));
    if (! imagewebp($output, $destination, 87)) {
        throw new RuntimeException('No se pudo generar '.$name);
    }
    $check = imagecreatefromwebp($destination);
    $transparent = 0;
    for ($y = 0; $y < imagesy($check); $y += 40) {
        for ($x = 0; $x < imagesx($check); $x += 40) {
            $transparent = max($transparent, imagecolorsforindex($check, imagecolorat($check, $x, $y))['alpha']);
        }
    }
    if ($transparent !== 127) {
        throw new RuntimeException('No se conservó transparencia completa: '.$name);
    }
    echo $name.' '.imagesx($check).'x'.imagesy($check).' '.filesize($destination).' bytes; alpha validado'.PHP_EOL;
}
