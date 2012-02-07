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
 * Nethesis Register Client
 * 
 */
class SoapClient extends \SoapClient implements \Nethgui\Log\LogConsumerInterface
{

    /**
     *
     * @var \Nethgui\Log\LogConsumerInterface
     */
    private $log;

    public function __construct()
    {
        $options = array(
            'exceptions' => true,
            'trace' => 0,
            'cache_wsdl' => WSDL_CACHE_NONE,
        );

        parent::__construct("http://c4.nethesis.it/soap/register-c4.wsdl", $options);
    }

    public function getPackageList($systemId)
    {
        static $packages;

        if ( ! isset($packages)) {
            try {
                $response = parent::__soapCall('getPackages', array($systemId));
                $packages = $response->pkglist;
            } catch (\SoapFault $ex) {
                $this->getLog()->exception($ex);
                $packages = array();
            }
        }

        return $packages;
    }

    public function getPackageNamesMap($systemId)
    {
        $map = array();

        try {
            foreach ($this->getPackageList($systemId) as $pkg) {
                $map[$pkg->package] = $pkg->name;
            }
        } catch (\SoapFault $ex) {
            $this->getLog()->exception($ex);
        }

        return $map;
    }

    public function registerServer($systemId)
    {
        return parent::__soapCall(__FUNCTION__, array($systemId));
    }

    public function isLicenseKeyFree($systemId)
    {
        return parent::__soapCall(__FUNCTION__, array($systemId));
    }

    public function getLog()
    {
        if ( ! isset($this->log)) {
            return new \Nethgui\Log\Nullog();
        }

        return $this->log;
    }

    public function setLog(\Nethgui\Log\LogInterface $log)
    {
        $this->log = $log;
    }

}
