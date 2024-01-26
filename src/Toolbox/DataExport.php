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

namespace Glpi\Toolbox;

use Glpi\RichText\RichText;

class DataExport
{
    /**
     * Normalize a value for text export (PDF, CSV, SYLK, ...).
     * Assume value cames from DB and has been processed by GLPI sanitize process.
     *
     * @param string $value
     *
     * @return string
     */
    public static function normalizeValueForTextExport(string $value): string
    {
        $value = Sanitizer::unsanitize($value);

        if (RichText::isRichTextHtmlContent($value)) {
           // Remove invisible contents (tooltips for instance)
            libxml_use_internal_errors(true); // Silent errors
            $document = new \DOMDocument();
            $document->loadHTML(
                mb_convert_encoding('<div>' . $value . '</div>', 'HTML-ENTITIES', 'UTF-8'),
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            $xpath = new \DOMXPath($document);
            $invisible_elements = $xpath->query('//div[contains(@class, "invisible")]');
            foreach ($invisible_elements as $element) {
                 $element->parentNode->removeChild($element);
            }

            $value = $document->saveHTML();

           // Transform into simple text
            $value = RichText::getTextFromHtml($value, true, true);
        }

        return $value;
    }
}
