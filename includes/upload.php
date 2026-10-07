<?php
declare(strict_types=1);

function store_uploaded_image(array $file, string $folder, int $minEdge = 200): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new AppException('The upload could not be read.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new AppException('Choose an image to upload.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        log_message('upload', 'Upload error code ' . (int) $file['error']);
        throw new AppException('The image upload failed.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new AppException('The image upload was not accepted.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 2 * 1024 * 1024) {
        throw new AppException('Images must be 2 MB or smaller.');
    }
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new AppException('Use a JPG, PNG, or WEBP image.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($map[$mime])) {
        throw new AppException('The file content is not a supported image.');
    }
    $info = @getimagesize($tmp);
    if ($info === false) {
        throw new AppException('The image could not be read.');
    }
    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width < $minEdge || $height < $minEdge) {
        throw new AppException('Images must be at least ' . $minEdge . ' by ' . $minEdge . ' pixels.');
    }
    if ($width > 4000 || $height > 4000) {
        throw new AppException('Images must be 4000 pixels or smaller on each side.');
    }
    if (!in_array($folder, ['products', 'profiles', 'categories'], true)) {
        throw new AppException('The upload destination is not allowed.');
    }

    $dir = APP_ROOT . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new AppException('The upload folder is not available.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $map[$mime];
    $dest = $dir . DIRECTORY_SEPARATOR . $filename;
    reencode_image($tmp, $dest, $mime);

    $realDir = realpath($dir);
    $realFile = realpath($dest);
    if ($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir)) {
        if (is_file($dest)) {
            unlink($dest);
        }
        throw new AppException('The image could not be stored safely.');
    }
    return 'uploads/' . $folder . '/' . $filename;
}

function reencode_image(string $source, string $dest, string $mime): void
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new AppException('Image processing is not available on this server.');
    }
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($source),
        'image/png' => @imagecreatefrompng($source),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
        default => false,
    };
    if (!$src) {
        throw new AppException('The image could not be processed.');
    }
    $width = imagesx($src);
    $height = imagesy($src);
    $scale = min(1600 / $width, 1600 / $height, 1);
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    if ($canvas === false) {
        imagedestroy($src);
        throw new AppException('The image could not be processed.');
    }
    if ($mime !== 'image/jpeg') {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
    } else {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $white);
    }
    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($canvas, $dest, 86),
        'image/png' => imagepng($canvas, $dest, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $dest, 86) : false,
        default => false,
    };
    imagedestroy($src);
    imagedestroy($canvas);
    if (!$saved) {
        throw new AppException('The image could not be saved.');
    }
}

function delete_managed_upload(?string $relative): void
{
    if ($relative === null || $relative === '') {
        return;
    }
    $relative = str_replace('\\', '/', $relative);
    if (!preg_match('#^uploads/(products|profiles|categories)/[A-Za-z0-9._-]+$#', $relative)) {
        return;
    }
    $full = APP_ROOT . '/' . $relative;
    $uploads = realpath(APP_ROOT . '/uploads');
    $real = realpath($full);
    if ($uploads && $real && str_starts_with($real, $uploads) && is_file($real)) {
        unlink($real);
    }
}

function normalize_uploads(?array $files): array
{
    if ($files === null || !isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
    }
    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $files['size'][$index] ?? 0,
        ];
    }
    return $normalized;
}
