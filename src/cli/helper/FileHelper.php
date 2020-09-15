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
        if (!is_resource($fd)) {
            $flag = @chmod($file, 0755);
            if ($flag === false) {
                AmqpIniHelper::exit($file . ' chmod 0755 failed', Logger::ERROR);
            }
            $fd = fopen($file, $mode);
            is_resource($fd) or AmqpIniHelper::exit($file . ' fopen failed', Logger::ERROR);
            return false;
        }
        $size = fwrite($fd, $data, strlen($data));
        fclose($fd);
        return $size;
    }

    public static function read($file, $mode = self::FILE_READ)
    {
        if (empty(@filesize($file))) return '';
        $fd = fopen($file, $mode);
        $data = fread($fd, filesize($file));
        fclose($fd);
        return $data;
    }
}
