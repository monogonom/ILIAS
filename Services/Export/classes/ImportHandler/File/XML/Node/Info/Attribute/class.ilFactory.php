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

namespace ILIAS\Export\ImportHandler\File\XML\Node\Info\Attribute;

use ILIAS\Export\ImportHandler\File\XML\Node\Info\Attribute\ilCollection as ilXMLFileNodeInfoAttribureCollection;
use ILIAS\Export\ImportHandler\File\XML\Node\Info\Attribute\ilPair as ilXMLFileNodeInfoAttributePair;
use ILIAS\Export\ImportHandler\I\File\XML\Node\Info\Attribute\ilCollectionInterface as ilXMLFileNodeInfoAttributeCollectionInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Node\Info\Attribute\ilFactoryInterface as ilXMLFileNodeInfoAttributeFactoryInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Node\Info\Attribute\ilPairInterface as ilXMLFileNodeInfoAttributePairInterface;
use ilLogger;

class ilFactory implements ilXMLFileNodeInfoAttributeFactoryInterface
{
    protected ilLogger $logger;

    public function __construct(ilLogger $logger)
    {
        $this->logger = $logger;
    }

    public function pair(): ilXMLFileNodeInfoAttributePairInterface
    {
        return new ilXMLFileNodeInfoAttributePair();
    }

    public function collection(): ilXMLFileNodeInfoAttributeCollectionInterface
    {
        return new ilXMLFileNodeInfoAttribureCollection($this->logger);
    }
}
