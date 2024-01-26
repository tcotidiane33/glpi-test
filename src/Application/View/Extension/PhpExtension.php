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

namespace Glpi\Application\View\Extension;

use Toolbox;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * @since 10.0.0
 */
class PhpExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('php_config', [$this, 'phpConfig']),
            new TwigFunction('call', [$this, 'call']),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('instanceof', [$this, 'isInstanceOf']),
            new TwigTest('usingtrait', [$this, 'isUsingTrait']),
        ];
    }

    /**
     * Get PHP configuration value.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function phpConfig(string $name)
    {
        return ini_get($name);
    }

    /**
     * Call function of static method.
     *
     * @param string $callable
     * @param array $parameters
     *
     * @return mixed
     */
    public function call(string $callable, array $parameters = [])
    {
        if (is_callable($callable)) {
            return call_user_func_array($callable, $parameters);
        }
        return null;
    }

    /**
     * Checks if a given value is an instance of given class name.
     *
     * @param mixed  $value
     * @param string $classname
     *
     * @return bool
     */
    public function isInstanceof($value, $classname): bool
    {
        return is_object($value) && $value instanceof $classname;
    }

    /**
     * Checks if a given value is an instance of class using given trait name.
     *
     * @param mixed  $value
     * @param string $trait
     *
     * @return bool
     */
    public function isUsingTrait($value, $trait): bool
    {
        return is_object($value) && Toolbox::hasTrait($value, $trait);
    }
}
