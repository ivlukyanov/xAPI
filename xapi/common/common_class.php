<?php

/**
 * Класс общих методов для всех узлов xAPI. Доступен в общем репозитории.
 */
class xAPI_Common {

	private static $error_type = array(
		E_ERROR             => 'ERROR',
		E_WARNING           => 'WARNING',
		E_PARSE             => 'PARSING ERROR',
		E_NOTICE            => 'NOTICE',
		E_CORE_ERROR        => 'CORE ERROR',
		E_CORE_WARNING      => 'CORE WARNING',
		E_COMPILE_ERROR     => 'COMPILE ERROR',
		E_COMPILE_WARNING   => 'COMPILE WARNING',
		E_USER_ERROR        => 'USER ERROR',
		E_USER_WARNING      => 'USER WARNING',
		E_USER_NOTICE       => 'USER NOTICE',
		E_STRICT            => 'STRICT NOTICE',
		E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR'
	);

// ------- ОБЩЕГО НАЗНАЧЕНИЯ -------

	/**
	 * Вызывает метод указанного класса и передает ему массив параметров, отсортированный в том порядке, в каком они описаны в методе.
	 * Если некоторые из переданных в массиве параметров не присутствуют в исходном методе, они будут игнорироваться.
	 * Не может работать с методами, в которые параметры передаются по ссылке!
	 * @param string $class : Название класса для вызова
	 * @param string $method : Название метода для вызова
	 * @param array $params_array : Ассоциативный(!) массив параметров, передаваемых в метод
	 */
	static function call_user_class_method($class, $method, $params_array) {

		// Тип этой операции для уведомления об ошибке
		$operation_type = 'Class Method Call in Module ' . strtoupper(_XAPI_MODULE_NAME); // _XAPI_MODULE_NAME должна быть объявлена в /xapi/index.php и в /xapi/MODULE_NAME/bootstrap.php

		// Проверяем существует ли метод класса
		if (!method_exists($class, $method)) {
			self::handle_error("Ukazan nesuschestvuyuschii metod $method klassa $class", $operation_type);
		}

		$reflectMethod = new ReflectionMethod($class, $method); // Класс ReflectionMethod сообщает информацию о методах
		$real_params = array(); // Массив для передачи параметров в метод

		foreach ($reflectMethod->getParameters() as $count_params => $reflectParam) // Получаем параметры по порядку их описания
		{
			$param_name = $reflectParam->getName(); // Получаем имя параметра
			if (array_key_exists($param_name, $params_array)) { // Ищем параметр в исходном массиве и добавляем в массив для вызовы
				$real_params[] = $params_array[$param_name];
			}
			elseif ($reflectParam->isDefaultValueAvailable()) { // Определяем пропущенный необязательный параметр
				$real_params[] = $reflectParam->getDefaultValue();
			}
			else {
				// Ошибка: пропущен обязательный параметр метода
				self::handle_error("Propuschen obyazatel'nyi " . ($count_params + 1) . "-i parametr '$param_name' v metode $method klassa $class", $operation_type);
			}
		}
		return call_user_func_array(array($class, $method), $real_params);
	}

	/**
	 * Для удобства обработки нескольких переменных в одной строке кода.
	 * Обрабатывает переданные (по ссылке) переменные указанной функцией.
	 * Если одна из переменных является массивом - то рекурсивно обрабатываются все его элементы.
	 * Максимум: 10 параметров (после названия функции), последующие просто не примут значение, т.к. должны быть приняты по ссылке.
	 * Для большего количества нужно добавить еще аргументы в описание этой функции.
	 * @param callback $function : Имя вызываемой функции
	 * @param mixed $param : Любая переменная или массив
	 * */
	static function process_vars($function, &$p1, &$p2 = '', &$p3 = '', &$p4 = '', &$p5 = '',
	                             &$p6 = '', &$p7 = '', &$p8 = '', &$p9 = '', &$p10 = '') {
		$num_params = func_num_args();
		for ($i = 1; $i < $num_params; $i++) { // максимум 10 параметров
			$param_name = 'p' . ($i);
			$param = &$$param_name;
			if (is_array($param)) {
				foreach ($param as $key => $value) {
					self::process_vars($function, $param[$key]);
				}
			} else {
				$param = call_user_func($function, $param);
			}
		}
	}


// ------- ВЫЗОВЫ УЗЛОВ -------

