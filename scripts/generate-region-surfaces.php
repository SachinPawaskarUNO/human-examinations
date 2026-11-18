<?php

declare(strict_types=1);

// Maintenance tool: php scripts/generate-region-surfaces.php
// Flatten the filled SVG anatomy in normalized coordinates without changing artwork.
$root = dirname(__DIR__);
$sources = $outlines = $landmarks = [];
$distance = static function (array $point, array $a, array $b): float {
    $dx = $b[0] - $a[0];
    $dy = $b[1] - $a[1];
    $length = $dx * $dx + $dy * $dy;
    $t = $length > 0 ? max(0, min(1, (($point[0] - $a[0]) * $dx + ($point[1] - $a[1]) * $dy) / $length)) : 0;

    return hypot($point[0] - $a[0] - $t * $dx, $point[1] - $a[1] - $t * $dy);
};
$middle = static fn (array $a, array $b): array => [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2];
$simplify = static function (array $points) use (&$simplify, $distance): array {
    if (count($points) < 3) {
        return $points;
    }
    $last = count($points) - 1;
    $farthest = 0;
    $index = 0;
    for ($i = 1; $i < $last; $i++) {
        $d = $distance($points[$i], $points[0], $points[$last]);

        if ($d > $farthest) {
            $farthest = $d;
            $index = $i;
        }
    }

    if ($farthest <= 0.0001) {
        return [$points[0], $points[$last]];
    }
    $left = $simplify(array_slice($points, 0, $index + 1));
    array_pop($left);

    return [...$left, ...$simplify(array_slice($points, $index))];
};
$flatten = static function (array $a, array $b, array $c, array $d, array &$points, int $depth = 0) use (&$flatten, $distance, $middle): void {
    if ($depth >= 24 || max($distance($b, $a, $d), $distance($c, $a, $d)) <= 0.0001) {
        $points[] = $d;

        return;
    }
    $ab = $middle($a, $b);
    $bc = $middle($b, $c);
    $cd = $middle($c, $d);
    $abc = $middle($ab, $bc);
    $bcd = $middle($bc, $cd);
    $center = $middle($abc, $bcd);
    $flatten($a, $ab, $abc, $center, $points, $depth + 1);
    $flatten($center, $bcd, $cd, $d, $points, $depth + 1);
};

foreach (glob($root.'/resources/sketches/interpol-dvi-*.svg') as $file) {
    $id = substr(basename($file, '.svg'), strlen('interpol-dvi-'));
    $document = new DOMDocument;
    $document->load($file, LIBXML_NONET);
    $svg = $document->documentElement;
    [$vx, $vy, $width, $height] = array_map('floatval', preg_split('/[\s,]+/', $svg->getAttribute('viewBox')));
    $sources[$id] = hash_file('sha256', $file);
    $outlines[$id] = [];
    $nipples = [];

    foreach ($document->getElementsByTagName('path') as $path) {
        if (! preg_match('/^(#fff(?:fff)?|white)$/i', $path->getAttribute('fill'))) {
            continue;
        }
        $matrices = [];
        $inDefinitions = false;
        for ($element = $path; $element instanceof DOMElement; $element = $element->parentNode) {
            $inDefinitions = $inDefinitions || $element->localName === 'defs';

            if ($element->hasAttribute('transform')) {
                if (! preg_match('/^matrix\(([^)]+)\)$/', $element->getAttribute('transform'), $match)) {
                    throw new RuntimeException('Unsupported transform: '.$file);
                }
                $matrices[] = array_map('floatval', preg_split('/[\s,]+/', $match[1]));
            }
        }

        if ($inDefinitions) {
            continue;
        }
        $normalize = static function (array $point) use ($matrices, $vx, $vy, $width, $height): array {
            foreach ($matrices as [$a, $b, $c, $d, $e, $f]) {
                $point = [$a * $point[0] + $c * $point[1] + $e, $b * $point[0] + $d * $point[1] + $f];
            }

            return [($point[0] - $vx) / $width, ($point[1] - $vy) / $height];
        };
        preg_match_all('/[A-Za-z]|[-+]?(?:\d*\.\d+|\d+\.?\d*)(?:[eE][-+]?\d+)?/', $path->getAttribute('d'), $tokens);
        $tokens = $tokens[0];
        $points = [];
        $cursor = [0, 0];
        $command = null;
        for ($i = 0; $i < count($tokens);) {
            if (ctype_alpha($tokens[$i])) {
                $command = $tokens[$i++];
            }

            if (! in_array($command, ['M', 'L', 'H', 'V', 'C'], true)) {
                throw new RuntimeException('Unsupported path command: '.$command.' in '.$file);
            }

            if ($command === 'M' && $points !== []) {
                throw new RuntimeException('Compound fill requires review: '.$file);
            }

            if ($command === 'C') {
                $b = [(float) $tokens[$i++], (float) $tokens[$i++]];
                $c = [(float) $tokens[$i++], (float) $tokens[$i++]];
                $d = [(float) $tokens[$i++], (float) $tokens[$i++]];
                $flatten($normalize($cursor), $normalize($b), $normalize($c), $normalize($d), $points);
                $cursor = $d;
            } else {
                $cursor = match ($command) {
                    'H' => [(float) $tokens[$i++], $cursor[1]],
                    'V' => [$cursor[0], (float) $tokens[$i++]],
                    default => [(float) $tokens[$i++], (float) $tokens[$i++]],
                };
                $points[] = $normalize($cursor);

                if ($command === 'M') {
                    $command = 'L';
                }
            }
        }

        if (count($points) < 3) {
            continue;
        }
        $points = array_map(static fn (array $point): array => array_map(static fn (float $n): float => round($n, 6), $point), $points);
        $simplified = $simplify($points);
        $outlines[$id][] = count($simplified) >= 3 ? $simplified : $points;

        if ($id === 'body-front') {
            $xs = array_column($points, 0);
            $ys = array_column($points, 1);
            $x = (min($xs) + max($xs)) / 2;
            $y = (min($ys) + max($ys)) / 2;
            $w = (max($xs) - min($xs)) * $width;
            $h = (max($ys) - min($ys)) * $height;

            if ($y > 0.23 && $y < 0.31 && $h > 0 && $w / $h > 0.7 && $w / $h < 1.4 && $w < 5) {
                $nipples[$x < 0.5 ? 'RightNipple' : 'LeftNipple'] = ['x' => $x, 'y' => $y, 'rx' => $w / $width * 0.75, 'ry' => $h / $height * 0.75];
            }
        }
    }

    if ($id === 'body-front') {
        if (count($nipples) !== 2) {
            throw new RuntimeException('Expected two nipple landmarks in the front body drawing.');
        }
        $landmarks[$id] = $nipples;
    }
}

$lines = ['{', '  "sources": '.str_replace("\n", "\n  ", json_encode($sources, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)).',', '  "landmarks": '.str_replace("\n", "\n  ", json_encode($landmarks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)).',', '  "outlines": {'];
$ids = array_keys($outlines);
foreach ($ids as $index => $id) {
    $lines[] = '    "'.$id.'": [';
    foreach ($outlines[$id] as $j => $outline) {
        $lines[] = '      '.json_encode($outline, JSON_THROW_ON_ERROR).($j < count($outlines[$id]) - 1 ? ',' : '');
    }
    $lines[] = '    ]'.($index < count($ids) - 1 ? ',' : '');
}
$lines[] = '  }';
$lines[] = '}';
file_put_contents($root.'/resources/region-surfaces.json', implode("\n", $lines)."\n");
echo 'Traced '.count($ids)." anatomical surfaces and nipple landmarks.\n";
