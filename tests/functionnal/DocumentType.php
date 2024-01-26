<?php

/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2022 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace tests\units;

use DbTestCase;
use Toolbox;

/* Test for inc/documenttype.class.php */

class DocumentType extends DbTestCase
{
    public function testGetUploadableFilePattern()
    {
        $doctype = new \DocumentType();

       // Clear types to prevent test to be impacted by potential default types changes
        $this->boolean($doctype->deleteByCriteria(['1']))->isTrue();

        $this->integer((int)$doctype->add(Toolbox::addslashes_deep(['name' => 'JPG' ,'ext' => '/\.jpe?g$/'])))->isGreaterThan(0);
        $this->integer((int)$doctype->add(Toolbox::addslashes_deep(['name' => 'DOC' ,'ext' => 'doc'])))->isGreaterThan(0);
        $this->integer((int)$doctype->add(Toolbox::addslashes_deep(['name' => 'XML' ,'ext' => 'xml'])))->isGreaterThan(0);
        $this->integer((int)$doctype->add(Toolbox::addslashes_deep(['name' => 'Tarball' ,'ext' => 'tar.gz'])))->isGreaterThan(0);

       // Validate generated pattern
        $pattern = \DocumentType::getUploadableFilePattern();
        $this->string($pattern)->isIdenticalTo('/((\.jpe?g$)|\.doc$|\.xml$|\.tar\.gz$)/i');

       // Validate matches
        $this->integer(preg_match($pattern, 'test.jpg'))->isEqualTo(1);
        $this->integer(preg_match($pattern, 'test.jpeg'))->isEqualTo(1);
        $this->integer(preg_match($pattern, 'test.jpag'))->isEqualTo(0);
        $this->integer(preg_match($pattern, 'test.doc'))->isEqualTo(1);
        $this->integer(preg_match($pattern, 'test.xml'))->isEqualTo(1);
        $this->integer(preg_match($pattern, 'testxml'))->isEqualTo(0);
        $this->integer(preg_match($pattern, 'test.tar.gz'))->isEqualTo(1);
    }
}
