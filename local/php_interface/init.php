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

// Файл для AddMessage2Log (использовать для логирования)
// if (!defined('LOG_FILENAME')) {
//     define('LOG_FILENAME', $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/debug.log');
// }

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

$userHandlers = __DIR__ . '/include/ex2_user_handlers.php';
if (is_file($userHandlers)) {
    require_once $userHandlers; // Подключаем класс обработчиков
    
    // Получаем экземпляр EventManager для регистрации обработчиков
    $eventManager = \Bitrix\Main\EventManager::getInstance();
    
    // Регистрируем обработчик OnBeforeUserUpdate модуля 'main'
    // Выполняется ПЕРЕД обновлением пользователя: сохраняет текущий класс в буфер
    $eventManager->addEventHandler('main', 'OnBeforeUserUpdate', ['Ex2UserHandlers', 'onBeforeUserUpdate']);
    
    // Регистрируем обработчик OnAfterUserUpdate модуля 'main'
    // Выполняется ПОСЛЕ успешного обновления: сравнивает классы и отправляет письмо
    $eventManager->addEventHandler('main', 'OnAfterUserUpdate', ['Ex2UserHandlers', 'onAfterUserUpdate']);
}

// [ex2-610] Регистрация агента для проверки рецензий (интервал 86400 секунд - раз в сутки)
$agentsFile = __DIR__ . '/include/ex2_agents.php';
if (is_file($agentsFile)) {
    require_once $agentsFile; // Подключаем функции агентов
}