	/**
	 * Вызов удаленного узла xAPI по https-протоколу
	 * @param string $remote_site_name : Кодовое слово сайта, узел xAPI которого вызываем
	 * @param string $remote_module_name : Название модуля удаленного узла xAPI
	 * @param string $remote_action_name : Параметр действия модуля удаленного узла xAPI
	 * @param array $params_array : Массив переменных для отправки в модуль удаленного узла xAPI
	 * @param bool $display_debug_info : Выводить на экран информацию для отладки функции
	 */
	static function call($remote_site_name, $remote_module_name, $remote_action_name, $params_array, $display_debug_info = false) {

		global $_XAPI_SITES_URL;

		$operation_type = 'Outcoming Call from Module ' . strtoupper(_XAPI_MODULE_NAME);

		// 1. Проверка входных параметров
		if (empty($remote_site_name)) {
			self::handle_error('Ne ukazano imya vyzyvaemogo uzla xAPI', $operation_type);
		} elseif (empty($remote_module_name)) {
			self::handle_error('Ne ukazano nazvanie vyzyvaemogo modulya', $operation_type);
		} elseif (empty($remote_action_name)) {
			self::handle_error('Ne peredan parametr deistviya vyzyvaemogo modulya', $operation_type);
		}
		if ($display_debug_info) { // отладка, шаг 1
			echo "<pre>\n>>> (1). Параметры запроса: >>>\n";
			var_dump($remote_module_name, $remote_action_name, $params_array);
		}

		// 2. Составление URL запроса
		if (!array_key_exists($remote_site_name, $_XAPI_SITES_URL)) {
			self::handle_error("V massive _XAPI_SITES_URL v confige net adresa vyzyvaemogo saita: $remote_site_name", $operation_type);
		}
		$called_url = $_XAPI_SITES_URL[$remote_site_name] . '?module=' . $remote_module_name . '&act=' . $remote_action_name . '&from=' . _XAPI_CURRENT_NAME;
		if ($display_debug_info) { // отладка, шаг 2
			echo "\n>>> (2). URL-адрес для запроса: >>>\n";
			var_dump($called_url);
		}

		// 3. Вызов удаленной функции и получение ответа
		$params_array_JSON = array('params' => json_encode($params_array));
		$call_response_JSON = self::curl_POST($called_url, $params_array_JSON);
		if (!$call_response_JSON[0]) { // проверяем ответ
			self::handle_error('Pustoi otvet ot vyzyvaemogo uzla xAPI. Error: ' . $call_response_JSON[2] . ' URL: _' . $called_url, $operation_type);
		}
		if ($display_debug_info) { // отладка, шаг 3
			echo "\n<<< (3). Otvet udalennogo uzla xAPI v formate JSON: <<<\n";
			var_dump($call_response_JSON[2]);
		}

		// 4. Распаковка массива параметров из переменной формата JSON
		$result_array = json_decode($call_response_JSON[2], true);
		if ($display_debug_info) { // отладка, шаг 4
			echo "\n<<< (4). Raspakovannye parametry otveta: <<<\n";
			var_dump($result_array);
			echo "</pre>";
		}

		// 5.1 Проверим, нормально ли декодирован ответ
		if (is_null($result_array)) {
			self::handle_error('Udalennyi uzel vozvratil nevernuyu JSON-stroku', $operation_type);
		}
		// 5.2 Проверим формат ответа
		if (!is_array($result_array) || count($result_array) < 1) { // ответ обязательно должен быть в виде массива и, как минимум, с 1 элементом
			self::handle_error('Nevernyi format otveta (massiv) ot vyzyvaemogo uzla xAPI', $operation_type);
		}

		return $result_array;
	}

