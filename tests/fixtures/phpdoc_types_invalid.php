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

// phpcs:ignoreFile

/**
 * A collection of invalid types for testing
 *
 * All these should fail type checking.
 * Having just invalid types in here means the number of errors should match the number of type annotations.
 *
 * @package   local_moodlecheck
 * @copyright 2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author    James Calder
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (or CC BY-SA v4 or later)
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * A collection of invalid types for testing
 *
 * @package   local_moodlecheck
 * @copyright 2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author    James Calder
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (or CC BY-SA v4 or later)
 */
class types_invalid {

    /**
     * Expecting variable name, saw end
     * @param int
     */
    public function expecting_var_saw_end(int $x): void {
    }

    /**
     * Expecting variable name, saw other
     * @param int int
     */
    public function expecting_var_saw_other(int $x): void {
    }

    // Expecting type, saw end.
    /** @var */
    public $expectingtypesawend;

    /** @var $varname Expecting type, saw other */
    public $expectingtypesawother;

    // Unterminated string.
    /** @var " */
    public $unterminatedstring;

    // Unterminated string with escaped quote.
    /** @var "\"*/
    public $unterminatedstringwithescapedquote;

    // String has escape with no following character.
    /** @var "\*/
    public $stringhasescapewithnofollowingchar;

    // Expecting class for class-string, saw end.
    /** @var class-string< */
    public $expectingclassforclassstringsawend;

    /** @var class-string<int> Expecting class for class-string, saw other */
    public $expectingclassforclassstringsawother;

    /** @var list<int, string> List key */
    public $listkey;

    /** @var non-empty-array{'a': int} Non-empty-array shape */
    public $nonemptyarrayshape;

    /** @var object{0.0: int} Invalid object key */
    public $invalidobjectkey;

    /**
     * Class name has trailing slash
     * @param types_invalid/ $x
     */
    public function class_name_has_trailing_slash(object $x): void {
    }

    // Expecting closing bracket, saw end.
    /** @var (types_invalid */
    public $expectingclosingbracketsawend;

    /** @var (types_invalid int Expecting closing bracket, saw other*/
    public $expectingclosingbracketsawother;

}
