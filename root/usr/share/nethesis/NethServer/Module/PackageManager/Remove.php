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

use Nethgui\System\PlatformInterface as Validate;
use Nethgui\Controller\Table\Modify as Table;

/**
 * TODO: add component description here
 *
 * @author Davide Principi <davide.principi@nethesis.it>
 * @since 1.0
 */
class Remove extends \Nethgui\Controller\Table\RowAbstractAction
{

    public function initialize()
    {
        parent::initialize();

        $this->setSchema(array(
            array('Id', FALSE, Table::KEY),
        ));
    }

    public function bind(\Nethgui\Controller\RequestInterface $request)
    {
        $this->getAdapter()->setKeyValue(\Nethgui\array_head($request->getPath()));
        parent::bind($request);
    }

}