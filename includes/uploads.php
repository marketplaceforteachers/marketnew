<?php
/**
 * Handles a user-uploaded image file (profile photos, blog images). Security model: never trust
 * the uploaded bytes or the client-supplied filename/mime type. Every accepted upload is decoded
 * by GD and re-encoded to a fresh JPEG before being written to disk — a file that isn't a real,
 * fully-decodable image (a polyglot, a PHP shell renamed to .jpg, a corrupt/malformed file
 * crafted to exploit a parser bug elsewhere) simply fails to decode and is rejected outright.
 * The output filename is always a random hex string chosen by this code, never derived from
 * anything the client sent, and always ends in .jpg regardless of the source format.
 */

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5MB — generous for a photo, still bounded
const UPLOAD_MAX_DIMENSION = 1600; // longest edge, px — plenty for web display, keeps files small

/**
 * @param array $file One element of $_FILES, e.g. $_FILES['avatar']
 * @param string $subdir 'avatars' or 'blog' — a subfolder under uploads/
 * @return array{ok: bool, url?: string, error?: string}
 */
function handle_image_upload(array $file, string $subdir): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was uploaded.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (error code ' . $file['error'] . ').'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Image is too large — max ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . 'MB.'];
    }

    // getimagesize() reads the actual file header/content, not the client-supplied filename or
    // Content-Type — this is the first real gate, not just a rubber stamp on client metadata.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'error' => 'That file is not a readable image.'];
    }

    $image = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        default => false,
    };
    if (!$image) {
        return ['ok' => false, 'error' => 'Only JPEG, PNG, or WebP images are supported.'];
    }

    // Flatten transparency onto white before JPEG re-encoding (JPEG has no alpha channel).
    $width = imagesx($image);
    $height = imagesy($image);
    $flattened = imagecreatetruecolor($width, $height);
    imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
    imagealphablending($flattened, true);
    imagecopy($flattened, $image, 0, 0, 0, 0, $width, $height);
    imagedestroy($image);
    $image = $flattened;

    if ($width > UPLOAD_MAX_DIMENSION || $height > UPLOAD_MAX_DIMENSION) {
        $scale = UPLOAD_MAX_DIMENSION / max($width, $height);
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);
        $resized = imagescale($image, $newWidth, $newHeight);
        if ($resized) {
            imagedestroy($image);
            $image = $resized;
        }
    }

    $subdir = preg_replace('/[^a-z]/', '', $subdir); // this is only ever a fixed literal from our own code, never user input, but stay defensive
    $dir = __DIR__ . "/../uploads/$subdir";
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($image);
        return ['ok' => false, 'error' => 'Could not create upload directory.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $path = "$dir/$filename";
    $saved = imagejpeg($image, $path, 85);
    imagedestroy($image);

    if (!$saved) {
        return ['ok' => false, 'error' => 'Could not save the image.'];
    }

    return ['ok' => true, 'url' => "/uploads/$subdir/$filename"];
}
