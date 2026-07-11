# IBC TP-Link — документация спецификации

Модуль импорта каталога TP-Link из официального источника для сайта [tp-link.ru](https://tp-link.ru/) (Bitrix + Aspro Max).

| Задача | Файл |
|--------|------|
| Техническое задание модуля (задача A) | [MODULE_ASSIGNMENT.md](./MODULE_ASSIGNMENT.md) |
| Аудит фильтров и характеристик (задача B) | [AUDIT_TASK.md](./AUDIT_TASK.md) |
| Чеклист соответствия исходному ТЗ | [TZ_CHECKLIST.md](./TZ_CHECKLIST.md) |
| Схема JSON-лога импорта | [schemas/import-log.schema.json](./schemas/import-log.schema.json) |
| Исходный текст ТЗ (docx → txt) | [TZ_EXTRACTED.txt](./TZ_EXTRACTED.txt) |

## Модуль

- **ID:** `ibc.tplink`
- **Путь кода:** `local/modules/ibc.tplink/`
- **CLI:** `php -f local/modules/ibc.tplink/tools/import.php`
- **Логи:** `/local/logs/tplink_import_YYYY-MM-DD_HH-MM-SS.json`

## Контекст

- **Прод:** https://tp-link.ru/ — интернет-магазин, 1С-Битрикс Бизнес, шаблон Aspro Max, PHP 8.3.
- **Источник данных (импорт):** https://www.tp-link.com/kz/home-networking/wifi-router/
- **Тестовое задание:** v1.5 — импортёр (A) + аудит категории «Роутеры Wi‑Fi» (B).

## Быстрый старт (план)

1. Установить модуль в админке Bitrix или `php -f local/modules/ibc.tplink/install/index.php`.
2. Убедиться, что создан инфоблок `tplink_catalog_stage`.
3. Запустить импорт одной командой (см. MODULE_ASSIGNMENT §7).
4. Повторить запуск для проверки идемпотентности (`unchanged`).

## Связь с tp-link.ru

Задача A выполняется на **чистом Bitrix** без Aspro Max. Задача B — анализ **прод-категории** tp-link.ru. Модуль проектируется так, чтобы позже интегрироваться с боевым каталогом Aspro (отдельный этап, вне тестового ТЗ).
