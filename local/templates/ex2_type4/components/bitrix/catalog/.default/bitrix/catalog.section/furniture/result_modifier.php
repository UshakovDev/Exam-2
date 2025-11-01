<?php
// Защита от прямого вызова файла - если пролог не подключён, прекращаем выполнение
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserGroupTable;

// Загружаем языковые фразы из файла lang/ru/result_modifier.php
// Это позволяет не держать текстовые строки прямо в коде
Loc::loadMessages(__FILE__);

// --------------- работы с ценами для ex2-580 ---------------

// Извлекаем массив ID всех товаров, которые компонент уже подготовил
// array_column делает это за один проход, без цикла
$itemIds = array_column($arResult['ITEMS'], 'ID');
if (!$itemIds) {
    // Если товаров нет на странице, нет смысла выполнять запросы к базе
    return;
}

// Создаём пустой массив для хранения цен по ID товара
$priceMap = [];
// Загружаем значения свойств PRICE и PRICECURRENCY ОДНИМ запросом для всех товаров
// Это важно: без оптимизации был бы запрос в цикле на каждый товар (N+1)
$priceRes = CIBlockElement::GetList(
    [], // сортировка не важна
    [
        'IBLOCK_ID' => (int)$arParams['IBLOCK_ID'], // приводим к int для безопасности
        'ID' => $itemIds, // фильтр по массиву ID - один запрос вместо множества
        'ACTIVE' => 'Y', // только активные элементы
    ],
    false, // не группируем
    false, // без пагинации
    ['ID', 'PROPERTY_PRICE', 'PROPERTY_PRICECURRENCY'] // выбираем только нужные поля
);
while ($row = $priceRes->Fetch()) {
    // Преобразуем цену в число (float), так как из БД может прийти строка
    $price = (float)$row['PROPERTY_PRICE_VALUE'];
    if ($price <= 0) {
        // Нулевые или отрицательные цены нам не нужны
        continue;
    }
    // Сохраняем отформатированную цену в массив, ключ - ID товара
    $priceMap[$row['ID']] = [
        'PRINT_VALUE' => number_format($price, 0, '.', ' '), // форматируем с пробелами тысяч
        'CAN_ACCESS' => true, // флаг, что у пользователя есть доступ к цене
    ];
}

// Если нашлись цены, добавляем их в результат компонента
if ($priceMap) {
    // Заголовок поля цены берём из языковых фраз для локализации
    $arResult['PRICES']['PRICE']['TITLE'] = Loc::getMessage('EX2_PRICE_TITLE');
    // Проходим по всем товарам и добавляем цену тем, у кого она есть
    foreach ($arResult['ITEMS'] as $index => $item) {
        if (isset($priceMap[$item['ID']])) {
            // Подставляем уже отформатированную цену в структуру товара
            // Структура массива совпадает с тем, что ожидает шаблон
            $arResult['ITEMS'][$index]['PRICES']['PRICE'] = $priceMap[$item['ID']];
        }
    }
}

// --------------- работы с рецензиями для ex2-580 ---------------

// Инициализируем пустые массивы и переменные
$arResult['REVIEWS'] = []; // массив рецензий, ключ - ID товара, значение - массив названий
$arResult['FIRST_REVIEW'] = null; // название первой найденной рецензии для блока "Дополнительно"
$arResult['REVIEWS_CNT'] = 0; // количество товаров с рецензиями

// 1) состоят в группе "Авторы рецензий" (REVIEWS_GROUP_ID)
// 2) имеют статус "Публикуется" (UF_AUTHOR_STATUS_PUBLISH_ID)
// 3) пользователь активен
$publishUsers = UserGroupTable::getList([
    'filter' => [
        '=GROUP_ID' => (int)REVIEWS_GROUP_ID, // фильтр по ID группы
        '=USER.ACTIVE' => 'Y', // только активные пользователи
        '=USER.UF_AUTHOR_STATUS' => (int)UF_AUTHOR_STATUS_PUBLISH_ID, // фильтр по UF полю
    ],
    'select' => ['USER_ID'], // выбираем только ID пользователей
    'group' => ['USER_ID'], // группируем, чтобы не было дублей (на случай множественной привязки)
])->fetchAll(); // получаем все записи разом (массив массивов)
// Извлекаем только USER_ID в простой массив [1, 2, 3...]
$publishUserIds = array_column($publishUsers, 'USER_ID');

// Если нашлись подходящие авторы, ищем их рецензии
if ($publishUserIds) {
    // Загружаем рецензии ОДНИМ запросом для всех товаров и авторов
    // Опять избегаем запросов в цикле
    $reviewRes = CIBlockElement::GetList(
        [], // сортировка не важна
        [
            'IBLOCK_ID' => IBLOCK_REVIEWS_ID, // инфоблок с рецензиями
            'ACTIVE' => 'Y', // только активные рецензии
            'PROPERTY_AUTHOR' => $publishUserIds, // фильтр по авторам (массив ID)
            'PROPERTY_PRODUCT' => $itemIds, // фильтр по товарам (массив ID)
        ],
        false, // не группируем
        false, // без пагинации
        ['ID', 'NAME', 'PROPERTY_PRODUCT'] // выбираем ID, название и привязку к товару
    );

    // Обрабатываем результаты в цикле
    while ($review = $reviewRes->Fetch()) {
        // Приводим ID товара к int и проверяем, что он есть
        $productId = (int)$review['PROPERTY_PRODUCT_VALUE'];
        if (!$productId) {
            // Если рецензия без привязки к товару, пропускаем её
            continue;
        }
        // Добавляем название рецензии в массив по ID товара
        // Массив позволяет хранить несколько рецензий для одного товара
        $arResult['REVIEWS'][$productId][] = $review['NAME'];
        // Запоминаем первую рецензию для вывода в блоке "Дополнительно"
        if ($arResult['FIRST_REVIEW'] === null) {
            $arResult['FIRST_REVIEW'] = $review['NAME'];
        }
    }
}

// Если нашлись рецензии, работаем с кешированием
if ($arResult['REVIEWS']) {
    // Считаем количество товаров, у которых есть рецензии
    $arResult['REVIEWS_CNT'] = count($arResult['REVIEWS']);
    // Передаём значения в некешируемую часть компонента
    // Это важно: эти значения будут доступны в component_epilog.php
    $this->__component->SetResultCacheKeys(['REVIEWS_CNT', 'FIRST_REVIEW']);
}