<?php

error_reporting(-1);

define('YII_ENABLE_ERROR_HANDLER', false);
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

Yii::setAlias('@tests', __DIR__);

new \yii\console\Application([
    'id'         => 'unit',
    'basePath'   => __DIR__,
    'vendorPath' => dirname(__DIR__) . '/vendor',
    'bootstrap'  => ['log'],
    'components' => [
        'db'  => [
            'class' => 'yii\db\Connection',
            'dsn'   => 'sqlite::memory:',
        ],
        'log' => [
            'targets' => [
                [
                    'class'      => 'yii\log\FileTarget',
                    'categories' => ['yii\db\*', 'lhs\Yii2SaveRelationsBehavior\*']
                ],
            ]
        ],
    ]
]);
