<?php
// 找到verdor的绝对路径

use pzr\amqp\cli\helper\FileHelper;

require_once __DIR__ . '/src/cli/helper/FileHelper.php';

$vendor_path = __DIR__ . '/Vendor.php';
if (is_file($vendor_path)) {
    return require $vendor_path;
}

$dirs = [
    __DIR__ . '/vendor',
    dirname(__DIR__, 2) . '/vendor',
    dirname(__DIR__, 3) . '/vendor',
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $basePath = dirname($dir);
        return $basePath;
    }
}
