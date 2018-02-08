<?php

/**
 * Класс модуля узла xAPI
 */
class xAPI_Users {

//////////////////////////////////////////////////////////////
/////////// Проверки

	/**
	 * Проверка никнэйма на валидность и доступность
	 * Запрашивает одноименную функцию глобальных xAPI, обрабатывает ответ и передает назад на сайт (обертка).
	 *
	 * @param	string  $nick           : Никнейм пользователя для проверки
	 * @param   int     $user_auth_id   : ID пользователя из таблицы авторизации, который будет исключен из поиска на занятость
     *
     * @return array
	 */
	static function check_nick ($nick, $user_auth_id = 0)
	{
		$params = array();
		$params['nick'] = $nick;
        $params['user_auth_id'] = $user_auth_id;

		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'check_nick', $params);

		return $result;
	}

	/**
	 * Проверка email на валидность и доступность
	 * Запрашивает одноименную функцию глобальных xAPI, обрабатывает ответ и передает назад на сайт.
	 *
	 * @param	string	$email          : e-mail для проверки
	 * @param	int		$user_auth_id   : ID пользователя из таблицы авторизации, который будет исключен из поиска на занятость
	 *
	 * @return  array
	 */
	static function check_email ($email, $user_auth_id = 0)
	{
		$params = array();
		$params['action'] = 'check_email';
		$params['email'] = $email;
		$params['user_auth_id'] = $user_auth_id;

		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'check_email', $params);

		return $result;
	}

	/**
	 * Проверка пароля на соответствие правилам безопасности
	 * Запрашивает одноименную функцию глобальных xAPI, обрабатывает ответ и передает назад на сайт.
	 *
	 * @param	string	$password   : Пароль для проверки
	 *
	 * @return array
	 */
	static function check_password ($password)
	{
		$params = array();
		$params['password'] = $password;

		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'check_password', $params);

		return $result;
	}



//////////////////////////////////////////////////////////////
/////////// Регистрация

	/**
	 * Регистрация существующего пользователя
	 * На самом деле - простая установка флага сервиса
	 * Запрашивает одноименную функцию глобальных xAPI, обрабатывает ответ и передает назад на сайт.
	 *
	 * @param   int     $auth_id             : ID пользователя из таблицы авторизации
	 * @param   int     $referrer_auth_id    : ID реферрера из таблицы авторизации
	 *
	 * @return  array
	 */
	static function reg_exists_user($auth_id, $referrer_auth_id = 0)
	{
		$params = array();
		$params['user_auth_id'] = $auth_id;
		$params['referrer_auth_id'] = $referrer_auth_id;

		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'reg_exists_user', $params);

		return $result;
	}

	/**
	 * Передает данные в глобальные xAPI для проверки и создания общей учетной записи.
	 * Запрашивает одноименную функцию глобальных xAPI, обрабатывает ответ и передает назад на сайт.
	 *
	 * @param	string	$nick
	 * @param	string	$password
	 * @param	string	$conf_key
	 *
	 * @return array
	 */
	static function reg_new_user($nick, $password, $conf_key)
	{
		$params['nick'] = $nick;
		$params['password'] = $password;
		$params['confirm_key'] = $conf_key;
		
		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'reg_new_user', $params);

		return $result;
	}

	/**
	 * Новоявленная функция вставки в глобальную БД инфы об учетке (фамилия, имя, емайл, реферер)
	 *
	 * @param string $name
	 * @param string $surname
	 * @param string $email
	 * @param int    $referrer_auth_id
	 *
	 * @return array
	 */
	static function reg_get_confirm_key ($name, $surname, $email, $referrer_auth_id)
	{
		$params['name'] = $name;
		$params['surname'] = $surname;
		$params['email'] = $email;
		$params['referrer_auth_id'] = $referrer_auth_id;
		
		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'reg_get_confirm_key', $params);

		return $result;
	}
	
	//Функция проверки существования ключа подтверждения
	static function reg_check_confirm_key ($conf_key)
	{
		$params['confirm_key'] = $conf_key;
		
		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'reg_check_confirm_key', $params);

		return $result;
	}

