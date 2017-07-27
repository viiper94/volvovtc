<?php

use yii\helpers\Url;
use app\models\User; ?>

<div id="vacation">

    <div class="valign-wrapper" style="justify-content: space-between">
        <h5>Заявления на отпуск</h5>
        <?php if(User::isVtcMember()) : ?>
            <a href="<?= Url::to(['site/claims', 'claim' => 'vacation', 'action' => 'add']) ?>" class="btn indigo waves-effect waves-light">
                Подать заявление на отпуск<i class="material-icons right">add_circle</i>
            </a>
        <?php endif ?>
    </div>

    <?php foreach($vacation as $claim) :
        switch ($claim->status){
            case '1': $color_class = 'light-green lighten-4-5'; break;
            case '2': $color_class = 'red lighten-4-5'; break;
            case '0':
            default : $color_class = 'grey lighten-4'; break;
        }
        $user = User::find()->select(['picture', 'nickname'])->where(['id' => $claim->user_id])->one(); ?>
        <div class="card horizontal hoverable <?= $color_class ?>">
            <div class="card-image grey lighten-4 claim-img" style="background-image: url(<?=Yii::$app->request->baseUrl?>/images/users/<?= $user->picture ?>)">
                <a href="<?= Url::to(['site/profile', 'id' => $claim->user_id]) ?>" class="waves-effect waves-light"></a>
            </div>
            <div class="card-stacked">
                <div class="card-content">
                    <a class="card-title black-text" href="<?= Url::to(['site/profile', 'id' => $claim->user_id]) ?>">[Volvo Trucks] <?= htmlentities($user->nickname) ?></a>
                    <div class="flex">
                        <div style="max-width: 70%">
                            <p><?= \app\controllers\SiteController::getRuDate($claim->date) ?></p>
                            <?php if($claim->reason) : ?>
                                <p><b>Причина:</b> <?= strip_tags($claim->reason, '<br>') ?></p>
                            <?php endif ?>
                        </div>
                        <div class="right-align" style="flex: 1;">
                            <p class="fs17 bold"><?= \app\models\ClaimsRecruit::getStatusTitle($claim->status) ?><br><?= strip_tags($claim->reason) ?></p>
                            <?php if($claim->viewed):
                                $by = User::find()->where(['id' => $claim->viewed])->one() ?>
                                <p class="grey-text">Рассмотрел: <?= $by->first_name ?> <?= $by->last_name ?></p>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <?php if(!Yii::$app->user->isGuest && (Yii::$app->user->id == $claim->user_id ||
                        Yii::$app->user->identity->admin == 1) && $claim->status == 0 || User::isAdmin()) : ?>
                    <div class="card-action">
                        <?php if(User::isAdmin() && $claim->status == 0) : ?>
                            <a onclick='return confirm("Одобрить заявку?")' href="<?= Url::to(['site/claims',
                                'claim' => 'vacation',
                                'id' => $claim->id,
                                'action' => 'apply'])
                            ?>"><i class="material-icons to-text">done</i>Одобрить
                            </a>
                        <?php endif; ?>
                        <?php if(!Yii::$app->user->isGuest && (Yii::$app->user->id == $claim->user_id ||
                                Yii::$app->user->identity->admin == 1) && $claim->status == 0) : ?>
                            <a href="<?= Url::to(['site/claims',
                                'claim' => 'vacation',
                                'id' => $claim->id,
                                'action' => 'edit'])
                            ?>"><i class="material-icons to-text">edit</i>Редактировать
                            </a>
                        <?php endif; ?>
                        <?php if(User::isAdmin()) : ?>
                            <a onclick='return confirm("Удалить?")' href="<?=Url::to(['site/claims',
                                'claim' => 'vacation',
                                'id' => $claim->id,
                                'action' => 'delete'])
                            ?>"><i class="material-icons to-text">delete</i>Удалить
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    <?php endforeach ?>

</div>