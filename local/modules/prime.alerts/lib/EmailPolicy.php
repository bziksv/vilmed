<?php

namespace Prime\Alerts;

class EmailPolicy
{
	/** @var string[] */
	protected static $ruProviders = [
		'mail.ru',
		'inbox.ru',
		'list.ru',
		'bk.ru',
		'internet.ru',
		'yandex.ru',
		'ya.ru',
		'yandex.com',
		'yandex.by',
		'yandex.kz',
		'yandex.ua',
		'rambler.ru',
		'lenta.ru',
		'autorambler.ru',
		'ro.ru',
		'pochta.ru',
		'e-mail.ru',
		'qip.ru',
		'live.ru',
	];

	public static function getDomain(string $email): string
	{
		$email = strtolower(trim($email));
		if ($email === '' || strpos($email, '@') === false) {
			return '';
		}

		return substr(strrchr($email, '@'), 1) ?: '';
	}

	public static function isAllowed(string $email): bool
	{
		$domain = self::getDomain($email);
		if ($domain === '') {
			return false;
		}

		if (preg_match('/\.(ru|su)$/u', $domain)) {
			return true;
		}

		foreach (self::$ruProviders as $provider) {
			if ($domain === $provider || substr($domain, -strlen('.' . $provider)) === '.' . $provider) {
				return true;
			}
		}

		$extra = Config::get('extra_domains', '');
		foreach (preg_split('/[\s,;]+/', $extra) ?: [] as $allowed) {
			$allowed = strtolower(trim($allowed));
			if ($allowed === '') {
				continue;
			}
			if ($domain === $allowed || substr($domain, -strlen('.' . $allowed)) === '.' . $allowed) {
				return true;
			}
		}

		return false;
	}

	/** @return string[] */
	public static function getRuProviders(): array
	{
		return self::$ruProviders;
	}

	/** @return string[] */
	public static function getExtraDomains(): array
	{
		$extra = Config::get('extra_domains', '');
		$out = [];
		foreach (preg_split('/[\s,;]+/', $extra) ?: [] as $allowed) {
			$allowed = strtolower(trim($allowed));
			if ($allowed !== '') {
				$out[] = $allowed;
			}
		}

		return $out;
	}

	public static function getDefaultErrorText(string $context = 'signup'): string
	{
		if ($context === 'checkout') {
			return 'Оформление заказа доступно только с e-mail в зонах .ru / .su или на российском почтовом сервисе (Яндекс, Mail.ru и т.п.). Зарубежные адреса (gmail.com и др.) не принимаются.';
		}

		return 'Регистрация доступна только с e-mail в зонах .ru / .su или на российском почтовом сервисе (Яндекс, Mail.ru и т.п.). Зарубежные адреса (gmail.com и др.) не принимаются.';
	}

	public static function getDefaultNoticeTitle(string $context = 'signup'): string
	{
		if ($context === 'checkout') {
			return 'Оформление заказа: требования к e-mail';
		}

		return 'Регистрация: требования к e-mail';
	}

	public static function getDefaultNoticeText(string $context = 'signup'): string
	{
		$action = ($context === 'checkout')
			? 'Оформление заказа на сайте доступно'
			: 'Регистрация на сайте доступна';

		return '<p>' . $action . ' только с адресом электронной почты в доменных зонах <strong>.ru</strong> или <strong>.su</strong>, '
			. 'либо на российском почтовом сервисе (например, '
			. '<a href="https://360.yandex.ru/mail/" target="_blank" rel="noopener">Яндекс</a> или '
			. '<a href="https://mail.ru/" target="_blank" rel="noopener">Mail.ru</a>). '
			. 'Адреса зарубежных почтовых сервисов и доменов других зон не принимаются.</p>'
			. "\n"
			. '<p>Вы можете отправить нам заявку с любого почтового ящика на #EMAIL#.</p>'
			. "\n"
			. '<p class="prime-alerts-notice__legal">Данная мера применяется в соответствии с Федеральным законом от 31.07.2023 № 406-ФЗ, '
			. 'а также в связи с требованиями Федерального закона от 27.07.2006 № 152-ФЗ «О персональных данных» '
			. '(в том числе в части локализации баз данных на территории Российской Федерации).</p>'
			. "\n"
			. '<p>Если вы считаете, что это ошибка, позвоните нам: #PHONE#.</p>';
	}

