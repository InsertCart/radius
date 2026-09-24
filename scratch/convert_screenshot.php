<?php

$src = 'C:/Users/sande/.gemini/antigravity-ide/brain/2589fed0-308e-4006-a4ec-2baaf85ff191/winza_theme_preview_1790143827602.jpg';
$im = imagecreatefromjpeg($src);
$w = imagesx($im);
$h = imagesy($im);
$newW = 1200;
$newH = 675;
$thumb = imagecreatetruecolor($newW, $newH);
imagecopyresampled($thumb, $im, 0, 0, 0, 0, $newW, $newH, $w, $h);
imagetruecolortopalette($thumb, false, 256);
imagepng($thumb, 'themes/winza/screenshot.png', 9);
imagepng($thumb, 'themes/winza/assets/screenshot.png', 9);
imagedestroy($im);
imagedestroy($thumb);
echo 'Optimized 1200x675 palette PNG: ' . filesize('themes/winza/screenshot.png') . ' bytes' . PHP_EOL;
