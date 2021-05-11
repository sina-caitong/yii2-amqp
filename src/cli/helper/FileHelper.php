<?php

namespace pzr\amqp\cli\helper;

use Monolog\Logger;

class FileHelper
{

    /** 覆盖写 */
    const FILE_NORMAL = 'w';
    /** 追加写 */
    const FILE_APPEND = 'a';
    /** 只读 */
    const FILE_READ = 'r';

    public static function write($file, string $data, $mode = self::FILE_NORMAL)
    {
        $fd = fopen($file, $mode);
        $size = fwrite($fd, $data, strlen($data));
        fclose($fd);
        return $size;
    }

    public static function read($file, $mode = self::FILE_READ)
    {
        if (!file_exists($file)) return '';
        $fd = fopen($file, $mode);
        $size = @filesize($file) ?:1024;
        $data = fread($fd, $size);
        fclose($fd);
        return $data;
    }
}
