<?php

namespace Titlo\Relevance;

/**
 * Detect country mentions in product NAME (RU declensions / common EN forms).
 */
class CountryInName
{
	/**
	 * Stems that catch nominative + common cases (Германия/Германии/Германию…).
	 *
	 * @return string[]
	 */
	public static function stems(): array
	{
		return [
			'германи',
			'росси',
			'кита',
			'япони',
			'итали',
			'франци',
			'великобритани',
			'англи',
			'южная коре',
			'южной коре',
			'северная коре',
			'северной коре',
			'коре',
			'швейцари',
			'нидерланд',
			'голланди',
			'швеци',
			'финлянди',
			'польш',
			'турци',
			'инди',
			'тайван',
			'тайвань',
			'израил',
			'австри',
			'бельги',
			'испани',
			'чехи',
			'дани',
			'норвеги',
			'канад',
			'бразили',
			'мексик',
			'украин',
			'беларус',
			'белорусси',
			'казахстан',
			'венгри',
			'португали',
			'греци',
			'ирланди',
			'шотланди',
			'сингапур',
			'малайзи',
			'индонези',
			'таиланд',
			'вьетнам',
			'пакистан',
			'египет',
			'египт',
			'эмират',
			'саудовск',
			'австрали',
			'новозеланд',
			'аргентин',
			'чили',
			'словаки',
			'словени',
			'хорвати',
			'серби',
			'болгари',
			'румын',
			'литв',
			'латви',
			'эстон',
			'грузи',
			'армени',
			'азербайджан',
			'узбекистан',
			'молдов',
			'молдав',
			// EN
			'germany',
			'china',
			'japan',
			'italy',
			'france',
			'england',
			'switzerland',
			'netherlands',
			'sweden',
			'finland',
			'poland',
			'turkey',
			'india',
			'taiwan',
			'israel',
			'austria',
			'belgium',
			'spain',
			'czech',
			'denmark',
			'norway',
			'canada',
			'brazil',
			'mexico',
			'korea',
			'singapore',
		];
	}

	/**
	 * Exact tokens (word-boundary check in PHP; LIKE in SQL).
	 *
	 * @return string[]
	 */
	public static function exactTokens(): array
	{
		return [
			'сша',
			'usa',
			'u.s.a.',
			'u.s.a',
			'uk',
			'оаэ',
			'юар',
			'рф',
			'р.ф.',
		];
	}

	public static function contains(?string $name): bool
	{
		$name = trim((string) $name);
		if ($name === '') {
			return false;
		}

		$lower = mb_strtolower($name);
		$lower = preg_replace('/\s+/u', ' ', $lower);

		foreach (self::exactTokens() as $token) {
			$token = mb_strtolower($token);
			if (preg_match('/(^|[^0-9a-zа-яё.])' . preg_quote($token, '/') . '([^0-9a-zа-яё.]|$)/ui', $lower)) {
				return true;
			}
		}

		foreach (self::stems() as $stem) {
			$stem = mb_strtolower(trim($stem));
			if ($stem === '') {
				continue;
			}
			if (mb_strlen($stem) <= 3) {
				if (preg_match('/(^|[^0-9a-zа-яё])' . preg_quote($stem, '/') . '([^0-9a-zа-яё]|$)/ui', $lower)) {
					return true;
				}
				continue;
			}
			if (mb_strpos($lower, $stem) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * SQL OR: NAME contains country stem/token.
	 */
	public static function sqlNameHasCountry(string $nameExpr, $DB): string
	{
		$parts = [];
		foreach (self::stems() as $stem) {
			$stem = trim($stem);
			if ($stem === '' || mb_strlen($stem) < 3) {
				continue;
			}
			$parts[] = 'LOWER(' . $nameExpr . ') LIKE "%' . Config::forLike(mb_strtolower($stem)) . '%"';
		}
		foreach (self::exactTokens() as $token) {
			$token = mb_strtolower(trim($token));
			if ($token === '') {
				continue;
			}
			$parts[] = 'LOWER(' . $nameExpr . ') LIKE "%' . Config::forLike($token) . '%"';
		}

		if ($parts === []) {
			return '0';
		}

		return '(' . implode(' OR ', $parts) . ')';
	}
}
