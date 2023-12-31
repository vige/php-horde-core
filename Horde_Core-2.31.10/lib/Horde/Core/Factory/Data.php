<?php
/**
 * A Horde_Injector:: based Horde_Data:: factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * A Horde_Injector:: based Horde_Data:: factory.
 *
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Core_Factory_Data extends Horde_Core_Factory_Base
{
    /**
     * Return the Horde_Data:: instance.
     *
     * @param string $driver  The driver.
     * @param string $params  Driver parameters.
     *
     * @return Horde_Data_Driver  The instance.
     * @throws Horde_Data_Exception
     */
    public function create($driver, array $params = array())
    {
        $class = $this->_getDriverName($driver, 'Horde_Data');
        $params['browser'] = $this->_injector->getInstance('Horde_Browser');
        $params['vars'] = $this->_injector->getInstance('Horde_Variables');
        $params['http'] = $this->_injector
            ->getInstance('Horde_Core_Factory_HttpClient')
            ->create(array('request.verifyPeer' => false));

        return new $class($this->_injector->getInstance('Horde_Core_Data_Storage'), $params);
    }

}
