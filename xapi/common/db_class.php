<?php

/**
 * Класс для работы с базой данных MySQL на узлах xAPI. Доступен в общем репозитории.
 * TODO (при необходимости) добавить функции bindParam() и bindValue() для работы с шаблонами
 * TODO (при необходимости) добавить работу с типом SQL-запроса REPLACE
 */
class xAPI_DB {

	// Виды запросов
	const INS = 10; // INSERT
	const UPD = 20; // UPDATE
	const SEL = 30; // SELECT
	const DEL = 40; // DELETE

	// Варианты извлечения результата запроса
	const NO_FETCH = 100; // не извлекаем строки из выборки
	const FETCH_ONE = 200; // возвращаем одну строку из выборки
	const FETCH_ALL = 300; // возвращаем массив всех строк из выборки

	// Режимы извлечения результата запроса (значения соответствуют константам из класса PDO)
	const F_LAZY = PDO::FETCH_LAZY; // выборка объекта, которая подгружает данные только во время обращения к ним;
	// т.е. создает имена переменных объекта так, как они используются (комбинация PDO::FETCH_BOTH/PDO::FETCH_OBJ)
	const F_ASSOC = PDO::FETCH_ASSOC; // возвращает массив, индексированный по именам столбцов
	const F_NUM = PDO::FETCH_NUM; // возвращает массив, индексированный по номерам столбцов
	const F_BOTH = PDO::FETCH_BOTH; // массив, индексированный и по именам столбцов, и по номерам
	const F_OBJ = PDO::FETCH_OBJ; // выборка в виде объекта (если актуально данным выборки)
	const F_BOUND = PDO::FETCH_BOUND; // назначает значения ваших столбцов набору переменных с использованием метода ->bindColumn()
	const F_COLUMN = PDO::FETCH_COLUMN; // возвращает нумерованный массив, содержащий только первое поле из выборки
	const F_CLASS = PDO::FETCH_CLASS; // назначает значения столбцов свойствам именованного класса, если соответствующего свойства не существует - оно создается

	private $PDO_connect; // Используется для соединения с базой данных и создания объекта класса PDO
	private $connect_string; // Используется для исключения повторных соединений с идентичными данными
	private $query_string; // Содержит исходный текст запроса для записи в историю
	private $PDO_statement; // Содержит состояние запроса (ссылку на PDOStatement)
	public $last_row_count; // Содержит количество строк в результате запроса
	public $last_insert_id; // Содержит номер последней вставленной записи
	private $queries_log; // Массив для истории запросов

	protected static $instances = array(); // для работы класса по паттерну "MultiSingletone" (на самом деле, не совсем Singletone, т.к. для каждого коннекта к разным базам - свой экземпляр)

	// Защищаем класс от создания экземпляра через new xAPI_DB
	private function __construct() {
	}

	// Защищаем класс от создания экземпляра  через клонирование
	private function __clone() {
	}

	// Защищаем класс от создания экземпляра  через unserialize
	private function __wakeup() {
	}

	/**
	 * Возвращает единственный экземпляр класса для указанной БД.
	 * При создании экземпляра автоматически подключается к БД.
	 * По умолчанию использует первый элемент массива $_XAPI_DB, объявленного в конфигурационном файле узла xAPI.
	 * @param string $baseindex : Название индекса элемента массива $_XAPI_DB
	 * @return xAPI_DB : ссылка на экземпляр класса xAPI_DB для указанной базы
	 */
	public static function instance($baseindex = '') {
		global $_XAPI_DB;

		$db_params_keys = array_keys($_XAPI_DB);
		if (empty($db_params_keys)) {
			xAPI_Common::handle_error("Ne zapolnen massiv _XAPI_DB v konfige xAPI", 'DB');
		}
		if (!empty($baseindex)) { // если не передано название индекса
			if (array_key_exists($baseindex, $db_params_keys)) {
				xAPI_Common::handle_error("Nazvanie indeksa bazy $baseindex ne suschestvuet v massive _XAPI_DB", 'DB');
			}
		} else {
			$baseindex = $db_params_keys[0]; // используем по умолчанию первый элемент из массива настроек БД
		}
		$db_params = $_XAPI_DB[$baseindex]; // получаем настройки БД
		if (empty($db_params)) {
			xAPI_Common::handle_error("Ne ukazany nastroiki podklyucheniya k baze $baseindex v massive _XAPI_DB", 'DB');
		}

		if (!isset(self::$instances[$baseindex])) {
			self::$instances[$baseindex] = new xAPI_DB;
			self::$instances[$baseindex]->connect($db_params['base'], $db_params['user'], $db_params['password'], $db_params['server']);
		}
		return self::$instances[$baseindex];
	}

