<?php
function cloudinaryEnv(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return (string)$value;
}

return [
    'cloud_name' => cloudinaryEnv('CLOUDINARY_CLOUD_NAME', ''),
    'api_key'    => cloudinaryEnv('CLOUDINARY_API_KEY', ''),
    'api_secret' => cloudinaryEnv('CLOUDINARY_API_SECRET', ''),
    'folder'     => cloudinaryEnv('CLOUDINARY_FOLDER', 'Images')
];
