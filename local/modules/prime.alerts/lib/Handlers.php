<?php

namespace Prime\Alerts;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Sale\Order;
use Bitrix\Sale\ResultError;

class Handlers
{
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
