<?php
/**
 * Test the Group factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * Test the Group factory.
 *
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Core_Factory_GroupTest extends PHPUnit_Framework_TestCase
{
    public function testMock()
    {
        $injector = new Horde_Injector(new Horde_Injector_TopLevel());
        $injector->bindFactory('Horde_Group', 'Horde_Core_Factory_Group', 'create');
        $GLOBALS['conf']['group']['driver'] = 'mock';
        $this->assertInstanceOf(
            'Horde_Group_Mock',
            $injector->getInstance('Horde_Group')
        );
    }
}
