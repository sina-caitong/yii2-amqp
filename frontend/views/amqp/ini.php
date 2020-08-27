<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model app\models\LoginForm */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$this->title = 'AMQP LOGGER MANAGER';
$this->params['breadcrumbs'][] = $this->title;
[$sourceIni, $parseIni] = $model->getAmqpIni();
?>
<div class="" style="text-align: left;">
    <h1><?= Html::encode($this->title) ?></h1>
    <p><?= DEFALUT_AMQPINI_PATH ?></p>
    <pre>
<?= $parseIni ?>
    </pre>
    <pre style="height:auto;">
<?= $sourceIni ?>
    </pre>

    <br>
</div>