# exchangeWithIspringlearn
Данный скрипт позволяет выгружать назначения из обучающей платформы Ispring 

Скрипт автоматически запускается ежедневно по крону. Его задача выгрузить назначения (или иными словами поставленные задачи по прохождению курса на сотрудников компаниии).

**Механизм работы**:

1. Ежедневно ночью механизм запускается по крону.
2. После того, как назначения выгружены и механизму известно каким сотрудникам они назначены, на стороне Б24 создаются элементы УС. 
3. На основании созданных элементов УС запускается процесс, который ставит задачи сотрудникам на прохождение теста.

Решение может работать как на облачных, так и коробочных Битрикс24. 

**Как запустить**:
1. exchangeWithIspringlearn.php и auth.php необходимо разместить на хостинге с поддержкой SSL.
2. В разделе "Разработчикам" необходимо создать входящий вебхук с правами на Пользователи (user) и Списки (lists), а также Бизнес-процессы (bizproc). Подробнее как создать входящий / исходящий вебхук: [Ссылки на документацию 1С-Битрикс](https://github.com/thnik911/callbackMangoTelecom/blob/main/README.md#%D1%81%D1%81%D1%8B%D0%BB%D0%BA%D0%B8-%D0%BD%D0%B0-%D0%B4%D0%BE%D0%BA%D1%83%D0%BC%D0%B5%D0%BD%D1%82%D0%B0%D1%86%D0%B8%D1%8E-1%D1%81-%D0%B1%D0%B8%D1%82%D1%80%D0%B8%D0%BA%D1%81).
3. Полученный "Вебхук для вызова rest api" прописать в auth.php.
4. В строке 6 необходимо указать путь до файла auth.php.
5. В строке 14 необходимо указать путь, где у нас будет храниться файл page.log (предварительно создайте его на сервере). В данный файл будет записываться последняя страница, на которой в прошлый запуск остановился опрос назначений. Сделан отдельный файл с целью, чтобы каждый раз не опрашивать те назначения, которые уже были выгружены раннее.
6. В строках 18-20 необходимо указать адрес портала Ispring, логин и пароль администратора.
7. Указать 'IBLOCK_ID' в строке 107, в котром у Вас хрянятся пользователи и их Email.
8. Указать 'IBLOCK_ID' в строке 127, в котром у Вас хрянятся уже выгруженные назачения.
9. Указать 'IBLOCK_ID' в строке 144, в котром у Вы предпологаете хранить назначения (точно такой же ID, как в пункте 7). Также, внесите правки в массив 'FIELDS'. В моем примере: 'PROPERTY_520' - это поле с привязкой к пользователю, 'PROPERTY_521' и 'PROPERTY_522' - даты начала и окончнаия прохождения курса, 'PROPERTY_523' - ссылка на курс и 'PROPERTY_524' - уникальный ID назначения из Ispring для проверки, что данное назначение уже выгружалось или нет.
10. В строке 161 укажите ID процесса, который необходимо запускать при создании элемента.
11. Добавить выполнение механизма на крон. К примеру: `0 6 * * * root /usr/bin/php /home/bitrix/www/local/webhooks/ispring/ispringlearn.php >/dev/null 2>&1`

### Ссылки на документацию 1С-Битрикс и ispring

<details><summary>Развернуть список</summary>

1. Как создать Webhook https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=99&LESSON_ID=8581&LESSON_PATH=8771.8583.8581
2. Документация по работе API ispring: https://docs.ispring.ru/display/L3/REST+API
</details>
