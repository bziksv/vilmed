<?php

namespace Titlo\Relevance;

use Bitrix\Main\Config\Option;

/**
 * Несколько именованных промптов на тип генерации.
 * Группы: товары / категории / короткие названия.
 */
class Prompts
{
	public const TYPE_PREVIEW = 'preview';
	public const TYPE_DETAIL = 'detail';
	public const TYPE_CATEGORY = 'category';
	public const TYPE_PHRASE = 'phrase';
	public const TYPE_PHRASE_BATCH = 'phrase_batch';
	public const TYPE_SECTION_PHRASE = 'section_phrase';
	public const TYPE_SECTION_PHRASE_BATCH = 'section_phrase_batch';

	/** @var bool */
	protected static $tablesReady = false;

	/**
	 * Типы промптов коротких названий для entity E|S.
	 */
	public static function phraseTypeForEntity(string $entityType, bool $batch = false): string
	{
		$isSection = strtoupper($entityType) === 'S';
		if ($batch) {
			return $isSection ? self::TYPE_SECTION_PHRASE_BATCH : self::TYPE_PHRASE_BATCH;
		}
		return $isSection ? self::TYPE_SECTION_PHRASE : self::TYPE_PHRASE;
	}

	/**
	 * Группы для UI.
	 *
	 * @return array<string, array{title:string,hint:string,types:string[]}>
	 */
	public static function groups(): array
	{
		return [
			'product' => [
				'title' => 'Товары',
				'hint' => 'Тексты карточки товара: анонс и детальное описание. На проработке выбираете, каким промптом генерировать.',
				'types' => [self::TYPE_PREVIEW, self::TYPE_DETAIL],
			],
			'category' => [
				'title' => 'Категории',
				'hint' => 'SEO-текст раздела каталога (DESCRIPTION).',
				'types' => [self::TYPE_CATEGORY],
			],
			'names' => [
				'title' => 'Короткие названия товаров',
				'hint' => 'Укорочение NAME до ключевой фразы ≤ 50 символов: по одному и пачкой на «Проверке названий товаров».',
				'types' => [self::TYPE_PHRASE, self::TYPE_PHRASE_BATCH],
			],
			'section_names' => [
				'title' => 'Короткие названия категорий',
				'hint' => 'Укорочение NAME раздела ≤ 50 символов. Приоритет: тип → важное уточнение (педиатрические/взрослые) → бренд. «купить» в конце, только если влезает без потери смысла.',
				'types' => [self::TYPE_SECTION_PHRASE, self::TYPE_SECTION_PHRASE_BATCH],
			],
		];
	}

	/**
	 * Мета по типам (плейсхолдеры, дефолтный текст).
	 *
	 * @return array<string, array{title:string,for:string,placeholders:string,default:string}>
	 */
	public static function catalog(): array
	{
		return [
			self::TYPE_PREVIEW => [
				'title' => 'Анонс (PREVIEW_TEXT)',
				'for' => 'Короткий текст в карточке товара.',
				'placeholders' => '{link} — URL посадочной',
				'default' => "Роль:\nТы — профессиональный копирайтер интернет-магазина.\n\nЗадача:\nСоставь короткий анонс (preview) товара по ссылке: {link}.\nИспользуй реальное содержимое страницы.\n\nТребования:\n- 2–4 предложения, до 400 символов;\n- без HTML-разметки;\n- продающий, но без воды и без выдуманных характеристик;\n- один вариант текста.",
			],
			self::TYPE_DETAIL => [
				'title' => 'Детальное (DETAIL_TEXT)',
				'for' => 'Полное описание карточки товара.',
				'placeholders' => '{link} — URL посадочной',
				'default' => "Роль:\nТы — профессиональный копирайтер интернет-магазина.\n\nЗадача:\nСоставь детальное описание товара по ссылке: {link}.\nИспользуй реальное содержимое страницы.\n\nТребования:\n- HTML-разметка: абзацы <p>, при необходимости подзаголовки <h2>/<h3> и списки <ul><li>;\n- не выдумывай характеристики, которых нет на странице;\n- один полный вариант описания для карточки товара;\n- без markdown и без комментариев.",
			],
			self::TYPE_CATEGORY => [
				'title' => 'Текст категории (DESCRIPTION)',
				'for' => 'SEO-текст раздела каталога (HTML, при необходимости в разметке .vmd-desc).',
				'placeholders' => '{link} — URL посадочной',
				'default' => "Роль:\nТы — профессиональный копирайтер.\n\nЗадача:\nСоставь уникальный SEO-текст для категории товаров по ссылке: {link}.\nДля составления текста используй реальное содержимое указанной страницы.\n\nТекст должен быть пригоден для размещения на сайте как SEO-текст категории. Достаточно одного варианта.\n\nУникальность и грамотность:\nТекст должен быть полностью уникальным.\nПредложения должны быть грамотными и легко читаться.",
			],
			self::TYPE_PHRASE => [
				'title' => 'Короткая фраза (один товар)',
				'for' => 'Одна ключевая фраза ≤ 50 символов.',
				'placeholders' => '{name} — полное название товара',
				'default' => "Роль:\nТы — SEO-специалист интернет-магазина.\n\nЗадача:\nИз полного названия товара сделай короткую ключевую фразу для проверки релевантности в поиске.\n\nПолное название:\n{name}\n\nТребования:\n- одна фраза, максимум 50 символов (считая пробелы);\n- оставь бренд/модель и тип товара;\n- убери комплектацию, страну, лишние уточнения;\n- без кавычек, без HTML, без пояснений — только готовая фраза;\n- язык: русский (латиница бренда/модели допустима).",
			],
			self::TYPE_PHRASE_BATCH => [
				'title' => 'Короткие фразы (пачка)',
				'for' => 'Массовая генерация на «Проверке названий товаров». Ответ — JSON-массив.',
				'placeholders' => '{list} — список названий (подставляется автоматически)',
				'default' => "Роль:\nТы — SEO-специалист интернет-магазина.\n\nЗадача:\nДля КАЖДОГО полного названия ниже сделай короткую ключевую фразу для проверки релевантности.\n\nТребования к каждой фразе:\n- максимум 50 символов (с пробелами);\n- бренд/модель и тип товара;\n- без комплектации, страны и лишних уточнений;\n- без кавычек и HTML.\n\nФормат ответа — СТРОГО JSON-массив строк той же длины и в том же порядке, без текста вокруг.\nПример: [\"OMEGA 500 офтальмоскоп\",\"Mindray DC-70 УЗИ\"]\n\nНазвания:\n{list}",
			],
			self::TYPE_SECTION_PHRASE => [
				'title' => 'Короткая фраза (одна категория)',
				'for' => 'Коммерческая фраза категории ≤ 50 символов с обязательным «купить».',
				'placeholders' => '{name} — полное название категории',
				'default' => self::sectionPhraseDefaultSingle(),
			],
			self::TYPE_SECTION_PHRASE_BATCH => [
				'title' => 'Короткие фразы категорий (пачка)',
				'for' => 'Массовая генерация на «Проверке названий категорий». Ответ — JSON-массив.',
				'placeholders' => '{list} — список названий категорий (подставляется автоматически)',
				'default' => self::sectionPhraseDefaultBatch(),
			],
		];
	}

