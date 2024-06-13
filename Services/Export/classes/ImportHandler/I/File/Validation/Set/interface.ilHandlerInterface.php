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

namespace ILIAS\Export\ImportHandler\I\File\Validation\Set;

use ILIAS\Export\ImportHandler\I\File\Path\ilHandlerInterface as ilFilePathHandlerInterface;
use ILIAS\Export\ImportHandler\I\File\XML\ilHandlerInterface as ilXMLFileHandlerInterface;
use ILIAS\Export\ImportHandler\I\File\XSD\ilHandlerInterface as ilXSDFileHandlerInterface;

interface ilHandlerInterface
{
    public function getXMLFileHandler(): ilXMLFileHandlerInterface;

    public function getFilePathHandler(): ilFilePathHandlerInterface;

    public function getXSDFileHandler(): ilXSDFileHandlerInterface;

    public function withFilePathHandler(ilFilePathHandlerInterface $path_handler): ilHandlerInterface;

    public function withXSDFileHanlder(ilXSDFileHandlerInterface $xsd_file_handler): ilHandlerInterface;

    public function withXMLFileHandler(ilXMLFileHandlerInterface $xml_file_handler): ilHandlerInterface;
}
