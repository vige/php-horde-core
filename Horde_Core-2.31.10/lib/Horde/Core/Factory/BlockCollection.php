<?php
/**
 * A Horde_Injector:: based factory for creating Horde_Core_Block_Collection
 * objects.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * A Horde_Injector:: based factory for creating Horde_Core_Block_Collection
 * objects.
 *
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Core_Factory_BlockCollection extends Horde_Core_Factory_Base
{
    /**
     * Instances.
     *
     * @var array
     */
    private $_instances = array();

    /**
     * Return the Block_Collection instance.
     *
     * @param array $apps     The applications whose blocks to list.
     * @param string $layout  The preference name for the layout
     *                        configuration.
     *
     * @return Horde_Core_Block_Collection  The singleton instance.
     * @throws Horde_Exception
     */
    public function create(array $apps = array(), $layout = 'portal_layout')
    {
        global $registry;

        $apps = empty($apps)
            ? $registry->listApps()
            : array_intersect($registry->listApps(), $apps);
        sort($apps);
        $sig = hash('md5', json_encode(array($apps, $layout)));

        if (!isset($this->_instances[$sig])) {
            $this->_instances[$sig] =
                new Horde_Core_Block_Collection($apps, $layout);
        }

        return $this->_instances[$sig];
    }

}
