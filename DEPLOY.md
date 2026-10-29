# Перенос ZUBR GYM на сервер

Целевой адрес: **https://zubr-gym.by/**, без www. Репозиторий содержит ядро MODX 3.2.4-pl, код установленных Ace/pdoTools/MIGX/ClientConfig и файлы проекта. Переустанавливать CMS или импортировать старый HTML не нужно.

## 1. Файлы и домен

Скопируйте проект из Git либо распакуйте `zubr-gym-production-files.zip`. Сохраните структуру целиком: папки `public`, `cms`, `tools` должны находиться рядом.

Корень сайта (DocumentRoot) укажите на **папку public/**. Папки `cms/private`, `tools` и SQL не должны быть доступны через веб. На сервере нужны PHP 8.3 с pdo_mysql, mbstring, intl, iconv, curl, zip, GD, XML; MySQL 8; Apache 2.4 с AllowOverride All и mod_rewrite. PHP должен иметь право записи в `public/core/cache`, `public/core/logs`, `public/core/packages`, `public/assets` и `public/zubr/images`. Не используйте chmod777.

Подключите DNS для `zubr-gym.by` и `www.zubr-gym.by`, установите SSL-сертификат для обоих имён. `.htaccess` уже перенаправляет HTTP и www на `https://zubr-gym.by`, сохраняя путь и параметры. Если HTTPS завершается на прокси, сервер должен корректно выставлять HTTPS=on для доверенного прокси; произвольному заголовку X-Forwarded-Proto доверять нельзя. Для nginx потребуется перенести правила Apache в конфигурацию nginx.

## 2. Настройка подключения

Из корня проекта:

```sh
mkdir -p cms/private
cp tools/deploy/production.example.php cms/private/production.php
```

Заполните в `cms/private/production.php` имя новой базы, пользователя и пароль. Домен оставьте `zubr-gym.by`, префикс таблиц — `modx_`. Файл с доступами не попадает в Git.

```sh
php tools/deploy/configure.php --init
```

Команда создаёт переносимые config.core.php и config.inc.php с путями текущего сервера. Не копируйте локальные Windows-конфиги.

## 3. База

Создайте пустую базу MySQL с utf8mb4, выдайте пользователю права на неё. Загрузите отдельно подготовленный `zubr-gym-production.sql.gz` за пределы public/. Он содержит все ресурсы, TV/MIGX, чанки, шаблоны, настройки пакетов и пользователей. Это приватный файл, в Git он не включён.

Импорт заменяет таблицы с префиксом modx_ в выбранной базе; используйте новую пустую базу:

```sh
gzip -dc /private/path/zubr-gym-production.sql.gz | mysql -h localhost -u DB_USER -p DB_NAME
php tools/deploy/configure.php --apply
```

Последняя команда разрешает маркеры путей под текущий сервер, устанавливает продовый HTTPS-домен, secure-cookie сессии и обновляет кеш. Пропускать её нельзя. SQL уже подготовлен под продовый домен; сессии и временные журналы не переносятся. Доступ в админку сохраняется из базы, входить нужно заново. Установщика MODX в комплекте нет.

## 4. Проверка

Проверьте главную, `/manager/`, карточку тренера, блог, фото, расписание, `/robots.txt`, `/sitemap.xml` и случайный несуществующий URL (HTTP404). Canonical, OG и sitemap должны содержать `https://zubr-gym.by/`, без `.local`. Проверьте перенаправления HTTP/www и cookie-настройки. До согласия карта не загружается; счётчики аналитики пока не подключены. Индексация включена; ошибки и технические страницы исключены.

`SHA256SUMS.txt` рядом с архивом и дампом позволяет проверить их целостность (`sha256sum -c SHA256SUMS.txt`). SQL и конфигурацию с паролями не загружайте в public/ и Git. Настройка DNS/SSL и запуск на самом сервере выполняются при переносе; подготовка архива сама по себе не публикует сайт.

## Повторная подготовка

На локальной рабочей установке:

```sh
php tools/modx/export-elements.php
php tools/deploy/export-database.php
python tools/deploy/build-package.py
```

Экспорт базы не меняет локальную БД. Переносимые файлы и дамп создаются в игнорируемой папке `artifacts/`. Кеш, логи, пароли, SQL, локальные настройки Open Server и исходные референсы исключены из Git и архива.
