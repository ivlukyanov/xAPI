<?
/*
 * Created by Ivan Lukyanov.
 * Date: 12.01.2012
 *
 * В движке сайта, для которого подключается данный узел xAPI,
 * объявляем свою функцию автозагрузки классов
 */
function xAPI_autoload($class_name) {
	// название класса xAPI обязательно должно начинаться с 'xAPI_'
	if (substr($class_name, 0, 5) === 'xAPI_') {
		$module_name = strtolower(substr($class_name, 5)); // получаем название директории модуля из названия класса

		// Необходимо правильно указать полный путь к корневой папке узла xAPI!
		// Учтите, что при вызове xAPI каким-либо образом из шелла (командной строки - CLI), переменная $_SERVER['DOCUMENT_ROOT'] пустая
		defined('_XAPI_ROOT') OR define('_XAPI_ROOT', $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'xapi' . DIRECTORY_SEPARATOR); // корневая папка узла xAPI

		$file_name = _XAPI_ROOT . $module_name . DIRECTORY_SEPARATOR . 'bootstrap.php'; // предполагаемый путь к файлу подгрузки класса модуля

		if (!file_exists($file_name)) { // если файл не существует, выдадим ошибку
			trigger_error('PHP-file for autoload class ' . $class_name . ' not found!', E_USER_ERROR);
		}
		require_once $file_name; // в bootstrap.php объявляются константы модуля и подгружается сам файл класса
	}
}

// Устанавливаем объявленную функцию как обработчик автозагрузки для классов xAPI
spl_autoload_register('xAPI_autoload');
