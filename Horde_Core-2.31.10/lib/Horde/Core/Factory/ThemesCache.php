<?php
/**
 * A Horde_Injector:: based Horde_Themes_Cache:: factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * A Horde_Injector:: based Horde_Themes_Cache:: factory.
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
class Horde_Core_Factory_ThemesCache
extends Horde_Core_Factory_Base
implements Horde_Shutdown_Task
{
    /**
     * Instances.
     *
     * @var array
     */
    private $_instances = array();

    /**
     * Return the Horde_Themes_Cache:: instance.
     *
     * @param string $app    The application name.
     * @param string $theme  The theme name.
     *
     * @return Horde_Themes_Cache  The singleton instance.
     */
    public function create($app, $theme)
    {
        $sig = implode('|', array($app, $theme));

        if (!isset($this->_instances[$sig])) {
            $cache = empty($GLOBALS['conf']['cachethemes'])
                ? null
                : $this->_injector->getInstance('Horde_Cache');

            if (!$cache || ($cache instanceof Horde_Cache_Null)) {
                $instance = new Horde_Themes_Cache($app, $theme);
            } else {
                try {
                    $instance = @unserialize($cache->get($sig, $GLOBALS['conf']['cachethemesparams']['lifetime']));
                } catch (Exception $e) {
                    $instance = null;
                }

                if (!($instance instanceof Horde_Themes_Cache)) {
                    $instance = new Horde_Themes_Cache($app, $theme);
                    $instance->build();
                }

                Horde_Shutdown::add($this);
            }

            $this->_instances[$sig] = $instance;
        }

        return $this->_instances[$sig];
    }

    /**
     * Expire cache entry.
     *
     * @param string $app    The application name.
     * @param string $theme  The theme name.
     *
     * @return boolean  True if cache entry existed and was deleted.
     * @throws Horde_Exception
     */
    public function expireCache($app, $theme)
    {
        $sig = implode('|', array($app, $theme));

        $cache = $this->_injector->getInstance('Horde_Cache');

        if ($cache->exists($sig, $GLOBALS['conf']['cachethemesparams']['lifetime'])) {
            if (!$cache->expire($sig)) {
                throw new Horde_Exception('Could not delete cache entry.');
            }

            unset($this->_instances[$sig]);
            return true;
        }

        return false;
    }

    /**
     * Store object in cache.
     */
    public function shutdown()
    {
        $cache = $this->_injector->getInstance('Horde_Cache');

        foreach ($this->_instances as $key => $val) {
            if ($val->changed) {
                $cache->set($key, serialize($val), $GLOBALS['conf']['cachethemesparams']['lifetime']);
            }
        }
    }

}
