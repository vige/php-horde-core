<?php
/**
 * A Horde_Injector:: based Horde_Kolab_Storage:: factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * A Horde_Injector:: based Horde_Kolab_Storage:: factory.
 *
 * Copyright 2009-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Core_Factory_KolabStorage extends Horde_Core_Factory_Base
{
    /**
     */
    public function __construct(Horde_Injector $injector)
    {
        parent::__construct($injector);
        $this->_setup();
    }

    /**
     * Setup the machinery to create Horde_Kolab_Session objects.
     *
     * @return NULL
     */
    private function _setup()
    {
        $this->_setupConfiguration();
    }

    /**
     * Provide configuration settings for Horde_Kolab_Session.
     *
     * @return NULL
     */
    private function _setupConfiguration()
    {
        $configuration = array();

        //@todo: Update configuration parameters
        if (!empty($GLOBALS['conf']['imap'])) {
            $configuration = $GLOBALS['conf']['imap'];
        }
        if (!empty($GLOBALS['conf']['kolab']['imap'])) {
            $configuration = $GLOBALS['conf']['kolab']['imap'];
        }
        if (!empty($GLOBALS['conf']['kolab']['storage'])) {
            $configuration = $GLOBALS['conf']['kolab']['storage'];
        }

        $this->_injector->setInstance(
            'Horde_Kolab_Storage_Configuration', $configuration
        );
    }

    /**
     * Return the Horde_Kolab_Storage:: instance.
     *
     * @return Horde_Kolab_Storage The storage handler.
     */
    public function create()
    {
        $configuration = $this->_injector->getInstance('Horde_Kolab_Storage_Configuration');

        // Cache configuration
        $cache = !empty($configuration['cache'])
            ? $configuration['cache']
            : 'Mock';
        switch ($cache) {
        case 'Horde':
            $cacheob = $this->_injector->getInstance('Horde_Cache');
            break;
        case 'Mock':
        default:
            $cacheob = new Horde_Cache(
                new Horde_Cache_Storage_Mock(), array('compress' => true)
            );
        }

        $params = array(
            'driver' => 'horde',
            'params' => array(
                'host' => $configuration['server'],
                'username' => $GLOBALS['registry']->getAuth(),
                'password' => $GLOBALS['registry']->getAuthCredential('password'),
                'port'     => $configuration['port'],
                'secure'   => $configuration['secure'],
                'debug'    => isset($configuration['debug'])
                    ? $configuration['debug']
                    : null,
                'cache' =>  array(
                    'backend' => new Horde_Imap_Client_Cache_Backend_Cache(
                        array('cacheob' => $cacheob)
                    )
                )
            ),
            'queries' => array(
                'list' => array(
                    Horde_Kolab_Storage_List_Tools::QUERY_BASE => array(
                        'cache' => true
                    ),
                    Horde_Kolab_Storage_List_Tools::QUERY_ACL => array(
                        'cache' => true
                    ),
                    Horde_Kolab_Storage_List_Tools::QUERY_SHARE => array(
                        'cache' => true
                    ),
                )
            ),
            'queryset' => array(
                'data' => array('queryset' => 'horde'),
            ),
            'logger' => $this->_injector->getInstance('Horde_Log_Logger'),
            'log' => array('debug'),
            'cache' => $this->_injector->getInstance('Horde_Cache'),
        );

        // Check if the history system is enabled
        // @todo remove interface_exists check in H6.
        if (interface_exists('Horde_Kolab_Storage_HistoryPrefix')) {
            try {
                $history = $this->_injector->getInstance('Horde_History');
                $params['history'] = $history;
                $params['history_prefix_generator'] = new Horde_Core_Kolab_Storage_HistoryPrefix();
            } catch(Horde_Exception $e) {
            }
        }

        if (!empty($configuration['strategy'])) {
            $classname = 'Horde_Kolab_Storage_Synchronization_' . basename($configuration['strategy']);
            if (!class_exists($classname)) {
                throw new Horde_Exception(sprintf('Class %s not found.', $classname));
            }
            $params['sync_strategy'] = new $classname();
        }

        $factory = new Horde_Kolab_Storage_Factory($params);
        return $factory->create();
    }
}
