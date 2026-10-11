<?php
/**
 * What the tests of textgen.php (tests/Packaging/pages-scenario.sh) read of an image: its size and format, the colour of the
 * background (the pixel in the corner), the colour of the text (the most frequent colour that is not the background), and
 * how much of the picture is text.
 *
 *   php image-probe.php FILE      prints: WIDTHxHEIGHT FORMAT background=R,G,B text=R,G,B ink=PERCENT
 */


$image = new Imagick($argv[1] ?? '');
$width = $image->getImageWidth();
$height = $image->getImageHeight();
$corner = $image->getImagePixelColor(0, 0)->getColor();
$background = [$corner['r'], $corner['g'], $corner['b']];

$ink = 0;
$colors = [];
foreach ($image->getImageHistogram() as $pixel) {
    $color = $pixel->getColor();
    $rgb = [$color['r'], $color['g'], $color['b']];
    $count = $pixel->getColorCount();
    if ($rgb === $background) {
        continue;
    }
    $ink += $count;
    $key = implode(',', $rgb);
    $colors[$key] = ($colors[$key] ?? 0) + $count;
}
arsort($colors);
printf(
    "%dx%d %s background=%s text=%s ink=%.2f\n",
    $width,
    $height,
    $image->getImageFormat(),
    implode(',', $background),
    (string) array_key_first($colors),
    100 * $ink / ($width * $height),
);
