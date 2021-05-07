<?php

use Pheanstalk\Pheanstalk;
use Pheanstalk\SocketFactory;

define('QUEUE', 'beanstalk');

$basePath = __DIR__ . '/../../';
require $basePath . '/vendor/autoload.php';


// for ($i = 0; $i < 10; $i++) {
//     $pid = pcntl_fork();
//     $talker = Pheanstalk::create('127.0.0.1');
//     if ($pid < 0) {
//         exit();
//     } elseif ($pid > 0) {
//     } else {
//         $jobs = 'test';
//         $talker->useTube(QUEUE)->put(json_encode($jobs));
//     }
// }

// $factory = new class('127.0.0.1', 11300) extends SocketFactory
// {
//     public function create()
//     {
//         echo "CREATING SOCKET\N";
//         return parent::create();
//     }
// };

$factory = new SocketFactory('127.0.0.1', 11300);
$talker = Pheanstalk::createWithFactory($factory);

for ($i = 0; $i < 5; $i++) {
    echo "Starting fork $i\n";
    $pid = pcntl_fork();
    if ($pid < 0) {
        exit();
    } elseif ($pid > 0) {
    } else {
        echo "Putting from process $i\n";
        $talker->useTube(QUEUE)->put('test');
    }
}
