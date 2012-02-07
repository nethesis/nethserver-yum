<?php
namespace NethServer\Module\SoftwareManager;

/*
 * Copyright (C) 2011 Nethesis S.r.l.
 * 
 * This script is part of NethServer.
 * 
 * NethServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * NethServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with NethServer.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 */
class Progress extends \Nethgui\Controller\AbstractController
{

    public function prepareView(\Nethgui\View\ViewInterface $view)
    {
        parent::prepareView($view);

        $view['Close'] = $view->getModuleUrl('..');

        $process = $this->getPlatform()->getDetachedProcess('INSTALL');

        if ( ! $process instanceof \Nethgui\System\ProcessInterface) {
            $view['Percent'] = 'Not running';
            $view['Log'] = '';
            $view['InstallStatus'] = 'The installer is not running';
            return;
        }

        $state = $process->readExecutionState();

        if ($state === \Nethgui\System\ProcessInterface::STATE_RUNNING) {
            $chunk = $process->readOutput();
            $view['Percent'] = $this->calcPercent($chunk);
            $view['Log'] = $chunk;
            $view['InstallStatus'] = 'Installation in progress..';
            $view->getCommandList('BTN_CLOSE')->setLabel('Hide');
            $view->getCommandList()->reloadData(1333);
        } elseif ($state === \Nethgui\System\ProcessInterface::STATE_EXITED) {
            $view['Percent'] = '100';
            $view['Log'] = $process->readOutput() . "\nCompleted!";
            $view['InstallStatus'] = 'Installation completed';

            $view->getCommandList('BTN_CLOSE')->setLabel('Done');
            $view->getCommandList('BTN_REFRESH')->disable();
            $process->dispose();
        }
    }

    private function calcPercent($buffer)
    {
        $pos = strrpos($buffer, 'ITERATION ');

        $iteration = 0;

        sscanf(substr($buffer, $pos), 'ITERATION %D', $iteration);
        
        return $iteration > 0 ? ($iteration * 11.11) : FALSE;
    }

}
