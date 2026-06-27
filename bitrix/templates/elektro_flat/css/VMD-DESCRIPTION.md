# VMD — оформление описания товара (`.vmd-desc`)

Набор стилей для красивого, единообразного описания товара (по аналогии с
medmarket, но в фирменном красном VILMED). Всё изолировано под классом
`.vmd-desc` — на остальной сайт не влияет.

---

## 1. Где лежит и как подключается

| Что | Путь |
|---|---|
| CSS | `bitrix/templates/elektro_flat/css/vmd-description.css` |
| Подключение | `header.php` → внутри `isProductDetail()` (только карточка товара) |
| Эта документация | `bitrix/templates/elektro_flat/css/VMD-DESCRIPTION.md` |

Подключается автоматически на странице товара. Никаких JS не требуется
(FAQ работает на нативном `<details>`).

---

## 2. Как наполнять описание

Контент кладётся в **детальный текст товара** (админка → товар → вкладка
«Подробно» → поле «Детальное описание», режим **HTML/исходный код**).
Весь блок оборачивается в один контейнер:

```html
<article class="vmd-desc">
  ... содержимое ...
</article>
```

Всё, что внутри `.vmd-desc`, получает оформление. Снаружи — обычный сайт.

> Важно: вставлять именно **HTML-исходник** (кнопка «Источник» / `</>` в
> визуальном редакторе), иначе теги экранируются и стили не применятся.

---

## 3. Справочник классов

### 3.1 Контейнер

| Класс | Назначение |
|---|---|
| `vmd-desc` | Корневой контейнер. **Обязателен** — задаёт переменные и скоуп. Обычно `<article class="vmd-desc">`. |

### 3.2 Текст и заголовки (без классов, просто теги внутри `.vmd-desc`)

| Тег / класс | Назначение |
|---|---|
| `<h1>` | Главный заголовок описания. |
| `<p class="vmd-subtitle">` | Подзаголовок-лид под `h1` (серый, крупнее основного текста). |
| `<h2>` | Заголовок раздела. Под ним автоматически рисуется короткая красная черта. |
| `<h3>` | Подзаголовок. |
| `<p>` | Абзац. |
| `<strong>` | Жирный акцент. |
| `<mark>` | Выделение жёлтым маркером. |
| `<a>` | Ссылка (красная). |

### 3.3 Список с маркерами

| Класс | Назначение |
|---|---|
| `ul.vmd-list` | Список с красными круглыми маркерами и разделителями-линиями. `<li>` без классов. |

```html
<ul class="vmd-list">
  <li><strong>Рефракция:</strong> сферические и цилиндрические аберрации.</li>
  <li><strong>Кератометрия:</strong> радиус кривизны роговицы.</li>
</ul>
```

### 3.4 Карточки преимуществ (сетка 2×2 → 1 колонка на мобиле)

| Класс | Назначение |
|---|---|
| `vmd-features` | Грид-обёртка карточек. |
| `vmd-feature` | Одна карточка. |
| `vmd-feature > .ic` | Квадрат с иконкой (внутрь — `<svg>`, см. раздел 4). |
| `vmd-feature > h3` | Заголовок карточки. |
| `vmd-feature > p` | Описание карточки. |

```html
<div class="vmd-features">
  <div class="vmd-feature">
    <div class="ic"><svg ...>...</svg></div>
    <h3>Заголовок</h3>
    <p>Короткое пояснение.</p>
  </div>
  <!-- ещё карточки -->
</div>
```

### 3.5 Таблица характеристик (zebra)

| Класс | Назначение |
|---|---|
| `vmd-table-wrap` | Обёртка (рамка + скругление + overflow). |
| `table.vmd-spec` | Таблица. Чётные строки подсвечены. |
| `td.k` | Ячейка-ключ (название параметра, серая). |
| `td.v` | Ячейка-значение. |

```html
<div class="vmd-table-wrap">
  <table class="vmd-spec">
    <tr><td class="k">Параметр</td><td class="v">Значение</td></tr>
  </table>
</div>
```

### 3.6 Примечания-callout (3 типа)

| Класс | Цвет | Когда использовать |
|---|---|---|
| `vmd-note vmd-note--info` | синий | нейтральная справка («Подходит для…»). |
| `vmd-note vmd-note--warn` | жёлтый | предупреждение (характеристики могут меняться). |
| `vmd-note vmd-note--accent` | красный | важный акцент. |
| `.vmd-note > .ic` | — | иконка (`<svg>`), красится в цвет блока автоматически. |

```html
<div class="vmd-note vmd-note--info">
  <div class="ic"><svg ...>...</svg></div>
  <div><strong>Подходит для:</strong> кабинетов офтальмолога…</div>
</div>
```

