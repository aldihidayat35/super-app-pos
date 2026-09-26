<?php
require __DIR__.'/vendor/autoload.php';
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

$manager = new ImageManager(new Driver());
$image = $manager->decodePath('public/assets/media/avatars/300-1.jpg');
$image->scaleDown(width: 800, height: 800);
$image->encodeUsingFormat(\Intervention\Image\Format::WEBP, quality: 80)->save('test_output.webp');
echo "OK";
