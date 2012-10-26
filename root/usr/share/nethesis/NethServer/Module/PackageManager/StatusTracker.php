<?php
namespace NethServer\Module\PackageManager;

/*
 * Copyright (C) 2012 Nethesis S.r.l.
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
 * TODO: add component description here
 *
 * @author Davide Principi <davide.principi@nethesis.it>
 * @since 1.0
 */
class StatusTracker extends \Nethgui\Controller\AbstractController
{
    private $done = FALSE;

    public function prepareView(\Nethgui\View\ViewInterface $view)
    {
        parent::prepareView($view);

        /* @var $process \Nethgui\System\ProcessInterface */

        if ($this->getRequest()->isValidated()) {
            $process = $this->getPlatform()->getDetachedProcess('PackageManager');
            if ($process === FALSE) {
                $this->done = TRUE;
            } elseif (is_object($process)) {
                if ($process->readExecutionState() === \Nethgui\System\ProcessInterface::STATE_EXITED) {
                    $this->done = TRUE;
                    if ( ! $process->isDisposed()) {
                        $process->dispose();
                    }
                } else {
                    $this->getLog()->notice(sprintf('%s: PackageManager process `%s` is still running..', __CLASS__, $process->getIdentifier()));
                    $view->getCommandList()->reloadData(2000);
                }
            }
        }
    }

    public function nextPath()
    {
        return $this->done === TRUE ? 'read' : FALSE;
    }
    

}