	/**
	 * Вызов URL-адреса через curl() с передачей параметров по методу POST
	 * @param string $called_url : Удаленный URL для вызова
	 * @param array $params : Параметры для отправки по методу POST
	 * @return string : Ответ вызова или сообщение об ошибке
	 */
	static function curl_POST($called_url, $params) {

		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL, $called_url); // уcтанавливаем адрес, к которому обратимся
		curl_setopt($curl_handle, CURLOPT_HEADER, 0); // отключаем вывод заголовков в ответе
		curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0); // отключаем проверку SSL-сертификата (пока не приобретен, используем самоподписанный)
		curl_setopt($curl_handle, CURLOPT_POST, 1); // устанавливаем метод POST
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $params); // устанавливаем параметры, которые будет переданы по методу POST
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1); // запрещаем вывод на экран
		curl_setopt($curl_handle, CURLOPT_USERAGENT, 'xAPI'); // подпишем себя в логах веб-сервера
		$response = curl_exec($curl_handle);
		if (!$response) { // проверяем ответ
			$curl_error = curl_error($curl_handle) . '(' . curl_errno($curl_handle) . ')';
			curl_close($curl_handle); // закрываем соединение
			return array(false, 'curl_error', $curl_error);
		}
		curl_close($curl_handle); // закрываем соединение

		return array(true, 'curl_success', $response);
	}

	/**
	 * Вывод ответа удаленному узлу в массиве формата JSON и завершение работы
	 * @param array $event_array : Массив должен содержать 3 элемента:
	 *      Тип ответа - успех/неудача (true/false)
	 *      Краткое кодовое слово события (с нижним подчеркиванием) для разбора удаленным узлом
	 *      Переменная или массив переменных ответа функции, либо развернутый текст ошибки
	 * @return void
	 */
	static function final_output($event_array) {
		switch (_XAPI_CALL_DIRECTION) {
			case 'OUTCOMING':
				header('Location: ' . _XAPI_ERROR_REDIRECT_URL);
				exit;
				break;
			case 'INCOMING':
			default:
				exit(json_encode($event_array));
				break;
		}
	}


	// ------- ОБРАБОТКА ОШИБОК -------

	/**
	 * Уведомляет разработчика о перехваченной ошибке уровня программы по Email (и SMS, если указан).
	 * Т.е. данный метод вызывается только вручную в коде, после проверок различных состояний и ответов функций.
	 * Записывает в лог переданные параметры ошибки функции или запроса к БД.
	 * @param string $error_message : Текст ошибки для записи в лог и уведомления
	 * @param string $operation : Название операции
	 * @return none
	 * Выводит на экран массив в формате JSON: false, краткий код и подробный текст ошибки,
	 * и завершает работу скрипта
	 * */
	static function handle_error($error_message, $operation_type) {
		// получаем стек вызовов
		$debug_dump_string = self::backtrace_to_string(debug_backtrace());

		$event_type = 'xapi_error'; // будет использовано для названия таблицы лога в базе SQLite

		// формируем запись строки лога
		$log_string = "Error: $error_message\nOperation: $operation_type";

		if ($operation_type == 'DB') { // SQL-ошибки обрабатываем иначе
			$event_key = 'sql'; // тип события в таблице лога
			// получим последние запросы к БД
			$log_string .= "\n\nSQL Queries:\n" . self::sql_log_array_to_string(xAPI_DB::get_all_logs());
		} else {
			$event_key = 'xapi'; // тип события в таблице лога
		}

		$log_string .= "\n\n" . $debug_dump_string;

		// записываем лог в базу SQLite
		self::write_log($event_type, $event_key, $log_string);

		// отправляем уведомление разработчику
		self::send_email_notice($log_string);

		// выводим в ответ массив в формате JSON: ошибку, её краткий код и пояснение
		// массив будет выведен как для входящего вызова (удаленному узлу xAPI), так и для исходящего вызова (сайту)
		self::final_output(array(false, $event_type, "Operation: $operation_type. Error: $error_message"));
	}

	/**
	 * Обрабатывает события, генерируемые движком PHP, типа E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE.
	 * Пишет в лог и отправляет уведомление разработчику по Email (и SMS, если указан).
	 * Данный метод вызывается автоматически, если зарегистрирован при помощи set_error_handler(array('xAPI_Common', 'warnings_catcher'))
	 * @param string $error_message : Текст ошибки для записи в лог и уведомления
	 * @param string $operation : Название операции
	 * @return true : Для того, чтобы не передавать управление ошибкой встроенному обработчику в PHP
	 * */
	static function warnings_catcher($errno, $errstr, $errfile, $errline) {

		$event_type = 'warnings'; // будет использовано для названия таблицы лога в базе SQLite

		// формируем запись строки лога
		$event_key = array_key_exists($errno, self::$error_type) ? self::$error_type[$errno] : 'CAUGHT WARNING';
		$log_string = "ERROR: $errstr\nFILE: $errfile\nLINE: $errline";

		// записываем в лог-файл
		self::write_log($event_type, $event_key, $log_string);

		// отправляем уведомление разработчику
		self::send_email_notice("EVENT: $event_key\n" . $log_string);

		return true; // возвращаем true, чтобы не передавать управление ошибкой встроенному обработчику в PHP
	}

	/**
	 * Обрабатывает критический события, по которым работа скрипта завершается.
	 * Тип событий: E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_RECOVERABLE_ERROR
	 * Пишет в лог и отправляет уведомление разработчику по Email (и SMS, если указан).
	 * И под конец, при завершении работы выводит вызывающему узлу сообщение об ошибке в формате JSON:
	 *      false, краткий код и подробный текст ошибки
	 * Данный метод вызывается автоматически, если зарегистрирован при помощи register_shutdown_function(array('xAPI_Common', 'fatal_error_catcher'));
	 * @param string $error_message : Текст ошибки для записи в лог и уведомления
	 * @param string $operation : Название операции
	 * @return none
	 * */
	static function fatal_error_catcher() {

		$last_error = error_get_last();
		if (!empty($last_error) && ($last_error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_RECOVERABLE_ERROR))) {

			$event_type = 'fatal_errors'; // будет использовано для названия таблицы лога в базе SQLite

			// формируем запись строки лога
			$event_key = array_key_exists($last_error['type'], self::$error_type) ? self::$error_type[$last_error['type']] : 'CAUGHT FATAL ERROR';
			$log_string = "ERROR: {$last_error['message']}\nFILE: {$last_error['file']}\nLINE: {$last_error['line']}";

			// записываем в лог-файл
			self::write_log($event_type, $event_key, $log_string);

			// отправляем уведомление разработчику
			self::send_email_notice("EVENT: $event_key\n" . $log_string);

			// выводим в ответ массив в формате JSON: ошибку, её краткий код и пояснение
			// массив будет выведен как для входящего вызова (удаленному узлу xAPI), так и для исходящего вызова (сайту)
			self::final_output(array(false, $event_type, "Event: $event_key. Message: {$last_error['message']}"));
		}
	}

	/**
	 * Регистрирует свой обработчик ошибок (E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE),
	 * и свой обработчик при фатальном завершении работы скрипта (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_RECOVERABLE_ERROR)
	 * Вызывается в xapi_config.php в конце скрипта.
	 * @param string $error_message : Текст ошибки для записи в лог и уведомления
	 * @param string $operation : Название операции
	 * @return none
	 * */
	static function set_errors_handler() {

		// регистрируем свой обработчик ошибок для их логгирования
		defined('_XAPI_DEVELOPMENT') OR define ('_XAPI_DEVELOPMENT', isset($_SERVER['_XAPI_ENV']) ? true : false);
		if (_XAPI_DEVELOPMENT) { // только при разработке (включается в /xapi/.htaccess при помощи "SetEnv _XAPI_ENV DEVELOPMENT")
			set_error_handler(array('xAPI_Common', 'warnings_catcher'));
		}

		// регистрируем функцию, вызываемую при завершении скрипта, для перехвата критических ошибок
		register_shutdown_function(array('xAPI_Common', 'fatal_error_catcher'));


	}


