<?php

namespace app\controllers;

use app\models\AddModForm;
use app\models\ModsCategories;
use app\models\ModsSubcategories;
use app\models\Notifications;
use app\models\Trailers;
use app\models\User;
use Yii;
use app\models\Mods;
use yii\data\Pagination;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\Controller;

class ModificationsController extends Controller{

    public function beforeAction($action){
        // getting user notifications
        if(!Yii::$app->user->isGuest){
            // online
            User::setUserActivity(Yii::$app->user->id);

            // notifications
            $has_unread = false;
            $notifications = Notifications::find()
                ->where(['uid' => Yii::$app->user->id])
                ->orderBy(['date' => SORT_DESC, 'status' => SORT_ASC])
                ->all();
            foreach ($notifications as $notification){
                if($notification->status == '0') {
                    $has_unread = true;
                    break;
                }
            }
            Yii::$app->view->params['notifications'] = $notifications;
            Yii::$app->view->params['hasUnreadNotifications'] = $has_unread;
        }
        if($this->action->id != 'index' && $this->action->id != 'category' && $this->action->id != 'all'){
			if(Yii::$app->user->isGuest){
				Url::remember();
				return $this->redirect(['site/login']);
			}
		}
        return parent::beforeAction($action);
    }

    public function actionIndex(){
        return $this->render('index');
    }

    public function actionCategory(){
        if(Yii::$app->request->get('game') && Yii::$app->request->get('category')){
            $game = Yii::$app->request->get('game');
            $category = Yii::$app->request->get('category');
            $subcategory = Yii::$app->request->get('subcategory') ? Yii::$app->request->get('subcategory') : Yii::$app->request->get('category');
            $mods_query = Mods::find()
				->select(['mods.*', 'trailers.picture as tr_image'])
				->leftJoin('trailers', 'mods.trailer = trailers.id');
            if(!User::isAdmin()) $mods_query = $mods_query->where(['mods.visible' => '1']);
            $mods = $mods_query->andWhere(['mods.game' => $game, 'mods.category' => $category, 'mods.subcategory' => $subcategory])
				->orderBy(['mods.sort' => SORT_DESC])->all();
            $subcategory = ModsSubcategories::find()
				->select([
					'mods_subcategories.*',
					'mods_categories.id as cat_id',
					'mods_categories.name as cat_name',
					'mods_categories.title as cat_title',
					'mods_categories.picture as cat_image',
				])
				->leftJoin('mods_categories', 'mods_categories.id = mods_subcategories.category_id')
				->where([
					'mods_subcategories.name' => $subcategory,
					'mods_categories.name' => $category,
					'mods_subcategories.for_ets' => $game == 'ets' ? '1' : '0'
				])
				->one();
            $all_subcategories = ModsSubcategories::find()->where(['category_id' => $subcategory->cat_id])->orderBy(['id' => SORT_ASC])->all();
            if(!$subcategory) return $this->redirect(['modifications/index']);
            return $this->render('category', [
                'mods' => $mods,
                'subcategory' => $subcategory,
                'all_subcategories' => $all_subcategories
            ]);
        }else{
            return $this->render('//site/error');
        }
    }

    public function actionAdd(){
        if(User::isAdmin()){
            $model = new AddModForm();
            if($model->load(Yii::$app->request->post()) && $model->validate()){
                if($model->addMod() != false){
                    $cat = explode('/', $model->category);
                    return $this->redirect(['modifications/category', 'game' => $cat[0], 'category' => $cat[1], 'subcategory' => $cat[2]]);
                }
            }
            return $this->render('form/index', [
                'model' => $model,
                'categories' => ArrayHelper::merge(['Нет категории' => ['' => 'Выберите категорию']], ModsCategories::getCatsWithSubCats()),
                'trailers' => Trailers::getTrailers(['0' => 'Нет прицепа']),
            ]);
        }else{
            return $this->render('//site/error');
        }
    }

