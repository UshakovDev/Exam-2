<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Agent_ex_610 - ежедневная проверка изменённых рецензий.
 * 
 * если не будет выполнятся агент  в первый раз можно в 
 * Командной PHP-строке выполнить: Agent_ex_610();
 * дальше агент будет выполнятся в запланированном интервале
 */
function Agent_ex_610($lastRun = null)
{
    // Без модуля инфоблоков агент ничего сделать не сможет, пробуем снова через сутки
    if (!CModule::IncludeModule('iblock')) {
        return "Agent_ex_610('" . date('Y-m-d H:i:s') . "');";
    }

    // Текущее время фиксируем сразу - его использует и фильтр, и планировщик
    $now = new DateTime();

    // Определяем время предыдущего запуска. Если нам его не передали (первый запуск), берём сутки назад
    $from = $lastRun ? DateTime::createFromFormat('Y-m-d H:i:s', $lastRun) : null;
    if (!$from) {
        $from = (clone $now)->modify('-1 day');
    }

    // Формируем фильтр по инфоблоку рецензий: все элементы, изменённые в интервале (прошлый запуск; текущий запуск]
    $filter = [
        'IBLOCK_ID' => IBLOCK_REVIEWS_ID,
        '>TIMESTAMP_X' => ConvertTimeStamp($from->getTimestamp(), 'FULL'),
        '<=TIMESTAMP_X' => ConvertTimeStamp($now->getTimestamp(), 'FULL'),
    ];

    // Собираем уникальные ID изменённых рецензий
    $ids = [];
    $res = CIBlockElement::GetList([], $filter, false, false, ['ID']);
    while ($row = $res->Fetch()) {
        $ids[(int)$row['ID']] = true;
    }

    // Пишем результат в журнал событий в требуемом формате
    CEventLog::Add([
        'AUDIT_TYPE_ID' => 'ex2_610',
        'MODULE_ID' => 'iblock',
        'DESCRIPTION' => Loc::getMessage('EX2_610_LOG', [
            '#DATE#' => ConvertTimeStamp($from->getTimestamp(), 'FULL'),
            '#COUNT#' => count($ids),
        ]),
    ]);

    // Возвращаем строку следующего запуска с текущим временем - Bitrix подставит её в функцию, чтобы хранить параметр
    return "Agent_ex_610('" . $now->format('Y-m-d H:i:s') . "');";
}