	public static function sectionPhraseDefaultSingle(): string
	{
		return "Роль:\nТы — SEO-специалист интернет-магазина.\n\nЗадача:\nИз полного названия категории сделай короткую ключевую фразу для проверки релевантности.\n\nПолное название:\n{name}\n\nПриоритет смысла (от важного к второстепенному):\n1) тип/группа (фибросигмоидоскопы, отоскопы, видеосистема…);\n2) важное уточнение аудитории/вида: педиатрические, взрослые, детские и т.п. — НЕ выбрасывай;\n3) бренд из названия (Welch Allyn, Heine, CHIROMEGA…) — СОХРАНЯЙ;\n4) слово «купить» в КОНЦЕ — только если влезает в 50 символов ПОСЛЕ пунктов 1–3.\n\nЧто убирать в первую очередь:\n- страну (Германия, Китай…);\n- второстепенные хвосты: «с обтуратором», «дополнительные», «и комплектующие», «Full HD…».\n\nЖёсткие правила:\n- максимум 50 символов вместе с пробелами;\n- не обрезай слова посередине;\n- не жертвуй уточнением или брендом ради «купить»;\n- без кавычек, HTML и пояснений — только фраза;\n- язык: русский (латиница бренда допустима).\n\nПримеры:\nПолное: «Фибросигмоидоскопы с обтуратором педиатрические Welch Allyn» → фибросигмоидоскопы педиатрические Welch Allyn\nПолное: «Фибросигмоидоскопы с обтуратором взрослые Welch Allyn» → фибросигмоидоскопы взрослые Welch Allyn купить\nПолное: «Дополнительные проктологические принадлежности Heine, Германия» → проктологические Heine купить\nПолное: «Отоскопы» → отоскопы купить\nПолное: «Видеосистема для ректоскопа и комплектующие Full HD 1080» → видеосистема для ректоскопа купить";
	}

	public static function sectionPhraseDefaultBatch(): string
	{
		return "Роль:\nТы — SEO-специалист интернет-магазина.\n\nЗадача:\nДля КАЖДОГО полного названия категории сделай короткую ключевую фразу ≤ 50 символов.\n\nПриоритет: тип → важное уточнение (педиатрические/взрослые…) → бренд → «купить» в конце только если влезает.\nУбирай страну и хвосты вроде «с обтуратором», «дополнительные», «комплектующие».\nНе режь слова посередине. Не выкидывай уточнение/бренд ради «купить».\n\nФормат ответа — СТРОГО JSON-массив строк той же длины и в том же порядке, без текста вокруг.\nПример: [\"фибросигмоидоскопы педиатрические Welch Allyn\",\"проктологические Heine купить\"]\n\nНазвания:\n{list}";
	}

