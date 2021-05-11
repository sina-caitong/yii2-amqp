<?php



use pzr\amqp\jobs\RpcJob;
use pzr\amqp\MyYii;
use pzr\amqp\Response;

$basePath = __DIR__ . '/../../';

require $basePath . '/vendor/autoload.php';
define('YII_CONSOLE_PATH', $basePath . 'frontend/config/console.php');
$yii = new MyYii();

$jobs[] = new RpcJob([
    'object' => 'pzr\amqp\tests\Obj',
    'action' => 'test',
    'params' => [],
    'debug' => true,
    'args' => ''
]);

$jobs[] = new RpcJob([
    'object' => 'pzr\amqp\tests\Obj2',
    'action' => 'staticTest',
    'params' => [],
    'debug' => true,
    'args' => ''
]);

$response = $yii->request([
    'amqp/rpc-serve',
    $jobs
]);

// foreach($response as &$resp) {
//     $resp = Response::checkResponse($resp);
// }

var_dump($response);
