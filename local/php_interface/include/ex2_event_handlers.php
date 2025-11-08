<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Обработчики для [ex2-590] Обновление элементов инфоблоков.
 *
 * Главные задачи, которые решает класс:
 * 1. Проверить анонс (#del# → удаляем, длина ≥5) при создании / обновлении рецензии.
 * 2. Отследить смену значения свойства «Автор» и записать событие в журнал.
 */
class Ex2ReviewsHandlers
{
    /**
     * Буфер для хранения автора до обновления элемента.
     * Заполняем в OnBeforeIBlockElementUpdate(), используем в OnAfterIBlockElementUpdate().
     * После записи в журнал обязательно очищаем, чтобы буфер не «переехал» на следующий элемент.
     */
    protected static $oldAuthor;

    /**
     * Обработчик события OnBeforeIBlockElementAdd.
     * Срабатывает при создании рецензии, когда ID ещё отсутствует.
     * Здесь вызываем checkPreview() - всю проверку/очистку анонса.
     */
    public static function onBeforeIBlockElementAdd(array &$fields): bool
    {
        return self::checkPreview($fields);
    }

    /**
     * Обработчик события OnBeforeIBlockElementUpdate (перед сохранением изменений).
     * 1. Проверяем, что работаем с нужным инфоблоком - «Рецензии» (IBLOCK_REVIEWS_ID).
     * 2. Если элемент найден (есть ID), сохраняем текущий ID автора в $oldAuthor.
     * 3. Через checkPreview() проводим ту же обработку текста, что и при добавлении.
     */
    public static function onBeforeIBlockElementUpdate(array &$fields): bool
    {
        if ((int)($fields['IBLOCK_ID'] ?? 0) === IBLOCK_REVIEWS_ID) {
            self::$oldAuthor = self::getAuthorId((int)($fields['ID'] ?? 0));
        }

        return self::checkPreview($fields);
    }

    /**
     * Обработчик события OnAfterIBlockElementUpdate.
     * Выполняется только в случае, если обновление не было отменено другими обработчиками.
     * 1. Проверяем инфоблок.
     * 2. Получаем актуального автора после изменения.
     * 3. Сравниваем со значением, записанным до обновления.
     * 4. Если автор изменился, вызываем CEventLog::Add с AUDIT_TYPE_ID = ex2_590.
     * 5. По завершении сбрасываем $oldAuthor.
     */
    public static function onAfterIBlockElementUpdate(array &$fields): void
    {
        if ((int)($fields['IBLOCK_ID'] ?? 0) !== IBLOCK_REVIEWS_ID) {
            return;
        }

        $newAuthor = self::getAuthorId((int)($fields['ID'] ?? 0));
        if (self::$oldAuthor == $newAuthor) {
            return;
        }

        CEventLog::Add([
            'AUDIT_TYPE_ID' => 'ex2_590',
            'MODULE_ID' => 'iblock',
            'ITEM_ID' => $fields['ID'],
            'DESCRIPTION' => sprintf('В рецензии [%s] изменился автор с [%s] на [%s]', $fields['ID'], self::$oldAuthor ?: '', $newAuthor ?: ''),
        ]);

        self::$oldAuthor = null;
    }

    /**
     * Применяется при любом добавлении/обновлении рецензии:
     * 1. Удаляем плейсхолдер #del#.
     * 2. Обрезаем пробелы по краям.
     * 3. Проверяем длину (с учётом mb_strlen, чтобы не сломаться на UTF-8).
     * 4. Если длина < 5 - бросаем исключение, что отменяет сохранение и показывает ошибку пользователю.
     * 5. Если всё хорошо - подменяем исходное значение (для корректного сохранения).
     */
    private static function checkPreview(array &$fields): bool
    {
        if ((int)($fields['IBLOCK_ID'] ?? 0) !== IBLOCK_REVIEWS_ID) {
            return true;
        }

        $text = trim(str_replace('#del#', '', self::getPreviewText($fields)));
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($len < 5) {
            global $APPLICATION;
            if (is_object($APPLICATION)) {
                $APPLICATION->ThrowException(Loc::getMessage('EX2_590_PREVIEW_SHORT', ['#LEN#' => $len]));
            }
            return false;
        }

        $fields['PREVIEW_TEXT'] = $text;
        return true;
    }

    /**
     * Источник текста анонса -> строка:
     * - если в $fields['PREVIEW_TEXT'] уже есть значение - берём его (с учётом формата массива).
     * - если в событии пусто, пробуем найти в $_REQUEST['PREVIEW_TEXT'] (админка Bitrix иногда отправляет туда).
     * - если это обновление (есть ID) и текст не пришёл из формы, берём текущий текст из базы (чтобы не затереть).
     */
    private static function getPreviewText(array &$fields): string
    {
        $value = self::extractPreviewValue($fields['PREVIEW_TEXT'] ?? '');
        if ($value === '' && isset($_REQUEST['PREVIEW_TEXT'])) {
            $value = self::extractPreviewValue($_REQUEST['PREVIEW_TEXT']);
        }

        if ($value === '' && !empty($fields['ID'])) {
            $row = \CIBlockElement::GetList(
                [],
                [
                    'ID' => (int)$fields['ID'],
                    'IBLOCK_ID' => (int)$fields['IBLOCK_ID'],
                ],
                false,
                false,
                ['ID', 'PREVIEW_TEXT']
            )->Fetch();
            if ($row) {
                $value = (string)$row['PREVIEW_TEXT'];
            }
        }

        return $value;
    }

    /**
     * Универсальный преобразователь массива → строку:
     * Bitrix может передать значение анонса в виде массива с ключами TEXT/ VALUE или в виде многоуровневых структур.
     * Метод рекурсивно достаёт первый строковый элемент.
     */
    private static function extractPreviewValue($value): string
    {
        if (is_array($value)) {
            if (array_key_exists('TEXT', $value)) {
                return (string)$value['TEXT'];
            }
            if (array_key_exists('VALUE', $value)) {
                return (string)$value['VALUE'];
            }
            $first = reset($value);
            return $first === false ? '' : self::extractPreviewValue($first);
        }

        return (string)$value;
    }

    /**
     * Возвращает значение свойства «Автор» по ID элемента.
     * Используем отдельный запрос, чтобы гарантированно получить актуальное значение (после всех обработчиков).
     * Если ID некорректный или свойство пустое, вернёт null.
     */
    private static function getAuthorId(int $elementId): ?int
    {
        if ($elementId <= 0) {
            return null;
        }

        $row = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, ['ID', 'PROPERTY_AUTHOR'])->Fetch();

        return $row ? (int)$row['PROPERTY_AUTHOR_VALUE'] : null;
    }
}


