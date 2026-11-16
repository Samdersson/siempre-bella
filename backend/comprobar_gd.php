<?php

header('Content-Type: application/json');

echo json_encode([
    'gd' => extension_loaded('gd'),
    'webp' => function_exists('imagewebp')
]);