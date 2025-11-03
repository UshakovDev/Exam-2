<?php
// Константы проекта для экзамена ex2 (расположение в local согласно требованиям)
if (!defined('IBLOCK_REVIEWS_ID')) {
    define('IBLOCK_REVIEWS_ID', 5); // ИБ «Рецензии»
}
if (!defined('UF_AUTHOR_STATUS_PUBLISH_ID')) {
    define('UF_AUTHOR_STATUS_PUBLISH_ID', 36); // Элемент «Публикуется» в ИБ статусов
}
if (!defined('REVIEWS_GROUP_ID')) {
    define('REVIEWS_GROUP_ID', 6); // Группа «Авторы рецензий»
}


$handlersFile = __DIR__ . '/include/ex2_event_handlers.php';
if (is_file($handlersFile)) {
    require_once $handlersFile; // Подключаем класс обработчиков

    $eventManager = \Bitrix\Main\EventManager::getInstance();
    // Проверка анонса при создании рецензии
    $eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementAdd', ['Ex2ReviewsHandlers', 'onBeforeIBlockElementAdd']);
    // Проверка анонса + фиксация старого автора перед обновлением
    $eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementUpdate', ['Ex2ReviewsHandlers', 'onBeforeIBlockElementUpdate']);
    // Логирование смены автора после успешного обновления
    $eventManager->addEventHandler('iblock', 'OnAfterIBlockElementUpdate', ['Ex2ReviewsHandlers', 'onAfterIBlockElementUpdate']);
}


