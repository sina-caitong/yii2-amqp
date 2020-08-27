<?php

namespace pzr\amqp\cli\helper;


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
        umask(0);
        $fd = fopen($file, $mode);
        $size = fwrite($fd, $data, strlen($data));
        fclose($fd);
        return $size;
    }

    public static function read($file, $mode = self::FILE_READ)
    {
        umask(0);
        if (empty(filesize($file))) return '';
        $fd = fopen($file, $mode);
        $data = fread($fd, filesize($file));
        fclose($fd);
        return $data;
    }
}
