# Приёмка задачи A — ibcmoney.store

**Стенд:** https://ibcmoney.store/  
**Админка:** https://ibcmoney.store/ibc/tplink/admin/  
**API логов:** https://ibcmoney.store/ibc/tplink/api/tplink/v1  

## 1. Первый прогон (CLI на сервере)

SSH на сервер, затем:

```bash
cd /home/bitrix/www   # или фактический DOCUMENT_ROOT

php -f local/modules/ibc.tplink/tools/import.php -- \
  --csv=ibc/tplink/artifacts/tplink_import_first_run.csv
```

Сохранить JSON-лог:

```bash
cp "$(ls -t local/logs/tplink_import_*.json | head -1)" \
  ibc/tplink/artifacts/tplink_import_first_run.json
```

Записать из JSON:

| Поле | Значение |
|------|----------|
| `hash_check.card_count` | |
| `hash_check.element_count` | |
| `hash_check.sorted_full_articles_sha256` | |
| `counters.new` | |
| `counters.errors` | |

## 2. Второй прогон (сразу, источник не менять)

```bash
php -f local/modules/ibc.tplink/tools/import.php -- \
  --csv=ibc/tplink/artifacts/tplink_import_second_run.csv

cp "$(ls -t local/logs/tplink_import_*.json | head -1)" \
  ibc/tplink/artifacts/tplink_import_second_run.json
```

| Поле | 1-й прогон | 2-й прогон | OK? |
|------|------------|------------|-----|
| `sorted_full_articles_sha256` | | | ☐ одинаковый |
| `counters.new` | | | ☐ 2-й = 0 |
| `counters.updated` | | | ☐ 2-й = 0 |
| Все `status` в CSV | (new/…) | | ☐ 2-й = все `unchanged` |
| `element_count` | | | ☐ >> `card_count` (ориентир 200–300) |

## 3. Альтернатива: кнопка в админке

https://ibcmoney.store/ibc/tplink/admin/ → «Запустить импорт» (полный прогон 10–30 мин).

Для **сдачи ТЗ** всё равно нужны **два CLI-прогона** и CSV — веб-кнопка не заменяет `--csv=`.

## 4. Проверка через API (после прогона)

Токен: Bitrix → Настройки → `ibc.tplink` → `api_log_read_token` (заголовок `X-Tplink-Log-Token`).

```bash
curl -sS -H "X-Tplink-Log-Token: $TOKEN" \
  "https://ibcmoney.store/ibc/tplink/api/tplink/v1/admin/logs/runs?limit=5"

curl -sS -H "X-Tplink-Log-Token: $TOKEN" \
  "https://ibcmoney.store/ibc/tplink/api/tplink/v1/admin/logs/run?file=tplink_import_YYYY-MM-DD_HH-MM-SS.json"
```

## 5. Коммит в репо

```
ibc/tplink/artifacts/
  tplink_import_first_run.csv
  tplink_import_second_run.csv
  tplink_import_first_run.json
  tplink_import_second_run.json
  ACCEPTANCE.md   ← этот файл с заполненными таблицами
```

Локальная сверка:

```bash
python ibc/tplink/artifacts/tools/verify_acceptance.py
```

## Hash Check (для README модуля)

```
sha256(sorted_full_articles) = 897f31a95d3eec46662c8d21626be9108ff994a60367335c273809ac5d3963e5
```

Повторный прогон (3-й): тот же sha256, new=0, **173 unchanged / 239 updated** — нужен 4-й прогон после v1.2.3.

Дата прогона: **2026-07-10** (1-й прогон с ibcmoney.store)  
Исполнитель: __________

---

## Результаты 1-го прогона (зафиксировано)

| Поле | Значение |
|------|----------|
| JSON на сервере | `tplink_import_2026-07-10_20-42-05.json` |
| Старт / финиш | 2026-07-10T20:37:08+03:00 → 20:42:05 |
| `card_count` | **91** |
| `element_count` | **412** |
| `sorted_full_articles_sha256` | `897f31a95d3eec46662c8d21626be9108ff994a60367335c273809ac5d3963e5` |
| counters | new=412, updated=0, unchanged=0, errors=0, needs_review=168 |

