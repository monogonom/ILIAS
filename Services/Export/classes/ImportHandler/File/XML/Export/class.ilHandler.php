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

use ilDataSet;
use ILIAS\Export\ImportHandler\File\Validation\ilHandler as ilFileValidationHandler;
use ILIAS\Export\ImportHandler\File\XML\ilHandler as ilXMLFileHandler;
use ILIAS\Export\ImportHandler\I\File\Namespace\ilFactoryInterface as ilFileNamespaceHandlerInterface;
use ILIAS\Export\ImportHandler\I\File\Path\ilFactoryInterface as ilFilePathFactoryInterface;
use ILIAS\Export\ImportHandler\I\File\Validation\Set\ilFactoryInterface as ilFileValidationSetFactoryInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Export\ilHandlerInterface as ilXMLExportFileHandlerInterface;
use ILIAS\Export\ImportHandler\I\File\XML\ilHandlerInterface as ilXMLFileHandlerInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Node\Info\Attribute\ilFactoryInterface as ilXMlFileInfoNodeAttributeFactoryInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Node\Info\ilTreeInterface as ilXMLFileNodeInfoTreeInterface;
use ILIAS\Export\ImportHandler\I\File\XML\Schema\ilFactoryInterface as ilXMLFileSchemaFactory;
use ILIAS\Export\ImportHandler\I\File\XSD\ilHandlerInterface as ilXSDFileHandlerInterface;
use ILIAS\Export\ImportHandler\I\Parser\ilFactoryInterface as ilParserFactoryInterface;
use ILIAS\Export\ImportStatus\Exception\ilException as ilImportStatusException;
use ILIAS\Export\ImportStatus\I\ilFactoryInterface as ilImportStatusFactoryInterface;
use ILIAS\Export\ImportStatus\I\ilHandlerInterface as ilImportStatusHandlerInterface;
use ILIAS\Export\ImportStatus\StatusType;
use ilLanguage;
use ilLogger;
use SplFileInfo;

abstract class ilHandler extends ilXMLFileHandler implements ilXMLExportFileHandlerInterface
{
    protected ilXMLFileSchemaFactory $schema;
    protected ilParserFactoryInterface $parser;
    protected ilFilePathFactoryInterface $path;
    protected ilXMlFileInfoNodeAttributeFactoryInterface $attribute;
    protected ilFileValidationSetFactoryInterface $set;
    protected ilLogger $logger;
    protected ilLanguage $lng;

    public function __construct(
        ilFileNamespaceHandlerInterface $namespace,
        ilImportStatusFactoryInterface $status,
        ilXMLFileSchemaFactory $schema,
        ilParserFactoryInterface $parser,
        ilFilePathFactoryInterface $path,
        ilLogger $logger,
        ilXMlFileInfoNodeAttributeFactoryInterface $attribute,
        ilFileValidationSetFactoryInterface $set,
        ilLanguage $lng
    ) {
        parent::__construct($namespace, $status);
        $this->schema = $schema;
        $this->parser = $parser;
        $this->logger = $logger;
        $this->path = $path;
        $this->attribute = $attribute;
        $this->set = $set;
        $this->lng = $lng;
    }

    /**
     * @throws ilImportStatusException
     */
    public function withFileInfo(SplFileInfo $file_info): ilHandler
    {
        $clone = clone $this;
        $clone->xml_file_info = $file_info;
        return $clone;
    }

    public function getILIASPath(ilXMLFileNodeInfoTreeInterface $component_tree): string
    {
        $matches = [];
        $pattern = '/([0-9]+)__([0-9]+)__([a-z_]+)_([0-9]+)/';
        $path_part = $this->getSubPathToDirBeginningAtPathEnd('temp')->getPathPart($pattern);
        if (
            is_null($path_part) ||
            preg_match($pattern, $path_part, $matches) !== 1
        ) {
            return 'No path found';
        };
        $node = $component_tree->getFirstNodeWith(
            $this->attribute->collection()
                ->withElement($this->attribute->pair()->withValue($matches[4])->withKey('Id'))
                ->withElement($this->attribute->pair()->withValue($matches[3])->withKey('Type'))
        );
        return is_null($node)
            ? ''
            : $component_tree->getAttributePath($node, 'Title', DIRECTORY_SEPARATOR);
    }

    public function isContainerExportXML(): bool
    {
        return $this->getSubPathToDirBeginningAtPathEnd('temp')->pathContainsFolderName('Container');
    }

    public function hasComponentRootNode(): bool
    {
        $xml = $this->withAdditionalNamespace(
            $this->namespace->handler()
                ->withNamespace(ilDataSet::DATASET_NS)
                ->withPrefix(ilDataSet::DATASET_NS_PREFIX)
        );
        try {
            $nodes = $this->parser->DOM()->withFileHandler($xml)->getNodeInfoAt($this->getPathToComponentRootNodes());
        } catch (ilImportStatusException $e) {
            return false;
        }
        return count($nodes) > 0;
    }

    protected function getFailMsgNoMatchingVersionFound(
        ilXMLFileHandlerInterface $xml_file_handler,
        ilXSDFileHandlerInterface $xsd_file_handler,
        string $version_str
    ): ilImportStatusHandlerInterface {
        $xml_str = "<br>XML-File: " . $xml_file_handler->getSubPathToDirBeginningAtPathEnd(ilFileValidationHandler::TMP_DIR_NAME)->getFilePath();
        $xsd_str = "<br>XSD-File: " . $xsd_file_handler->getSubPathToDirBeginningAtPathEnd(ilFileValidationHandler::XML_DIR_NAME)->getFilePath();
        $msg = sprintf($this->lng->txt('exp_import_validation_err_no_matching_xsd'), $version_str);
        $content = $this->status->content()->builder()->string()->withString(
            "Validation FAILED"
            . $xml_str
            . $xsd_str
            . "<br>ERROR Message: " . $msg
        );
        return $this->status->handler()
            ->withType(StatusType::FAILED)
            ->withContent($content);
    }
}
