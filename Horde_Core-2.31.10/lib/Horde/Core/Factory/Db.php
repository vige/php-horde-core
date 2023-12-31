<?php
/**
 * A Horde_Injector:: based factory for creating Horde_Db_Adapter objects.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * A Horde_Injector:: based factory for creating Horde_Db_Adapter objects.
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
class Horde_Core_Factory_Db extends Horde_Core_Factory_Base
{
    /**
     * Instances.
     *
     * @var array
     */
    private $_instances = array();

    /**
     * Returns the DB instance.
     *
     * @param string $app            The application.
     * @param string|array $backend  The backend, see Horde::getDriverConfig().
     *                               If this is an array, this is used as the
     *                               configuration array.
     *
     * @return Horde_Db_Adapter  The singleton instance.
     * @throws Horde_Exception
     * @throws Horde_Db_Exception
     */
    public function create($app = 'horde', $backend = null)
    {
        global $registry;

        $pushed = ($app == 'horde')
            ? false
            : $registry->pushApp($app);

        $config = is_array($backend)
            ? $backend
            : $this->getConfig($backend);

        /* Prevent DSN from getting polluted (this only applies to non-custom
         * auth type connections. All other custom sql configurations MUST be
         * cleansed prior to passing to the factory (at least until Horde 5).
         * @todo Fix with Horde 6. */
        if (!is_array($backend) && ($backend == 'auth')) {
            unset(
                $config['count_bad_logins'],
                $config['cyradmin'],
                $config['cyrhost'],
                $config['cyrpass'],
                $config['cyrport'],
                $config['domain_field'],
                $config['driverconfig'],
                $config['encryption'],
                $config['folders'],
                $config['hidden_accounts'],
                $config['login_block'],
                $config['login_block_count'],
                $config['login_block_time'],
                $config['password_field'],
                $config['query_auth'],
                $config['query_add'],
                $config['query_getpw'],
                $config['query_update'],
                $config['query_resetpassword'],
                $config['query_remove'],
                $config['query_list'],
                $config['query_exists'],
                $config['show_encryption'],
                $config['table'],
                $config['userhierarchy'],
                $config['username_field']
            );
        }
        unset($config['umask']);

        $e = null;

        ksort($config);
        $sig = hash('md5', serialize($config));

        /* Determine if we are using the base SQL config. */
        if (isset($config['driverconfig']) &&
            ($config['driverconfig'] == 'horde')) {
            $this->_instances[$sig] = $this->create();
        } elseif (!isset($this->_instances[$sig])) {
            try {
                $this->_createDb($config, $sig);
            } catch (Horde_Exception $e) {}
        }

        if ($pushed) {
            $registry->popApp();
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
        return Horde::getDriverConfig($backend, 'sql');
    }

    /**
     */
    public function createDb($config)
    {
        return $this->_createDb($config);
    }

    /**
     * @param string $sig     Save instance under this signature key.
     * @param boolean $cache  Add default cache to driver?
     */
    protected function _createDb($config, $sig = null, $cache = true)
    {
        unset($config['driverconfig']);
        $logqueries = !empty($config['logqueries']);
        unset($config['logqueries']);

        // Split read?
        if (!empty($config['splitread'])) {
            $read_config = $config['read'];
            unset($config['read'], $config['splitread']);
            $ob = new Horde_Db_Adapter_SplitRead(
                $this->_createDb(array_merge($config, $read_config), null, false),
                $this->_createDb($config, null, false)
            );

            /* Don't attach logger to base split read object. */
            $config['logger'] = true;
        } else {
            if (isset($config['adapter'])) {
                $class = $this->_getDriverName($config['adapter'], 'Horde_Db_Adapter');
                unset($config['adapter']);
            } elseif (empty($config['phptype'])) {
                throw new Horde_Exception('The database configuration is missing.');
            } else {
                switch ($config['phptype']) {
                case 'mysqli':
                    $class = 'Horde_Db_Adapter_Mysqli';
                    break;

                case 'mysql':
                    $class = extension_loaded('pdo_mysql')
                        ? 'Horde_Db_Adapter_Pdo_Mysql'
                        : 'Horde_Db_Adapter_Mysql';
                    break;

                case 'oci8':
                    $class = 'Horde_Db_Adapter_Oci8';
                    break;

                default:
                    $class = 'Horde_Db_Adapter_Pdo_' . Horde_String::ucfirst($config['phptype']);
                    break;
                }
            }

            if (!empty($config['hostspec'])) {
                $config['host'] = $config['hostspec'];
                unset($config['hostspec']);
            }

            $ob = new $class($config);
        }

        if ($sig) {
            $this->_instances[$sig] = $ob;
        }

        if ($cache && !isset($config['cache'])) {
            /* Need to add cache ob here, after it is stored as an instance,
             * or else we enter infinite bootstrapping loop. Bug #13439 */
            $ob->setCache($this->_injector->getInstance('Horde_Cache'));
        }

        /* Bug #13463: setting logger before cache causes intermittent issues
         * with DB object during session shutdown. */
        if (!isset($config['logger'])) {
            $ob->setLogger(
                $this->_injector->getInstance('Horde_Log_Logger'),
                $logqueries
            );
        }

        return $ob;
    }

}
