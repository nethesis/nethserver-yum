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
 *
 */
class Register extends \Nethgui\Controller\AbstractController
{

    /**
     *
     * @var \NethServer\Module\SoftwareManager\SoapClient
     */
    private $client;

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

        $this->declareParameter('SystemID', "/^[\w-]+$/", //TODO: improve regex
                                array('configuration', 'nethupdate', 'SystemID'));
    }

    public function validate(\Nethgui\Controller\ValidationReportInterface $report)
    {
        parent::validate($report);

        if ($this->getRequest()->isMutation()
            && $this->getRequest()->hasParameter('SystemID')) {
            if ( ! $report->hasValidationErrors()) {
                try {
                    if ($this->client->isLicenseKeyFree($this->parameters['SystemID']) === FALSE)
                        $report->addValidationErrorMessage($this, 'SystemID', "License Key already registered");
                } catch (\SoapFault $ex) {
                    $report->addValidationErrorMessage($this, 'SystemID', $ex->getMessage());
                }
            }
        }
    }

    protected function onParametersSaved($changedParameters)
    {
        if (in_array('SystemID', $changedParameters)) {
            try {
                $this->client->registerServer($this->parameters['SystemID']);
                $this->getPlatform()->signalEvent('nethupdate-install-systemid@post-process');
            } catch (\Exception $ex) {
                $this->getLog()->exception($ex);
            }
        }
    }

    public function nextPath()
    {
        return 'Install';
    }

}

