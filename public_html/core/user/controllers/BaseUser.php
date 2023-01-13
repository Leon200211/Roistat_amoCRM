<?php


namespace core\user\controllers;

//use core\admin\models\Model;
use core\base\controllers\BaseController;
use core\base\exceptions\RouteException;
use core\base\settings\Settings;
use Couchbase\PasswordAuthenticator;
use core\base\controllers\AmocrmController;


require_once $_SERVER['DOCUMENT_ROOT'] . PATH . 'libraries/functions.php';

// базовый контроллер для админки
abstract class BaseUser extends BaseController
{

    protected $model;  // для обращения к моделям
    protected $amoCRM;  // для обращения к amoCRM

    protected $table;
    protected $columns;
    protected $data;
    protected $foreignData;

    protected $userPath;

    protected $messages;  // путь к служебным сообщениям

    protected $menu;  // меню для админ панели
    protected $title;  // title для страницы

    protected $alias;  // для алиасов

    protected $fileArray; // массив для работы с файлами

    protected $translate;
    protected $blocks = [];

    protected $templateArr;
    protected $formTemplates;


    // разрешение на удаление
    protected $noDelete;

    protected function inputData(){

        $this->init(false);  // настраиваем internal_settings.php для админа

        $this->title = 'Fitlent engine';

        //if(!$this->model) $this->model = Model::getInstance();
        if(!$this->menu) $this->menu = Settings::get('projectTables');
        if(!$this->userPath) $this->userPath = PATH . Settings::get('routes')['user']['alias'];
        if(!$this->amoCRM) $this->amoCRM = AmocrmController::getInstance();


        if(!$this->templateArr) $this->templateArr = Settings::get('templateArr');
        if(!$this->formTemplates) $this->formTemplates = Settings::get('formTemplates');

        if(!$this->messages) $this->messages = include $_SERVER['DOCUMENT_ROOT'] . PATH . Settings::get('messages') . 'informationMessages.php';

        // запрет на кеширование админки
        $this->sendNoCacheHeaders();

    }


    // вывод шаблона
    protected function outputData(){


        if(!$this->content){
            $args = func_get_arg(0);
            $vars = $args ? $args : [];

            // доп проверка, можно убрать так как есть render в BaseController
            //if(!$this->template) $this->template = ADMIN_TEMPLATE . 'show';

            $this->content = $this->render($this->template, $vars);
        }

        $this->header = $this->render(TEMPLATE . 'include/header');
        $this->footer = $this->render(TEMPLATE . 'include/footer');

        return $this->render(TEMPLATE . 'layout/default');
    }


    // запрет на кеширование админки
    protected function sendNoCacheHeaders(){

        @header("Last-Modified: " . gmdate("D, d m Y H:i:s") . " GMT");
        @header("Cache-Control: no-cache, mush-revalidate");
        @header("Cache-Control: max-age=0");
        @header("Cache-Control: post-check=0,pre-check=0");  // for explore only

    }


    protected function execBase(){
        self::inputData();
    }


    protected  function createTableData($settings = false){

        if(!$this->table){
            if($this->parameters){
                $this->table = array_keys($this->parameters)[0];
            }else{
                if(!$settings){
                    $settings = Settings::getInstance();
                }
                $this->table = Settings::get('defaultTable');
            }
        }

        // вернет список всех полей из таблицы
        $this->columns = $this->model->showColumns($this->table);

        // если не пришли данные
        if(!$this->columns) new RouteException('Не найдены поля в таблице - ' . $this->table, 2);

    }





    // расширение для нашего фреймворка
    protected function expansion($args = [], $settings = false){

        // на всякий случай проверяем на наличие _ в названии таблицы
        $filename = explode('_', $this->table);
        $className = '';

        // Создаем имя для класс в нормализованном формате
        foreach ($filename as $item) $className .= ucfirst($item);


        if(!$settings){
            $path = Settings::get('expansion');
        }elseif(is_object($settings)){
            $path = $settings::get('expansion');
        }else{
            $path = $settings;
        }

        $class = $path . $className . 'Expansion';


        if(is_readable($_SERVER['DOCUMENT_ROOT'] . PATH . $class . '.php')){

            $class = str_replace('/', '\\', $class);

            $exp = $class::getInstance();


            // динамическое создание свойств у объекта
            foreach ($this as $name => $value) {
                $exp->$name = &$this->$name;
            }

            return $exp->expansion($args);

        }else{

            $file = $_SERVER['DOCUMENT_ROOT'] . PATH . $path . $this->table . '.php';

            extract($args);

            if(is_readable($file)){
                return include $file;
            }

        }

        return false;

    }


