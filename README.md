# Сайт для Unity-приложения «Тест ПДД"

Это стартовый PHP-проект для приёма результатов из Unity и просмотра аналитики.

## Что уже готово

- вход с ролями администратора и преподавателя;
- таблица результатов с поиском;
- страница аналитики с графиком;
- экспорт результатов в CSV;
- API для приёма результатов по ключу;
- SQL-схема MySQL;
- привязка результатов Unity к студентам `user1`, `user2`, `user3`;
- аудит входов и получения результатов.

## Быстрый запуск

### Вариант для этого компьютера

1. Проверьте настройки проекта в [config/config.php](config/config.php):
   - база данных: pdd_test
   - хост: 127.0.0.1
   - порт: 3306
   - пользователь: root
   - пароль: пустой

2. Если база ещё не создана, импортируйте [database/schema.sql](database/schema.sql) в локальный MySQL.

3. Убедитесь, что локальный MySQL уже запущен как служба Windows и доступен на `127.0.0.1:3306`.

4. Запустите сайт из папки проекта:

```powershell
.\run-local.ps1
```

5. Откройте сайт в браузере:

```text
http://127.0.0.1:8016/public
```

Если demo-логины не работают, откройте установщик:

```text
http://127.0.0.1:8016/public/install_demo_users.php
```

Если нужен готовый набор результатов для dashboard и аналитики, откройте:

```text
http://127.0.0.1:8016/public/install_test_data.php
```

Скрипт `run-local.ps1` запускает PHP с локальным файлом [php.local.ini](php.local.ini), где включены `pdo_mysql` и `mysqli`. Это нужно, потому что при прямом запуске `php.exe` у вас не загружался `php.ini`, и MySQL-драйвер был недоступен.

6. В Unity укажите:
   - Server Url: http://127.0.0.1:8016/api/submit_result.php
   - Api Key: значение из [config/config.php](config/config.php)

7. После отправки результата сервер должен вернуть success true и storage database.

### Короткая инструкция на каждый запуск

- сначала запустить MySQL;
- потом запустить PHP-сервер;
- затем открыть сайт или запустить Unity.

## Вход в админку

Демо-учетные записи и группы находятся в [docs/demo-accounts.md](docs/demo-accounts.md).
Основные логины:
- админ: `admin` / `admin12345`
- преподаватель: `teacher1` / `teacher12345`

## Пример запроса из Unity

Отправляйте POST-запрос на:

```text
http://127.0.0.1:8016/api/submit_result.php
```

Заголовок:

```text
X-API-KEY: CHANGE_THIS_SECRET_KEY
```

JSON-тело:

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
