<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

class Convoys extends ActiveRecord{

    public static function tableName(){
        return 'convoys';
    }

    public function rules(){
        return [
            [['truck_var', 'time', 'date', 'updated'], 'safe'],
            [['visible', 'open', 'updated_by'], 'integer'],
            [['description'], 'string', 'max' => 2048],
            [['rest', 'participants'], 'string', 'max' => 1024],
            [['add_info'], 'string', 'max' => 512],
            [['picture_full', 'picture_small', 'start_city', 'start_company', 'finish_city', 'finish_company', 'extra_picture'], 'string', 'max' => 255],
            [['server'], 'string', 'max' => 45],
            [['length'], 'string', 'max' => 10],
            [['game'], 'string', 'max' => 3],
            [['dlc', 'trailer', 'author'], 'string']
        ];
    }

    public static function getNearestConvoy(){
        $nearest_convoy_query = Convoys::find()
//            ->select(['id', 'title', 'picture_full', 'picture_small', 'description', 'departure_time'])
            ->where(['visible' => '1'])
            ->andWhere(['>=', 'departure_time', gmdate('Y-m-d ').(intval(gmdate('H'))+2).':'.gmdate('i:s')]);
        if(!User::isVtcMember()) $nearest_convoy_query = $nearest_convoy_query->andWhere(['open' => '1']); // only open convoys for guests
        $nearest_convoy = $nearest_convoy_query->orderBy(['date' => SORT_ASC])->one();
        return $nearest_convoy;
    }

    public static function getFutureConvoys(){
        $convoys_query = Convoys::find()->select(['id', 'picture_small', 'title', 'departure_time', 'visible'])
            ->andWhere(['>=', 'departure_time', gmdate('Y-m-d ').(intval(gmdate('H'))+2).':'.gmdate('i:s')]);
        if(!User::isVtcMember()) $convoys_query = $convoys_query->andWhere(['open' => '1']); // only open convoys for guests
        if(!User::isAdmin()) $convoys_query = $convoys_query->andWhere(['visible' => '1']); // only visible convoys for non-admins
        $convoys = $convoys_query->orderBy(['date' => SORT_ASC])->all();
        return $convoys;
    }

    public static function getPastConvoys(){
        if(User::isVtcMember() || User::isAdmin()){
            $hidden_convoys = Convoys::find()
                ->select(['id', 'picture_small', 'title', 'departure_time', 'visible', 'scores_set'])
                ->andWhere(['<', 'departure_time', gmdate('Y-m-d ') . (intval(gmdate('H')) + 2) . ':' . gmdate('i:s')]);
            if(!User::isAdmin()) $hidden_convoys->andWhere(['visible' => '1']); // only visible convoys for non-admins
            $hidden_convoys = $hidden_convoys->orderBy(['date' => SORT_ASC])->all();
            return $hidden_convoys;
        }
        return false;
    }

    public static function deleteConvoy($id){
        $convoy = Convoys::findOne($id);
        if($convoy->picture_full && file_exists(Yii::$app->request->baseUrl.'/web/images/convoys/'.$convoy->picture_full)) {
            unlink($_SERVER['DOCUMENT_ROOT'].Yii::$app->request->baseUrl.'/web/images/convoys/'.$convoy->picture_full);
        }
        if($convoy->picture_small && file_exists(Yii::$app->request->baseUrl.'/web/images/convoys/'.$convoy->picture_small)) {
            unlink($_SERVER['DOCUMENT_ROOT'].Yii::$app->request->baseUrl.'/web/images/convoys/'.$convoy->picture_small);
        }
        if($convoy->extra_picture && file_exists(Yii::$app->request->baseUrl.'/web/images/convoys/'.$convoy->extra_picture)) {
            unlink($_SERVER['DOCUMENT_ROOT'].Yii::$app->request->baseUrl.'/web/images/convoys/'.$convoy->extra_picture);
        }
        return $convoy->delete();
    }

    public static function visibleConvoy($id, $action){
        $convoy = Convoys::findOne($id);
        $convoy->visible = $action == 'show' ? '1' : '0';
        return $convoy->update() == 1 ? true : false;
    }

    public static function deleteExtraPicture($id) {
        $convoy = Convoys::findOne($id);
        $convoy->extra_picture = null;
        $convoy->save();
    }

    public static function getServerName($short){
        switch ($short){
            case 'eu1' : $server = 'Europe 1'; break;
            case 'eu3' : $server = 'EU3 [No Cars]'; break;
            case 'us_ets' : $server = 'United States - ETS2'; break;
            case 'us_ats' : $server = 'United States - ATS'; break;
            case 'hk' : $server = 'Honk Kong'; break;
            case 'sa' : $server = 'South America'; break;
            case 'ffa' : $server = 'Fun4All'; break;
            case 'eu2_ats' :
            case 'eu2_ets' :
            default: $server = 'Europe 2'; break;
        }
        return $server;
    }

