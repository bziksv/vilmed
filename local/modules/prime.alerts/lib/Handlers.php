<?php

namespace Prime\Alerts;

use Bitrix\Main\Application;
use Bitrix\Main\Entity\EntityError;
use Bitrix\Main\Entity\Event as EntityEvent;
use Bitrix\Main\Entity\EventResult as EntityEventResult;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Sale\Order;
use Bitrix\Sale\ResultError;

class Handlers
{
	/**
	 * Профили заказов: POST с чужим e-mail — не даём компоненту «успешно» сохранить
	 * (он игнорирует Result от UserPropsValueTable::update).
	 */
	public static function onProlog()
	{
		if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
			return;
		}

		if (!Config::isEnabled() || !Config::isYes('policy_enabled', 'Y') || !Config::isYes('policy_order', 'Y')) {
			return;
		}

		$request = Application::getInstance()->getContext()->getRequest();
		if (!$request->isPost()) {
			return;
		}
		if ($request->getPost('save') === null && $request->getPost('apply') === null) {
			return;
		}

		$uri = (string)$request->getRequestUri();
		if (!preg_match('#/personal/profiles(/|$|\?)#i', $uri)) {
			return;
		}

		if (!check_bitrix_sessid()) {
			return;
		}

		$post = $request->getPostList()->toArray();
		foreach ($post as $key => $value) {
			if (!preg_match('/^ORDER_PROP_(\d+)$/', (string)$key, $m)) {
				continue;
			}
			if (is_array($value)) {
				continue;
			}
			$email = trim((string)$value);
			if ($email === '' || strpos($email, '@') === false) {
				continue;
			}
			$orderPropsId = (int)$m[1];
			if (!self::isEmailOrderProp($orderPropsId)) {
				continue;
			}
			if (EmailPolicy::isAllowed($email)) {
				continue;
			}

			$session = Application::getInstance()->getSession();
			$session->set('PRIME_ALERTS_FLASH_ERROR', EmailPolicy::getErrorText('checkout'));
			LocalRedirect($uri);
		}
	}

	public static function onBeforeUserRegister(&$arFields)
	{
		return self::validateUserEmail($arFields, 'signup');
	}

	public static function onBeforeUserAdd(&$arFields)
	{
		if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
			return true;
		}

		$login = strtolower((string)($arFields['LOGIN'] ?? ''));
		if ($login === 'technical_boc' || strpos($login, 'technical_') === 0) {
			return true;
		}

		$email = (string)($arFields['EMAIL'] ?? '');
		if ($email === '') {
			return true;
		}

		return self::validateUserEmail($arFields, 'signup');
	}

	/** Смена e-mail в «Персональные данные» / main.profile */
	public static function onBeforeUserUpdate(&$arFields)
	{
		if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
			return true;
		}

		if (!array_key_exists('EMAIL', $arFields)) {
			return true;
		}

		$email = trim((string)$arFields['EMAIL']);
		if ($email === '') {
			return true;
		}

		$userId = (int)($arFields['ID'] ?? 0);
		if ($userId > 0) {
			$rs = \CUser::GetByID($userId);
			if ($user = $rs->Fetch()) {
				$login = strtolower((string)($user['LOGIN'] ?? ''));
				if ($login === 'technical_boc' || strpos($login, 'technical_') === 0) {
					return true;
				}
				// e-mail не меняли — не трогаем
				if (strtolower(trim((string)($user['EMAIL'] ?? ''))) === strtolower($email)) {
					return true;
				}
			}
		}

		return self::validateUserEmail($arFields, 'signup');
	}

	public static function onSaleOrderBeforeSaved(Event $event)
	{
		if (!Config::isEnabled() || !Config::isYes('policy_enabled', 'Y') || !Config::isYes('policy_order', 'Y')) {
			return;
		}

		/** @var Order|null $order */
		$order = $event->getParameter('ENTITY');
		if (!$order instanceof Order) {
			return;
		}

		// «Заказать товар» / buy.one.click — учётка с введённого e-mail не создаётся
		if (self::isBuyOneClickRequest($order)) {
			return;
		}

		$isNew = $event->getParameter('IS_NEW');
		$orderId = (int)$order->getId();
		$looksNew = ($isNew === true) || $orderId <= 0 || (method_exists($order, 'isNew') && $order->isNew());
		if (!$looksNew) {
			return;
		}

		$email = self::orderEmail($order);
		if ($email === '') {
			return;
		}

		if (EmailPolicy::isAllowed($email)) {
			return;
		}

		return new EventResult(
			EventResult::ERROR,
			new ResultError(EmailPolicy::getErrorText('checkout'), 'PRIME_ALERTS_EMAIL_POLICY')
		);
	}

	/** E-mail в профиле покупателя (sale.personal.profile.detail) */
	public static function onBeforeUserPropsValueUpdate(EntityEvent $event)
	{
		return self::validateUserPropsValueEvent($event);
	}

	public static function onBeforeUserPropsValueAdd(EntityEvent $event)
	{
		return self::validateUserPropsValueEvent($event);
	}

	protected static function validateUserPropsValueEvent(EntityEvent $event): EntityEventResult
	{
		$result = new EntityEventResult();

		if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
			return $result;
		}

		if (!Config::isEnabled() || !Config::isYes('policy_enabled', 'Y') || !Config::isYes('policy_order', 'Y')) {
			return $result;
		}

		$fields = $event->getParameter('fields');
		if (!is_array($fields) || !array_key_exists('VALUE', $fields)) {
			return $result;
		}

		$email = trim((string)$fields['VALUE']);
		if ($email === '' || strpos($email, '@') === false) {
			return $result;
		}

		$orderPropsId = (int)($fields['ORDER_PROPS_ID'] ?? 0);
		if ($orderPropsId <= 0) {
			$id = $event->getParameter('id');
			if (is_array($id)) {
				$id = (int)($id['ID'] ?? reset($id));
			} else {
				$id = (int)$id;
			}
			if ($id > 0 && class_exists('\Bitrix\Sale\Internals\UserPropsValueTable')) {
				$row = \Bitrix\Sale\Internals\UserPropsValueTable::getById($id)->fetch();
				$orderPropsId = (int)($row['ORDER_PROPS_ID'] ?? 0);
			}
		}

		if ($orderPropsId <= 0 || !self::isEmailOrderProp($orderPropsId)) {
			return $result;
		}

		if (EmailPolicy::isAllowed($email)) {
			return $result;
		}

		$result->addError(new EntityError(EmailPolicy::getErrorText('checkout')));

		return $result;
	}

	protected static function isEmailOrderProp(int $orderPropsId): bool
	{
		if ($orderPropsId <= 0 || !class_exists('\Bitrix\Sale\Internals\OrderPropsTable')) {
			return false;
		}

		$prop = \Bitrix\Sale\Internals\OrderPropsTable::getById($orderPropsId)->fetch();
		if (!$prop) {
			return false;
		}

		$code = strtoupper((string)($prop['CODE'] ?? ''));
		if ($code === 'EMAIL') {
			return true;
		}

		return (($prop['IS_EMAIL'] ?? 'N') === 'Y');
	}

	protected static function orderEmail(Order $order): string
	{
		$collection = $order->getPropertyCollection();
		if ($collection) {
			foreach ($collection as $property) {
				if (strtoupper((string)$property->getField('CODE')) === 'EMAIL') {
					$email = trim((string)$property->getValue());
					if ($email !== '') {
						return $email;
					}
				}
			}
		}

		$userId = (int)$order->getUserId();
		if ($userId > 0) {
			$rs = \CUser::GetByID($userId);
			if ($user = $rs->Fetch()) {
				return (string)($user['EMAIL'] ?? '');
			}
		}

		return '';
	}

	protected static function isBuyOneClickRequest(Order $order): bool
	{
		$hay = strtolower(
			(string)($_SERVER['REQUEST_URI'] ?? '') . ' ' .
			(string)($_SERVER['SCRIPT_NAME'] ?? '') . ' ' .
			(string)($_SERVER['PHP_SELF'] ?? '')
		);
		if (strpos($hay, 'buy.one.click') !== false
			|| strpos($hay, '/boc_') !== false
			|| strpos($hay, 'altop/forms') !== false
			|| strpos($hay, 'under_order') !== false
		) {
			return true;
		}

		$userId = (int)$order->getUserId();
		if ($userId > 0) {
			$rs = \CUser::GetByID($userId);
			if ($user = $rs->Fetch()) {
				$login = strtolower((string)($user['LOGIN'] ?? ''));
				if ($login === 'technical_boc' || strpos($login, 'technical_') === 0) {
					return true;
				}
			}
		}

		return false;
	}

	protected static function validateUserEmail(&$arFields, string $context)
	{
		if (!Config::isEnabled() || !Config::isYes('policy_enabled', 'Y') || !Config::isYes('policy_register', 'Y')) {
			return true;
		}

		$email = (string)($arFields['EMAIL'] ?? '');
		if ($email === '') {
			return true;
		}

		if (EmailPolicy::isAllowed($email)) {
			return true;
		}

		global $APPLICATION;
		if (is_object($APPLICATION)) {
			$APPLICATION->ThrowException(EmailPolicy::getErrorText($context));
		}

		return false;
	}
}
