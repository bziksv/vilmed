<?
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_TESTMODE_NOTIFY"] = "Автоматические задачи - позволяют генерировать данные в фоновом режиме. Создал задачу, добавил элементы, запустил - можно заниматься своими делами. При использовании <b>рекомендуется делать бэкапы</b>.<br><br>
Видео-инструкция по работе с автоматическими задачами: <a href='https://arturgolubev.ru/knowledge/course35/lesson281/' target='_blank'>https://arturgolubev.ru/knowledge/course35/lesson281/</a>";


$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_CRON_ERROR_BODY"] = "Агенты выполняются на хитах (константа BX_CRONTAB_SUPPORT не определена).<br><br>
Для стабильной работы фоновой генерации агенты рекомендуется перевести на cron (Процесс перевода агентов на cron не сложен, но требует создания cron задания на сервере, поэтому для выполнения рекомендую обратиться к вашему разработчику)";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_TITLE"] = "Автоматическая генерация данных";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_TITLE"] = "Редактирование автоматической генерации ##id#";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_NEW_TITLE"] = "Создание новой автоматической генерации";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_PREPARE_DESCRIPTION"] = "Подготовка к созданию генерации, выберите инфобок и для какой сущности будут генерироваться данные.";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_TAB_TASK"] = "Редактирование задания";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_TAB_ELEMENTS"] = "Элементы задания";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_SEND"] = "Отправить";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_SAVE"] = "Сохранить настройки";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_START"] = "Запустить";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_CONTINUE"] = "Продолжить";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_RESTART"] = "Сбросить ошибки и запустить";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_STOP"] = "Остановить (пауза)";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_FINISH_START_DESCR"] = "Сброс ошибок и генерация для элементов с ошибками + новых элементов (добавленым после завешения задачи)";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_FINISH_RESTART"] = "Повторный запуск";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_FINISH_RESTART_DESCR"] = "Повторный запуск для всех элементов добавленных в задачу (для новых, для элементов с ошибками, для успешно сгенерированных)";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_STOP_RESTART"] = "Перезапуск запуск";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_STOP_RESTART_DESCR"] = "Выполнение задачи пойдет заново (для всех элементов добавленных в задачу)";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_NAME"] = "Название:";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK"] = "Инфоблок:";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_NEED_SELECT"] = "Выберите инфоблок генерации";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_ENTITY"] = "Для чего генерируем данные:";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_ENTITY_TYPE"] = "Сущность:";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_E"] = "Элементы";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_S"] = "Разделы";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_STATUS"] = "Статус задачи:";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_STATUS_"] = "?";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_STATUS_new"] = "Создание";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_STATUS_work"] = "Выполнение";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_STATUS_stop"] = "Остановлено";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_STATUS_finish"] = "Завершена";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_STATUS_stop_error"] = "Приостановлена с ошибкой";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_PROMPT"] = "Запрос к нейросети:";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_SAVE"] = "Поле сохранения результата:";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_DELETE_TASK"] = "Удалить задачу";

// $MESS["ARTURGOLUBEV_CHATGPT_TASKS_GENERATE_ERROR_STATUS_demo_limit"] = "Достингнут лимит генерации в демо-версии решения";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_AGENT_INFO_NOT_FOUND"] = "Агент не найден";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_AGENT_INFO"] = "<b>Агент генерации</b>:<br>Активность: #active#; Выполняется: #exec#; Попыток: #errs#<br>Последний запуск: #last#; Следующий запуск: #next#;";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_AGENT_ERR_REPEATS"] = "(Предположительно агент не выполняется, обратитесь в поддержку решения)";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_START_TASK_ERROR_NO_ELEMENTS"] = "В задачу не добавлены элементы для которых должна выполняться генерация.";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_ELEMENTS_FIELD_STATUS_"] = "Ожидает";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_ELEMENTS_FIELD_STATUS_success"] = "Сгенерировано";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_ELEMENTS_FIELD_STATUS_error"] = "Ошибка";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_EDIT_ELEMENTS_FIELD_STATUS_skip"] = "Пропущено";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_ADEDDED_ON_NEW"] = "Добавление элементов будет доступно после создания";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_ADEDDED_ON_EDIT"] = "Добавление элементов в задачу осуществляется через список элементов инфоблока. Открываем список элементов, выделяем нужные и через массовые действия добавляем в задачу на генерацию данных.";


$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_DESCRIPTION"] = "На данной странице вы можете посмотреть текущий список заданий автоматической генерации и создать новые задания. Обработка и генерация данных по заданиям осуществляется в автоматическом фоновом режиме";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_MENU_ADD_NEW"] = "Добавить задачу";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ID"] = "ID";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_NAME"] = "Название задачи";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_IBLOCK"] = "Инфоблок";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_STATUS"] = "Статус";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_ERROR_TEXT"] = "Ошибка";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_GENERATION_DATE"] = "Дата генерации";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_GENERATION_RESULT"] = "Результат генерации";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_PROMPT"] = "Промпт";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ETYPE"] = "Сущность";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ELEMENT"] = "Элемент";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ELEMENTS"] = "Элементов в задаче";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_ACTION_EDIT"] = "Изменить";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_ACTION_COPY"] = "Копировать";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_ACTION_DELETE"] = "Удалить";

$MESS["ARTURGOLUBEV_CHATGPT_TASKS_ELEMENTS_LIST_TABLE_ACTION_MOREINFO"] = "Подробнее";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_ELEMENTS_LIST_TABLE_ACTION_DELETE_ELEMENT"] = "Удалить из задачи";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_ELEMENTS_LIST_TABLE_ACTION_READD_ELEMENT"] = "Повторить генерацию";
$MESS["ARTURGOLUBEV_CHATGPT_TASKS_ELEMENTS_LIST_TABLE_ACTION_RETURN_DATA_ELEMENT"] = "Откатить генерацию";

?>