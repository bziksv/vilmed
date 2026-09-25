<?php
class CNigesCookiesAcceptPublic
{
	/**
	 * Inject cookie notice before </body>.
	 * OnEpilog runs after footer closes </html> — that produced invalid markup
	 * (banner after </html> → cascade of unexpected end tags in validators).
	 * Prefer footer include (VILMED_COOKIE_BANNER_RENDERED); OnEpilog is fallback
	 * via buffer rewrite if footer did not render.
	 */
	public static function OnEpilog()
	{
		if (defined('VILMED_COOKIE_BANNER_RENDERED')) {
			return;
		}
		if (!self::shouldRender()) {
			return;
		}

		ob_start();
		self::renderBanner();
		$html = ob_get_clean();
		if ($html === '' || $html === false) {
			return;
		}

		static $bufferHtml = null;
		$bufferHtml = $html;
		static $handlerAdded = false;
		if (!$handlerAdded) {
			$handlerAdded = true;
			AddEventHandler('main', 'OnEndBufferContent', function (&$content) use (&$bufferHtml) {
				if ($bufferHtml === null || $bufferHtml === '') {
					return;
				}
				if (stripos($content, 'nca-cookiesaccept') !== false) {
					$bufferHtml = null;
					return;
				}
				if (preg_match('/<\/body>/i', $content)) {
					$content = preg_replace('/<\/body>/i', $bufferHtml . '</body>', $content, 1);
				} else {
					$content .= $bufferHtml;
				}
				$bufferHtml = null;
			});
		}
	}

	public static function shouldRender(): bool
	{
		if (!CModule::IncludeModule(cookiesaccept_MODULE_ID)) {
			return false;
		}
		if (COption::GetOptionString(cookiesaccept_MODULE_ID, 'ACTIVE', 'N', SITE_ID) !== 'Y') {
			return false;
		}
		if (defined('PUBLIC_AJAX_MODE') && PUBLIC_AJAX_MODE === true) {
			return false;
		}
		if (isset($_REQUEST['ajax']) && (string)$_REQUEST['ajax'] !== '') {
			return false;
		}
		if (isset($_REQUEST['bxajaxid']) && (string)$_REQUEST['bxajaxid'] !== '') {
			return false;
		}
		return true;
	}

	public static function renderBanner(): void
	{
		global $APPLICATION;
		if (defined('VILMED_COOKIE_BANNER_RENDERED')) {
			return;
		}
		if (!self::shouldRender()) {
			return;
		}
		define('VILMED_COOKIE_BANNER_RENDERED', true);
		$APPLICATION->IncludeComponent(
			'niges:cookiesaccept',
			'.default',
			array(),
			false,
			array('HIDE_ICONS' => 'Y')
		);
	}
}
