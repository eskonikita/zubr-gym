# Hero: изображения и реализация

18 октября 2026. Хедер и hero реализованы по двум изображениям, приложенным пользователем. Иллюстрация зубра и логотип восстановлены встроенным ImageGen с прозрачным фоном; это производные изображения по образцам. Фон, навигация, заголовок, описание и кнопки выполнены в HTML/CSS. Основная кнопка ведёт на звонок.

Сохранённые изображения: `public/zubr/images/bison-hero.png`, `public/zubr/images/zubr-wordmark.png`.

Стили первого экрана: `public/zubr/hero.css`. Обе копии HTML: `public/index.html` и `public/zubr/home.html`.

Заголовок использует локальный Russo One из официального репозитория Google Fonts; лицензия сохранена рядом: `public/zubr/fonts/Russo-One-OFL.txt`.

## Промпт зубра

Use case: background-extraction. Edit target: the supplied ZUBR GYM mobile website screenshot. Extract ONLY the exact black bison head with red braided headband and hanging red tassels, together with the surrounding diagonal red and black paint brush splashes. Preserve the bison identity, pose facing left, eye, horn, black fur, intricate textures, colors and brush strokes exactly as in reference. Remove ALL website text, logo, icons, buttons, gray background. Output a large portrait transparent PNG asset, tightly framed around the bison and paint splashes with a small safe transparent margin. Actual transparent background. No lettering or UI. This is the production image used inside a responsive website hero; preserve original composition of bison and brushwork as closely as possible.

## Промпт логотипа

Use case: background-extraction. Extract only the existing exact ZUBR GYM wordmark from the top-left header of this reference screenshot. It says ZUBR GYM, uppercase, heavy bold forward italic geometric athletic sans-serif. ZUBR is black #000000, GYM is vivid red #de0014. Preserve exact letter shapes and proportions from the source. Output a tightly cropped wide transparent PNG logo with very little transparent margin, approximately 7:1 aspect ratio. No website content, no additional icons, no tagline, no background. This is faithful logo extraction, not a redesign. Black ZUBR, red GYM, exact reference lettering.