### 3.7 FAQ (без JS)

| Класс | Назначение |
|---|---|
| `vmd-faq` | Обёртка аккордеона. |
| `vmd-faq > details` | Один вопрос. Добавь `open` у первого, чтобы был раскрыт. |
| `details > summary` | Текст вопроса (плюс/минус справа — автоматически). |
| `.vmd-faq__a` | Блок ответа. |

```html
<div class="vmd-faq">
  <details open>
    <summary>Вопрос?</summary>
    <div class="vmd-faq__a">Ответ.</div>
  </details>
  <details>
    <summary>Ещё вопрос?</summary>
    <div class="vmd-faq__a">Ответ.</div>
  </details>
</div>
```

### 3.8 CTA-форма (для «цена по запросу»)

| Класс | Назначение |
|---|---|
| `vmd-cta` | Светлая карточка с красной полосой слева. |
| `vmd-cta__badge` | Бейдж-плашка сверху («Цена по запросу»). |
| `vmd-cta > h2` / `> p` | Заголовок и текст (черта под h2 отключена). |
| `vmd-cta form` | Форма (имя + телефон + кнопка; на мобиле — в столбик). |
| `vmd-cta__hint` | Мелкая приписка про согласие/политику. |

> Форма в шаблоне — визуальная заглушка (`onsubmit="return false"`).
> Для боевой отправки нужно подключить реальный обработчик/компонент заявки.

### 3.9 Финальное контактное примечание

| Класс | Назначение |
|---|---|
| `vmd-manager` | Серая плашка с иконкой письма и контактом менеджера. |
| `.vmd-manager > .ic` | Иконка (`<svg>`). |

---

## 4. Иконки

**Стиль:** тонкая линия (line / outline), а не заливка. Параметры:

- размер вьюбокса `viewBox="0 0 24 24"`;
- `fill="none"`, `stroke="currentColor"`, `stroke-width="2"`;
- `stroke-linecap="round"`, `stroke-linejoin="round"`.

**Цвет не задаётся в самом SVG** — он наследуется от блока (`currentColor`):
в карточке преимуществ иконка красная, в info-callout — синяя, в warn —
жёлтая и т.д. Это уже настроено в CSS, ничего дополнительно делать не нужно.

**Откуда брать ещё иконки:** [Lucide](https://lucide.dev) (лицензия MIT,
бесплатно). Можно также Feather или Tabler Icons — у всех одинаковая
line-стилистика и те же параметры, поэтому они сочетаются. Алгоритм:

1. Найти иконку на lucide.dev → «Copy SVG».
2. Убедиться, что у `<svg>` есть `fill="none" stroke="currentColor"` и
   нет жёстко прописанных `width`/`height` и `color` (размер задаёт CSS).
3. Вставить `<svg>…</svg>` внутрь нужного `.ic`.

### Стартовый набор (готовые SVG)

Скопируй нужный `<svg>` целиком в `<div class="ic">…</div>`.

```html
<!-- target — точность / измерение -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>

<!-- microscope — оптика / лаборатория -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h2"/><path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2Z"/><path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/></svg>

<!-- sliders-horizontal — функционал / настройки -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/></svg>

<!-- move-horizontal — диапазон / ширина -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 8 22 12 18 16"/><polyline points="6 8 2 12 6 16"/><line x1="2" x2="22" y1="12" y2="12"/></svg>

<!-- info — info-callout -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>

<!-- alert-triangle — warn-callout -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>

<!-- mail — контакт менеджера -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>

<!-- shield-check — гарантия / сертификация -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>

<!-- truck — доставка -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>

<!-- printer — печать / термопринтер -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect width="12" height="8" x="6" y="14" rx="1"/></svg>

<!-- monitor — дисплей / экран -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>

<!-- gauge — точность / диапазон измерений -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/></svg>
```

---

## 5. Готовый скелет (копируется целиком)

См. живой пример: открой превью `_vmd-preview.html` (на dev) или товар
RMK-200. Минимальный скелет:

