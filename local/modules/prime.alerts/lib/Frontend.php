<?php

namespace Prime\Alerts;

use Bitrix\Main\Web\Json;

class Frontend
{
	public static function onEndBufferContent(&$content): void
	{
		if (PHP_SAPI === 'cli' || (defined('ADMIN_SECTION') && ADMIN_SECTION === true)) {
			return;
		}

		if (!is_string($content) || $content === '') {
			return;
		}

		// Avoid double inject
		if (strpos($content, 'PRIME_ALERTS') !== false) {
			return;
		}

		// Only full HTML pages — never append to JSON/AJAX (location selector, sale.order.ajax, etc.)
		if (stripos($content, '</body>') === false) {
			return;
		}
		try {
			$request = \Bitrix\Main\Context::getCurrent()->getRequest();
			if ($request->isAjaxRequest()) {
				return;
			}
		} catch (\Throwable $e) {
			// ignore
		}

		if (!Config::isEnabled() || !Config::isYes('policy_enabled', 'Y')) {
			return;
		}

		$policyRegister = Config::isYes('policy_register', 'Y');
		$policyOrder = Config::isYes('policy_order', 'Y');
		if (!$policyRegister && !$policyOrder) {
			return;
		}

		$providers = array_values(array_unique(array_merge(
			EmailPolicy::getRuProviders(),
			EmailPolicy::getExtraDomains()
		)));

		$config = [
			'enabled' => true,
			'providers' => $providers,
			'policyRegister' => $policyRegister,
			'policyOrder' => $policyOrder,
			'noticeEverywhere' => Config::isYes('notice_everywhere', 'N'),
			'noticeSignup' => EmailPolicy::getNoticeHtml('signup'),
			'noticeCheckout' => EmailPolicy::getNoticeHtml('checkout'),
		];

		$cssHref = '/local/modules/prime.alerts/assets/style.css?v=1.2.2';
		$jsHref = '/local/modules/prime.alerts/assets/policy.js?v=1.2.2';
		$flash = '';
		try {
			$session = \Bitrix\Main\Application::getInstance()->getSession();
			if ($session->has('PRIME_ALERTS_FLASH_ERROR')) {
				$msg = (string)$session->get('PRIME_ALERTS_FLASH_ERROR');
				$session->remove('PRIME_ALERTS_FLASH_ERROR');
				if ($msg !== '') {
					$flash = '<div class="prime-alerts-flash" role="alert">'
						. htmlspecialcharsbx($msg)
						. '</div>';
				}
			}
		} catch (\Throwable $e) {
			$flash = '';
		}

		$inject = "\n<link rel=\"stylesheet\" href=\"" . htmlspecialcharsbx($cssHref) . "\">\n"
			. '<script>window.PRIME_ALERTS=' . Json::encode($config) . ';</script>' . "\n"
			. '<script src="' . htmlspecialcharsbx($jsHref) . '"></script>' . "\n";

		if ($flash !== '') {
			if (preg_match('/<h1[^>]*>/i', $content)) {
				$content = preg_replace('/(<h1[^>]*>)/i', $flash . '$1', $content, 1);
			} elseif (stripos($content, '<div class="workarea') !== false) {
				$content = preg_replace('/(<div class="workarea[^"]*"[^>]*>)/i', '$1' . $flash, $content, 1);
			} else {
				$inject = $flash . $inject;
			}
		}

		$content = preg_replace('/<\/body>/i', $inject . '</body>', $content, 1);
	}
}