    public static function getVariationsByGame($game = 'ets'){
        if($game == 'ets'){
            $vars = [
                '0' => 'Любая вариация',
                '1' => 'Вариация №1',
                '2' => 'Вариация №2.1 или 2.2',
                '21' => 'Вариация №2.1',
                '22' => 'Вариация №2.2',
                '3' => 'Вариация №3',
                '4' => 'Вариация №1 или №2',
                '5' => 'Вариация №1 или №3',
                '6' => 'Тягач, как в описании',
                '7' => 'Легковой автомобиль Scout',
            ];
        }else if($game == 'ats'){
            $vars = [
                '0' => 'Любой тягач',
                '6' => 'Тягач, как в описании',
                '7' => 'Легковой автомобиль Scout',
            ];
        }
        return $vars;
    }

    public static function getVariationName($short, $link = false){
        switch ($short){
            case '0' : $variation = 'Любая вариация'; break;
            case '6' : $variation = 'Легковой автомобиль Scout'; break;
            case '5' : $variation = 'Тягач, как в описании'; break;
            case '4' : $variation = 'Вариация №1 или №3'; break;
            case '3' : $variation = 'Вариация №3'; break;
            case '2' : $variation = 'Вариация №2'; break;
            case '21' : $variation = 'Вариация №2.1'; break;
            case '22' : $variation = 'Вариация №2.2'; break;
            case '1' :
            default: $variation = 'Вариация №1'; break;
        }
        if($link && ($short == '1' || $short == '21' || $short == '22' || $short == '3')){
            $variation = '<a href="'.Url::to(['site/variations', '#' => $short]).'">'.$variation.'</a>';
        }
        return $variation;
    }

    public static function getDLCString($dlc){
        $need = false;
        $string = '<i class="material-icons left" style="font-size: 22px">warning</i>Для участия необходимо ';
        foreach ($dlc as $key => $item){
            if($item == '1'){
                $string .= 'DLC '.$key.', ';
                $need = true;
            }
        }
        return $need ? substr($string, 0, strlen($string) - 2) : false;
    }

    public static function getVarList($string, $with_img){
        $var_images = [
            '1' => ['var1_1', 'var1_2'],
            '21' => ['var2'],
            '22' => ['var22']
        ];
        $list = '<ul class="var-list browser-default">';
        switch ($string){
            case '1' : $vars = ['1']; break;
            case '2' : $vars = ['21', '22']; break;
            case '21' : $vars = ['21']; break;
            case '22' : $vars = ['22']; break;
            case '4' : $vars = ['1', '21', '22']; break;
            case '5' : $vars = ['1', '3']; break;
            case '6' : $vars = ['6']; break;
            case '7' : $vars = ['7']; break;
            case '0' :
            default : $vars = ['0']; break;
        }
        foreach ($vars as $var){
            $list .= '<li><p class="var-name">'.self::getVariationName($var, true).'</p>';
            if($with_img && array_key_exists($var, $var_images)) {
                $rand_key = array_rand($var_images[$var], 1);
                $list .= '<img class="responsive-img materialboxed" src="/assets/img/'.$var_images[$var][$rand_key].'.jpg">';
            }
            $list .= '</li>';
        }
        return $list .= '</ul>';
    }

    public static function getTrailerData($trailers){
        $trailers_image = array();
        foreach ($trailers as $trailer){
            if($trailer != 0 && $trailer != -1) {
                $trailer_db = Trailers::findOne($trailer);
                $trailers_image[] = 'trailers/'.$trailer_db->picture;
            }else{
                $trailers_image[] = 'trailers/default.jpg';
            }
        }
        return $trailers_image;
    }

    public static function setConvoyScores($scores, $target, $lead = null){
        foreach($scores as $id => $score){
            $score = intval($score);
            if($score != 0){
                if($lead && $lead == $id && $score != 5){
                    $score += ($score/2);
                }
                VtcMembers::addScores($id, $score, $target);
            }
        }
        return true;
    }

    public static function changeConvoyParticipants($id, $user_id, $participate){
        $convoy = Convoys::findOne($id);
        $participants = unserialize($convoy->participants);
        $new_participants = [
            '100' => [],
            '50' => [],
            '0' => [],
        ];
        if($participants){
            foreach ($participants as $key => $participant) {
                foreach ($participant as $index => $val) {
                    if ($val != $user_id) {
                        $new_participants[$key][] = $val;
                    }
                }
            }
        }
        $new_participants[$participate][] = intval($user_id);
        $convoy->participants = serialize($new_participants);
        return $convoy->update() !== false ? $new_participants : $participants;
    }

}