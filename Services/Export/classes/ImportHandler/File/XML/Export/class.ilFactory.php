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

namespace ILIAS\Export\ImportHandler\File\XML\Export;

use ILIAS\Export\ImportHandler\File\XML\Export\Component\ilFactory as ilComponentXMLExportFileFactory;
use ILIAS\Export\ImportHandler\File\XML\Export\DataSet\ilFactory as ilDataSetXMLExportFileFactory;
use ILIAS\Export\ImportHandler\File\XML\Export\ilCollection as ilXMLExportFileHandlerCollection;
use ILIAS\Export\ImportHandler\I\File\XML\Export\Component\ilFactoryInterface as ilComponentXMLExportFileHandlerFactoryInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Export\DataSet\ilFactoryInterface as ilDataSetXMLExportFileHandlerFactoryInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Export\ilCollectionInterface as ilXMLExportFileCollectionInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Export\ilFactoryInterface as ilXMLExportFileFactoryInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Export\ilHandlerInterface as ilXMLExportFileHandlerInterface;
use ILIAS\Export\Schema\ilXmlSchemaFactory;
use ilLanguage;
use ilLogger;
use SplFileInfo;

class ilFactory implements ilXMLExportFileFactoryInterface
{
    protected ilLogger $logger;
    protected ilLanguage $lng;
    protected ilXmlSchemaFactory $schema_factory;

    public function __construct(
        ilLogger $logger,
        ilLanguage $lng,
        ilXmlSchemaFactory $schema_factory
    ) {
        $this->logger = $logger;
        $this->lng = $lng;
        $this->schema_factory = $schema_factory;
    }

    public function withFileInfo(SplFileInfo $file_info): ilXMLExportFileHandlerInterface
    {
        $comp_handler = $this->component()->handler()->withFileInfo($file_info);
        $dataset_handler = $this->dataSet()->handler()->withFileInfo($file_info);
        if ($dataset_handler->hasComponentRootNode()) {
            return $dataset_handler;
        }
        return $comp_handler;
    }

    public function collection(): ilXMLExportFileCollectionInterface
    {
        return new ilXMLExportFileHandlerCollection();
    }

    public function component(): ilComponentXMLExportFileHandlerFactoryInterface
    {
        return new ilComponentXMLExportFileFactory(
            $this->logger,
            $this->lng,
            $this->schema_factory
        );
    }

    public function dataSet(): ilDataSetXMLExportFileHandlerFactoryInterface
    {
        return new ilDataSetXMLExportFileFactory(
            $this->logger,
            $this->lng,
            $this->schema_factory
        );
    }
}
