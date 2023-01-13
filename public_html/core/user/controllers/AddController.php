<?php


namespace core\user\controllers;

use core\base\settings\Settings;



// контроллер для добавление информации через админ панель
class AddController extends BaseUser
{

    // определение действия
    protected $action = 'add';

    protected function inputData()
    {

        if(!$this->userId) $this->execBase();


        // Работа с данными из Post
        $this->checkPost();




    }



}