	public static function getErrorText(string $context = 'signup'): string
	{
		$key = ($context === 'checkout') ? 'error_text_checkout' : 'error_text_signup';
		$custom = trim(Config::get($key, ''));
		$text = $custom !== '' ? $custom : self::getDefaultErrorText($context);

		return self::applyMacrosPlain($text);
	}

	public static function getNoticeTitle(string $context = 'signup'): string
	{
		$key = ($context === 'checkout') ? 'notice_title_checkout' : 'notice_title_signup';
		$custom = trim(Config::get($key, ''));
		$title = $custom !== '' ? $custom : self::getDefaultNoticeTitle($context);

		return self::applyMacrosPlain($title);
	}

	public static function getNoticeBodyHtml(string $context = 'signup'): string
	{
		$key = ($context === 'checkout') ? 'notice_text_checkout' : 'notice_text_signup';
		$custom = trim(Config::get($key, ''));
		$body = $custom !== '' ? $custom : self::getDefaultNoticeText($context);

		return self::applyMacrosHtml($body);
	}

	public static function getNoticeHtml(string $context = 'signup'): string
	{
		$title = htmlspecialcharsbx(self::getNoticeTitle($context));
		$body = self::getNoticeBodyHtml($context);

		return '<div class="prime-alerts-notice signup-email-policy-notice">'
			. '<div class="prime-alerts-notice__inner">'
			. '<div class="prime-alerts-notice__icon" aria-hidden="true">!</div>'
			. '<div class="prime-alerts-notice__content">'
			. '<div class="prime-alerts-notice__title">' . $title . '</div>'
			. '<div class="prime-alerts-notice__text">' . $body . '</div>'
			. '</div></div></div>';
	}

	/** Macros for plain text (errors, titles): #EMAIL#, #PHONE#, #TEL#, #EMAIL_RAW#, #PHONE_RAW# */
	public static function applyMacrosPlain(string $text): string
	{
		[$email, $phone, $tel] = self::supportContacts();

		return str_replace(
			['#EMAIL#', '#PHONE#', '#TEL#', '#EMAIL_RAW#', '#PHONE_RAW#', '#SUPPORT_EMAIL#', '#SUPPORT_PHONE#'],
			[$email, $phone, $tel, $email, $phone, $email, $phone],
			$text
		);
	}

	/**
	 * Macros for HTML notice body:
	 * #EMAIL# / #SUPPORT_EMAIL# → mailto link
	 * #PHONE# / #SUPPORT_PHONE# → tel link
	 * #EMAIL_RAW# / #PHONE_RAW# / #TEL# → escaped plain values
	 */
	public static function applyMacrosHtml(string $text): string
	{
		[$email, $phone, $tel] = self::supportContacts();
		$emailEsc = htmlspecialcharsbx($email);
		$phoneEsc = htmlspecialcharsbx($phone);
		$telEsc = htmlspecialcharsbx($tel);
		$emailLink = '<a href="mailto:' . $emailEsc . '">' . $emailEsc . '</a>';
		$phoneLink = '<a href="tel:' . $telEsc . '">' . $phoneEsc . '</a>';

		return str_replace(
			['#EMAIL#', '#PHONE#', '#TEL#', '#EMAIL_RAW#', '#PHONE_RAW#', '#SUPPORT_EMAIL#', '#SUPPORT_PHONE#'],
			[$emailLink, $phoneLink, $telEsc, $emailEsc, $phoneEsc, $emailLink, $phoneLink],
			$text
		);
	}

	/** @return array{0:string,1:string,2:string} email, phone display, tel digits */
	protected static function supportContacts(): array
	{
		$email = trim(Config::get('support_email', 'info@kosmamed.ru')) ?: 'info@kosmamed.ru';
		$phone = trim(Config::get('support_phone', '8-800-100-37-97')) ?: '8-800-100-37-97';
		$tel = preg_replace('/\D+/', '', $phone) ?: '88001003797';

		return [$email, $phone, $tel];
	}
}