	public static function ensureTables(): void
	{
		if (self::$tablesReady) {
			return;
		}
		global $DB;
		$DB->Query("
			CREATE TABLE IF NOT EXISTS titlo_prompts (
				ID int(11) NOT NULL AUTO_INCREMENT,
				TYPE varchar(32) NOT NULL DEFAULT '',
				NAME varchar(255) NOT NULL DEFAULT '',
				BODY mediumtext,
				IS_DEFAULT char(1) NOT NULL DEFAULT 'N',
				SORT int(11) NOT NULL DEFAULT 100,
				LAST_USED_AT datetime DEFAULT NULL,
				USE_COUNT int(11) NOT NULL DEFAULT 0,
				CREATED_AT datetime DEFAULT NULL,
				UPDATED_AT datetime DEFAULT NULL,
				PRIMARY KEY (ID),
				KEY ix_type_sort (TYPE, SORT, ID)
			)
		");
		self::$tablesReady = true;
		self::seedAndMigrate();
		self::seedVilmedDetailExtra();
		self::seedVilmedCategoryExtra();
		self::seedRefineDetailPrompts();
		self::seedRefineCategoryPrompts();
		self::seedSectionPhraseKupitEnd();
		self::seedSectionPhraseKeepBrand();
		self::seedSectionPhraseMeaningFirst();
	}

	/**
	 * Пример промпта детального описания со стилями (дизайн-система .vmd-desc магазина Vilmed).
	 * Наглядное пособие: другие сайты копируют структуру и подставляют свои классы после внедрения CSS.
	 */
	public static function vilmedDetailBody(): string
	{
		return <<<'TXT'
ПРИМЕР промпта со стилями (магазин Vilmed, дизайн-система .vmd-desc).
На другом сайте: сначала сверстайте демо и CSS, затем замените классы ниже на свои.

Ты — контент-редактор интернет-магазина медтехники. Сформируй
HTML-описание товара строго в разметке дизайн-системы .vmd-desc
(см. правила ниже). Верни ТОЛЬКО HTML внутри <article class="vmd-desc">…</article>,
без markdown, без пояснений, без ```.

ДАННЫЕ О ТОВАРЕ:
Возьми из реального содержимого страницы: {link}
(название, назначение, ключевые особенности, таблицу ТТХ, типовые вопросы).
Не выдумывай характеристики и числа, которых нет на странице.

ПРАВИЛА РАЗМЕТКИ (используй только эти классы, ничего не выдумывай):
- Корень: <article class="vmd-desc"> … </article>.
- <h1> — название товара; сразу после — <p class="vmd-subtitle"> с одним
  предложением-лидом.
- 1–2 вводных <p>. Главное помечай <strong>, один-два ключевых факта — <mark>.
- Разделы — <h2> (например: «Назначение и принцип работы»,
  «Ключевые преимущества», «Что входит в диагностику»,
  «Технические характеристики», «Частые вопросы»).
- Ключевые преимущества — блок:
  <div class="vmd-features"> с 3–4 карточками
  <div class="vmd-feature"><div class="ic">SVG</div><h3>…</h3><p>…</p></div>.
- Списки — <ul class="vmd-list"><li>…</li></ul>.
- ТТХ — <div class="vmd-table-wrap"><table class="vmd-spec">
  <tr><td class="k">Параметр</td><td class="v">Значение</td></tr>…</table></div>.
- Примечания по смыслу (необязательно): <div class="vmd-note vmd-note--info|--warn|--accent">
  <div class="ic">SVG</div><div>…</div></div>.
  НЕ дублируй здесь дисклеймер про изменение характеристик — он только в конце.
- FAQ (если есть вопросы): <div class="vmd-faq">
  <details open><summary>Вопрос?</summary><div class="vmd-faq__a">Ответ.</div></details>…</div>.
- Блок vmd-cta (форма «Запросить цену») НЕ добавляй.
- В самом конце ОДИН раз — <div class="vmd-manager"><div class="ic">SVG</div><div>…</div></div>
  с текстом про возможное изменение характеристик/комплектации и контактом
  info@vilmed.ru. Эту фразу больше нигде не повторяй.

ИКОНКИ (.ic): только инлайновый <svg> в line-стиле Lucide:
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
stroke-linecap="round" stroke-linejoin="round". Цвет НЕ указывай.
Подбирай по смыслу (target, microscope, sliders-horizontal, move-horizontal,
gauge, monitor, printer, shield-check, truck, info, alert-triangle, mail).

ТОН: деловой, по делу, без рекламной воды и превосходных степеней без оснований.
Не выдумывай характеристики, которых нет в данных. Все числа — из данных.

ЗАПРЕТ НА ВЫДУМАННЫЕ КОММЕРЧЕСКИЕ УСЛОВИЯ (жёстко):
- Не пиши, что у нас «есть» лизинг / рассрочка / кредит / акции / скидки /
  «официальное КП» / «в наличии на складе» / гарантированные сроки поставки —
  если этого ДОСЛОВНО нет на странице товара.
- Не создавай FAQ вида «Есть ли лизинг/рассрочка?» с ответом «Да…».
- Если тема коммерческих условий нужна — максимум одна нейтральная фраза:
  «По действующим программам лизинга, рассрочки и условиям поставки напишите
  на info@vilmed.ru — менеджер уточнит актуальные возможности.»
  Без слова «да», без «доступны», без «по запросу рассчитаем».
- В блоке vmd-manager не упоминай лизинг/рассрочку — только характеристики/
  комплектация и контакт info@vilmed.ru.
TXT;
	}

	/**
	 * Пример промпта SEO-текста категории со стилями (.vmd-desc) — те же классы, что у товара.
	 */
	public static function vilmedCategoryBody(): string
	{
		return <<<'TXT'
ПРИМЕР промпта со стилями для КАТЕГОРИИ (магазин Vilmed, дизайн-система .vmd-desc).
Те же CSS-классы, что у детального описания товара — отдельный CSS не нужен.
На другом сайте: сначала сверстайте демо и CSS, затем замените классы ниже на свои.

Ты — контент-редактор интернет-магазина медтехники. Сформируй
SEO-текст раздела каталога строго в разметке дизайн-системы .vmd-desc
(см. правила ниже). Верни ТОЛЬКО HTML внутри <article class="vmd-desc">…</article>,
без markdown, без пояснений, без ```.

ДАННЫЕ О КАТЕГОРИИ:
Возьми из реального содержимого страницы: {link}
(название раздела, типы товаров в выдаче, бренды/линейки если видны на странице,
типовые вопросы покупателей). Не выдумывай ассортимент, бренды и условия,
которых нет на странице.

ПРАВИЛА РАЗМЕТКИ (используй только эти классы, ничего не выдумывай):
- Корень: <article class="vmd-desc"> … </article>.
- <h1> — название категории; сразу после — <p class="vmd-subtitle"> с одним
  предложением-лидом (что за раздел и для кого).
  Важно: h1 и vmd-subtitle должны идти первыми — на сайте они остаются
  видимыми, а весь остальной текст раскрывается по кнопке «Подробнее».
- 1–2 вводных <p>. Главное помечай <strong>, 1–2 ключевых факта — <mark>.
- Разделы — <h2> (например: «Что входит в раздел», «Кому подойдёт»,
  «На что обратить внимание при выборе», «Частые вопросы»).
- Сценарии / типы применения — блок:
  <div class="vmd-features"> с 3–4 карточками
  <div class="vmd-feature"><div class="ic">SVG</div><h3>…</h3><p>…</p></div>.
- Перечень типов оборудования / подгрупп — <ul class="vmd-list"><li>…</li></ul>.
- Таблицу ТТХ конкретного товара (<table class="vmd-spec">) НЕ делай —
  это текст раздела, не карточка модели.
- Примечания по смыслу (необязательно): <div class="vmd-note vmd-note--info|--warn|--accent">
  <div class="ic">SVG</div><div>…</div></div>.
- FAQ (2–4 вопроса): <div class="vmd-faq">
  <details open><summary>Вопрос?</summary><div class="vmd-faq__a">Ответ.</div></details>…</div>.
- Блок vmd-cta НЕ добавляй.
- В самом конце ОДИН раз — <div class="vmd-manager"><div class="ic">SVG</div><div>…</div></div>
  с нейтральной фразой про подбор оборудования и контактом info@vilmed.ru.
  Эту фразу больше нигде не повторяй.

ИКОНКИ (.ic): только инлайновый <svg> в line-стиле Lucide:
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
stroke-linecap="round" stroke-linejoin="round". Цвет НЕ указывай.
Подбирай по смыслу (stethoscope, ear, search, shield-check, users, package,
info, alert-triangle, mail, check-circle).

ТОН: деловой SEO-текст раздела, без рекламной воды и превосходных степеней
без оснований. Не выдумывай факты. Объём ориентировочно 1500–3500 символов
видимого текста.

ЗАПРЕТ НА ВЫДУМАННЫЕ КОММЕРЧЕСКИЕ УСЛОВИЯ (жёстко):
- Не пиши, что у нас «есть» лизинг / рассрочка / кредит / акции / скидки /
  «официальное КП» / «в наличии на складе» / гарантированные сроки поставки —
  если этого ДОСЛОВНО нет на странице.
- Не создавай FAQ вида «Есть ли лизинг/рассрочка?» с ответом «Да…».
- Если тема коммерческих условий нужна — максимум одна нейтральная фраза:
  «По действующим программам лизинга, рассрочки и условиям поставки напишите
  на info@vilmed.ru — менеджер уточнит актуальные возможности.»
- В блоке vmd-manager не упоминай лизинг/рассрочку — только подбор/
  консультация и контакт info@vilmed.ru.
TXT;
	}

	protected static function seedAndMigrate(): void
	{
		global $DB;
		foreach (self::catalog() as $type => $meta) {
			$cnt = (int) ($DB->Query(
				"SELECT COUNT(*) AS C FROM titlo_prompts WHERE TYPE='" . $DB->ForSql($type) . "'"
			)->Fetch()['C'] ?? 0);
			if ($cnt > 0) {
				continue;
			}

			// Миграция со старого Option prompt_*
			$legacy = trim((string) Option::get(Config::MODULE_ID, 'prompt_' . $type, ''));
			$now = date('Y-m-d H:i:s');
			if ($legacy !== '' && $legacy !== trim($meta['default'])) {
				$DB->Query("
					INSERT INTO titlo_prompts (TYPE, NAME, BODY, IS_DEFAULT, SORT, USE_COUNT, CREATED_AT, UPDATED_AT)
					VALUES (
						'" . $DB->ForSql($type) . "',
						'Свой (из старых настроек)',
						'" . $DB->ForSql($legacy) . "',
						'Y',
						10,
						0,
						'" . $DB->ForSql($now) . "',
						'" . $DB->ForSql($now) . "'
					)
				");
				$DB->Query("
					INSERT INTO titlo_prompts (TYPE, NAME, BODY, IS_DEFAULT, SORT, USE_COUNT, CREATED_AT, UPDATED_AT)
					VALUES (
						'" . $DB->ForSql($type) . "',
						'Стандарт',
						'" . $DB->ForSql($meta['default']) . "',
						'N',
						100,
						0,
						'" . $DB->ForSql($now) . "',
						'" . $DB->ForSql($now) . "'
					)
				");
			} else {
				$DB->Query("
					INSERT INTO titlo_prompts (TYPE, NAME, BODY, IS_DEFAULT, SORT, USE_COUNT, CREATED_AT, UPDATED_AT)
					VALUES (
						'" . $DB->ForSql($type) . "',
						'Стандарт',
						'" . $DB->ForSql($meta['default']) . "',
						'Y',
						100,
						0,
						'" . $DB->ForSql($now) . "',
						'" . $DB->ForSql($now) . "'
					)
				");
			}
		}
	}

	/**
	 * Второй промпт detail по умолчанию: пример со стилями (Vilmed .vmd-desc).
	 * Наглядное пособие для других магазинов — не делает его «выбранным для генерации».
	 */
	protected static function seedVilmedDetailExtra(): void
	{
		global $DB;
		$flag = 'seed_vmd_detail_v6';
		if (Option::get(Config::MODULE_ID, $flag, '') === 'Y') {
			return;
		}

		$name = 'Пример: со стилями (Vilmed)';
		$oldNames = ['Vilmed (.vmd-desc)', $name];
		$foundId = 0;
		foreach ($oldNames as $tryName) {
			$row = $DB->Query(
				"SELECT ID FROM titlo_prompts
				 WHERE TYPE='" . $DB->ForSql(self::TYPE_DETAIL) . "'
				   AND NAME='" . $DB->ForSql($tryName) . "'
				 LIMIT 1"
			)->Fetch();
			if ($row) {
				$foundId = (int) $row['ID'];
				break;
			}
		}

		$now = date('Y-m-d H:i:s');
		$body = self::vilmedDetailBody();
		if ($foundId > 0) {
			$DB->Query("
				UPDATE titlo_prompts
				SET NAME='" . $DB->ForSql($name) . "',
				    BODY='" . $DB->ForSql($body) . "',
				    SORT=200,
				    IS_DEFAULT='N',
				    UPDATED_AT='" . $DB->ForSql($now) . "'
				WHERE ID=" . $foundId
			);
		} else {
			$DB->Query("
				INSERT INTO titlo_prompts (TYPE, NAME, BODY, IS_DEFAULT, SORT, USE_COUNT, CREATED_AT, UPDATED_AT)
				VALUES (
					'" . $DB->ForSql(self::TYPE_DETAIL) . "',
					'" . $DB->ForSql($name) . "',
					'" . $DB->ForSql($body) . "',
					'N',
					200,
					0,
					'" . $DB->ForSql($now) . "',
					'" . $DB->ForSql($now) . "'
				)
			");
		}

		Option::set(Config::MODULE_ID, $flag, 'Y');
	}

	public const REFINE_DETAIL_NAME = 'Повторная доработка';
	public const REFINE_DETAIL_VMD_NAME = 'Повторная доработка: со стилями (Vilmed)';
	public const REFINE_CATEGORY_NAME = 'Повторная доработка (категория)';
	public const REFINE_CATEGORY_VMD_NAME = 'Повторная доработка: со стилями (Vilmed, категория)';

	public static function refineDetailBody(): string
	{
		return <<<'TXT'
Роль:
Ты — редактор SEO-текстов интернет-магазина. Задача — ДОРАБОТАТЬ уже размещённое
описание товара, а не писать карточку с нуля.

Страница товара: {link}
На странице уже есть описание после первого прохода. Сохрани его структуру, факты
и характеристики. Не выдумывай числа и свойства, которых нет на странице.

Цель доработки:
- естественнее вписать недостающие слова из TLP (они будут дописаны к запросу);
- подтянуть плотность без спама и без ломания читаемости;
- улучшить покрытие фразы, не раздувая текст водой.

Требования к ответу:
- один полный HTML-вариант описания;
- абзацы <p>, при необходимости <h2>/<h3> и списки <ul><li>;
- без markdown, без комментариев и без пояснений вокруг HTML.
TXT;
	}

	public static function refineVilmedDetailBody(): string
	{
		return <<<'TXT'
ПРИМЕР промпта повторной доработки со стилями (магазин Vilmed, .vmd-desc).

Ты — контент-редактор. ДОРАБОТАЙ уже размещённое HTML-описание товара на странице {link}.
Не пиши карточку с нуля: сохрани смысл, факты и структуру .vmd-desc, усили текст
словами из TLP (они будут дописаны к запросу).

Верни ТОЛЬКО HTML внутри <article class="vmd-desc">…</article>, без markdown и пояснений.

Правила разметки — те же, что у полного промпта Vilmed:
- корень <article class="vmd-desc">;
- <h1>, <p class="vmd-subtitle">, <strong>, <mark>, <h2>;
- при необходимости .vmd-features / .vmd-list / .vmd-spec / .vmd-faq / .vmd-manager;
- иконки только инлайновый SVG Lucide;
- не выдумывай характеристики и коммерческие условия (лизинг/рассрочка/«в наличии»),
  которых нет на странице;
- блок vmd-cta не добавляй.

Цель: выше покрытие TLP и читаемый текст без спама ключевых слов.
TXT;
	}

	public static function refineCategoryBody(): string
	{
		return <<<'TXT'
Роль:
Ты — редактор SEO-текстов. ДОРАБОТАЙ уже размещённый текст категории по ссылке {link},
а не пиши раздел с нуля.

Сохрани факты и структуру. Впиши недостающие слова из TLP (будут дописаны к запросу)
естественно, без спама. Не выдумывай ассортимент и условия, которых нет на странице.

Ответ — один HTML-вариант (абзацы <p>, при необходимости <h2>/<h3> и списки),
без markdown и пояснений.
TXT;
	}

	public static function refineVilmedCategoryBody(): string
	{
		return <<<'TXT'
ПРИМЕР повторной доработки SEO-текста категории (Vilmed, .vmd-desc).

ДОРАБОТАЙ уже размещённый HTML раздела {link}. Сохрани разметку .vmd-desc и факты.
Впиши слова из TLP без спама. Верни только <article class="vmd-desc">…</article>.

Правила классов — как у полного category-промпта Vilmed (h1, vmd-subtitle, features,
list, faq, manager). Не выдумывай ассортимент и коммерческие обещания.
TXT;
	}

	/**
	 * ID промпта по точному имени и типу (для автовыбора режима refine).
	 */
	public static function findIdByName(string $type, string $name): int
	{
		self::ensureTables();
		global $DB;
		$row = $DB->Query(
			"SELECT ID FROM titlo_prompts
			 WHERE TYPE='" . $DB->ForSql($type) . "'
			   AND NAME='" . $DB->ForSql($name) . "'
			 LIMIT 1"
		)->Fetch();

		return $row ? (int) $row['ID'] : 0;
	}

	protected static function upsertPromptByName(string $type, string $name, string $body, int $sort, string $flag): void
	{
		global $DB;
		if (Option::get(Config::MODULE_ID, $flag, '') === 'Y') {
			return;
		}
		$now = date('Y-m-d H:i:s');
		$row = $DB->Query(
			"SELECT ID FROM titlo_prompts
			 WHERE TYPE='" . $DB->ForSql($type) . "'
			   AND NAME='" . $DB->ForSql($name) . "'
			 LIMIT 1"
		)->Fetch();
		if ($row) {
			$DB->Query("
				UPDATE titlo_prompts
				SET BODY='" . $DB->ForSql($body) . "',
				    SORT=" . (int) $sort . ",
				    IS_DEFAULT='N',
				    UPDATED_AT='" . $DB->ForSql($now) . "'
				WHERE ID=" . (int) $row['ID']
			);
		} else {
			$DB->Query("
				INSERT INTO titlo_prompts (TYPE, NAME, BODY, IS_DEFAULT, SORT, USE_COUNT, CREATED_AT, UPDATED_AT)
				VALUES (
					'" . $DB->ForSql($type) . "',
					'" . $DB->ForSql($name) . "',
					'" . $DB->ForSql($body) . "',
					'N',
					" . (int) $sort . ",
					0,
					'" . $DB->ForSql($now) . "',
					'" . $DB->ForSql($now) . "'
				)
			");
		}
		Option::set(Config::MODULE_ID, $flag, 'Y');
	}

	protected static function seedRefineDetailPrompts(): void
	{
		self::upsertPromptByName(
			self::TYPE_DETAIL,
			self::REFINE_DETAIL_NAME,
			self::refineDetailBody(),
			150,
			'seed_refine_detail_v1'
		);
		self::upsertPromptByName(
			self::TYPE_DETAIL,
			self::REFINE_DETAIL_VMD_NAME,
			self::refineVilmedDetailBody(),
			160,
			'seed_refine_vmd_detail_v1'
		);
	}

	protected static function seedRefineCategoryPrompts(): void
	{
		self::upsertPromptByName(
			self::TYPE_CATEGORY,
			self::REFINE_CATEGORY_NAME,
			self::refineCategoryBody(),
			150,
			'seed_refine_category_v1'
		);
		self::upsertPromptByName(
			self::TYPE_CATEGORY,
			self::REFINE_CATEGORY_VMD_NAME,
			self::refineVilmedCategoryBody(),
			160,
			'seed_refine_vmd_category_v1'
		);
	}

	/**
	 * Второй промпт category: пример со стилями (Vilmed .vmd-desc) для DESCRIPTION раздела.
	 */
	protected static function seedVilmedCategoryExtra(): void
	{
		global $DB;
		$flag = 'seed_vmd_category_v2';
		if (Option::get(Config::MODULE_ID, $flag, '') === 'Y') {
			return;
		}

		$name = 'Пример: со стилями (Vilmed)';
		$row = $DB->Query(
			"SELECT ID FROM titlo_prompts
			 WHERE TYPE='" . $DB->ForSql(self::TYPE_CATEGORY) . "'
			   AND NAME='" . $DB->ForSql($name) . "'
			 LIMIT 1"
		)->Fetch();
		$foundId = $row ? (int) $row['ID'] : 0;

		$now = date('Y-m-d H:i:s');
		$body = self::vilmedCategoryBody();
		if ($foundId > 0) {
			$DB->Query("
				UPDATE titlo_prompts
				SET BODY='" . $DB->ForSql($body) . "',
				    SORT=200,
				    IS_DEFAULT='N',
				    UPDATED_AT='" . $DB->ForSql($now) . "'
				WHERE ID=" . $foundId
			);
		} else {
			$DB->Query("
				INSERT INTO titlo_prompts (TYPE, NAME, BODY, IS_DEFAULT, SORT, USE_COUNT, CREATED_AT, UPDATED_AT)
				VALUES (
					'" . $DB->ForSql(self::TYPE_CATEGORY) . "',
					'" . $DB->ForSql($name) . "',
					'" . $DB->ForSql($body) . "',
					'N',
					200,
					0,
					'" . $DB->ForSql($now) . "',
					'" . $DB->ForSql($now) . "'
				)
			");
		}

		Option::set(Config::MODULE_ID, $flag, 'Y');
	}

	/**
	 * Обновляет стандартные промпты категорий: «купить» только в конце фразы.
	 */
	protected static function seedSectionPhraseKupitEnd(): void
	{
		global $DB;
		$flag = 'seed_section_phrase_kupit_end_v1';
		if (Option::get(Config::MODULE_ID, $flag, '') === 'Y') {
			return;
		}

		$now = date('Y-m-d H:i:s');
		$map = [
			self::TYPE_SECTION_PHRASE => self::sectionPhraseDefaultSingle(),
			self::TYPE_SECTION_PHRASE_BATCH => self::sectionPhraseDefaultBatch(),
		];
		foreach ($map as $type => $body) {
			$row = $DB->Query(
				"SELECT ID FROM titlo_prompts
				 WHERE TYPE='" . $DB->ForSql($type) . "'
				   AND (IS_DEFAULT='Y' OR NAME='Стандарт')
				 ORDER BY IS_DEFAULT DESC, ID ASC
				 LIMIT 1"
			)->Fetch();
			if (!$row) {
				continue;
			}
			$DB->Query("
				UPDATE titlo_prompts
				SET BODY='" . $DB->ForSql($body) . "',
				    UPDATED_AT='" . $DB->ForSql($now) . "'
				WHERE ID=" . (int) $row['ID']
			);
		}

		Option::set(Config::MODULE_ID, $flag, 'Y');
	}

	/**
	 * Стандартные промпты категорий: бренд из NAME сохраняем (не «шум»).
	 * Обновляет default и активные/все section_phrase*, где ещё старая формулировка «бренд-шум».
	 */
	protected static function seedSectionPhraseKeepBrand(): void
	{
		global $DB;
		$flag = 'seed_section_phrase_keep_brand_v1';
		if (Option::get(Config::MODULE_ID, $flag, '') === 'Y') {
			return;
		}

		$now = date('Y-m-d H:i:s');
		$map = [
			self::TYPE_SECTION_PHRASE => self::sectionPhraseDefaultSingle(),
			self::TYPE_SECTION_PHRASE_BATCH => self::sectionPhraseDefaultBatch(),
		];
		foreach ($map as $type => $body) {
			$res = $DB->Query(
				"SELECT ID, BODY, IS_DEFAULT, NAME FROM titlo_prompts
				 WHERE TYPE='" . $DB->ForSql($type) . "'"
			);
			while ($row = $res->Fetch()) {
				$id = (int) ($row['ID'] ?? 0);
				if ($id <= 0) {
					continue;
				}
				$isDefault = (($row['IS_DEFAULT'] ?? 'N') === 'Y') || ((string) ($row['NAME'] ?? '') === 'Стандарт');
				$oldBody = (string) ($row['BODY'] ?? '');
				$looksOld = (mb_stripos($oldBody, 'бренд-шум') !== false)
					|| (mb_stripos($oldBody, 'без страны и лишнего шума') !== false)
					|| (mb_stripos($oldBody, 'стоматологические установки купить') !== false && mb_stripos($oldBody, 'CHIROMEGA') === false);
				if (!$isDefault && !$looksOld) {
					continue;
				}
				$DB->Query("
					UPDATE titlo_prompts
					SET BODY='" . $DB->ForSql($body) . "',
					    UPDATED_AT='" . $DB->ForSql($now) . "'
					WHERE ID=" . $id
				);
			}
		}

		Option::set(Config::MODULE_ID, $flag, 'Y');
	}

	/**
	 * Категории: смысл (тип+уточнение+бренд) важнее принудительного «купить».
	 */
	protected static function seedSectionPhraseMeaningFirst(): void
	{
		global $DB;
		$flag = 'seed_section_phrase_meaning_first_v1';
		if (Option::get(Config::MODULE_ID, $flag, '') === 'Y') {
			return;
		}

		$now = date('Y-m-d H:i:s');
		$map = [
			self::TYPE_SECTION_PHRASE => self::sectionPhraseDefaultSingle(),
			self::TYPE_SECTION_PHRASE_BATCH => self::sectionPhraseDefaultBatch(),
		];
		foreach ($map as $type => $body) {
			$res = $DB->Query(
				"SELECT ID FROM titlo_prompts WHERE TYPE='" . $DB->ForSql($type) . "'"
			);
			while ($row = $res->Fetch()) {
				$id = (int) ($row['ID'] ?? 0);
				if ($id <= 0) {
					continue;
				}
				$DB->Query("
					UPDATE titlo_prompts
					SET BODY='" . $DB->ForSql($body) . "',
					    UPDATED_AT='" . $DB->ForSql($now) . "'
					WHERE ID=" . $id
				);
			}
		}

		Option::set(Config::MODULE_ID, $flag, 'Y');
	}

	/**
	 * @return array<int, array{id:int,type:string,name:string,body:string,is_default:bool,sort:int,last_used_at:?string,use_count:int,updated_at:?string}>
	 */
	public static function listByType(string $type): array
	{
		self::ensureTables();
		if (!isset(self::catalog()[$type])) {
			return [];
		}
		global $DB;
		$res = $DB->Query("
			SELECT * FROM titlo_prompts
			WHERE TYPE='" . $DB->ForSql($type) . "'
			ORDER BY SORT ASC, ID ASC
		");
		$out = [];
		while ($row = $res->Fetch()) {
			$out[] = self::mapRow($row);
		}
		return $out;
	}

	/**
	 * Для селекта на страницах генерации.
	 *
	 * @return array{items:array,active_id:int,last:?array}
	 */
	public static function selectPayload(string $type): array
	{
		$items = self::listByType($type);
		$activeId = self::getActiveId($type);
		$last = null;
		$lastTs = null;
		foreach ($items as $it) {
			if ($it['last_used_at'] && ($lastTs === null || $it['last_used_at'] > $lastTs)) {
				$lastTs = $it['last_used_at'];
				$last = $it;
			}
		}
		return [
			'items' => $items,
			'active_id' => $activeId,
			'last' => $last,
		];
	}

	public static function getActiveId(string $type): int
	{
		self::ensureTables();
		$stored = (int) Option::get(Config::MODULE_ID, 'prompt_active_' . $type, '0');
		if ($stored > 0 && self::find($stored)) {
			return $stored;
		}
		foreach (self::listByType($type) as $it) {
			if ($it['is_default']) {
				return $it['id'];
			}
		}
		$items = self::listByType($type);
		return $items[0]['id'] ?? 0;
	}

	public static function setActiveId(string $type, int $id): void
	{
		if ($id > 0 && self::find($id)) {
			Option::set(Config::MODULE_ID, 'prompt_active_' . $type, (string) $id);
		}
	}

	/**
	 * Текст промпта: по id или активный для типа.
	 */
	public static function get(string $type, ?int $id = null): string
	{
		self::ensureTables();
		if ($id === null || $id <= 0) {
			$id = self::getActiveId($type);
		}
		$row = $id > 0 ? self::find($id) : null;
		if ($row && $row['type'] === $type) {
			return $row['body'];
		}
		$cat = self::catalog();
		return isset($cat[$type]) ? $cat[$type]['default'] : '';
	}

	/**
	 * @return array{id:int,type:string,name:string,body:string,is_default:bool,sort:int,last_used_at:?string,use_count:int,updated_at:?string}|null
	 */
	public static function find(int $id): ?array
	{
		self::ensureTables();
		if ($id <= 0) {
			return null;
		}
		global $DB;
		$row = $DB->Query('SELECT * FROM titlo_prompts WHERE ID=' . (int) $id)->Fetch();
		return $row ? self::mapRow($row) : null;
	}

	public static function markUsed(int $id): void
	{
		self::ensureTables();
		if ($id <= 0) {
			return;
		}
		global $DB;
		$now = date('Y-m-d H:i:s');
		$DB->Query("
			UPDATE titlo_prompts
			SET LAST_USED_AT='" . $DB->ForSql($now) . "',
			    USE_COUNT=USE_COUNT+1,
			    UPDATED_AT='" . $DB->ForSql($now) . "'
			WHERE ID=" . (int) $id
		);
		$row = self::find($id);
		if ($row) {
			self::setActiveId($row['type'], $id);
		}
	}

	/**
	 * @return array{ok:bool,id?:int,error?:string}
	 */
	public static function create(string $type, string $name, string $body, bool $asDefault = false): array
	{
		self::ensureTables();
		if (!isset(self::catalog()[$type])) {
			return ['ok' => false, 'error' => 'bad type'];
		}
		$name = trim($name);
		$body = trim(str_replace("\r\n", "\n", $body));
		if ($name === '') {
			$name = 'Вариант';
		}
		if ($body === '') {
			$body = self::catalog()[$type]['default'];
		}
		global $DB;
		$now = date('Y-m-d H:i:s');
		if ($asDefault) {
			$DB->Query("UPDATE titlo_prompts SET IS_DEFAULT='N' WHERE TYPE='" . $DB->ForSql($type) . "'");
		}
		$DB->Query("
			INSERT INTO titlo_prompts (TYPE, NAME, BODY, IS_DEFAULT, SORT, USE_COUNT, CREATED_AT, UPDATED_AT)
			VALUES (
				'" . $DB->ForSql($type) . "',
				'" . $DB->ForSql($name) . "',
				'" . $DB->ForSql($body) . "',
				'" . ($asDefault ? 'Y' : 'N') . "',
				50,
				0,
				'" . $DB->ForSql($now) . "',
				'" . $DB->ForSql($now) . "'
			)
		");
		$id = (int) $DB->LastID();
		if ($asDefault) {
			self::setActiveId($type, $id);
		}
		return ['ok' => true, 'id' => $id];
	}

	/**
	 * @return array{ok:bool,error?:string}
	 */
	public static function update(int $id, string $name, string $body): array
	{
		$row = self::find($id);
		if (!$row) {
			return ['ok' => false, 'error' => 'not found'];
		}
		$name = trim($name);
		$body = trim(str_replace("\r\n", "\n", $body));
		if ($name === '' || $body === '') {
			return ['ok' => false, 'error' => 'name and body required'];
		}
		global $DB;
		$now = date('Y-m-d H:i:s');
		$DB->Query("
			UPDATE titlo_prompts
			SET NAME='" . $DB->ForSql($name) . "',
			    BODY='" . $DB->ForSql($body) . "',
			    UPDATED_AT='" . $DB->ForSql($now) . "'
			WHERE ID=" . (int) $id
		);
		return ['ok' => true];
	}

	/**
	 * @return array{ok:bool,error?:string}
	 */
	public static function setDefault(int $id): array
	{
		$row = self::find($id);
		if (!$row) {
			return ['ok' => false, 'error' => 'not found'];
		}
		global $DB;
		$DB->Query("UPDATE titlo_prompts SET IS_DEFAULT='N' WHERE TYPE='" . $DB->ForSql($row['type']) . "'");
		$DB->Query("UPDATE titlo_prompts SET IS_DEFAULT='Y' WHERE ID=" . (int) $id);
		self::setActiveId($row['type'], $id);
		return ['ok' => true];
	}

	/**
	 * @return array{ok:bool,error?:string}
	 */
	public static function delete(int $id): array
	{
		$row = self::find($id);
		if (!$row) {
			return ['ok' => false, 'error' => 'not found'];
		}
		$others = self::listByType($row['type']);
		if (count($others) <= 1) {
			return ['ok' => false, 'error' => 'Нельзя удалить последний промпт типа'];
		}
		global $DB;
		$DB->Query('DELETE FROM titlo_prompts WHERE ID=' . (int) $id);
		if ($row['is_default']) {
			$rest = self::listByType($row['type']);
			if ($rest) {
				self::setDefault($rest[0]['id']);
			}
		}
		return ['ok' => true];
	}

	public static function formatUsedAt(?string $mysqlDt): string
	{
		if (!$mysqlDt) {
			return 'ещё не использовался';
		}
		$ts = strtotime($mysqlDt);
		if (!$ts) {
			return $mysqlDt;
		}
		return date('d.m.Y H:i', $ts);
	}

	/**
	 * @param array $row
	 * @return array{id:int,type:string,name:string,body:string,is_default:bool,sort:int,last_used_at:?string,use_count:int,updated_at:?string,last_used_label:string}
	 */
	protected static function mapRow(array $row): array
	{
		$last = $row['LAST_USED_AT'] ?? null;
		return [
			'id' => (int) $row['ID'],
			'type' => (string) $row['TYPE'],
			'name' => (string) $row['NAME'],
			'body' => (string) ($row['BODY'] ?? ''),
			'is_default' => ($row['IS_DEFAULT'] ?? 'N') === 'Y',
			'sort' => (int) ($row['SORT'] ?? 100),
			'last_used_at' => $last ?: null,
			'use_count' => (int) ($row['USE_COUNT'] ?? 0),
			'updated_at' => $row['UPDATED_AT'] ?? null,
			'last_used_label' => self::formatUsedAt($last),
		];
	}
}
