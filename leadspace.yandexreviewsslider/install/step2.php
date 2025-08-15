<?php
use Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid()) {
    return;
}
// Проверяем была ли выброшена ошибка при установке, если да, то записываем её в переменную $ex
if ($ex = $APPLICATION->GetException()) {
    // Выводим ошибку
    echo CAdminMessage::ShowMessage([
        'TYPE' => 'ERROR',
        'MESSAGE' => Loc::getMessage('MOD_INST_ERR'), // (MOD_INST_ERR - системная языковая переменная)
        'DETAILS' => $ex->GetString(),
        'HTML' => true,
    ]);
} else { // Если ошибки не было, то выводим сообщение об установке модуля (MOD_INST_OK - системная языковая переменная)
    echo CAdminMessage::ShowNote(Loc::getMessage('MOD_INST_OK'));
}
?>
<!-- Выводим кнопку для перехода на страницу модулей (мы и так находимся на этой странице но с выведенным файлом, значит просто получаем текущую директорию для перенаправления -->
<form action="<?php echo $APPLICATION->GetCurPage(); ?>">
    <!-- В форме обязательно должно быть поле lang, с айди языка, чтобы язык не сбросился -->
    <input type="hidden" name="lang" value="<?php echo LANGUAGE_ID; ?>">
    <!-- MOD_BACK - системная языковая переменная для возврата -->
    <input type="submit" name="" value="<?php echo Loc::getMessage('MOD_BACK'); ?>">
</form>
