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
 * Every type annotation should give an error either when checked with PHPStan or Psalm.
 * Having just invalid types in here means the number of errors should match the number of type annotations.
 *
 * @package   local_moodlecheck
 * @copyright 2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author    James Calder
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (or CC BY-SA v4 or later)
 */

defined('MOODLE_INTERNAL') || die();

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
     * Expecting variable name, saw other (passes Psalm)
     * @param int int
     */
    public function expecting_var_saw_other(int $x): void {
    }

    // Expecting type, saw end.
    /** @var */
    public $expectingtypesawend;

    /** @var $varname Expecting type, saw other */
    public $expectingtypesawother;

    // Unterminated string (passes Psalm).
    /** @var " */
    public $unterminatedstring;

    // Unterminated string with escaped quote (passes Psalm).
    /** @var "\"*/
    public $unterminatedstringwithescapedquote;

    // String has escape with no following character (passes Psalm).
    /** @var "\*/
    public $stringhasescapewithnofollowingchar;

    /** @var array-key&(int|string) Non-DNF type (passes PHPStan) */
    public $nondnftype;

    /** @var int&string Invalid intersection */
    public $invalidintersection;

    /** @var int<0.0, 1> Invalid int min */
    public $invalidintmin;

    /** @var int<0, 1.0> Invalid int max */
    public $invalidintmax;

    /** @var int-mask<1.0, 2.0> Invalid int mask 1 */
    public $invalidintmask1;

    /** @var int-mask-of<string> Invalid int mask 2 */
    public $invalidintmask2;

    // Expecting class for class-string, saw end.
    /** @var class-string< */
    public $expectingclassforclassstringsawend;

    /** @var class-string<int> Expecting class for class-string, saw other */
    public $expectingclassforclassstringsawother;

    /** @var list<int, string> List key */
    public $listkey;

    /** @var array<object, object> Invalid array key (passes Psalm) */
    public $invalidarraykey;

    /** @var non-empty-array{'a': int} Non-empty-array shape */
    public $nonemptyarrayshape;

    /** @var object{0.0: int} Invalid object key (passes Psalm) */
    public $invalidobjectkey;

    /** @var key-of<int> Can't get key of non-iterable */
    public $cantgetkeyofnoniterable;

    /** @var value-of<int> Can't get value of non-iterable */
    public $cantgetvalueofnoniterable;

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
