<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

if (is_file(__DIR__ . '/MY_amqp.php')) {
    $amqp = require __DIR__ . '/MY_amqp.php';
} else {
    $amqp = require __DIR__ . '/amqp.php';
}

$baseDir = require dirname(__DIR__, 2) . '/FindVendor.php';

$config = [
    'id' => 'basic2',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        // '@bower' => '@vendor/bower-asset',
        // '@npm'   => '@vendor/npm-asset',
        '@bower' => $baseDir . '/vendor/bower-asset',
        '@npm'   => $baseDir . '/vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'rLJT8YwqbwuvFT2v6rcWk9YzY4b0qD1z',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            // 'identityClass' => 'app\models\MyUser',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'amqp/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'consumer' => $amqp['consumer'],
        
        /*
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        */
    ],
    'defaultRoute' => 'amqp/index',
    'params' => $params,
];


if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
