# Чеклист соответствия ТЗ v1.5 → модуль `ibc.tplink`

Источник: [TZ_EXTRACTED.txt](./TZ_EXTRACTED.txt)  
Спека: [MODULE_ASSIGNMENT.md](./MODULE_ASSIGNMENT.md)

## Задача A — импортёр

| § ТЗ | Требование | Спека / реализация | Статус |
|------|------------|-------------------|--------|
| A.1 | Источник: tp-link.com/kz/.../wifi-router/ | MODULE §4.1, option `source_list_url` | ✅ |
| A.1 | Полный список без фильтров, динамическая подгрузка | `ListingParser` + sitemap merge | ✅ |
| A.1 | На списке: URL + имя; FULL_ARTICLE на A.2/A.4 | MODULE §4.1–4.3 | ✅ |
| A.2 | CLI PHP, D7, не через веб | `tools/import.php`, MODULE §7 | ✅ |
| A.3 | IB `tplink_catalog_stage` + свойства | MODULE §5, `IblockInstaller` | ✅ |
| A.3 | FULL_ARTICLE отдельное обязательное свойство | MODULE §5.1 | ✅ |
| A.3 | ARTICLE vs FULL_ARTICLE | MODULE §5.1, §6.1 | ✅ |
| A.4 | Уникальность по FULL_ARTICLE | `CatalogSyncService` | ✅ |
| A.4 | Fallback + NEEDS_REVIEW + лог причины | `FullArticleResolver` | ✅ |
| A.4 | Support/download для HW×region | `SupportPageParser` | ✅ |
| A.4 | 1 карточка → N элементов, общий SOURCE_URL | MODULE §4.3 | ✅ |
| A.4 | ~75 карточек → 200–300 элементов | `hash_check.element_count` | ⏳ verify on Bitrix |
| A.4 | Цену не трогать | MODULE §5.2 | ✅ |
| A.4 | new / updated / unchanged / missing | MODULE §6.2 | ✅ |
| A.4 | MISSING_AT_SOURCE=Y, не удалять | MODULE §6.2 | ✅ |
| A.5 | JSON `/local/logs/tplink_import_*.json` | `ImportLogWriter`, schema | ✅ |
| A.6 | README разделы | `local/modules/ibc.tplink/README.md` | ✅ |
| A.6 | CSV first + second run | `--csv=` в `tools/import.php` | ✅ |
| A.6 | sha256(sorted_articles) | `HashCheckService` | ✅ |
| — | Критерии оценки A | MODULE §12 | ✅ spec |

## Задача B — аудит

| § ТЗ | Требование | Документ | Статус |
|------|------------|----------|--------|
| B.1 | Фильтры + 2–4 скрина | [AUDIT_TASK.md](./AUDIT_TASK.md) §B.1 | 📋 spec |
| B.2 | ≥5 примеров характеристик | AUDIT §B.2 | 📋 spec |
| B.3 | Таблица ≥10 свойств | AUDIT §B.3 | 📋 spec |
| B.4 | Оценка часов по этапam | AUDIT §B.4 | 📋 spec |
| B.5 | ИИ-workflow с промптами | AUDIT §B.5 | 📋 spec |
| B.6 | Omada vs TP-Link (опц.) | AUDIT §B.6 | 📋 spec |
| B.7 | Поиск латиница (опц.) | AUDIT §B.7 | 📋 spec |

## Изменения v1.5 (трассировка)

| Изменение v1.5 | Отражение в спеке |
|----------------|-------------------|
| FULL_ARTICLE — отдельное свойство | MODULE §5.1 |
| Убрано HW_VERSION multi-value | только HW×region → N элементов |
| Ориентир 75→200–300 элементов | MODULE §4.3, §12 |
| B.7 поиск латиница | AUDIT §B.7 |
| Цена не трогается | MODULE §5.2 |
| 2-й CSV все unchanged | MODULE §8.2, §12 |

**Легенда:** 📋 spec — описано в спеке · ✅ реализовано · ⏳ требует прогона на Bitrix · ❌ не реализовано
