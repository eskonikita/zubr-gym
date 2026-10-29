# ZUBR GYM

Перенос на **https://zubr-gym.by/**: [DEPLOY.md](DEPLOY.md). Репозиторий содержит файлы MODX и установленных пакетов; приватный SQL-дамп передаётся отдельно. Корень сайта на сервере — `public/`.

Локальный сайт работает на MODX Revolution 3.2.4-pl: **http://zubr-gym.local/**.

Админка: **http://zubr-gym.local/manager/**. Инструкция, версии пакетов, расположение доступа и порядок дальнейшей работы: [cms/README.md](cms/README.md).

Общие части вынесены в чанки, заголовки и данные страниц — в TV. Направления, аудитории, статьи и документы используют общие шаблоны по типам. Контакты и реквизиты редактируются в ClientConfig. Исходный HTML в `public/` сохранён как статический предпросмотр.

Актуальный контекст и дальнейший план: [docs/zubr/CONTEXT.md](docs/zubr/CONTEXT.md).

`npm run dev` запускает статический предпросмотр. Для MODX нужны Open Server Panel, PHP 8.3 и MySQL 8.2.

Проверка статической сборки:

```powershell
node tools/build-pages.mjs
node tools/check-links.mjs dist /zubr-gym/
```

Файлы CMS, пароли и резервные копии не включаются в GitHub Pages.
