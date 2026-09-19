# Security / functional smoke — Titlo Relevance (§14)

Прогон на стенде после деплоя модуля + cabinet API.

## Prep
- [ ] `install/version.php` и Options: iblock_id, api_base_url (ключ не светить)
- [ ] Admin + non-admin: ajax/страницы только admin + sessid

## Ownership
- [ ] Save / phrase / skip / texts с ID чужого iblock → отказ
- [ ] Cluster noindex: чужие ID отфильтрованы; confirm в UI

## URL / key
- [ ] start_analysis / generate с чужим host → 422
- [ ] Options: ключ не в value; custom API base только с confirm

## Public robots
- [ ] `?ID=<noindex element>` на чужой странице → meta noindex **не** появляется
- [ ] На DETAIL_PAGE_URL флагнутого товара → noindex есть

## Mass ops
- [ ] PhraseBulk: confirm, default ≤ 200, audit в AddMessage2Log
- [ ] Soft-clear noindex (keep/clear) работает

## Cabinet API
- [ ] Bearer обязателен; `?api_key=` → 401
- [ ] Scope relevance / ai; throttle 429 при перегрузе
- [ ] TLP: missing/diff capped server-side
- [ ] AI: history_id чужой → 422; monthly budget → 429

## UI smoke (§14)
- [ ] Guide 1–7; names + section-names; gen без ключа disabled
- [ ] Single: Яндекс default, динамика ПС/регион/ТОП, TLP→keywords, облака
- [ ] Дубли keep/noindex/clear/cluster
- [ ] Промпты CRUD / active / типы

Авто-инварианты: `php tools/security-smoke-check.php` из репо cabinet (нужен путь к модулю vilmed).

**Автопрогон:** 2026-09-16 — `tools/security-smoke-check.php` OK (ownership, robots, scopes, TLP/AI caps, SSRF, uninstall). Ручной UI §14 — на стенде по чекбоксам выше.
