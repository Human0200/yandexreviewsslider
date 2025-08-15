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
        'MESSAGE' => Loc::getMessage('MOD_UNINST_ERR'), // MOD_UNINST_ERR - системная языковая переменная
        'DETAILS' => $ex->GetString(),
        'HTML' => true,
    ]);
} else { // Если ошибки не было, то выводим сообщение об установке модуля (MOD_UNINST_OK - системная языковая переменная)
    echo CAdminMessage::ShowNote(Loc::getMessage('MOD_UNINST_OK'));
}
?>

<form action="<?php echo $APPLICATION->GetCurPage(); ?>">

    <input type="hidden" name="lang" value="<?php echo LANGUAGE_ID; ?>">

    <input type="submit" name="" value="<?php echo Loc::getMessage('MOD_BACK'); ?>">
</form>