/****************************************************************
 *                         Авторизация                          *
 ****************************************************************/

	/**
	 * Вызывает authorize_user в глоб. xAPI и передает данные для проверки.
	 * Результат передается на сайт. По результату производится авторизацию на сайте либо выводит сообщение об ошибке.
	 *
	 * Если site_flag = 0, то:
	 *  - если учетная запись на сайте уже есть, возвратить ошибку, пользователь должен обратиться в саппорт (утерян признак сайта);
	 *  - если referrer_auth_id из ответа глоб. функции не пустой, то записать его в реферреры, иначе проверить куках
	 *    (т.е. использование рефссылки) и записать значение реферрера из кук;
	 *  - создать локальную учетную запись на сайте;
	 *  - вызвать register_exists_user в глоб. xAPI и передать в параметре referrer_auth_id значение из кук рефссылки.
	 *
	 * Если site_flag = 1, то произвести авторизацию.
	 *
	 * @param	string	$user_login
	 * @param	string	$password
     *
     * @return  array
	 */
	static function authorize_user ($user_login, $password)
	{
		$params['user_login']	= $user_login;
		$params['password']		= $password;

		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'authorize_user', $params);

		return $result;
	}


/****************************************************************
 *                    Восстановление пароля                     *
 ****************************************************************/

	/**
	 * Вызывает reset_pass_get_key в глоб. xAPI, передает в нее название и значение поля поиска (nick, login, email).
	 * Из полученного ключа формирует URL для подтверждения восстановления пароля, действующий для этого сайта.
	 * Ссылка передается обратно на сайт, сайт должен отправить письмо из шаблона для восстановления пароля.
	 *
	 * @param	string	$find_field
	 * @param	string	$find_value
     *
     * @return array
	 */
	static function reset_pass_get_key ($find_field, $find_value)
	{
		//$params['action']		= 'reset_pass_get_key';
		$params['find_field']	= $find_field;
		$params['find_value']	= $find_value;

		//$result = xAPI_Common::call($params, _NAME_API_LOCAL, _NAME_API_MAIN, 'users');
		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'reset_pass_get_key', $params);
		return $result;

	}

	/**
	 * Используется для вывода на сайте формы ввода нового пароля. Поскольку перед выводом формы нужно проверить существование ключа.
	 * Вызывает reset_pass_check_key в глоб. xAPI (там ключ просто проверяется и возвращается ID пользователя) и передает ответ на сайт.
	 * Сайт выводит форму ввода нового пароля.
	 *
	 * @param	string	$confirm_key
     *
     * @return array()
	 */
	static function reset_pass_check_key ($confirm_key)
	{
		$params['confirm_key']	= $confirm_key;

		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'reset_pass_check_key', $params);
		return $result;
	}

	/**
	 * Используется для отправки нового пароля из формы (после проверки ключа).
	 * Вызывает reset_pass_activate_key в глоб. xAPI, передает в нее также ключ и новый пароль
	 * (там ключ повторно проверяется, проверяется и обновляется пароль, ключ помечается использованным).
	 * Обратно на сайт передается сообщение об успехе, сайт должен отправить письмо из шаблона с измененным паролем.
	 *
	 * @param	string	$confirm_key
	 * @param	string	$password
     *
     * @return array
	 */
	static function reset_pass_activate_key ($confirm_key, $password)
	{
		$params['confirm_key']	= $confirm_key;
		$params['password']		= $password;

		//$result = xAPI_Common::call($params, _NAME_API_LOCAL, _NAME_API_MAIN, 'users');
		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'reset_pass_activate_key', $params);
		return $result;
	}


/****************************************************************
 *                         Смена пароля                         *
 ****************************************************************/

	/**
	 * Вызывает change_pass_get_key в глоб. xAPI, передает в нее ID пользователя и новый пароль, пароль сохраняется в общей базе ключей.
	 * Из полученного ключа формирует URL для подтверждения смены пароля, действующий для этого сайта.
	 * Ссылка передается обратно на сайт, сайт должен отправить письмо из шаблона для подтверждения смены пароля.
	 *
	 * @param	int		$user_auth_id
	 * @param	string	$password
     *
     * @return array()
	 */
	static function change_pass_get_url ($user_auth_id, $password)
	{
		$params['action']		= 'change_pass_get_key';
		$params['user_auth_id']	= $user_auth_id;
		$params['password']		= $password;

        $result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'change_pass_get_url', $params);

		return $result;
	}

	/**
	 * Используется для проверки ключа и подтверждения смены пароля.
	 * Вызывает change_pass_activate_key в глоб. xAPI, передает в нее ключ
	 * (там ключ проверяется, обновляется пароль, ключ помечается использованным).
	 * Обратно на сайт передается сообщение об успехе, сайт должен отправить письмо из шаблона с измененным паролем.
	 *
	 * @param	string	$confirm_key
     *
     * @return array
	 */
	static function change_pass_activate_key ($confirm_key)
	{
		$params['action']		= 'change_pass_activate_key';
		$params['confirm_key']	= $confirm_key;

        $result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'change_pass_activate_key', $params);

		return $result;
	}


