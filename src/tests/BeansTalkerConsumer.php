<?php

use Pheanstalk\Pheanstalk;

define('QUEUE', 'beanstalk');

$basePath = __DIR__ . '/../../';
require $basePath . '/vendor/autoload.php';

$talker = Pheanstalk::create('127.0.0.1');

while (true) {
    $talker->watch(QUEUE);
    $job = $talker->reserve();

    try {
        $jobPayload = $job->getData();
        // $body = json_decode($jobPayload, true);
        printf("%s \n", $jobPayload);
        $talker->delete($job);
    } catch (\Exception $e) {
        $talker->release($job);
    }
}