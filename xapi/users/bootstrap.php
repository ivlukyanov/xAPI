<?php

/**
 * Загрузка класса модуля xAPI для выполнения его методов и вызова других внешних узлов xAPI
 */

require_once _XAPI_ROOT . DIRECTORY_SEPARATOR . 'xapi_config.php'; // конфигурационный файл данного узла xAPI
require_once 'users_class.php'; // методы текущего модуля

define('_XAPI_CALL_DIRECTION', 'OUTCOMING'); // режим вызова узла xAPI: исходящий вызов
define('_XAPI_MODULE_NAME', 'users'); // название вызываемого модуля (для записи в логах)
define('_XAPI_DEVELOPMENT', isset($_SERVER['_XAPI_ENV']) ? true : false); // на production-сервере для уменьшения нагрузки в /xapi/.htaccess нужно закомментировать: "SetEnv XAPI_ENV DEVELOPMENT"

?>