    // разбор колонок на блоки
    protected function createOutputData($settings = false){

        if(!$settings) $settings = Settings::getInstance();

        $blocks = $settings::get('blockNeedle');
        $this->translate = $settings::get('translate');

        if(!$blocks or !is_array($blocks)){

            foreach ($this->columns as $name => $item){
                // если айдишник, то пропускаем
                if($name === 'id_row') continue;

                if(!$this->translate[$name]) $this->translate[$name][] = $name;

                $this->blocks[0][] = $name;
            }

            return;

        }else{

            // определение дефолтного блока
            $default = array_keys($blocks)[0];

            foreach ($this->columns as $name => $item) {
                // если айдишник, то пропускаем
                if($name === 'id_row') continue;

                // проверяем, произошла ли вставка
                $insert = false;
                foreach ($blocks as $block => $value){
                    if(!array_key_exists($block, $this->blocks)){
                        $this->blocks[$block] = [];
                    }

                    // если произошла вставка
                    if(in_array($name, $value)){
                        $this->blocks[$block][] = $name;
                        $insert = true;

                        break;
                    }
                }

                if(!$insert) $this->blocks[$default][] = $name;
                if(!$this->translate[$name]) $this->translate[$name][] = $name;

            }

        }

        return;

    }


    // метод для работы с radio
    protected function createRadio($settings = false){

        if(!$settings) $settings = Settings::getInstance();

        $radio = $settings::get('radio');

        if($radio){
            foreach ($this->columns as $name => $item){
                if($radio[$name]){
                    $this->foreignData[$name] = $radio[$name];
                }
            }
        }

    }


    // Работа с данными из Post
    protected function checkPost($settings = false){

        // если метод Post
        if($this->isPost()){

            // валидация данных
            $this->clearPostFields($settings);



            // тут будет отправка в amo сrm
            $this->editData();

        }


    }


    // добавляет данные в сессию
    protected function addSessionData($arr){
        if(!$arr) $arr = $_POST;

        foreach ($arr as $key => $item){
            $_SESSION['res'][$key] = $item;
        }

        // редиректим пользователя
        $this->redirect();

    }


    // проверка на пустую строку
    protected function emptyFields($str, $answer, $arr = []){

        if(empty($str)){
            // добавляем ошибку
            $_SESSION['res']['answer'] = '<div class="error">' . $this->messages['empty'] . ' ' . $answer . '</div>';
            $this->addSessionData($arr);
        }

    }


    // проверка на кол-во символов
    protected function countChar($str, $counter, $answer, $arr){
        if(mb_strlen($str) > $counter){

            // если кол-во символов превышает, то выдаем ошибку
            $str_res = mb_str_replace('$1', $answer, $this->messages['count']);
            $str_res = mb_str_replace('$2', $counter, $str_res);

            // добавляем ошибку
            $_SESSION['res']['answer'] = '<div class="error">' . $str_res . '</div>';
            $this->addSessionData($arr);
        }
    }