// ------- ЛОГГИРОВАНИЕ СОБЫТИЙ И УВЕДОМЛЕНИЕ -------

	/**
	 * Формирует строку из значений элементов массива для удобной записи в лог
	 * @param string $array : Массив
	 * @param string $string_delimiter : Разделитель элементов массива в строке
	 * @return string : Массив, представленный в виде строки
	 * */
	static function array_to_log_string($array, $string_delimiter = '') {

		$return_list = '';
		foreach ((array)$array as $key => $value) {
			if (is_object($value)) {
				$value = var_export($value, true);
			} elseif (is_array($value)) {
				$value = "Array => (" . self::array_to_log_string($value) . ")";
			} elseif (is_bool($value)) {
				$value = var_export($value, true);
			} elseif (empty($value)) {
				$value = ' <!EMPTY!> ';
			}
			// защищаем пароли от записи в лог
			if ($key === 'password' || $key === 'pass') {
				$value = '***';
			}
			$return_list .= $key . ' = ' . $value . '; ' . $string_delimiter;
		}
		return $return_list;
	}

	/**
	 * Формирует строку из значений элементов массива для удобной записи в лог
	 * @param string $sql_log_array : Массив, элементы которого - SQL-запросы
	 * @return string : Массив SQL-запросов, представленный в виде строки
	 * */
	static function sql_log_array_to_string($sql_log_array) {

		$return_list = '';
		foreach ((array)$sql_log_array as $time => $sql_string) {
			$sql_string = strtr($sql_string, "\n", '');
			$return_list .= "[$time] $sql_string \n";
		}
		return !empty($return_list) ? $return_list : 'none';
	}

	/**
	 * Формирует строку из массива бектрейса для записи в лог
	 * @param array $backtrace_array : Массив, возвращенный от функции debug_backtrace()
	 * @return string : Строка трассировки в обратном порядке вызовов функций
	 * */
	static function backtrace_to_string($backtrace_array) {

		$return_string = "STACK TRACE:\n";
		$stack_count = count($backtrace_array);

		for ($i = 2; $i <= $stack_count; $i++) { // в цикле исключается первый элемент, в котором находится вызов функции обработки ошибки (self::handle_error)
			$curr_trace = $backtrace_array[$i - 1]; // обращаемся к индексу массива
			$curr_args = self::array_to_log_string($curr_trace['args']); // формируем список аргументов функции
			$curr_func = (isset($curr_trace['class']) ? $curr_trace['class'] . $curr_trace['type'] : '') . $curr_trace['function']; // название класса или функции
			$return_string .= "--- FUNCTION(" . ($stack_count - $i + 1) . "): {$curr_func}( $curr_args )\n";
			if (isset($curr_trace['file']))
				$return_string .= "    FILE: " . basename($curr_trace['file']) . " LINE: {$curr_trace['line']}\n";
		}

		return $return_string;
	}

	/**
	 * Записывает в БД типа SQLite лог какого-либо события с текущим временем.
	 * @param string $event_type : Тип события: xapi_error, db_error, fatal_error, warning, module_call
	 * На каждое событие будет создана соответствующая таблица
	 * @param string $event_key : краткое кодовое слово события (с нижним подчеркиванием)
	 * @param string $message : переменная или массив переменных ответа функции, либо развернутый текст ошибки/события
	 * */
	static function write_log($event_type, $event_key, $message) {
		try {
			// Создаем или открываем созданную ранее базу данных
			$db = new PDO('sqlite:' . _XAPI_LOG_DIR . 'xapi_logs.db');

			// Создаем таблицу $event_type, если не существует
			$table_name = $db->quote($event_type);
			$db->exec("CREATE TABLE IF NOT EXISTS $table_name (
			    `id` INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL,
			    `time` VARCHAR NOT NULL,
			    `key` VARCHAR DEFAULT NULL,
			    `text` TEXT DEFAULT NULL
			)"); // возможно понадобится указывать кодировку таблицы COLLATE='utf8_general_ci' или для соединения exec('SET NAMES utf8')

			// Записываем строку события в таблицу
			$curr_time = date('Y-m-d H:i:s');
			$curr_time = $db->quote($curr_time);
			$event_key = $db->quote($event_key);
			$message = $db->quote($message);
			$db->exec("INSERT INTO $table_name (`time`, `key`, `text`) VALUES ($curr_time, $event_key, $message)");

		} catch (PDOException $e) {
			self::final_output(false, 'xapi_logs_error', 'SQLite DB for logging by PDO: ' . $e->getMessage());
		}
	}

	/**
	 * Отправляет уведомление о произошедшей ошибке на почту администратора.
	 * Используется в методах обработки ошибок.
	 * @param string $text : Текст ошибки
	 * */
	static function send_email_notice($text) {
		global $from_site;

		$curr_xapi_name = strtoupper(_XAPI_CURRENT_NAME); // _XAPI_CURRENT_NAME должна быть объявлена в /xapi/xapi_config.php
		if (defined('_XAPI_MODULE_NAME')) {
			$curr_module_name = ucfirst(_XAPI_MODULE_NAME); //_XAPI_MODULE_NAME должна быть объявлена в /xapi/index.php и в /xapi/MODULE_NAME/bootstrap.php
		} else {
			$curr_module_name = '<!>';
		}

		$headers = "From: xAPI " . $curr_xapi_name . " <xapi@yourdomain.com>\nContent-type: text/plain; charset=UTF-8";
		$subject = "Error! Module: $curr_module_name";
		if (!empty($from_site)) {
			$subject .= ". From: $from_site";
		}

		mail(_XAPI_ADMIN_EMAIL, $subject, $text, $headers);

		if (defined(_XAPI_ADMIN_SMS_EMAIL)) {
			$exploded_text = explode("\n", $text);
			$first_line = $exploded_text[0]; // в смс отправляем первую строку с ошибкой
			mail(_XAPI_ADMIN_SMS_EMAIL, $subject, $first_line, $headers);
		}
	}