/****************************************************************
 *                      Обновление данных                       *
 ****************************************************************/

	/**
	 * Обновляет общие данные пользователя в базе авторизации. Разрешено обновлять: nick, name, surname
	 * Вызывает update_info в глоб. xAPI, передает в нее $user_data_array : Ассоциативный массив с данными пользователя.
	 * Обратно на сайт передается сообщение об успехе, сайт должен отправить письмо из шаблона об успешном обновлении данных.
	 *
	 * @param	int		$user_auth_id
	 * @param	array	$user_data_array
     *
     * @return array
	 */
	static function update_info ($user_auth_id, $user_data_array)
	{
		$params['action']			= 'update_info';
		$params['user_auth_id']		= $user_auth_id;
		$params['user_data_array']	= $user_data_array;

        $result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'update_info', $params);

		return $result;
	}


/****************************************************************
 *                         Смена email                          *
 ****************************************************************/

	/**
	 * Вызывает change_email_get_key в глоб. xAPI, передает в нее ID пользователя и новый email, email сохраняется в общей базе ключей.
	 * Получает 2 ключа (для нового и старого email-адреса) и формирует 2 URL для подтверждения смены email, действующих для этого сайта.
	 * 2 ссылки и старый адрес передаются обратно на сайт, сайт должен отправить 2 письма из шаблона для подтверждения смены email на оба адреса.
	 *
	 * @param	int		$user_auth_id
	 * @param	string	$new_email
     *
     * @return array
	 */
	static function change_email_get_url ($user_auth_id, $new_email)
	{
		$params['action']		= 'change_email_get_key';
		$params['user_auth_id']	= $user_auth_id;
		$params['new_email']	= $new_email;

        $result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'change_email_get_url', $params);

		return $result;
	}

	/**
	 * Используется для проверки ключа и подтверждения смены email.
	 * Вызывает change_email_activate_key в глоб. xAPI, передает в нее ключ
	 * (там ключ проверяется, обновляется email, ключ помечается использованным).
	 * Обратно на сайт передается сообщение об успехе и 2 email-адреса, сайт должен отправить 2 письма из шаблона об успешной смене email.
	 *
	 * @param	string	$confirm_key
     *
     * @return array
	 */
	static function change_email_activate_key ($confirm_key)
	{
		$params['action']		= 'change_email_activate_key';
		$params['confirm_key']	= $confirm_key;

        $result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'change_email_activate_key', $params);

		return $result;
	}


/****************************************************************
 *                          Удаление                            *
 ****************************************************************/

	/**
	 * Вызывает delete_user_get_key в глоб. xAPI, передает в нее ID пользователя.
	 * Из полученного ключа формирует URL для подтверждения удаления пользователя, действующий для этого сайта.
	 * Ссылка передается обратно на сайт, сайт должен отправить письмо из шаблона для подтверждения удаления пользователя.
	 *
	 * @param	int		$user_auth_id
     *
     * @return array
	 */
	static function delete_user_get_url ($user_auth_id)
	{
		$params['action']		= 'delete_user_get_key';
		$params['user_auth_id']	= $user_auth_id;

        $result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'delete_user_get_url', $params);

		return $result;
	}

	/**
	 * Используется для проверки ключа и подтверждения удаления пользователя.
	 * Вызывает delete_user_activate_key в глоб. xAPI, передает в нее ключ
	 * (там ключ проверяется, пользователь удаляется, ключ помечается использованным).
	 * Обратно на сайт передается сообщение об успехе, сайт должен удалить учетную запись пользователя
	 * и отправить письмо из шаблона об успешном удалении.
	 *
	 * @param	string	$confirm_key
     *
     * @return array
	 */
	static function delete_user_activate_key ($confirm_key)
	{
		$params['action']		= 'delete_user_activate_key';
		$params['confirm_key']	= $confirm_key;

        $result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'delete_user_activate_key', $params);

		return $result;
	}
	
	static function update_office_status($user_auth_id, $status)
	{
		$params['user_auth_id']	= $user_auth_id;
		$params['status']		= $status;
		$result = xAPI_Common::call(_XAPI_MAIN_NAME, 'users', 'update_office_status', $params);
		return $result;
	}

}
?>
