<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$items = [
    ['Carafe', '#f3e6d6', '#8d5a3c', '#e7b48a'],
    ['Throw', '#f7f1e8', '#c8b79a', '#6f7d62'],
    ['Board', '#f6ecdf', '#a86b3c', '#e6c39a'],
    ['Lamp', '#f4efe6', '#d08a45', '#1f6a5c'],
    ['Bowls', '#f8f3ec', '#c46a4a', '#efd3bf'],
    ['Cups', '#f6efe8', '#7c4e3a', '#e7d2c4'],
    ['Trivets', '#f3efe6', '#8ea58b', '#d7c4a3'],
    ['Vase', '#f7f2ea', '#355f56', '#d7ddd4'],
    ['Napkins', '#fbf7f1', '#d8c3a5', '#8c6a4a'],
    ['Candle', '#f8f1e4', '#c9842a', '#6b4a2e'],
    ['Beanie', '#eef3f1', '#355f56', '#d7c7a1'],
    ['Duffel', '#f4efe6', '#8a6232', '#1c2430'],
    ['Optics', '#e7eef0', '#1c2430', '#d08a45'],
    ['Jacket', '#eef2f4', '#3d4c5c', '#d06a32'],
    ['Mug', '#f6f3ee', '#9aa3a0', '#1f6a5c'],
    ['Lantern', '#f8f4ec', '#d08a45', '#1c2430'],
    ['Socks', '#f7f1ea', '#6e7f62', '#e7d7c3'],
    ['Tote', '#f4ecdf', '#6b4f32', '#e6d3b4'],
    ['Whistle', '#f8f4ea', '#c6a15a', '#1c2430'],
    ['Shell', '#eef3f6', '#4d6474', '#d7e2ea'],
    ['Journal', '#f7f1e6', '#1c2430', '#e7d3b0'],
    ['Ink', '#f6f3f8', '#3d355f', '#d7c4e8'],
    ['Scissors', '#f8f4ea', '#b08a3e', '#1c2430'],
    ['Steam', '#f3f7f4', '#1f6a5c', '#d7e6df'],
    ['Clay', '#f6eee8', '#a86b5a', '#efd8cf'],
    ['Puzzle', '#f8f3e8', '#d08a45', '#1f6a5c'],
    ['Rattle', '#fbf6f1', '#c46a4a', '#f0d2c4'],
    ['Cocoa', '#f6efe8', '#6b3e2e', '#e7c7a1'],
    ['Honey', '#fbf6e8', '#d7a441', '#8a6232'],
    ['Tea', '#f4f7f2', '#3f6b45', '#e4d7b8'],
];

function illustration(string $title, string $bg, string $a, string $b, bool $alt = false): string
{
    $shift = $alt ? 40 : 0;
    $safe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
  <rect width="800" height="800" fill="{$bg}"/>
  <circle cx="{$shift}" cy="0" r="220" fill="{$b}" opacity="0.35"/>
  <rect x="170" y="150" width="460" height="460" rx="48" fill="#fffdfb" opacity="0.72"/>
  <circle cx="400" cy="340" r="120" fill="{$a}"/>
  <rect x="300" y="470" width="200" height="28" rx="14" fill="{$b}"/>
  <text x="400" y="650" text-anchor="middle" font-family="Georgia, serif" font-size="42" fill="#1c2430">{$safe}</text>
</svg>
SVG;
}

$dir = $root . '/uploads/products';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
foreach ($items as $index => $item) {
    $n = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    file_put_contents($dir . "/seed-{$n}.svg", illustration($item[0], $item[1], $item[2], $item[3]));
    if (in_array($index + 1, [1, 4, 12, 16, 21, 26], true)) {
        file_put_contents($dir . "/seed-{$n}b.svg", illustration($item[0], $item[3], $item[2], $item[1], true));
    }
}

$categories = [
    'home' => ['Home', '#e7d7c4', '#8d5a3c'],
    'kitchen' => ['Kitchen', '#f3d7c8', '#c46a4a'],
    'apparel' => ['Apparel', '#d9e3ea', '#3d4c5c'],
    'outdoor' => ['Outdoor', '#d7e4dc', '#1f6a5c'],
    'stationery' => ['Paper', '#efe6d4', '#1c2430'],
    'wellness' => ['Wellness', '#e4efe8', '#1f6a5c'],
    'lighting' => ['Light', '#f8e7cf', '#d08a45'],
    'audio' => ['Audio', '#ece7f4', '#3d355f'],
    'kids' => ['Play', '#f8e4d8', '#c46a4a'],
    'pantry' => ['Pantry', '#f6e8c8', '#8a6232'],
];
$catDir = $root . '/assets/images/categories';
if (!is_dir($catDir)) {
    mkdir($catDir, 0755, true);
}
foreach ($categories as $slug => $cat) {
    file_put_contents($catDir . '/' . $slug . '.svg', illustration($cat[0], $cat[1], $cat[2], '#fffdfb'));
}

$imageDir = $root . '/assets/images';
file_put_contents($imageDir . '/placeholder.svg', illustration('Meridian', '#f4efe7', '#1f6a5c', '#d06a32'));
file_put_contents($imageDir . '/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="18" fill="#1f6a5c"/><circle cx="24" cy="26" r="10" fill="#f2c9a4"/><path d="M18 40h28l-6 12H24z" fill="#d06a32"/></svg>');
echo "Media written\n";
