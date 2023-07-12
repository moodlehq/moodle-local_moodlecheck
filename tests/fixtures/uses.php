<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Fixture file with use statements, none of which should trigger warnings, despite containing "function" and "const".
 *
 * @package     local_moodlecheck
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use these\dont\actually\need\to\point\to\anything;
use function ns\fun_1;
use function ns\fun_2 as alias;
use const ns\CONST_1;
use const ns\CONST_2 as ALIAS;

use {
    function ns\fun_3,
    const ns\const_3
};

use function ns\fun_1?>
