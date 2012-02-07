<?php
echo $view->header()->setAttribute('template', 'Register the license key');

echo $view->textInput('SystemID');

echo $view->buttonList()
    ->insert($view->button('Help', $view::BUTTON_HELP))
    ->insert($view->button('Register', $view::BUTTON_SUBMIT));
