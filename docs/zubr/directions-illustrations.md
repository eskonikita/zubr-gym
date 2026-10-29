# Иллюстрации направлений ZUBR GYM

Промпты подготовлены для встроенного генератора imagegen. Основной и повторный запросы завершились сетевой ошибкой. Иллюстрации ещё не созданы. Общий визуальный референс — существующий `public/zubr/images/bison-hero.png`; он используется для головы, рогов, красной повязки и сурового характера, а не для фотореализма.

## Подготовленная композиция

Секция на светлом фоне #FAFAFA, заголовок 42–48 px на ПК с красным акцентом. Две карточки высотой 340–360 px, промежуток 20–24 px, фон #111214, радиус 6 px. Названия Russo One 28–32 px, описания Golos Text, красные прямоугольные кнопки «Расписание». Текст занимает отдельную левую колонку, иллюстрация — правую. На ширинах ниже 1024 px карточки располагаются друг под другом, сохраняя компактную горизонтальную композицию внутри. При наведении меняются только цвета кнопок.

Тексты направлений и все три ссылки на #schedule сохранены. Подготовленная вёрстка проверена на ширинах 320, 390, 640, 768, 1024, 1280, 1440 и 1920 px; в этой предварительной проверке использовалось только техническое заполнение слота изображением существующего зубра. Новые иллюстрации и их композиция ещё требуют визуальной проверки. Новая вёрстка подключена к рабочей странице. До появления оригинальных иллюстраций в обеих карточках временно используется существующий зубр из херо. Размеры, ссылки и отсутствие движения при наведении проверены в браузере.

Полная подготовленная разметка страницы: `reports/directions-bison-before/directions-pending.html`. Стили: `reports/directions-bison-before/directions-pending.css`. Для подключения только секции подготовлен `.tmp-redesign-directions.cjs`. Исходный вариант шаблона ожидает четыре файла изображений ниже; рабочая страница сейчас использует временное изображение bison-hero.png.

## Общий промпт

Create one professional original 2D sports illustration, transparent RGBA background, NO text or lettering. Use case: stylized-concept. Reference image is ONLY identity reference for the serious European bison head, curved ivory horns, black fur, stern eyes and red braided headband; convert its detail into bold hand-drawn ink / engraved sports poster illustration, NOT photo or 3D. Anthropomorphic adult bison combat athlete with massive shoulders, believable powerful muscular anatomy, black/white crosshatched fur, red boxing gloves, black fight shorts with red trim, red headband trailing slightly. Fierce focused closed mouth, no smile, no comedy, no cute cartoon proportions. Full body all limbs visible, generous transparent margin, silhouette facing toward the LEFT, intended for right half of a dark website card. Predominantly white/light-gray highlights and black ink shapes so anatomy reads against #111214. Restrained red dry brush strokes closely behind body, still isolated on transparency. No background scene, no floor rectangle, no typography, no logos, no glow, no gradients.

## Тайский бокс — дополнение к общему промпту

POSE: MUAY THAI explosive rising knee strike. One knee raised forcefully to chest height, other foot firmly supporting body, elbows tucked, both red gloves guarding face, compact upright fighting stance. Readable strong knee silhouette; exactly two arms and two legs. Portrait composition.

## Кикбоксинг — дополнение к общему промпту

POSE: KICKBOXING powerful high roundhouse kick aimed toward the LEFT. One leg extends diagonally to the left at waist/chest height, other leg supports pivot, torso turns and red gloved hands guard face. Readable kick silhouette; exactly two arms and two legs. Keep entire kick and both horns inside canvas. Portrait or square composition.

## Планируемые файлы для сайта

- `public/zubr/images/bison-muay-thai-480.webp` и `bison-muay-thai-800.webp`.
- `public/zubr/images/bison-kickboxing-480.webp` и `bison-kickboxing-800.webp`.
- Прозрачность сохраняется при оптимизации; оригиналы сохраняются отдельно в `docs/zubr/illustration-sources/`.
