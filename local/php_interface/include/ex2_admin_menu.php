<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class Ex2AdminMenu
{
    public static function onBuildGlobalMenu(array &$globalMenu, array &$moduleMenu)
    {
        global $USER;

        // Обновляем меню только для авторизованных контент-редакторов.
        if (!is_object($USER) || !$USER->IsAuthorized() || !in_array((int)CONTENT_EDITORS_GROUP_ID, $USER->GetUserGroupArray(), true)) {
            return;
        }

        // Если раздел «Контент» недоступен, удаляем всё меню (альтернатив нет).
        if (empty($globalMenu['global_menu_content'])) {
            $globalMenu = [];
            $moduleMenu = [];
            return;
        }

        // Короткая функция для получения фраз из lang-файла.
        $l = static fn(string $code): string => Loc::getMessage($code);

        // Оставляем стандартный раздел «Контент» и добавляем свой «Быстрый доступ».
        $globalMenu = [
            'global_menu_content' => $globalMenu['global_menu_content'],
            'global_menu_quick' => [
                'menu_id' => 'quick',
                'items_id' => 'global_menu_quick',
                'text' => $l('EX2_190_QUICK_TITLE'),
                'title' => $l('EX2_190_QUICK_TITLE'),
                'sort' => 50,
                'items' => [
                    [
                        'text' => $l('EX2_190_LINK_1'),
                        'title' => $l('EX2_190_LINK_1'),
                        'url' => 'https://test1',
                        'items_id' => 'global_menu_quick_link_1',
                    ],
                    [
                        'text' => $l('EX2_190_LINK_2'),
                        'title' => $l('EX2_190_LINK_2'),
                        'url' => 'https://test2',
                        'items_id' => 'global_menu_quick_link_2',
                    ],
                ],
            ],
        ];

        // Очищаем список ссылок модулей, кроме тех, что принадлежат разделу «Контент».
        foreach ($moduleMenu as $index => $menuItem) {
            if (($menuItem['parent_menu'] ?? '') !== 'global_menu_content') {
                unset($moduleMenu[$index]);
            }
        }
    }
}