Файлы в репо: `tplink_import_first_run.csv`, `tplink_import_first_run.json`

## 2-й прогон (2026-07-11, 11:32–11:37)

| Поле | Значение |
|------|----------|
| JSON | `tplink_import_2026-07-11_11-37-47.json` |
| `sorted_full_articles_sha256` | `897f31a95d3eec46662c8d21626be9108ff994a60367335c273809ac5d3963e5` (**совпадает с 1-м**) |
| counters | new=168, **updated=244**, unchanged=**0**, errors=0 |

**Статус приёмки:** sha256 OK, но **идемпотентность не пройдена** — ложные `updated` (enum Y/N + SYNCED_AT) и 168 повторных `new` для NEEDS_REVIEW.

**Исправление:** `CatalogSyncService` v1.2.2 — нормализация list-свойств, пропуск SYNCED_AT в diff, улучшенный index fallback.

### 3-й прогон (2026-07-11, 11:44–11:49)

| Поле | Значение |
|------|----------|
| JSON | `tplink_import_2026-07-11_11-49-06.json` |
| sha256 | ✅ `897f31a95d3eec46662c8d21626be9108ff994a60367335c273809ac5d3963e5` |
| counters | new=**0**, updated=239, unchanged=**173**, errors=0 |

Лучше 2-го (new=0), но **ещё не все unchanged**. Ложные `updated`: NEEDS_REVIEW (168), CATEGORY (134).

**Fix v1.2.3** — чтение enum из `GetProperties` (`VALUE_ENUM_ID`, …).

### 4-й прогон (2026-07-11, 11:56–12:01)

| Поле | Значение |
|------|----------|
| JSON | `tplink_import_2026-07-11_12-01-11.json` |
| sha256 | ✅ тот же |
| counters | new=0, updated=239, unchanged=173 |

**Идентичен 3-му** — fix v1.2.3 на сервере не применился или list-свойства писались строкой `'Y'`/`'N'` вместо enum ID.

**Fix v1.2.4** — запись `NEEDS_REVIEW` / `MISSING_AT_SOURCE` через enum ID.

**Fix v1.2.6** — детерминированная `CATEGORY` (`Роутеры Wi-Fi` по URL), `markMissing` по element ID.

### 5-й прогон (после очистки, 2026-07-11 ~12:21–12:35)

```bash
php -f local/modules/ibc.tplink/tools/import.php -- \
  --csv=ibc/tplink/artifacts/tplink_import_fifth_run.csv
```

| Критерий | 1-й (12:26) | 2-й (12:35) | 6-й v1.2.6 (12:50 / 12:56) |
|----------|-------------|-------------|----------------------------|
| sha256 | 897f31a9… | ✅ | ✅ **897f31a9…** |
| new=0 | — | ✅ | ✅ **0** |
| all unchanged | — | ❌ 278/412 | ✅ **412/412** |
| missing | — | ❌ 69 | ✅ **0** |

### 6-й прогон — приёмка пройдена (v1.2.6, 2026-07-11)

**Очистка каталога** → два CLI-прогона подряд.

#### Прогон 1 — `tplink_import_2026-07-11_12-50-16.json`

| Поле | Значение |
|------|----------|
| Старт / финиш | 2026-07-11T12:45:26+03:00 → 12:50:16 |
| counters | new=**412**, updated=0, unchanged=0, missing=0, errors=0 |
| hash_check | card_count=91, element_count=412 |
| sha256 | `897f31a95d3eec46662c8d21626be9108ff994a60367335c273809ac5d3963e5` |
| category | `Роутеры Wi-Fi` (детерминированно) |

#### Прогон 2 — `tplink_import_2026-07-11_12-56-28.json`

| Поле | Значение |
|------|----------|
| Старт / финиш | 2026-07-11T12:51:38+03:00 → 12:56:28 |
| counters | new=**0**, updated=**0**, unchanged=**412**, missing=**0**, errors=0 |
| sha256 | ✅ тот же |
| все status в items | ✅ **412 × unchanged** |

**Статус задачи A:** ✅ **ПРИНЯТО**
