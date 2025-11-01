<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
global $APPLICATION;

// Замена #count# в ex2_meta
$meta = $APPLICATION->GetProperty('ex2_meta');
if ($meta !== null && strpos($meta, '#count#') !== false) {
    $APPLICATION->SetPageProperty('ex2_meta', str_replace('#count#', (int)$arResult['REVIEWS_CNT'], $meta));
}

// Дополнительный HTML-блок до строки поиска
if (!empty($arResult['FIRST_REVIEW'])) {
    $blockHtml = sprintf(
    '<div id="filial-special" class="information-block">
	<div class="top"></div>
	<div class="information-block-inner">
		<h3>%s</h3>
		<div class="special-product">
			<div class="special-product-title">%s</div>
		</div>
	</div>
	<div class="bottom"></div>
</div>',
        GetMessage('EX2_ADDITIONAL_TITLE'),
        htmlspecialcharsbx($arResult['FIRST_REVIEW'])
    );

    // Добавляем блок в область до строки поиска
    $APPLICATION->AddViewContent('first_review', $blockHtml);
}


