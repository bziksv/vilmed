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

		$cssHref = '/local/modules/prime.alerts/assets/style.css?v=1.1.9';
		$jsHref = '/local/modules/prime.alerts/assets/policy.js?v=1.1.9';
		$inject = "\n<link rel=\"stylesheet\" href=\"" . htmlspecialcharsbx($cssHref) . "\">\n"
			. '<script>window.PRIME_ALERTS=' . Json::encode($config) . ';</script>' . "\n"
			. '<script src="' . htmlspecialcharsbx($jsHref) . '"></script>' . "\n";

		if (stripos($content, '</body>') !== false) {
			$content = preg_replace('/<\/body>/i', $inject . '</body>', $content, 1);
		} else {
			$content .= $inject;
		}
	}
}
