<?php

$path = dirname(__DIR__, 2) . '/frontend/';
`cd {$path} && php yii amqp/rpc-consumer rpc`;