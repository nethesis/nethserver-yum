<?php
echo $view->header('SystemID')->setAttribute('template', 'Addons installation (${0})');

if (FALSE) {

    if ( ! empty($view['InstalledPackages'])) {
        $installedList = $view->elementList();

        foreach ($view['InstalledPackages'] as $packageInfo) {
            $installedList->insert($view->literal($packageInfo));
        }
    } else {
        $installedList = $view->literal($view->translate('No addons installed'));
    }



    $installedTab = $view->panel()->setAttribute('name', 'Installed')->insert($installedList);
    $availableTab = $view->panel()->setAttribute('name', 'Available')
        ->insert($view->selector('packages', $view::SELECTOR_MULTIPLE))
        ->insert($view->elementList()->setAttribute('class', 'buttonList')
        ->insert($view->button('Install', $view::BUTTON_SUBMIT)));

    echo $view->tabs()->insert($installedTab)
        ->insert($availableTab);
} else {

    //
    // Alternative version
    //


    if (empty($view['InstalledPackages'])) {
        $installedList = $view->literal($view->translate('No addons installed'));
    } else {
        $installedList = $view->elementList();
        foreach ($view['InstalledPackages'] as $packageInfo) {
            $installedList->insert($view->literal($packageInfo));
        }
    }

    echo $view->columns()
        ->insert($view->panel()->insert($view->selector('packages', $view::SELECTOR_MULTIPLE)))
        ->insert($view->fieldset()->setAttribute('template', 'Installed')
            ->insert($installedList));

    echo $view->buttonList()
        ->insert($view->button('Install', $view::BUTTON_SUBMIT))
        ->insert($view->button('Register', $view::BUTTON_LINK)->setAttribute('receiver', 'BTN_REGISTER'));
    ;
}