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
class YumAdapter implements \Nethgui\Adapter\AdapterInterface, \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     *
     * @var \Nethgui\System\PlatformInterface
     */
    private $platform;

    /**
     *
     * @var \ArrayObject
     */
    private $data;

    public function __construct(\Nethgui\System\PlatformInterface $platform)
    {
        $this->platform = $platform;
    }

    /**
     *
     *
     * @return \Nethgui\System\PlatformInterface
     */
    protected function getPlatform()
    {
        return $this->platform;
    }

    public function isModified()
    {
        return FALSE;
    }

    public function get()
    {
        if ( ! isset($this->data)) {
            $this->lazyInitialization();
        }

        return $this->data;
    }

    public function offsetExists($offset)
    {
        if ( ! isset($this->data)) {
            $this->lazyInitialization();
        }

        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        if ( ! isset($this->data)) {
            $this->lazyInitialization();
        }

        return $this->data[$offset];
    }

    public function count()
    {
        if ( ! isset($this->data)) {
            $this->lazyInitialization();
        }

        return $this->data->count();
    }

    public function getIterator()
    {
        if ( ! isset($this->data)) {
            $this->lazyInitialization();
        }
        return $this->data->getIterator();
    }

    public function offsetSet($offset, $value)
    {
        throw new \LogicException(sprintf("%s: read-only adapter, %s() method is not allowed", __CLASS__, __METHOD__), 1351072309);
    }

    public function offsetUnset($offset)
    {
        throw new \LogicException(sprintf("%s: read-only adapter, %s() method is not allowed", __CLASS__, __METHOD__), 1351072310);
    }

    public function save()
    {
        throw new \LogicException(sprintf("%s: read-only adapter, %s() method is not allowed", __CLASS__, __METHOD__), 1351072307);
    }

    public function set($value)
    {
        throw new \LogicException(sprintf("%s: read-only adapter, %s() method is not allowed", __CLASS__, __METHOD__), 1351072308);
    }

    public function delete()
    {
        throw new \LogicException(sprintf("%s: read-only adapter, %s() method is not allowed", __CLASS__, __METHOD__), 1351072306);
    }

    private function lazyInitialization()
    {
        // XXX: read from User object.
        $lang = 'it';

        /*
         * NOTE on package groups; packages:
         *  - "optional" are not automatic but can be checked
         *  - "default" are, but can be unchecked in a gui tool
         *  - "mandatory" are always brought in (if group is selected),
         *    and not visible in the Package Selection dialog.
         *  - "conditional" are brought in if their requires package is
         *    installed
         *
         * See http://fedoraproject.org/wiki/How_to_use_and_edit_comps.xml_for_package_groups
         */

        $data = json_decode($this->getPlatform()->exec('/usr/bin/sudo /sbin/e-smith/pkginfo grouplist')->getOutput(), TRUE);

        $this->data = new \ArrayObject();

        // Flatten the data structure:
        foreach (array('installed', 'available') as $dState) {
            foreach ($data[$dState] as $dGroup) {
                $this->data[$dGroup['id']] = array(
                    'Id' => $dGroup['id'],
                    'Status' => $dState,
                    'Name' => isset($dGroup['translated_name'][$lang]) ? $dGroup['translated_name'][$lang] : $dGroup['name'],
                    'Description' => isset($dGroup['translated_description'][$lang]) ? $dGroup['translated_description'][$lang] : $dGroup['description'],
                    'OptionalPackages' => $dGroup['optional_packages'],
                    'DefaultPackages' => $dGroup['default_packages'],
                    'MandatoryPackages' => $dGroup['mandatory_packages'],
                    'ConditionalPackages' => $dGroup['conditional_packages'],
                );
            }
        }
    }

}