<?php
/**
 * A Horde_Injector:: based factory for creating Horde_Ldap objects.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * A Horde_Injector:: based factory for creating Horde_Ldap objects.
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
class Horde_Core_Factory_Ldap extends Horde_Core_Factory_Base
{
    /**
     * Instances.
     *
     * @var array
     */
    private $_instances = array();

    /**
     * Return the LDAP instance.
     *
     * @param string $app            The application.
     * @param string|array $backend  The backend, see Horde::getDriverConfig().
     *                               If this is an array, this is used as the
     *                               configuration array.
     *
     * @return Horde_Ldap  The singleton instance.
     * @throws Horde_Exception
     * @throws Horde_Ldap_Exception
     */
    public function create($app = 'horde', $backend = null)
    {
        $sig = hash('md5', serialize(array($app, $backend)));

        if (isset($this->_instances[$sig])) {
            return $this->_instances[$sig];
        }

        $pushed = ($app == 'horde')
            ? false
            : $GLOBALS['registry']->pushApp($app);

        $config = is_array($backend)
            ? $backend
            : $this->getConfig($backend);

        /* BC check for old configuration without 'user' setting, so that
         * administrators can still log in through LDAP and update the
         * configuration. */
        if (!isset($config['user'])) {
            $config['user'] = $config;
        }
        $config['cache'] = $this->_injector->getInstance('Horde_Cache');
        $config['cache_root_dse'] = true;

        $e = null;
        try {
            $this->_instances[$sig] = new Horde_Ldap($config);
            try {
                if (isset($config['bindas']) &&
                    $config['bindas'] == 'user' &&
                    $GLOBALS['registry']->getAuth()) {
                    $this->_instances[$sig]->bind(
                        $this->_instances[$sig]->findUserDN($GLOBALS['registry']->getAuth()),
                        $GLOBALS['registry']->getAuthCredential('password'));
                }
            } catch (Horde_Exception_NotFound $notfound) {
            }
        } catch (Horde_Exception $e) {
        }

        if ($pushed) {
            $GLOBALS['registry']->popApp();
        }

        if ($e) {
            throw $e;
        }

        return $this->_instances[$sig];
    }

    /**
     */
    public function getConfig($backend)
    {
        return Horde::getDriverConfig($backend, 'ldap');
    }

}
