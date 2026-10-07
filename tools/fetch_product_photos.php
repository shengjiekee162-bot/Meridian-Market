<?php
declare(strict_types=1);

/**
 * Downloads realistic product photos for the sample catalog.
 * Sources are Unsplash images sized for marketplace cards.
 */
$root = dirname(__DIR__);
$dir = $root . '/uploads/products';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$photos = [
    '01' => 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=900&h=900&q=80',
    '01b' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=900&h=900&q=80',
    '02' => 'https://images.unsplash.com/photo-1584100936595-c0654b55a2e2?auto=format&fit=crop&w=900&h=900&q=80',
    '03' => 'https://images.unsplash.com/photo-1606923829579-0cb981a83e2e?auto=format&fit=crop&w=900&h=900&q=80',
    '04' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=900&h=900&q=80',
    '04b' => 'https://images.unsplash.com/photo-1513506003901-1e6a229e2d15?auto=format&fit=crop&w=900&h=900&q=80',
    '05' => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?auto=format&fit=crop&w=900&h=900&q=80',
    '06' => 'https://images.unsplash.com/photo-1497515114629-f71d768fd07c?auto=format&fit=crop&w=900&h=900&q=80',
    '07' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&h=900&q=80',
    '08' => 'https://images.unsplash.com/photo-1485955900006-10f4d324d981?auto=format&fit=crop&w=900&h=900&q=80',
    '09' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=900&h=900&q=80',
    '10' => 'https://images.unsplash.com/photo-1603006905003-be475563bc59?auto=format&fit=crop&w=900&h=900&q=80',
    '11' => 'https://images.unsplash.com/photo-1576871337632-b9aef4c17ab9?auto=format&fit=crop&w=900&h=900&q=80',
    '12' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&h=900&q=80',
    '12b' => 'https://images.unsplash.com/photo-1548036328-c37ea8ecfb14?auto=format&fit=crop&w=900&h=900&q=80',
    '13' => 'https://images.unsplash.com/photo-1470116945706-e6bf5d5a53ca?auto=format&fit=crop&w=900&h=900&q=80',
    '14' => 'https://images.unsplash.com/photo-1544022613-e87ca75a784a?auto=format&fit=crop&w=900&h=900&q=80',
    '15' => 'https://images.unsplash.com/photo-1514228742587-6b1558fcc036?auto=format&fit=crop&w=900&h=900&q=80',
    '16' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&h=900&q=80',
    '16b' => 'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=900&h=900&q=80',
    '17' => 'https://images.unsplash.com/photo-1586350977771-b3b0abd50c82?auto=format&fit=crop&w=900&h=900&q=80',
    '18' => 'https://images.unsplash.com/photo-1590874103328-eac38a67478e?auto=format&fit=crop&w=900&h=900&q=80',
    '19' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&h=900&q=80',
    '20' => 'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?auto=format&fit=crop&w=900&h=900&q=80',
    '21' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=900&h=900&q=80',
    '21b' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=900&h=900&q=80',
    '22' => 'https://images.unsplash.com/photo-14565130808-af99578affb1?auto=format&fit=crop&w=900&h=900&q=80',
    '23' => 'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?auto=format&fit=crop&w=900&h=900&q=80',
    '24' => 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?auto=format&fit=crop&w=900&h=900&q=80',
    '25' => 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?auto=format&fit=crop&w=900&h=900&q=80',
    '26' => 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=900&h=900&q=80',
    '26b' => 'https://images.unsplash.com/photo-1566576912321-dcdbf8052141?auto=format&fit=crop&w=900&h=900&q=80',
    '27' => 'https://images.unsplash.com/photo-1558060370-d644479cb7f7?auto=format&fit=crop&w=900&h=900&q=80',
    '28' => 'https://images.unsplash.com/photo-1511381939415-e44015466834?auto=format&fit=crop&w=900&h=900&q=80',
    '29' => 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?auto=format&fit=crop&w=900&h=900&q=80',
    '30' => 'https://images.unsplash.com/photo-1564890369478-c89ca6d9cde9?auto=format&fit=crop&w=900&h=900&q=80',
];

function download_photo(string $url, string $destination): bool
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: MeridianMarketSeed/1.0\r\nAccept: image/*,*/*\r\n",
            'timeout' => 45,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data === false || strlen($data) < 2000) {
        return false;
    }
    if (file_put_contents($destination, $data) === false) {
        return false;
    }
    $info = @getimagesize($destination);
    if ($info === false) {
        unlink($destination);
        return false;
    }
    return true;
}

$ok = 0;
$fail = 0;
foreach ($photos as $key => $url) {
    $target = $dir . '/seed-' . $key . '.jpg';
    echo "Fetching {$key}... ";
    if (download_photo($url, $target)) {
        echo 'OK (' . filesize($target) . " bytes)\n";
        $ok++;
    } else {
        echo "FAIL\n";
        $fail++;
    }
}

echo "Done. {$ok} downloaded, {$fail} failed.\n";
exit($fail > 0 && $ok === 0 ? 1 : 0);