    // обработка полученных полей из Post
    protected function clearPostFields($settings, &$arr = []){

        // в случае ошибки валидации, будет происходить редирект

        if(!$arr){
            // ссылка на суперглобальный массив POST
            $arr = &$_POST;
        }
        if(!$settings) $settings = Settings::getInstance();

        // идентификатор
        $id = isset($_POST[@$this->columns['id_row']]) ? $_POST[@$this->columns['id_row']] : false;

        $validation = $settings::get('validation');
        if(!$this->translate) $this->translate = $settings::get('translate');


        // проходимся по всем данным
        foreach ($arr as $key => $item){

            if(is_array($item)){
                $this->clearPostFields($settings, $item);
            }else{
                // если пришел числовой код
                if(is_numeric($item)){
                    $arr[$key] = $this->clearNum($item);
                }

                // начало валидации
                if(isset($validation)){

                    if(isset($validation[$key])){
                        // если есть псевдоним у поля
                        if(isset($this->translate[$key]) ){
                            $answer = $this->translate[$key][0];
                        }else{
                            $answer = $key;
                        }

                        // проверка на шифрование
                        if(isset($validation[$key]['crypt'])){
                            if($id){
                                if(empty($item)){
                                    // разрегистрация поля
                                    unset($arr[$key]);
                                    continue;
                                }

                                // кеширование
                                $arr[$key] = md5($item);

                            }
                        }

                        if(isset($validation[$key]['empty'])){
                            $this->emptyFields($item, $answer, $arr);
                        }

                        if(isset($validation[$key]['trim'])){
                            // зачищаем пробелы
                            $arr[$key] = trim($item);
                        }

                        if(isset($validation[$key]['int'])){
                            // переводим в int
                            $arr[$key] = $this->clearNum($item);
                        }

                        if(isset($validation[$key]['count'])){
                            $this->countChar($item, $validation[$key]['count'], $answer, $arr);
                        }


                    }

                }

            }

        }

        return true;

    }


    // редактирование или добавление новых данных после валидации
    protected function editData(){



        $this->amoCRM->amoCRMScript();


        $answerSuccess = $this->messages['addSuccess'];
        $answerFail = $this->messages['addFail'];
        // если получилось выполнить запрос
        if(true){
            $_SESSION['res']['answer'] = '<div class="success">' . $answerSuccess . '</div>';

            $this->redirect();

            return $_POST[$this->columns['id_row']];
        }else{  // если получили ошибку
            $_SESSION['res']['answer'] = '<div class="error">' . $answerFail . '</div>';

            $this->redirect();
        }

    }



    // метод создание чпу/алиасов
    protected function createAlias($id = false){

        if(isset($this->columns['alias'])){

            if(!isset($_POST['alias'])){

                if($_POST['name']){
                    $alias_str = $this->clearStr($_POST['name']);
                }else{
                    foreach ($_POST as $key => $item){
                        if(strpos($key, 'name') !== false and isset($item)){
                            $alias_str = $this->clearStr($item);
                            break;
                        }
                    }
                }

            }else{

                $alias_str = $_POST['alias'] = $this->clearStr($_POST['alias']);

            }


            $textModify = new \libraries\TextModify();
            $alias = $textModify->translit($alias_str);


            // проверка не существует ли в текущей таблицы такой ссылки
            $where['alias'] = $alias;
            $operand[] = '=';
            if($id){
                $where[$this->columns['id_row']] = $id;
                $operand[] = '!=';
            }
            $res_alias = $this->model->read($this->table, [
                'fields' => ['alias'],
                'where' => $where,
                'operand' => $operand,
                'limit' => '1'
            ])[0];


            // сохраняем измененный алиас
            if(!$res_alias){
                $_POST['alias'] = $alias;
            }else{
                $this->alias = $alias;
                $_POST['alias'] = '';
            }



            // для системы старых ссылок
            if($_POST['alias'] and $id){
                method_exists($this, 'checkOldAlias') && $this->checkOldAlias($id);
            }


        }

    }


    // метод проверки чпу/алиасов
    protected function checkAlias($id): bool {

        if($id){
            // если такой алиас уже есть, то в новый дописываем id
            if($this->alias){
                $this->alias .= '-' . $id;

                $this->model->update($this->table, [
                    'fields' => ['alias' => $this->alias],
                    'where' => [$this->columns['id_row'] => $id]
                ]);

                return true;
            }
        }

        return false;

    }


    // метод обработка файлов
    protected function createFile(){

        $fileEdit = new FileEdit();
        $this->fileArray = $fileEdit->addFile();


    }



    // метод для работы с позициями в меню
    protected function updateMenuPosition(){

    }


    // метод формирующий поля исключения при добавлении в БД
    protected function checkExceptFields($arr = []){

        if(!$arr) $arr = $_POST;

        $except = [];

        if($arr){
            foreach ($arr as $key => $item){
                if(!$this->columns[$key]){
                    $except[] = $key;
                }
            }
        }

        return $except;

    }




}