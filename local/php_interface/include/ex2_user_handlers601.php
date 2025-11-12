<?php

/** [ex2-601] Отслеживаем UF_AUTHOR_STATUS и шлём EX2_AUTHOR_INFO. */
class Ex2UserHandlers
{
    /** Буфер: ID пользователя => ['VALUE','TEXT'] старого статуса. */
    protected static $buffer = [];

    /** OnBeforeUserUpdate: запоминаем текущий статус и приводим новое значение к ID. */
    public static function onBeforeUserUpdate(array &$fields)
    {
        $id = (int)($fields['ID'] ?? 0);
        if ($id <= 0) {
            return true;
        }

        self::$buffer[$id] = self::getStatus($id);

        if (isset($fields['UF_AUTHOR_STATUS'])) {
            $fields['UF_AUTHOR_STATUS'] = self::normalize($fields['UF_AUTHOR_STATUS'])['VALUE'];
        }

        return true;
    }

    /** OnAfterUserUpdate: сравниваем и, если изменился статус, отправляем письмо. */
    public static function onAfterUserUpdate(array &$fields)
    {
        $id = (int)($fields['ID'] ?? 0);
        if ($id <= 0 || ($fields['RESULT'] ?? true) === false) {
            unset(self::$buffer[$id]);
            return;
        }

        $old = self::$buffer[$id] ?? ['VALUE' => null, 'TEXT' => ''];
        unset(self::$buffer[$id]);

        $new = isset($fields['UF_AUTHOR_STATUS'])
            ? self::normalize($fields['UF_AUTHOR_STATUS'])
            : self::getStatus($id);

        if ((int)$old['VALUE'] !== (int)$new['VALUE']) {
            CEvent::Send('EX2_AUTHOR_INFO', SITE_ID, [
                'USER_ID' => $id,
                'OLD_UF_STATUS' => $old['TEXT'],
                'NEW_UF_STATUS' => $new['TEXT'],
            ]);
        }
    }

    /** Берём значение UF_AUTHOR_STATUS из базы. */
    protected static function getStatus(int $userId): array
    {
        $row = CUser::GetByID($userId)->Fetch();
        return $row ? self::normalize($row['UF_AUTHOR_STATUS']) : ['VALUE' => null, 'TEXT' => ''];
    }

    /**
     * Нормализуем любое значение поля: приводим к ['VALUE','TEXT'].
     * UF_AUTHOR_STATUS хранит ID элемента ИБ, поэтому подгружаем его название (кешируем).
     */
    protected static function normalize($value): array
    {
        if (is_array($value)) {
            $value = $value['VALUE'] ?? reset($value);
            if (is_array($value)) {
                $value = $value['VALUE'] ?? reset($value);
            }
        }

        $id = (int)$value;
        if ($id <= 0) {
            return ['VALUE' => null, 'TEXT' => ''];
        }

        static $cache = [];
        if (!isset($cache[$id])) {
            $cache[$id] = ['VALUE' => $id, 'TEXT' => ''];
            if (CModule::IncludeModule('iblock')) {
                if ($row = CIBlockElement::GetList([], ['ID' => $id], false, false, ['ID', 'NAME'])->Fetch()) {
                    $cache[$id] = ['VALUE' => (int)$row['ID'], 'TEXT' => (string)$row['NAME']];
                }
            }
        }

        return $cache[$id];
    }
}


