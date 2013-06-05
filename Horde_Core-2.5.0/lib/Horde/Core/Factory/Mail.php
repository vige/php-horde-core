<?php
/**
 * Copyright 2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @link      http://pear.horde.org/index.php?package=Core
 * @package   Core
 */

/**
 * A Horde_Injector based factory for creating a Horde_Mail_Transport object.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @link      http://pear.horde.org/index.php?package=Core
 * @since     2.5.0
 * @package   Core
 */
class Horde_Core_Factory_Mail extends Horde_Core_Factory_Base
{
    /**
     * Return the Horde_Mail instance.
     *
     * @param array $config  If null, use Horde defaults. Otherwise, an
     *                       array with two keys:
     * <pre>
     *   - params: (array) Configuration parameters.
     *   - transport: (string) Transport driver.
     * </pre>
     *
     * @return Horde_Mail_Transport  The singleton instance.
     * @throws Horde_Exception
     */
    public function create($config = null)
    {
        if (is_null($config)) {
            list($transport, $params) = $this->getConfig();
        } else {
            $transport = $config['transport'];
            $params = $config['params'];
        }

        if ((strcasecmp($transport, 'smtp') !== 0) || empty($params['auth'])) {
            unset($params['username'], $params['password']);
        }

        $class = $this->_getDriverName($transport, 'Horde_Mail_Transport');
        $ob = new $class($params);

        if (!empty($params['sendmail_eol']) &&
            (strcasecmp($transport, 'sendmail') == 0)) {
            $ob->sep = $params['sendmail_eol'];
        }

        return $ob;
    }

    /**
     * Return the mailer configuration.
     *
     * @return array  Two-element array: transport driver (string) and
     *                configuration parameters (array).
     */
    public function getConfig()
    {
        global $conf, $registry;

        $transport = isset($conf['mailer']['type'])
            ? Horde_String::lower($conf['mailer']['type'])
            : 'null';
        $params = isset($conf['mailer']['params'])
            ? $conf['mailer']['params']
            : array();

        /* Add username/password options now, regardless of current value of
         * 'auth'. Will remove in create() if final config doesn't require
         * authentication. */
        if (strcasecmp($transport, 'smtp') === 0) {
            if (!isset($params['username'])) {
                $params['username'] = $registry->getAuth();
            }
            if (!isset($params['password'])) {
                $params['password'] = $registry->getAuthCredential('password');
            }
        }

        return array($transport, $params);
    }

}
