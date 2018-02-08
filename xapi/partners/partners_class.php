<?php

/**
 * Класс модуля узла xAPI
 */
class xAPI_Partners {
/* Функции модуля */

/* *
 * Получение глобального ID реферрера пользователя, который (реферрер) является дистрибьютором сервисов
 * Реферрер может отсутствовать, если пользователь регистрировался без рефссылки
 * Внешний вызов: есть
 * @param integer $user_auth_id : ID пользователя в таблице `authorization` общей БД
 * @return array : успешность выполнения, код выполнения, прочие данные в массиве
 * */
	static function get_referrer_distr($user_auth_id) {
		$user_auth_id = intval($user_auth_id);
		if ($user_auth_id == 0) {
			return array(false, 'auth_id_empty');
		}
		$params['action'] = 'get_referrer_distr';
		$params['user_auth_id'] = $user_auth_id;

		$result = xAPI_Common::call($params, _NAME_API_LOCAL, _NAME_API_MAIN, 'partner');

		return $result;
	}

/* *
 * Запись в глобальной учетной записи пользователя ID реферрера, который является дистрибьютором сервисов
 * Внешний вызов: есть
 * @param integer $user_auth_id : ID пользователя в таблице `authorization` общей БД
 * @return array : успешность выполнения, код выполнения, прочие данные в массиве
 * */
	static function write_referrer_distr($user_auth_id, $referrer_auth_id) {
		$user_auth_id = intval($user_auth_id);
		if ($user_auth_id == 0) {
			return array(false, 'auth_id_empty');
		}
		$params['action'] = 'write_referrer_distr';
		$params['user_auth_id'] = $user_auth_id;
		$params['referrer_auth_id'] = $referrer_auth_id;

		$result = xAPI_Common::call($params, 'partner');

		return $result;
	}

/* *
 * Запись информации об оплате/начислении клиентом(-у) денежных средств в общую БД
 * Внешний вызов: есть
 * @param integer $user_auth_id : ID пользователя в таблице `authorization` общей БД
 * @param integer $referrer_auth_id : ID реферера пользователя (Нужен ли этот параметр?)
 * @return array : успешность выполнения, код выполнения, прочие данные в массиве
 * */
	static function register_payment_info($user_auth_id, $referrer_auth_id, $payment_amount, $site_product_type,
	                                      $payment_time, $payment_method, $accrual_balance_type, $purse_number,
	                                      $site_transfer_id, $comment = "") {
		$user_auth_id = intval($user_auth_id);
		if ($user_auth_id == 0) {
			return array(false, 'auth_id_empty');
		}
		$payment_amount = str_replace(',', '.', $payment_amount);
		$payment_amount = floatval($payment_amount);

		$params['action'] = 'register_payment_info';
		$params['user_auth_id'] = $user_auth_id;
		$params['referrer_auth_id'] = $referrer_auth_id;
		$params['payment_amount'] = $payment_amount;
		$params['site_product_type'] = $site_product_type;
		$params['payment_time'] = $payment_time;
		$params['payment_method'] = $payment_method;
		$params['accrual_balance_type'] = $accrual_balance_type;
		$params['purse_number'] = $purse_number;
		$params['site_transfer_id'] = $site_transfer_id;
		$params['comment'] = $comment;

		$result = xAPI_Common::call($params, _NAME_API_LOCAL, _NAME_API_MAIN, 'partner');
		return $result;
	}

/* *
 * Получение глобального ID реферрера из таблицы пользователей сайта
 * Реферрер может отсутствовать, если пользователь регистрировался без рефссылки

 * ВНИМАНИЕ! Используется доступ к локальной БД - организовать свою логику для каждого сервиса!

 * Внешний вызов: есть
 * @param integer $user_auth_id : глобальный ID пользователя
 * @return array : успешность выполнения, код выполнения, прочие данные в массиве
 * */
	static function get_user_referrer($user_auth_id) {
		global $db;
		$user_auth_id = intval($user_auth_id);
		if ($user_auth_id == 0) {
			return array(false, 'auth_id_empty');
		}
		$result = $db->query_pattern("SELECT `referrer_auth_id` FROM `accounts` WHERE `auth_id` = :auth_id", array('auth_id' => $user_auth_id), DB::SEL);

		return array(true, $result['referrer_auth_id']);
	}

/* *
 * Получение списка реферралов для реферрера из таблицы пользователей сайта
 * Т.е. список тех, кого пользователь (реферрер) привлек по реферральной ссылке

 * ВНИМАНИЕ! Используется локальная функция local_get_user_info() - получение информации из локальной БД - использовать свою!

 * Внешний вызов: есть
 * @param integer $user_auth_id : глобальный ID пользователя (реферрера)
 * @return array : успешность выполнения, код выполнения, прочие данные в массиве
 * */
	static function get_referrals($user_auth_id) {
		global $db;

		$result = '';

		$user_auth_id = intval($user_auth_id);
		if ($user_auth_id == 0) {
			return array(false, 'auth_id_empty');
		}

		$referrals = $db->query_pattern("SELECT `auth_id` FROM `accounts` WHERE `referrer_auth_id` = :referrer_auth_id",
			array(':referrer_auth_id' => $user_auth_id),
			DB::SEL,
			DB::FETCH_ALL);

		foreach ($referrals as $referral)
		{
			$fields = array('name', 'surname', 'login', 'reg_date', 'profit');
			$result[] = local_get_user_info($referral['auth_id'], $fields);
		}

		return array('true', $result);
	}

/* *
 * Получение баланса партнера (или вебмастера, для Линка) из глобального узла
 * Внешний вызов: есть
 * @param integer $user_auth_id : глобальный ID пользователя (реферрера)
 * @param string $accrual_balance_type : тип баланса ('partner', 'site1_webmaster' - только для Сайта1)
 * @return array : успешность выполнения, код выполнения, прочие данные в массиве
 * */
	static function get_user_balance($user_auth_id, $accrual_balance_type) {
		$user_auth_id = intval($user_auth_id);
		if ($user_auth_id == 0) {
			return array(false, 'auth_id_empty');
		}

		$params['action'] = 'get_user_balance';
		$params['user_auth_id'] = $user_auth_id;
		$params['accrual_balance_type'] = $accrual_balance_type;

		$result = xAPI_Common::call($params, _NAME_API_LOCAL, _NAME_API_MAIN, 'partner');

		if ($result[0]) {
			return array(true, $result[1]);
		}
		else
		{
			return array(true, $result[1]);
		}
	}

/* *
 * Получение списка заявок на вывод средств пользователя из глобального узла
 * Внешний вызов: есть
 * @param integer $user_auth_id : глобальный ID пользователя
 * @param string $accrual_balance_type : тип баланса ('partner', 'site1_webmaster' - только для Сайта1)
 * @return array : успешность выполнения, код выполнения, прочие данные в массиве
 * */
	static function get_user_payout_requests($user_auth_id, $accrual_balance_type) {
		$user_auth_id = intval($user_auth_id);
		if ($user_auth_id == 0) {
			return array(false, 'auth_id_empty');
		}

		$params['action'] = 'get_user_payout_requests';
		$params['user_auth_id'] = $user_auth_id;
		$params['accrual_balance_type'] = $accrual_balance_type;

		$result = xAPI_Common::call($params, _NAME_API_LOCAL, _NAME_API_MAIN, 'partner');

		if ($result[0]) {
			return array(true, $result[1], $result[2]);
		}
		else
		{
			return array(true, $result[1]);
		}
	}

/* *
 * Отправка списка заявок на вывод средств пользователя в глобальный узел
 * Внешний вызов: есть
 * @param integer $user_auth_id : глобальный ID пользователя
 * @param string $accrual_balance_type : тип баланса ('partner', 'site1_webmaster' - только для Сайта1)
 * @param (дописать параметры...)
 * @return array : успешность выполнения, код выполнения, прочие данные в массиве
 * */
	static function send_payout_request($user_auth_id, $accrual_balance_type, $request_amount,
	                                    $request_time, $payout_method, $purse_number, $payout_comment) {
		$params['action'] = 'send_payout_request';
		$params['user_auth_id'] = $user_auth_id;
		$params['accrual_balance_type'] = $accrual_balance_type;
		$params['request_amount'] = $request_amount;
		$params['request_time'] = $request_time;
		$params['payout_method'] = $payout_method;
		$params['purse_number'] = $purse_number;
		$params['payout_comment'] = $payout_comment;

		$result = xAPI_Common::call($params, _NAME_API_LOCAL, _NAME_API_MAIN, 'partner');
		return $result;
		/*
  return array(false, 'user_auth_id_empty');
  return array(false, 'user_not_found_in_auth');
  return array(false, 'user_has_no_enough_balance');
  return array(false, 'user_has_no_balance');
  return array(false, 'payout_request_not_writed');
  return array(true, 'payout_request_accepted');
  */
	}

/* *
 * Формирование реферральной ссылки, действующей для пользователя данного сайта

 * ВНИМАНИЕ! Функция привязана к сервису - для каждого сервиса реф.ссылка генериться по своему

 * Внешний вызов: есть
 * @param integer $user_auth_id : глобальный ID пользователя
 * @return array : успешность выполнения, код выполнения, рефссылка
 * */
	static function get_reflink($user_auth_id) {
		$user_auth_id = intval($user_auth_id);
		if ($user_auth_id == 0) {
			return array(false, 'auth_id_empty');
		}

		$fields = array('name', 'login');
		$result = local_get_user_info($user_auth_id, $fields);

		$reflink = 'http://' . $result['login'] . '.ref.site1.org';
		return array(true, $reflink);
	}

}

?>