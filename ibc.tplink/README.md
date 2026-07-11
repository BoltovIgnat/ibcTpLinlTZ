# ibc.tplink — импорт каталога TP-Link

CLI-модуль Bitrix для тестового задания tp-link.ru (задача A).

## Установка

1. Скопировать `local/modules/ibc.tplink/` на сайт Bitrix (Бизнес, PHP 8.3+).
2. Установить модуль в админке или выполнить `DoInstall`.
3. Убедиться, что включены расширения PHP: `curl`, `dom`, `json`, `mbstring`.

## Веб-админка

После установки/обновления модуля (v1.2.0+):

| URL | Страница |
|-----|----------|
| `/ibc/tplink/admin/` | Дашборд: статистика IB, **кнопка «Запустить импорт»**, последний прогон |
| `/ibc/tplink/admin/logs.php` | Логи import/api + JSON-прогоны |

Доступ: администратор Bitrix или группа `ibc_tplink_admin`.

Шаблон: `ibc_tplink_admin` (Ethereal Glass UI).

## Запуск импорта

```bash
php -f local/modules/ibc.tplink/tools/import.php
```

Опции:

```bash
php -f local/modules/ibc.tplink/tools/import.php -- --dry-run
php -f local/modules/ibc.tplink/tools/import.php -- --csv=tplink_import_first_run.csv
```

## Архитектура

| Компонент | Назначение |
|-----------|------------|
| `ListingParser` | Карточки с listing + sitemap kz |
| `ProductPageParser` | ARTICLE, характеристики с карточки |
| `SupportPageParser` | HW×region с `/support/download/{slug}/vN/` |
| `CatalogSyncService` | Upsert в IB `tplink_catalog_stage` |
| `ImportLogWriter` | JSON в `/local/logs/tplink_import_*.json` |

## Источник данных

- Listing: https://www.tp-link.com/kz/home-networking/wifi-router/
- Sitemap: https://www.tp-link.com/kz/sitemap.xml (полный список slug, т.к. listing подгружает часть моделей через JS)
- Support: версии `/kz/support/download/{slug}/v1/` …

## Логирование

### Файловые логи (каналы)

| Канал | Путь | Содержимое |
|-------|------|------------|
| `import` | `local/modules/ibc.tplink/log/import/YYYY-MM-DD.log` | Старт/финиш импорта, ошибки карточек, HTTP |
| `api` | `local/modules/ibc.tplink/log/api/YYYY-MM-DD.log` | Ошибки REST API |

Писать через `ImportLog::info|warning|error()`.

### JSON прогоны

Каждый импорт: `{DOCUMENT_ROOT}/local/logs/tplink_import_YYYY-MM-DD_HH-MM-SS.json`

## REST API логов

База (после установки): `https://{host}/ibc/tplink/api/tplink/v1`

Авторизация: заголовок `X-Tplink-Log-Token` или `?token=` (опция `api_log_read_token`).

| Метод | Путь | Описание |
|-------|------|----------|
| GET | `/admin/logs/channels` | Каналы файловых логов |
| GET | `/admin/logs/{channel}/dates` | Даты |
| GET | `/admin/logs/{channel}?tail=200&date=YYYY-MM-DD&level=ERROR&search=text` | Строки лога |
| GET | `/admin/logs/runs?limit=50` | Список JSON-прогонов |
| GET | `/admin/logs/run?file=tplink_import_....json&tail_items=500` | Один прогон |
| GET | `/admin/logs/remote/{channel}` | Логи с prod (прокси) |
| GET | `/admin/logs/remote/runs` | Прогоны с prod |
| GET | `/admin/logs/remote/run?file=...` | Прогон с prod |

Пример (prod):

```bash
curl -sS -H "X-Tplink-Log-Token: $TOKEN" \
  "https://ibcmoney.store/ibc/tplink/api/tplink/v1/admin/logs/import?tail=200&level=ERROR"
```

Локально — подтянуть prod:

```bash
curl -sS -H "X-Tplink-Log-Token: $TOKEN" \
  "http://localhost/ibc/tplink/api/tplink/v1/admin/logs/remote/import?tail=300"
```

Класс для программного доступа: `Ibc\Tplink\Helper\RemoteLogFetcher`.

## Hash Check

После запуска в JSON-логе:

```
hash_check.sorted_full_articles_sha256
```

SHA256 от отсортированного списка `FULL_ARTICLE` через `\n`.

## Ограничения

- Импорт только CLI (без веб-URL).
- Цены не импортируются (на tp-link.com их нет).
- Задача B (аудит tp-link.ru) — отдельный документ `ibc/tplink/spec/AUDIT_TASK.md`.

## Спека

`ibc/tplink/spec/MODULE_ASSIGNMENT.md`