    public function actionEdit(){
        if(Yii::$app->request->get('id') && User::isAdmin()){
            $model = new AddModForm(Yii::$app->request->get('id'));
            if($model->load(Yii::$app->request->post()) && $model->validate()){
                if($model->editMod(Yii::$app->request->get('id')) != false){
                    $mod = Mods::findOne(Yii::$app->request->get('id'));
                    return $this->redirect(['modifications/category',
                        'game' => $mod->game,
                        'category' => $mod->category,
                        'subcategory' => $mod->category == $mod->subcategory ? '' : $mod->subcategory
                    ]);
                }
            }
            return $this->render('form/index', [
                'model' => $model,
                'categories' => ModsCategories::getCatsWithSubCats(),
                'trailers' => Trailers::getTrailers(['0' => 'Нет прицепа']),
                'trailer_data' => Mods::getTrailerData($model)
            ]);
        }else{
            return $this->render('//site/error');
        }
    }

    public function actionRemove(){
        if(Yii::$app->request->get('id') && User::isAdmin()){
            $mod = Mods::findOne(Yii::$app->request->get('id'));
            Mods::deleteMod(Yii::$app->request->get('id'));
            return $this->redirect(['modifications/category',
                'game' => $mod->game,
                'category' => $mod->category,
                'subcategory' => $mod->category == $mod->subcategory ? '' : $mod->subcategory
            ]);
        }else{
            return $this->render('//site/error');
        }
    }

    public function actionSort(){
        if(Yii::$app->request->get('dir') && Yii::$app->request->get('id') && User::isAdmin()){
            $mod = Mods::findOne(Yii::$app->request->get('id'));
            Mods::resortMod(Yii::$app->request->get('id'), Yii::$app->request->get('dir'));
            return $this->redirect(['modifications/category', 'game' => $mod->game, 'category' => $mod->category, 'subcategory' => $mod->subcategory]);
        }else{
            return $this->render('//site/error');
        }
    }

    public function actionShow(){
        if(Yii::$app->request->get('id') && User::isAdmin()){
            Mods::visibleMod(Yii::$app->request->get('id'), 'show');
            $mod = Mods::findOne(Yii::$app->request->get('id'));
            return $this->redirect(['modifications/category', 'game' => $mod->game, 'category' => $mod->category, 'subcategory' => $mod->subcategory]);
        }else{
            return $this->render('//site/error');
        }
    }

    public function actionHide(){
        if(Yii::$app->request->get('id') && User::isAdmin()){
            Mods::visibleMod(Yii::$app->request->get('id'), 'hide');
            $mod = Mods::findOne(Yii::$app->request->get('id'));
            return $this->redirect(['modifications/category', 'game' => $mod->game, 'category' => $mod->category, 'subcategory' => $mod->subcategory]);
        }else{
            return $this->render('//site/error');
        }
    }

    public function actionAll(){
        $query = Mods::find()
			->select(['mods.*', 'trailers.picture as tr_image'])
			->leftJoin('trailers', 'mods.trailer = trailers.id');
        if(Yii::$app->request->get('q')){
            $query = $query->where(['like', 'mods.title', Yii::$app->request->get('q')])
                ->orWhere(['like', 'mods.description', Yii::$app->request->get('q')]);
        }
        if(!User::isAdmin()) $query = $query->andWhere(['mods.visible' => '1']);
        $total = $query->count();
        $pagination = new Pagination([
            'defaultPageSize' => 10,
            'totalCount' => $total
        ]);
        if(Yii::$app->request->get('sort') == 'title') $query = $query->orderBy(['mods.title' => SORT_ASC]);
        $mods = $query->orderBy(['mods.id' => SORT_DESC])->offset($pagination->offset)->limit($pagination->limit)->all();
        return $this->render('all', [
            'mods' => $mods,
            'currentPage' => Yii::$app->request->get('page', 1),
            'totalPages' => $pagination->getPageCount(),
            'pagination' => $pagination,
            'total' => $total,
        ]);
    }

}