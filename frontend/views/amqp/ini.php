<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model app\models\LoginForm */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$this->title = 'amqp.ini VIEW';
$this->params['breadcrumbs'][] = $this->title;
[$sourceIni, $parseIni] = $model->getAmqpIni();
?>
<div class="" style="text-align: left;">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>检测amqp.ini</p>
    <pre>
<?= $parseIni ?>
    </pre>
    <p><?= DEFALUT_AMQPINI_PATH ?></p>
    <pre style="height:auto;">
<?= $sourceIni ?>
    </pre>

    <br>
</div>