<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * A minimalistic file phpdoc block without package tag.
 *
 * @copyright 2022 onwards Eloy Lafuente (stronk7) {@link https://stronk7.com}
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// These are missing any package tag.

/**
 * This is a class without package tag.
 */
class missingclass {
    /**
     * This is a method.
     */
    public function somemethod() {
        return;
    }
}

/**
 * This is a trait without package tag.
 */
trait missingtrait {
    /**
     * This is a method.
     */
    public function somemethod() {
        return;
    }
}

/**
 * This is an interface without package tag.
 */
interface missinginterface {
    /**
     * This is a method.
     */
    public function somemethod();
}

/**
 * This is a global scope function without package tag.
 */
function missingfunction() {
    return;
}

// These have parent package tag.

/**
 * Lovely class with package tag.
 *
 * @package local_moodlecheck
 */
class packagedclass {
    /**
     * This is a method.
     */
    public function somemethod() {
        return;
    }
}

/**
 * Lovely trait with package tag.
 *
 * @package local_moodlecheck
 */
trait packagedtrait {
    /**
     * This is a method
     */
    public function somemethod() {
        return;
    }
}

/**
 * Lovely interface with package tag.
 *
 * @package local_moodlecheck
 */
interface packagedinterface {
    /**
     * This is a method
     */
    public function somemethod();
}

/**
 * Lovely global scope function with package tag.
 *
 * @package local_moodlecheck
 */
function packagedfunction() {
    return;
}
