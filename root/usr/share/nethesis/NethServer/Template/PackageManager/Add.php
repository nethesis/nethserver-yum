<?php

echo $view->header('Id')->setAttribute('template', $T('Add ${0}'));
echo $view->buttonList()
    ->insert($view->button('Add', $view::BUTTON_SUBMIT))
    ->insert($view->button('Cancel', $view::BUTTON_CANCEL));