<?php


namespace core\base\controllers;

use AmoCRM\Exception;


// контроллер для работы с amoCRM
class AmocrmController
{

    protected $subdomin;
    protected $client_secret;
    protected $client_id;
    protected $code;
    protected $token_file;
    protected $redirect_uri;

    

    use Singleton;

    private function __construct(){

        // тут создание подключения


    }



    function amoCRMScript() {

        /* получаем значения токенов из файла */
        $dataToken = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/amo/tokens.txt');
        $dataToken = json_decode($dataToken, true);

        /* проверяем, истёкло ли время действия токена Access */
        if($dataToken["endTokenTime"] < time()) {
            /* запрашиваем новый токен */
            $dataToken = $this->returnNewToken($dataToken["refresh_token"]);
            $newAccess_token = $dataToken["access_token"];
        }
        else {
            $newAccess_token = $dataToken["access_token"];
        }

        $idContact = $this->amoAddContact($newAccess_token);
        $this->amoAddTask($newAccess_token, $idContact);

    }


    function amoAddContact($access_token) {


        $contacts['request']['contacts']['add'] = array(
            [
                'name' => $_POST['customer_name'],
                'tags' => 'авто отправка',
                'custom_fields'	=> [
                    // ИМЯ ПОЛЬЗОВАТЕЛЯ
                    [
                        'id'	=> 518661,
                        "values" => [
                            [
                                "value" => $_POST['customer_name'],
                            ]
                        ]
                    ],
                    // ТЕЛЕФОН
                    [
                        'id'	=> 518139,
                        "values" => [
                            [
                                "value" => $_POST['phone'],
                            ]
                        ]
                    ],
                    // EMAIL
                    [
                        'id'	=> 518595,
                        "values" => [
                            [
                                "value" => $_POST['email'],
                            ]
                        ]
                    ],
                ]
            ]
        );


        /* Формируем заголовки */
        $headers = [
            "Accept: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        $link='https://leon20022018.amocrm.ru/private/api/v2/json/contacts/set';

        $curl = curl_init(); //Сохраняем дескриптор сеанса cURL
        /** Устанавливаем необходимые опции для сеанса cURL  */
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($contacts));
        curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out=curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
        $code=curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        $Response=json_decode($out,true);
        $account=$Response['response']['account'];
        echo '<b>Данные о пользователе:</b>'; echo '<pre>'; print_r($Response); echo '</pre>';

        return $Response["response"]["contacts"]["add"]["0"]["id"];

    }




    function amoAddTask($access_token, $contactId = false) {


        $arrTaskParams = [
            'add' => [
                0 => [
                    'name'  => 'Тестовая',
                    'price'         => $_POST['price'],
                    'pipeline_id'   => '9168',
                    'tags'          => [
                        'авто отправка',
                        'Тестовая'
                    ],
                    'status_id'     => '10937736',
                    'custom_fields'	=> [
                        /* ИМЯ ПОЛЬЗОВАТЕЛЯ */
                        [
                            'id'	=> 525741,
                            "values" => [
                                [
                                    "value" => $_POST['customer_name'],
                                ]
                            ]
                        ],
                        /* ТЕЛЕФОН */
                        [
                            'id'	=> 525687,
                            "values" => [
                                [
                                    "value" => $_POST['phone'],
                                ]
                            ]
                        ],
                        /* EMAIL */
                        [
                            'id'	=> 525739,
                            "values" => [
                                [
                                    "value" => $_POST['email'],
                                ]
                            ]
                        ],
                    ],

                    'contacts_id' => [
                        0 => $contactId,
                    ],
                ],
            ],
        ];


        $link = "https://leon20022018.amocrm.ru/api/v2/leads";

        $headers = [
            "Accept: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_USERAGENT, "amoCRM-API-client-undefined/2.0");
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($arrTaskParams));
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_HEADER,false);
        curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__)."/cookie.txt");
        curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__)."/cookie.txt");
        $out = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($out,TRUE);

    }


    /* в эту функцию мы передаём текущий refresh_token */
    function returnNewToken($token) {

        $link = 'https://leon20022018.amocrm.ru/oauth2/access_token'; //Формируем URL для запроса

        /** Соберем данные для запроса */
        $data = [
            'client_id' => 'xxxxx',
            'client_secret' => 'xxxxx',
            'grant_type' => 'refresh_token',
            'refresh_token' => $token,
            'redirect_uri' => 'http://roistat.amocrm/',
        ];

        /**
         * Нам необходимо инициировать запрос к серверу.
         * Воспользуемся библиотекой cURL (поставляется в составе PHP).
         * Вы также можете использовать и кроссплатформенную программу cURL, если вы не программируете на PHP.
         */
        $curl = curl_init(); //Сохраняем дескриптор сеанса cURL
        /** Устанавливаем необходимые опции для сеанса cURL  */
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        /** Теперь мы можем обработать ответ, полученный от сервера. Это пример. Вы можете обработать данные своим способом. */
        $code = (int)$code;
        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try
        {
            /** Если код ответа не успешный - возвращаем сообщение об ошибке  */
            if ($code < 200 || $code > 204) {
                throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
            }
        } catch(\Exception $e) {
            die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
        }

        /**
         * Данные получаем в формате JSON, поэтому, для получения читаемых данных,
         * нам придётся перевести ответ в формат, понятный PHP
         */

        $response = json_decode($out, true);

        if($response) {

            /* записываем конечное время жизни токена */
            $response["endTokenTime"] = time() + $response["expires_in"];

            $responseJSON = json_encode($response);

            /* передаём значения наших токенов в файл */
            $filename = $_SERVER['DOCUMENT_ROOT'] . '/amo/tokens.txt';
            $f = fopen($filename,'w');
            fwrite($f, $responseJSON);
            fclose($f);

            $response = json_decode($responseJSON, true);

            return $response;
        }
        else {
            return false;
        }

    }


}