// ------- РАБОТА СО СТРОКАМИ -------

	/**
	 * Если в запросах используются шаблоны, то использовать данную функцию не следует -
	 * - т.к. в классе БД массив значений шаблона экранируется автоматически!
	 * И рекомендуется строго использовать шаблоны вместо простых запросов с экранированием.
	 * Данная функция экранирует спецсимволы в строке и обрамляет её кавычками.
	 * Работает только со строками. Числа и другие типы пропускаются.
	 * @param string $string : Любая переменная или массив (принимается по ссылке)
	 * */
	static function escape_and_quote(&$string) {

		if (is_string($string)) {
			$search = array("\\", "\0", "\n", "\r", "\x1a", "'", '"');
			$replace = array("\\\\", "\\0", "\\n", "\\r", "\Z", "\'", '\"');
			$string = "'" . str_replace($search, $replace, $string) . "'";
		}

		return $string;
	}

	/**
	 * Генерирует уникальную случайную строку и выдает её в md5 (длина - 32 байта)
	 * @return string : Уникальная строка, длиной в 32 байта
	 * */
	static function generate_unique_str32() {

		$k_time = mt_rand();
		mt_srand(microtime(true) + $k_time); // задаем уникальный источник генерации
		$k_rand = mt_rand(100000000, 999999999); // получаем случайное число
		$k_str = $k_rand . microtime(); // добавляем уникальность - время в микросекундах

		return md5(hash('sha256', (string)$k_str)); // возвращаем двойной хеш
	}

	/**
	 * Формирует строку в формате JSON из значений массива.
	 * При этом нормально отображает кириллицу (не экранирует Unicode-последовательности)
	 * @param string $array : Массив
	 * @return string : Массив, представленный в виде строки
	 * */
	static function json_encode_unescaped($array) {
		// Обходим в json_encode() конвертацию кирилических символов в Unicode-последовательность
		// convmap since 0x80 char codes so it takes all multibyte codes (above ASCII 127). So such characters are being "hidden" from normal json_encoding
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$array[$key] = self::json_encode_unescaped($value);
			} elseif (is_string($value)) {
				$array[$key] = mb_encode_numericentity($value, array(0x80, 0xffff, 0, 0xffff), 'UTF-8');
			}
		}
		$array = mb_decode_numericentity(json_encode($array), array(0x80, 0xffff, 0, 0xffff), 'UTF-8');

		return $array;
	}

	/**
	 * Возвращает текстовый код ошибки последнего применения функции json_decode()
	 * @return string : Код ошибки
	 * */
	static function json_last_text_error() {

		switch (json_last_error()) {
			case JSON_ERROR_NONE:
				$error = 'No error';
				break;
			case JSON_ERROR_DEPTH:
				$error = 'Maximum stack depth exceeded';
				break;
			case JSON_ERROR_STATE_MISMATCH:
				$error = 'Incorrect digits or mismatch modes';
				break;
			case JSON_ERROR_CTRL_CHAR:
				$error = 'Unexpected control character found';
				break;
			case JSON_ERROR_SYNTAX:
				$error = 'Syntax error, malformed JSON';
				break;
			case JSON_ERROR_UTF8:
				$error = 'Incorrect UTF-8 characters, may the wrong encoding';
				break;
			default:
				$error = 'Unknown error';
				break;
		}

		return $error;
	}