```html
<article class="vmd-desc">
  <h1>Название товара</h1>
  <p class="vmd-subtitle">Короткий лид одним предложением.</p>

  <p>Вводный абзац: что это, для кого, ключевая польза. <strong>Главное</strong>
  выделяем жирным, важный параметр — <mark>маркером</mark>.</p>

  <h2>Назначение</h2>
  <p>…</p>

  <h2>Ключевые преимущества</h2>
  <div class="vmd-features">
    <div class="vmd-feature"><div class="ic"><!-- svg --></div><h3>Преимущество</h3><p>Пояснение.</p></div>
    <div class="vmd-feature"><div class="ic"><!-- svg --></div><h3>Преимущество</h3><p>Пояснение.</p></div>
  </div>

  <div class="vmd-note vmd-note--info"><div class="ic"><!-- svg --></div><div><strong>Подходит для:</strong> …</div></div>

  <h2>Технические характеристики</h2>
  <div class="vmd-table-wrap"><table class="vmd-spec">
    <tr><td class="k">Параметр</td><td class="v">Значение</td></tr>
  </table></div>

  <div class="vmd-note vmd-note--warn"><div class="ic"><!-- svg --></div><div>Характеристики могут изменяться производителем без уведомления.</div></div>

  <h2>Частые вопросы</h2>
  <div class="vmd-faq">
    <details open><summary>Вопрос?</summary><div class="vmd-faq__a">Ответ.</div></details>
  </div>

  <!-- CTA-форма — только для товаров «цена по запросу» -->
  <div class="vmd-cta">
    <span class="vmd-cta__badge">Цена по запросу</span>
    <h2>Узнайте актуальную цену</h2>
    <p>Оставьте контакты — менеджер свяжется с вами.</p>
    <form onsubmit="return false">
      <input type="text" placeholder="Ваше имя"><input type="tel" placeholder="Телефон"><button type="submit">Запросить цену</button>
    </form>
    <p class="vmd-cta__hint">Нажимая кнопку, вы соглашаетесь с <a href="#">политикой обработки персональных данных</a>.</p>
  </div>

  <div class="vmd-manager"><div class="ic"><!-- svg --></div><div>Обращаем внимание: производитель может изменять характеристики и комплектацию без уведомления. Уточняйте у менеджера: <a href="mailto:info@vilmed.ru">info@vilmed.ru</a>.</div></div>
</article>
```

### Обязательные / опциональные блоки

| Блок | Статус |
|---|---|
| `vmd-desc`, `h1`, вводный `<p>` | обязательно |
| `h2` + текст по разделам | обязательно (минимум «Назначение» и «Характеристики») |
| `vmd-features` | желательно (3–4 карточки) |
| `vmd-table-wrap`/`vmd-spec` | желательно, если есть ТТХ |
| `vmd-note--warn` про изменение характеристик | желательно |
| `vmd-faq` | опционально (если есть типовые вопросы) |
| `vmd-cta` | только для «цена по запросу» |
| `vmd-manager` | желательно (единый контакт в конце) |

---

## 6. Готовый промт для генерации описаний

См. отдельный раздел в `DOCUMENTATION.md` и текст ниже — его можно отдавать
нейросети, приложив сырьё по товару (ТТХ, назначение).

> Скопируй промт из конца этого файла (раздел «PROMPT»).

---

## PROMPT

```
Ты — контент-редактор интернет-магазина медтехники VILMED. Сформируй
HTML-описание товара строго в разметке дизайн-системы .vmd-desc
(см. правила ниже). Верни ТОЛЬКО HTML внутри <article class="vmd-desc">…</article>,
без markdown, без пояснений, без ```.

ДАННЫЕ О ТОВАРЕ:
<сюда вставить: название, назначение, ключевые особенности, таблицу ТТХ,
есть ли цена или «цена по запросу», типовые вопросы>

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
- Примечания: <div class="vmd-note vmd-note--info|--warn|--accent">
  <div class="ic">SVG</div><div>…</div></div>.
  Обязательно добавь vmd-note--warn про возможное изменение характеристик.
- FAQ (если есть вопросы): <div class="vmd-faq">
  <details open><summary>Вопрос?</summary><div class="vmd-faq__a">Ответ.</div></details>…</div>.
- Если товар «цена по запросу» — добавь блок vmd-cta (бейдж «Цена по запросу»,
  заголовок, текст, форма имя/телефон/кнопка «Запросить цену», hint про политику).
  Если цена есть — блок vmd-cta НЕ добавляй.
- В конце — <div class="vmd-manager"><div class="ic">SVG</div><div>…</div></div>
  с фразой про изменение характеристик/комплектации и контактом info@vilmed.ru.

ИКОНКИ (.ic): только инлайновый <svg> в line-стиле Lucide:
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
stroke-linecap="round" stroke-linejoin="round". Цвет НЕ указывай.
Подбирай по смыслу (target, microscope, sliders-horizontal, move-horizontal,
gauge, monitor, printer, shield-check, truck, info, alert-triangle, mail).

ТОН: деловой, по делу, без рекламной воды и превосходных степеней без оснований.
Не выдумывай характеристики, которых нет в данных. Все числа — из данных.
```
