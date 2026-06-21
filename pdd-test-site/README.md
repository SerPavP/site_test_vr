# Сайт для Unity-приложения «Тест ПДД»

PHP-проект для приема результатов из Unity, просмотра результатов, аналитики и экспорта CSV.

## Windows-скрипты

Проект теперь можно поднимать локально без MySQL. По умолчанию используется SQLite-файл `database/pdd.sqlite`.

1. Первый запуск: скачивает portable PHP для Windows, включает `pdo_sqlite` и `sqlite3`, создаёт `config/config.php`, инициализирует SQLite.

```powershell
powershell -ExecutionPolicy Bypass -File .\first-run.ps1
```

2. Запуск приложения:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-app.ps1
```

3. Запуск приложения в текущем окне PowerShell до `Ctrl+C`:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-app-foreground.ps1
```

4. Остановка фонового приложения:

```powershell
powershell -ExecutionPolicy Bypass -File .\stop-app.ps1
```

После запуска сайт будет доступен по адресу:

```text
http://127.0.0.1:8081/public
```

API:

```text
http://127.0.0.1:8081/api/submit_result.php
```

## Режим БД

- По умолчанию проект использует SQLite через `config/config.php`.
- Схема SQLite лежит в `database/schema_sqlite.sql`.
- Старый MySQL-конфиг оставлен как запасной вариант внутри `db.mysql`.
- Если нужно вернуть MySQL, достаточно поменять `db.driver` на `mysql`.

## Настройки Unity

- Server Url: `http://127.0.0.1:8081/api/submit_result.php`
- API Key: значение из `config/config.php`

Пример заголовка:

```text
X-API-KEY: pdd_live_6f2a9d1c4e8b7a5f0d3c9e2b1a4f6d8c7b9e0a2d5f1c3b6a8e4d7f0c2b5a9
```

Пример JSON:

```json
{
  "name": "Игрок 1",
  "score": 19,
  "total_questions": 20,
  "answers": [
    { "question_id": 1, "is_correct": true },
    { "question_id": 2, "is_correct": false }
  ]
}
```

## Что уже настроено

- В `config/config.php` установлен `base_url` на `http://127.0.0.1:8081/public`
- По умолчанию включен SQLite-файл `database/pdd.sqlite`
- В проекте есть скрипты `first-run.ps1`, `start-app.ps1`, `start-app-foreground.ps1`, `stop-app.ps1`
- Слой БД поддерживает и `sqlite`, и `mysql`
- В проекте добавен резервный режим чтения из `logs/results_fallback.jsonl`, если БД временно недоступна