	/**
	 * Подключение к базе данных.
	 * @param string $base : Имя базы даных
	 * @param string $user : Пользователь базы данных
	 * @param string $password : Пароль пользователя базы данных
	 * @param string $server : Адрес сервера базы данных
	 * @return none
	 */
	private function connect($base, $user, $password, $server) { // константы, указанные в xapi_config.php
		try {
			$dsn = 'mysql:host=' . $server . ';dbname=' . $base;
			$check_string = $dsn . $user . md5($password);
			if (!empty($this->connect_string)) {
				if ($this->connect_string === $check_string) {
					return; // исключаем повторные соединения с идентичными данными
				}
			}
			$this->connect_string = $check_string; // для исключения повторных коннектов к той же базе с теми же данными доступа
			$this->PDO_connect = new PDO($dsn, $user, $password);
			$this->PDO_connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->PDO_connect->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false); // не работает для PHP 5.2 и без драйвера mysqldns !
			$this->PDO_connect->exec('SET NAMES utf8');
		} catch (PDOException $e) {
			xAPI_Common::handle_error($e->getMessage(), 'DB');
		}
	}

	/**
	 * Подготовка запроса на сервере (компиляция).
	 * @param string $query : Текст SQL-запроса
	 * @return object : ссылка на объект класса PDOStatement (или false в случае ошибки)
	 */
	public function prepare($query) {
		// * желательный способ запросов - с использованием шаблонов! квотирование и экранирование строк происходит автоматически!
		$this->query_string = $query; // для сохранения в истории логов
		return $this->PDO_statement = $this->PDO_connect->prepare($query);
	}

	/**
	 * Выполнение SQL-запроса с компиляцией (без использования шаблона).
	 * Все параметры, отправленные в шаблон, экранируются от спецсимволов автоматически! экранировать предварительно не нужно.
	 * @param string $query : Текст SQL-запроса
	 * @param int $query_type : xAPI_DB::INS, xAPI_DB::UPD, xAPI_DB::SEL, xAPI_DB::DEL
	 * @param int $fetch_type : xAPI_DB::NO_FETCH, xAPI_DB::FETCH_ONE or xAPI_DB::FETCH_ALL
	 * @param int $fetch_mode : xAPI_DB::F_ASSOC, xAPI_DB::F_NUM, xAPI_DB::F_BOTH, xAPI_DB::F_OBJ or xAPI_DB::F_COLUMN
	 * @return bool : true или false в случае успеха / ошибки
	 */
	public function query($query, $query_type, $fetch_type = self::FETCH_ONE,
	                      $fetch_mode = self::F_ASSOC) {
		$this->prepare($query);
		// * данный вариант - без использования шаблона, используем в простых запросах (без добавления внешних данных)
		// * желательный способ запросов - с использованием шаблонов! т.к. квотирование и экранирование строк происходит автоматически!
		return $this->execute(null, $query_type, $fetch_type, $fetch_mode);
	}

	/**
	 * Выполнение SQL-запроса с компиляцией (по шаблону).
	 * Все параметры, отправленные в шаблон, экранируются от спецсимволов автоматически! экранировать предварительно не нужно.
	 * Если не передан массив значений, то запрос только подготавливается (компилируется),
	 * после чего ожидается вызов метода execute()
	 * @param string $query : Текст SQL-запроса
	 * @param array $values_assoc_array : Асоциативный массив значений для вставки в шаблон
	 * @param int $query_type : xAPI_DB::INS, xAPI_DB::UPD, xAPI_DB::SEL, xAPI_DB::DEL
	 * @param int $fetch_type : xAPI_DB::NO_FETCH, xAPI_DB::FETCH_ONE or xAPI_DB::FETCH_ALL
	 * @param int $fetch_mode : xAPI_DB::F_ASSOC, xAPI_DB::F_NUM, xAPI_DB::F_BOTH, xAPI_DB::F_OBJ or xAPI_DB::F_COLUMN
	 * @return mixed : true / false в случае успеха / ошибки или object - ссылка на PDOStatement, если не передан массив значений (отработает только prepare)
	 */
	public function query_pattern($query, $values_assoc_array, $query_type,
	                              $fetch_type = self::FETCH_ONE, $fetch_mode = self::F_ASSOC) {
		$this->prepare($query);
		if (!is_null($values_assoc_array)) {
			// * желательный способ запросов - с использованием шаблона!
			// * т.к. квотирование и экранирование строк происходит автоматически!
			return $this->execute($values_assoc_array, $query_type, $fetch_type, $fetch_mode);
		} else {
			return $this->PDO_statement;
		}
	}

	/**
	 * Непосредственное выполнение скомпилированного ранее SQL-запроса
	 * Все параметры, отправленные в шаблон, экранируются от спецсимволов автоматически! экранировать предварительно не нужно.
	 * @param array $values_assoc_array : Асоциативный массив значений для вставки в шаблон
	 * @param int $query_type : xAPI_DB::INS, xAPI_DB::UPD, xAPI_DB::SEL, xAPI_DB::DEL
	 * @param int $fetch_type : xAPI_DB::NO_FETCH, xAPI_DB::FETCH_ONE or xAPI_DB::FETCH_ALL
	 * @param int $fetch_mode : xAPI_DB::F_ASSOC, xAPI_DB::F_NUM, xAPI_DB::F_BOTH, xAPI_DB::F_OBJ or xAPI_DB::F_COLUMN
	 * @return bool : true или false в случае успеха / ошибки
	 */
	public function execute($values_assoc_array, $query_type,
	                        $fetch_type = self::FETCH_ONE, $fetch_mode = self::F_ASSOC) {
		$this->put_log($values_assoc_array); // сохраняем историю запросов
		try {
			if (!is_null($values_assoc_array)) {
				// * желательный способ запросов - с использованием шаблона!
				// * т.к. квотирование и экранирование строк происходит автоматически!
				$this->PDO_statement->execute($values_assoc_array);
			} else {
				$this->PDO_statement->execute();
			}
			if (!$this->PDO_statement) {
				$error = $this->PDO_connect->errorInfo();
				throw new PDOException($error[2]);
			}
			$this->last_row_count = $this->PDO_statement->rowCount(); // подсчитываем кол-во обработанных строк
			if ($query_type == self::SEL) {
				return $this->fetch($fetch_type, $fetch_mode); // извлекаем и возвращаем результат запроса
			} elseif ($query_type == self::INS) {
				$this->last_insert_id = $this->PDO_connect->lastInsertId(); // сохраняем идентификатор последней вставленной строки
				return true;
			} elseif ($query_type == self::UPD) {
				return true;
			} elseif ($query_type == self::DEL) {
				return true;
			}
		} catch (PDOException $e) {
			xAPI_Common::handle_error($e->getMessage(), 'DB');
		}
	}

	/**
	 * Извлечение строки из результата запроса
	 * @param int $fetch_type : xAPI_DB::NO_FETCH, xAPI_DB::FETCH_ONE or xAPI_DB::FETCH_ALL
	 * @param int $fetch_mode : xAPI_DB::F_ASSOC, xAPI_DB::F_NUM, xAPI_DB::F_BOTH, xAPI_DB::F_OBJ or xAPI_DB::F_COLUMN
	 * @return mixed : результат извлечения запроса (массив данных, либо одна переменная) либо ссылка на PDOStatement (если отработала без извлечения)
	 */
	public function fetch($fetch_type = self::FETCH_ONE,
	                      $fetch_mode = self::F_ASSOC) {
		// TODO (при необходимости) доработать для использования F_BOUND и F_CLASS
		if ($fetch_type == self::FETCH_ONE) { // извлекаем 1 строку из запроса
			return $this->PDO_statement->fetch($fetch_mode);
		} elseif ($fetch_type == self::FETCH_ALL) { // извлекаем все строки запроса в массив
			return $this->PDO_statement->fetchAll($fetch_mode);
		} elseif ($fetch_type == self::NO_FETCH) { // не извлекаем строки, просто возвращаем состояние запроса (ссылку на PDOStatement)
			return $this->PDO_statement;
		}
	}

	/**
	 * Выполняет экранирование спецсимволов в строке и оборачивает строку в кавычки.
	 * Параметр передается по ссылке для того, чтобы использовать в функции array_walk().
	 * @param string $unsafe_data : Строка для квотирования
	 * @return string : Экранированная строка, пригодная для безопасного использования в SQL-запросе
	 */
	public function quote(&$unsafe_data) {
		if (is_string($unsafe_data)) {
			$unsafe_data = $this->PDO_connect->quote($unsafe_data); // экранирование и квотирование строки
		} elseif (is_array($unsafe_data)) {
			array_walk($unsafe_data, array($this, 'quote'));
		}
		return $unsafe_data;
	}

	/**
	 * Сохраняет историю запросов.
	 * Если передан массив значений, значит выполнялся запрос с шаблоном, и нужно заменить ключи на значения
	 * @param string $query : Текст SQL-запроса
	 * @return none
	 */
	private function put_log($values_assoc_array = null) {
		if (!is_null($values_assoc_array)) {
			// заменяем в шаблоне ключи на значения для логов
			foreach ($values_assoc_array as $key => $value) {
				if ($key[0] != ':') {
					$key = ':' . $key;
				}
				// показываем запрос в логах правильно - с квотированием,
				// единственное - без экранирования (в логах оно не нужно, а в запросах с шаблонами выполняется автоматически).
				if (is_string($value)) {
					$value = "'" . $value . "'";
				}
				$this->query_string = str_replace($key, $value, $this->query_string); // заменяем в шаблоне ключи на их значения
			}
		}
		$this->queries_log[microtime(true)] = $this->query_string;
	}

	/**
	 * Выдает лог запросов от всех экземпляров данного класса, отсортированный по времени
	 * @param string $base : Имя базы даных
	 * @return none
	 */
	public static function get_all_logs() {
		$logs_array = array();
		foreach (self::$instances as $db) {
			$logs_array += $db->queries_log;
		}
		ksort($logs_array);
		return $logs_array;
	}

	/**
	 * Удаляет экземпляр класса, созданный с подключением к указанной базе данных
	 * @param string $base : Имя базы даных
	 * @return none
	 */
	public static function kill_instance($base = _XAPI_SQL_DB) {
		unset(self::$instances[$base]);
	}
}

?>