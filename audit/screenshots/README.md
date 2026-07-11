# Скриншоты для аудита (задача B)

PNG лежат в `screenshots/`. Повторная съёмка:

```bash
cd ibc/tplink/spec/audit/tools
npm install puppeteer
node capture-screenshots.mjs
```

Файлы:

| Файл | Содержание |
|------|------------|
| `b1-01-subsections-and-filter.png` | Плитки Wi‑Fi 7–4 + боковой фильтр |
| `b1-02-wifi6-filter-not-applied.png` | SEF Wi‑Fi 6, выдача AC1200 |
| `b1-03-wifi6-subsection-correct.png` | Подраздел `/wi-fi-6/` |
| `b1-04-degenerate-filters.png` | DHCP/WAN/антенны |

### B.2 — карточки

| Файл | Карточка |
|------|----------|
| `b2-01-mr550-price-zero.png` | MR550 — 0 ₽ |
| `b2-02-mr550-wifi6-vs-ac1200.png` | MR550 — Wi‑Fi 6 vs AC1200 |
| `b2-03-c6-ac1300-vs-ac750.png` | C6 — AC1300/AC750 |
| `b2-04-ax55-missing-wan25-spec.png` | AX55 — нет 2,5G в IB |
| `b2-05-ax10-speed-mismatch.png` | AX10 — 1201 vs AX1500 |
| `b2-06-wr842n-widget-and-adsl.png` | WR842N — виджет / ADSL |

```bash
node capture-b2-screenshots.mjs
```

Документ: [ROUTERS_WIFI_AUDIT.md](../ROUTERS_WIFI_AUDIT.md)
