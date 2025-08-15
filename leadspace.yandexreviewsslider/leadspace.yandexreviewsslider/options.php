<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;

CJSCore::Init(['jquery']);
Extension::load(
    [
        'ui.buttons',
        'ui.dialogs.messagebox',
    ]
);

$module_id = 'leadspace.yandexreviewsslider';

Loader::requireModule($module_id);

$request = Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();

$aTabs = [
    [
        'DIV' => 'edit1', // Идентификатор вкладки (используется для javascript)
        'TAB' => Loc::getMessage('LEADSPACE_REVIEWS_TAB_SETTINGS'), // Название вкладки
        'TITLE' => Loc::getMessage('LEADSPACE_REVIEWS_TAB_TITLE'),    // Заголовок и всплывающее сообщение вкладки
        // Массив настроек опций для вкладки
        'OPTIONS' => [
            [
                'company_id',
                Loc::getMessage('LEADSPACE_REVIEWS_COMPANY_TITLE'),
                '',
                [
                    'text',
                    50, // Ширина
                ],
            ],

            [
                'hide_logo', // Имя поля для хранения в бд
                Loc::getMessage('LEADSPACE_REVIEWS_HIDE_LOGO_TITLE'),
                '',
                [
                    'checkbox',
                    [
                        'var1' => 'var1',
                    ],
                ],
            ],
            [
                'hide_negative', // Имя поля для хранения в бд
                Loc::getMessage('LEADSPACE_REVIEWS_NOT_NEGATIVE_REVIEWS'),
                '',
                [
                    'checkbox',
                    [
                        'var2' => 'var2',
                    ],
                ],
            ],
        ],
    ],
];

// Если пришел запрос на обновление и сессия активна, то обходим массив созданных полей
if ($request->isPost() && $request['Update'] && check_bitrix_sessid()) {
    foreach ($aTabs as $aTab) {
        foreach ($aTab['OPTIONS'] as $arOption) {
            // Существуют строки с подстветкой, которые не нужно обрабатывать, поэтому пропускаем их
            if (!is_array($arOption)) {
                continue;
            }
            if ($arOption['note']) {
                continue;
            }

            // Имя настройки
            $optionName = $arOption[0];
            // Значение настройки, которое пришло в запросе
            $optionValue = $request->getPost($optionName);
            // Установка значения по айди модуля и имени настройки
            // Хранить можем только текст, значит если приходит массив, то разбиваем его через запятую
            Option::set($module_id, $optionName, is_array($optionValue) ? implode(',', $optionValue) : $optionValue);
        }
    }
}

// Создаем объект класса AdminTabControl
$tabControl = new CAdminTabControl('tabControl', $aTabs);

// Начинаем формирование формы
$tabControl->Begin();

?>
<form method="post" name="leadspace_reviews_settings" action="<?php echo $APPLICATION->GetCurPage(); ?>?mid=<?php echo htmlspecialcharsbx($request['mid']); ?>&lang=<?php echo $request['lang']; ?>">
    <?php
    echo bitrix_sessid_post();
foreach ($aTabs as $aTab) {
    if ($aTab['OPTIONS']) {
        // Указываем начало формирования первой вкладки
        $tabControl->BeginNextTab();
        // Отрисовываем поля по заданному массиву (автоматически подставляет значения, если они были заданы)
        __AdmSettingsDrawList($module_id, $aTab['OPTIONS']);
    }
}

$tabControl->Buttons();
?>
    <input type="submit" name="Update" value="<?php echo GetMessage('MAIN_SAVE'); ?>">
    <input type="reset" name="reset" value="<?php echo GetMessage('MAIN_RESET'); ?>">
</form>

<?php

// Заканчиваем формирование формы
$tabControl->End();

?>
<div id="importButtonContainer"></div>
<script>
BX.ready(function() {
    const importButton = new BX.UI.Button({
        text: "<?php echo Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_ZAPUSTITQ_IMPORT_OTZ'); ?>",
        id: 'importButton',
        color: BX.UI.Button.Color.PRIMARY,
        onclick: function(btn, event) {
            btn.setWaiting(true);

            BX.ajax.runAction('leadspace:yandexreviewsslider.AdminActions.importReviews')
                .then(response => {
                    BX.UI.Dialogs.MessageBox.alert(
                        BX.util.htmlspecialchars(response.data.message)
                    );
                    btn.setWaiting(false);
                })
                .catch(err => {
                    const messages = (err.errors || []).map(e => BX.util.htmlspecialchars(e.message));
                    const combined = messages.join('<br>');

                    if (combined) {
                        BX.UI.Dialogs.MessageBox.alert(combined);
                    }

                    btn.setWaiting(false);
                });
        }
    });

    importButton.renderTo(document.getElementById('importButtonContainer'));
});
</script>
