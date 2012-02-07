<?php
$view->requireFlag($view::INSET_DIALOG);

echo $view->form()->setAttribute('method', 'get')
    ->insert($view->header('InstallStatus'))
    ->insert($view->progressBar('Percent'))
    ->insert($view->fieldset('InstallStatus', $view::FIELDSET_EXPANDABLE)->insert($view->console('Log')))
    ->insert($view->buttonList()
        ->insert($view->button('Close', $view::BUTTON_CANCEL)->setAttribute('receiver', 'BTN_CLOSE'))
    )
;
