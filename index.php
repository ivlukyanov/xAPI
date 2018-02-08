<?php

/* Обертка узла xAPI для обращений к нему из вне */

require_once 'xapi/xapi_config.php'; // конфигурационный файл данного узла xAPI


// 0.1 Объявляем константы

// Проверяем, передано ли название модуля, оно должно быть передано в GET-запросе
if (empty($_GET['module'])) {
	xAPI_Common::handle_error('Ne peredano nazvanie modulya', 'Incoming Call');
}

// Режим вызова узла xAPI: входящий вызов
define('_XAPI_CALL_DIRECTION', 'INCOMING');
// Объявляем название текущего модуля
define('_XAPI_MODULE_NAME', strtolower($_GET['module'])); // название вызываемого модуля (для записи в логах)
// Если узел не на production-сервере, будем вести лог запросов для отладки в процессе разработки
defined('_XAPI_DEVELOPMENT') OR define ('_XAPI_DEVELOPMENT', isset($_SERVER['_XAPI_ENV']) ? true : false); // на develop-сервере в /xapi/.htaccess должно быть: "SetEnv XAPI_ENV DEVELOPMENT"

// 0.2 Объявляем переменные событий для логов и уведомлений

$operation_type = 'Incoming Call in Module ' . ucfirst(strtolower(_XAPI_MODULE_NAME)); // тип этой операции для уведомления об ошибке
$event_type = 'module_call'; // будет использовано для названия таблицы в базе SQLite для логов

// Проверяем, передано ли действие модуля, оно также должно быть передано в GET-запросе
if (empty($_GET['act'])) {
	xAPI_Common::handle_error('Ne peredan parametr deistviya modulya', $operation_type);
}
$event_key = _XAPI_MODULE_NAME . '/' . $_GET['act']; // краткая ключевая фраза события для записи в лог


// 1.1 Проверяем остальные обязательные параметры
if (empty($_GET['from'])) {
	xAPI_Common::handle_error('Ne ukazano imya vyzyvayuschego uzla xAPI', $operation_type);
}

// 1.2 Пишем в лог входные параметры при разработке
if (_XAPI_DEVELOPMENT) {
	xAPI_Common::write_log($event_type, $event_key, "=> INPUT: " . xAPI_Common::array_to_log_string($_REQUEST) . "(FROM: " . $_SERVER['REMOTE_ADDR'] . ")");
}


// 2.1 Готовимся вызвать запрошенное действие нужного модуля (метод класса)
$class_name = 'xAPI_' . ucfirst(_XAPI_MODULE_NAME); // формируем название класса модуля
require_once _XAPI_ROOT . _XAPI_MODULE_NAME . DIRECTORY_SEPARATOR . _XAPI_MODULE_NAME . '_class.php'; // подгружаем класс модуля
$action_name = $_GET['act']; // параметр действия модуля (метод класса) должен быть получен в GET-запросе
$from_site = $_GET['from']; // объявляем имя обратившегося узла для работы с ним в методах (через global)

// 2.2 Если переданы дополнительные параметры запроса (аргументы вызываемого метода),
// то они должны быть в формате JSON, декодируем их

if (!empty($_POST['params'])) {
	$params_array = get_magic_quotes_gpc() ? stripslashes($_POST['params']) : $_POST['params']; // проверяем невыключенный magic quotes
	$params_array = json_decode($params_array, true);
	// 2.3 Проверим, нормально ли декодированы параметры
	if (is_null($params_array)) {
		xAPI_Common::handle_error('V POST-zaprose peredana nevernaya JSON-stroka (v elemente \'params\' massiva POST)', $operation_type);
	}
}

// 2.3 Вызываем метод класса модуля по его названию. Переданный массив параметров будет отсортирован в том порядке, в котором они (параметры) описаны в методе
$return_array = xAPI_Common::call_user_class_method($class_name, $action_name, $params_array);
// ВНИМАНИЕ: здесь мы передаем массив $_REQUEST для того, чтобы была возможность удаленной отладки,
// дописывая легковесные параметры к GET-запросу прямо из строки браузера.


// 3.1 Проверяем ответ метода класса
if (!$return_array) {
	xAPI_Common::handle_error('Ne vypolnen metod klassa modulya. Vozmojno, ukazan nevernyi parametr deistviya', $operation_type);
}

// 3.2 Пишем в лог при разработке
if (_XAPI_DEVELOPMENT) {
	// Запросы к БД
	xAPI_Common::write_log($event_type, $event_key, "<!> SQL QUERIES: \n" . xAPI_Common::sql_log_array_to_string(xAPI_DB::get_all_logs()));
	// Выходные параметры до кодирования в JSON
	xAPI_Common::write_log($event_type, $event_key, "<= OUTPUT: " . xAPI_Common::array_to_log_string($return_array));
}


// 4. Возвращаем кодированный ответ в JSON
xAPI_Common::final_output($return_array);

?>