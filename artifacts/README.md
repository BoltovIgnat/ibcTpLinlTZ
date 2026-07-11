# Артефакты приёмки (задача A)

Прогоны на **https://ibcmoney.store/** — см. [ACCEPTANCE.md](./ACCEPTANCE.md).

После двух CLI-импортов положите сюда (или подтяните с prod):

```bash
# env: TPLINK_LOG_TOKEN=<api_log_read_token с ibcmoney.store>
python ibc/tplink/artifacts/tools/fetch_acceptance_artifacts.py
```

Файлы:

- `tplink_import_first_run.csv` / `.json`
- `tplink_import_second_run.csv` / `.json`

Проверка:

```bash
python ibc/tplink/artifacts/tools/verify_acceptance.py
```

Для коммита в репо временно уберите `*.csv` и `*.json` из `.gitignore` или force-add файлы.
