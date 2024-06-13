<?php

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

declare(strict_types=1);

namespace ILIAS\Export\ImportHandler\File\Path\Comparison;

use ILIAS\Export\ImportHandler\File\Path\Comparison\ilHandler as ilFilePathComparisonHandler;
use ILIAS\Export\ImportHandler\File\Path\Comparison\Operator as ilFilePathComparisonOperator;
use ILIAS\Export\ImportHandler\I\File\Path\Comparison\ilFactoryInterface as ilFilePathComparisonFactoryInterface;
use ILIAS\Export\ImportHandler\I\File\Path\Comparison\ilHandlerInterface as ilFilePathComparisonHandlerInterface;

class ilFactory implements ilFilePathComparisonFactoryInterface
{
    public function handler(
        ilFilePathComparisonOperator $operator,
        string $content
    ): ilFilePathComparisonHandlerInterface {
        return new ilFilePathComparisonHandler($operator, $content);
    }
}
