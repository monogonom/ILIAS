<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * Class ilTestEvalObjectiveOrientedGUITest
 * @author Marvin Beym <mbeym@databay.de>
 */
class ilTestEvalObjectiveOrientedGUITest extends ilTestBaseTestCase
{
    private ilTestEvalObjectiveOrientedGUI $testObj;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addGlobal_tpl();
        $this->addGlobal_lng();
        $this->addGlobal_ilCtrl();
        $this->addGlobal_ilias();
        $this->addGlobal_tree();
        $this->addGlobal_ilDB();
        $this->addGlobal_ilComponentRepository();
        $this->addGlobal_ilTabs();
        $this->addGlobal_ilObjDataCache();

        $objTest_mock = $this->createMock(ilObjTest::class);
        $this->testObj = new ilTestEvalObjectiveOrientedGUI($objTest_mock);
    }

    public function test_instantiateObject_shouldReturnInstance(): void
    {
        $this->assertInstanceOf(ilTestEvalObjectiveOrientedGUI::class, $this->testObj);
    }
}
