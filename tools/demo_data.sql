--
-- ---------------------------------------------------------------------
-- GLPI - Gestionnaire Libre de Parc Informatique
-- Copyright (C) 2015-2022 Teclib' and contributors.
--
-- http://glpi-project.org
--
-- based on GLPI - Gestionnaire Libre de Parc Informatique
-- Copyright (C) 2003-2014 by the INDEPNET Development Team.
--
-- ---------------------------------------------------------------------
--
-- LICENSE
--
-- This file is part of GLPI.
--
-- GLPI is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- GLPI is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with GLPI. If not, see <http://www.gnu.org/licenses/>.
-- ---------------------------------------------------------------------
--

UPDATE glpi_configs SET value = 'https://demo.glpi-project.org' WHERE name = 'url_base';
UPDATE glpi_configs SET value = 'Pick an account, choose a language and try GLPI!

Following accounts are available:

- Administrator (login admin, password admin),
- Standard user (login normal, password normal),
- Self-service helpdesk user (login post-only, password postonly)'
   WHERE name = 'text_login';
UPDATE glpi_configs SET value = 1 WHERE name = 'use_public_faq';
UPDATE glpi_configs SET value = 1 WHERE name = 'translate_dropdowns';
UPDATE glpi_configs SET value = 1 WHERE name = 'translate_kb';
UPDATE glpi_configs SET value = 1 WHERE name = 'translate_reminders';
-- all profile rights
UPDATE glpi_profilerights SET rights = 0 WHERE name = 'password_update';
-- admin profile rights
UPDATE glpi_profilerights SET rights = 2049 WHERE profiles_id = 3 AND name = 'user';
UPDATE glpi_profilerights SET rights = 1 WHERE profiles_id = 3 AND name = 'typedoc';
UPDATE glpi_profilerights SET rights = 23 WHERE profiles_id = 3 AND name = 'slm';
UPDATE glpi_profilerights SET rights = 261151 WHERE profiles_id = 3 AND name = 'ticket';
UPDATE glpi_profilerights SET rights = 23 WHERE profiles_id = 3 AND name = 'ticketrecurrent';
UPDATE glpi_profilerights SET rights = 23 WHERE profiles_id = 3 AND name = 'itiltemplate';
UPDATE glpi_profilerights SET rights = 23 WHERE profiles_id = 3 AND name = 'reminder_public';
UPDATE glpi_profilerights SET rights = 23 WHERE profiles_id = 3 AND name = 'rssfeed_public';
UPDATE glpi_profilerights SET rights = 23 WHERE profiles_id = 3 AND name = 'bookmark_public';
INSERT INTO glpi_users (name, password, authtype, language) VALUES ('admin', '$2y$10$ERFSQRmAVBzX9xNDtkV82.AixFN3ds6WKWQOwwUBcG2.7.U4c2hCa', 1, 'en_GB');
INSERT INTO glpi_profiles_users (users_id, profiles_id)
   SELECT id, 3 from glpi_users WHERE name = 'admin';
