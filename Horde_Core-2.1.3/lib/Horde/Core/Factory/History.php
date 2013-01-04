<?php
/**
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_History extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector $injector)
    {
        if (empty($GLOBALS['conf']['sql']['phptype']) ||
            ($GLOBALS['conf']['sql']['phptype'] == 'none')) {
            throw new Horde_Exception(Horde_Core_Translation::t("The History system is disabled."));
        }

        return new Horde_History_Sql(
            $injector->getInstance('Horde_Registry')->getAuth(),
            $injector->getInstance('Horde_Core_Factory_Db')->create('horde', 'history')
        );
    }
}