// ------- ОБЩИЕ ПРОВЕРКИ -------

	/**
	 * Проверяет никнейм на валидность
	 * @param string $nick : Ник-нейм пользователя
	 * @param int $length : Минимальная длина ника (использовать этот параметр, только в исключительных случаях)
	 * @return boolean : успех проверки
	 */
	static function is_valid_nick($nick, $length = 5) {
		if (!preg_match('/^[A-Za-z][A-Za-z\d-]{' . ($length - 2) . ',28}[A-Za-z\d]$/', $nick)) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Проверяет email на валидность.
	 * На данный момент обычная проверка по RFC: filter_var($email, FILTER_VALIDATE_EMAIL).
	 * Планируется добавить нижеобъявленную функцию проверки на существование DNS почтового хоста
	 * и проверку на существование самого адреса на удаленном хосте (попыткой отправки и прерыванием).
	 * @param string $email : email-адрес
	 * @return boolean : успех проверки
	 * */
	static function is_valid_email($email) {
		$checked_email = filter_var($email, FILTER_VALIDATE_EMAIL);
		return (bool)$checked_email;
	}

	/**
	 * Проверяет email на существование его домена
	 * @param string $email : email-адрес
	 * @return boolean : успех проверки
	 * */
	static function is_email_host_exists($email) {
		$email_chunks = explode('@', $email);
		$domain = $email_chunks[1];
		$mail_host_prefix = array("", "mail.", "smtp.");
		$host_exists = false;
		if (function_exists("getmxrr") && getmxrr($domain, $mxhosts)) {
			$host_exists = true;
		} else {
			foreach ($mail_host_prefix as $val) {
				if ($host_exists OR fsockopen($val . $domain, 25, $errno, $errstr, 5)) {
					$host_exists = true;
				}
			}
		}
		return $host_exists;
	}

	/**
	 * Проверяет длину нового пароля.
	 * Требования к длине пароля: от 8 до 32 символов включительно.
	 * @param string $password : пароль
	 * @return boolean : успех проверки
	 * */
	static function is_valid_password_length($password) {
		$pass_length = strlen($password);
		return ($pass_length >= 8 && $pass_length <= 32) ? true : false;

	}

	/**
	 * Проверяет не пустые ли переменные в ассоциативном массиве.
	 * Используется для быстрой проверки обязательных параметров в функциях.
	 * @param array $params : ассоциативный массив параметров
	 * @return array : успех проверки и текст ошибки о пустом параметре (для первого же пустого элемента)
	 * */
	static function required_params_passed($params_arr) {
		foreach ($params_arr as $key => $value) {
			if (empty($value)) {
				return array(false, $key . '_is_empty'); // общий для всех, понятный код ошибки упущенного параметра
			}
		}
		return array(true, 'all_params_passed');
	}

}

?>