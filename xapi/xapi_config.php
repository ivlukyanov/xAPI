<?php

/**
 * Конфигурационный файл узла xAPI
 */

// Отладка
error_reporting(0); // подавляем вывод ошибок, поскольку будем использовать свой обработчик ошибок

// Кодовые слова
define('_XAPI_MAIN_NAME', 'main'); // кодовое слово главного узла xAPI
defined('_XAPI_CURRENT_NAME') OR define('_XAPI_CURRENT_NAME', 'site1'); // кодовое слово узла xAPI данного сайта; может объявляться в тестовых скриптах

// Директории
defined('_XAPI_ROOT') OR define('_XAPI_ROOT', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR); // корневая папка данного узла xAPI
define('_XAPI_COMMON_DIR', _XAPI_ROOT . 'common' . DIRECTORY_SEPARATOR); // директория с функциями, общими для всех xAPI из SVN "api_common"
define('_XAPI_LOCAL_DIR', _XAPI_ROOT . 'local' . DIRECTORY_SEPARATOR); // директория с локальными функциями (используемыми только в текущем узле)
define('_XAPI_LOG_DIR', _XAPI_ROOT . 'logs' . DIRECTORY_SEPARATOR); // директория для хранения логов в БД формата SQLite

// Контакты
define('_XAPI_ADMIN_EMAIL', 'developer@site1.org'); // email для почтовых уведомлений об ошибках
define('_XAPI_ADMIN_SMS_EMAIL', '79001234567@sms.beeline.ru'); // email для коротких смс-уведомлений, зависит от провайдера сотовой связи
// подсказка: сервис sms.ru предоставляет разработчикам специальный email для отправки себе до 5 бесплатных sms в день

// Обработка ошибок
define('_XAPI_ERROR_REDIRECT_URL', '/oops'); // URI, на который перенаправляется пользователь сайта в случае ошибки в исходящем вызове узла xAPI этого сайта

// Настройки базы данных
$_XAPI_DB = array(
	'site1' => array( // основная база сайта
		'server'   => 'localhost',
		'base'     => 'digest',
		'user'     => 'digest_user',
		'password' => 'digest_pass'
	)
);

// Адреса узлов xAPI (ОБЯЗАТЕЛЬНО с https)
$_XAPI_SITES_URL = array(
	'main' => 'https://main.yourdomain.org/xapi/',
	'site1' => 'https://site1.local/xapi/', // для отладки своего узла тестовым скриптом
	'site2' => 'https://site2.local/xapi/', // для отладки своего узла тестовым скриптом
);

// Для узлов, встраиваемых в движок Kohana и аналогичные, которые очищают переменыне в глобальной область видимости,
// сохраняем переменные в суперглобальный массив напрямую
$GLOBALS['_XAPI_DB'] = $_XAPI_DB;
$GLOBALS['_XAPI_SITES_URL'] = $_XAPI_SITES_URL;

// Вызовы обязательных классов и функций
require_once _XAPI_COMMON_DIR . 'db_class.php'; // общий класс для работы с базой данных
require_once _XAPI_COMMON_DIR . 'common_class.php'; // общие функции, предназначенные для всех xAPI
require_once _XAPI_LOCAL_DIR . 'local_class.php'; // локальные функции, предназначенные только для модулей данного узла

// Регистрируем свои обработчики ошибок
xAPI_Common::set_errors_handler();

?>
