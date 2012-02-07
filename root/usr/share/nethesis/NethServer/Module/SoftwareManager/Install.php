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
class Install extends \Nethgui\Controller\AbstractController
{

    /**
     *
     * @var SoapClient
     */
    private $client = NULL;

    /**
     *
     * @var array
     */
    private $installed_packages;

    /**
     *
     * @var array
     */
    private $availablePackages;

    /**
     *
     * @param string $identifier
     * @param SoapClient $soapClient Register client object
     */
    public function __construct($identifier = NULL, SoapClient $soapClient = NULL)
    {
        parent::__construct($identifier);
        $this->client = $soapClient;
    }

    public function initialize()
    {
        parent::initialize();
        if (is_null($this->client)) {
            $this->client = new SoapClient();
            $this->client->setLog($this->getLog());
        }

        $this->parameters['SystemID'] = $this->getPlatform()
            ->getDatabase('configuration')
            ->getProp('nethupdate', 'SystemID');

        if ($this->parameters['SystemID']) {
            // build datasource
            $this->availablePackages = array_keys($this->client->getPackageNamesMap($this->parameters['SystemID']));

            // Selected packages:
            $this->declareParameter('packages', $this->createValidator()->collectionValidator($this->createValidator()->memberOf($this->availablePackages)));

            // from RPM queries:
            if (empty($this->availablePackages)) {
                $this->installed_packages = array();
            } else {
                $this->installed_packages = array_filter($this->getPlatform()
                        ->exec('/bin/rpm -qa --queryformat "%{NAME}\n" ${@}', $this->availablePackages)
                        ->getOutputArray()
                );
            }
        } else {
            $this->availablePackages = array();
            $this->installed_packages = array();
        }
    }

    public function bind(\Nethgui\Controller\RequestInterface $request)
    {
        parent::bind($request);

        if ( ! $request->isMutation()) {
            $this->parameters['packages'] = $this->installed_packages;
        }
    }

    public function process()
    {
        if ($this->getRequest()->isMutation()) {
            $pkgs_to_install = array_diff($this->parameters['packages'], $this->installed_packages);

            // signal event yum
            $arguments = array('package');
            $arguments = array_merge($arguments, $pkgs_to_install);
            // XXX REMOVE (?) array_unshift($pkgs_to_install, 'package');
            //$this->getPlatform()->signalEvent('yum-install', $arguments);
            $process = $this->getPlatform()->exec('/bin/nethgui-job.sh ${@}', $arguments, TRUE);
            //$process = $this->getPlatform()->exec('cat /etc/system-release', $arguments, TRUE);
            $process->setIdentifier('INSTALL');
        }
    }

    public function prepareView(\Nethgui\View\ViewInterface $view)
    {
        parent::prepareView($view);

        if ( ! $this->parameters['SystemID']) {
            $view['Register'] = $view->getModuleUrl('../Register');
            $view->getCommandList('BTN_REGISTER')->enable();
        } else {
            $view['Register'] = '';
            $view->getCommandList('BTN_REGISTER')->disable();
        }
       
        $view['InstalledPackages'] = $this->installed_packages;
        $view['packagesDatasource'] = \Nethgui\Renderer\AbstractRenderer::hashToDatasource($this->client->getPackageNamesMap($this->parameters['SystemID']));        
    }

    public function nextPath()
    {
        return 'Progress';
    }

}

