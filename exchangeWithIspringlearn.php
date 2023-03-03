<?
ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
ini_set('error_reporting', E_ALL);

require_once '/home/bitrix/www/local/webhooks/auth.php';

$isWhile = 'Y';

/*
Отдельный файл нужен для того, чтобы хранить последнюю страницу, которую опрашивали, чтобы каждый раз не опрашивать все назначения.
Выгрузка назначений работает на кроне ежедневно.
 */
$filename = '/home/bitrix/www/local/webhooks/ispring/page.log';

// Авторизация в Айспринг, хранить в секрете. Нужны доступы администратора, что корректно работала выгрузка.
$arHeader = [
    "X-Auth-Account-Url: https://yourportal.ispringlearn.ru/",
    "X-Auth-Email: test@test.ru",
    "X-Auth-Password: **********",
];

while ($isWhile == 'Y') {

    $data = file_get_contents($filename);
    $pageToken = json_decode($data, false); // Если нет TRUE то получает объект, а не массив.

    // Блок получения назначений
    $curl = curl_init('https://api-learn.ispringlearn.ru/enrollments?pageSize=50&pageToken=' . $pageToken);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $arHeader);

    $response = curl_exec($curl);
    $xml = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA);
    $json = json_encode($xml);
    $arrayForEnroll = json_decode($json, true);

    if (!empty($arrayForEnroll['nextPageToken'])) {
        $data = json_encode($arrayForEnroll['nextPageToken']); // JSON формат сохраняемого значения.
        file_put_contents($filename, $data);
    } else {
        $isWhile = 'N';
    }

    // Перебираем назначения.
    foreach ($arrayForEnroll['enrollments']['enrollment'] as $valueOfEnroll) {
        $courseId = $valueOfEnroll['courseId']; // ид курса
        $student = $valueOfEnroll['learnerId']; // ид ученика
        $dateStart = $valueOfEnroll['accessDate']; // дата начала
        $dateFinish = $valueOfEnroll['dueDate']; // дедлайн
        $idOfEnroll = $valueOfEnroll['enrollmentId']; // ид назначения

        // Блок получения курса на основании назначений
        $curl = curl_init('https://api-learn.ispringlearn.ru/content/' . $courseId);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $arHeader);

        $response = curl_exec($curl);
        $xml = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $arrayForCourse = json_decode($json, true);

        writeToLog($arrayForCourse);

        $courseName = $arrayForCourse['contentItem']['title']; // название курса
        $courseURL = $arrayForCourse['contentItem']['viewUrl']; // урл курса

        // Блок получения юзера на основании назначения
        $curl = curl_init('https://api-learn.ispringlearn.ru/user/' . $student);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $arHeader);

        $response = curl_exec($curl);
        $xml = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $arrayForStudent = json_decode($json, true);

        $searchStudentEmail = $arrayForStudent['userProfile']['fields']['field'];
        // Вытаскиваем почту из карты пользователя Айспринг
        foreach ($searchStudentEmail as $valueOfStudendEmail) {
            if ($valueOfStudendEmail['name'] == 'EMAIL') {
                $studentEmail = $valueOfStudendEmail['value'];
                break;
            }
        }

        // Собираем массив, чтобы начать взаимодействие с Б24
        $allElements[] = array('EMAIL' => $studentEmail, 'COURSE' => $courseName, 'DATE_START' => $dateStart, 'DATE_FINISH' => $dateFinish, 'URL' => $courseURL, 'ID' => $idOfEnroll);

    }
}

foreach ($allElements as $value) {

    /*
    Сопоставление пользователя Б24 и почты Айспринг.
    Так как, почта в карточке пользователя Б24 не совпадает с почтой в Айспринг, создан отдельный УС, где пользователь Б24 и почта Айспринг сопоставлены.
     */

    $UserGet1 = executeREST(
        'lists.element.get',
        array(
            'IBLOCK_TYPE_ID' => 'lists',
            'IBLOCK_ID' => '112',
            'ELEMENT_ORDER' => array(
                "ID" => "DESC",
            ),
            'FILTER' => array(
                'NAME' => $value['EMAIL'],
            ),
        ),
        $domain, $auth, $user);

    if (!empty($UserGet1['result'][0]['PROPERTY_533'])) {

        foreach ($UserGet1['result'][0]['PROPERTY_533'] as $valueList) {
            $userId = $valueList;
        }
        // Проверка, не ставили ли мы уже задачу сотруднику на прохождение курса.
        $listGet = executeREST(
            'lists.element.get',
            array(
                'IBLOCK_TYPE_ID' => 'lists',
                'IBLOCK_ID' => '109',
                'ELEMENT_ORDER' => array(
                    "ID" => "DESC",
                ),
                'FILTER' => array(
                    'PROPERTY_524' => $value['ID'],
                ),
            ),
            $domain, $auth, $user);
        // Если не ставили, то создаем элемент УС и запускаем БП, который поставит задачу и событие в календарь пользователя
        if (empty($listGet['result'])) {
            $random = random_bytes(10);
            $random = (bin2hex($random));
            $elementAdd = executeREST(
                'lists.element.add',
                array(
                    'IBLOCK_TYPE_ID' => 'lists',
                    'IBLOCK_ID' => '109',
                    'ELEMENT_CODE' => $random,
                    'FIELDS' => array(
                        'NAME' => $value['COURSE'],
                        'PROPERTY_520' => $userId,
                        'PROPERTY_521' => $value['DATE_START'],
                        'PROPERTY_522' => $value['DATE_FINISH'],
                        'PROPERTY_523' => $value['URL'],
                        'PROPERTY_524' => $value['ID'],

                    ),
                ),
                $domain, $auth, $user);

            $startworkflow = executeREST(
                'bizproc.workflow.start',
                array(
                    'TEMPLATE_ID' => '1897',
                    'DOCUMENT_ID' => array(
                        'lists', 'Bitrix\Lists\BizprocDocumentLists', $elementAdd['result'],
                    ),
                    'PARAMETERS' => array(
                    ),
                ),
                $domain, $auth, $user);
        }
    } else {
        // Уведомление админа на тот случай, если сотрудник есть в Айспринг, но его нет в УС.
        $notify = executeREST(
            'im.notify.system.add',
            array(
                'USER_ID' => '1',
                'MESSAGE' => 'Для почты ' . $value['EMAIL'] . ' не найден пользователь в Б24.',
            ),
            $domain, $auth, $user);
    }
}

function executeREST($method, array $params, $domain, $auth, $user)
{
    $queryUrl = 'https://' . $domain . '/rest/' . $user . '/' . $auth . '/' . $method . '.json';
    $queryData = http_build_query($params);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));
    return json_decode(curl_exec($curl), true);
    curl_close($curl);
}

function writeToLog($data, $title = '')
{
    $log = "\n------------------------\n";
    $log .= date("Y.m.d G:i:s") . "\n";
    $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
    $log .= print_r($data, 1);
    $log .= "\n------------------------\n";
    file_put_contents(getcwd() . '/ispringlearn.log', $log, FILE_APPEND);
    return true;
}