// [ex2-620] Перед отправкой письма USER_INFO подставляем текстовый «класс пользователя»
AddEventHandler('main', 'OnBeforeEventAdd', static function (&$event, &$lid, array &$fields) {
    // Нас интересует только событие USER_INFO, остальные письма не трогаем
    if ($event !== 'USER_INFO') {
        return true;
    }

    // Чтобы обратиться к пользователю, нужен его ID в полях события
    $userId = (int)($fields['USER_ID'] ?? 0);
    if ($userId <= 0) {
        return true;
    }

    // Получаем данные пользователя (в том числе пользовательское поле UF_USER_CLASS)
    $user = CUser::GetByID($userId)->Fetch();
    if (!$user) {
        return true;
    }

    // Если класс не задан - подставляем фразу «класс не указан»
    $classId = $user['UF_USER_CLASS'] ?? null;
    if (!$classId) {
        $fields['CLASS'] = Loc::getMessage('EX2_620_CLASS_EMPTY');
        return true;
    }

    // Если класс задан, из справочника (CUserFieldEnum) берём текстовое значение
    $enum = CUserFieldEnum::GetList([], ['ID' => $classId])->Fetch();
    $fields['CLASS'] = $enum ? (string)$enum['VALUE'] : Loc::getMessage('EX2_620_CLASS_EMPTY');

    // Временное логирование для проверки
    // if (function_exists('AddMessage2Log')) {
    //     AddMessage2Log('USER_INFO CLASS=' . $fields['CLASS'], 'ex2_620');
    // }

    return true;
});


// [ex2-630] При индексации рецензий добавляем к заголовку класс автора
AddEventHandler('search', 'BeforeIndex', static function (array $fields) {
    // Нас интересуют только элементы инфоблока «Рецензии»
    if ($fields['MODULE_ID'] !== 'iblock' || (int)$fields['PARAM2'] !== IBLOCK_REVIEWS_ID || !CModule::IncludeModule('iblock')) {
        return $fields;
    }

    // Достаём автора рецензии из свойства AUTHOR
    $author = CIBlockElement::GetProperty($fields['PARAM2'], $fields['ITEM_ID'], [], ['CODE' => 'AUTHOR'])->Fetch();
    $authorId = (int)($author['VALUE'] ?? 0);
    if ($authorId <= 0) {
        return $fields;
    }

    // Загружаем пользователя и проверяем, что у него заполнено поле UF_USER_CLASS
    $user = CUser::GetByID($authorId)->Fetch();
    if (!$user || empty($user['UF_USER_CLASS'])) {
        return $fields;
    }

    // Получаем текстовое значение класса из перечисления
    $enum = CUserFieldEnum::GetList([], ['ID' => $user['UF_USER_CLASS']])->Fetch();
    if ($enum && $enum['VALUE'] !== '') {
        // Добавляем класс автора в конец заголовка - он попадёт в поисковый индекс
        $fields['TITLE'] .= ' ' . Loc::getMessage('EX2_630_CLASS_TITLE', ['#CLASS#' => $enum['VALUE']]);
    }

    return $fields;
});