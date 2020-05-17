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
 * A fixture to verify various phpdoc tags are allowed inline.
 *
 * @package   local_moodlecheck
 * @copyright 2020 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * A fixture to verify various phpdoc tags can be used standalone or inline.
 *
 * @package   local_moodlecheck
 * @copyright 2020 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fixturing_inline {

    /**
     * Some valid tags, to verify they are ok.
     *
     * @license
     * @throws
     * @deprecated
     * @author
     * @todo
     * @codingStandardsIgnoreLine
     */
    public function all_valid_tags() {
        echo "yay!";
    }

    /**
     * Some tags that are valid standalone and inline
     *
     * @link https://moodle.org
     * @see has_capability()
     *
     * To know more, visit {@link https://moodle.org} for Moodle info.
     * To know more, take a look to {@see has_capability} about permissions.
     * And verify that crazy {@see \so-me\com-plex\th_ing::come()->baby()} are ok too.
     */
    public function correct_inline_tags() {
        echo "done!";
    }

    /**
     * Some invalid inline tags.
     *
     * This tag {@param string Some param} cannot be used inline.
     * Neither {@throws exception} can.
     * Ideally all {@link tags have to be 1 url}.
     * And all {@see must be 1 word only}
     * Also they aren't valid without using @see curly brackets
     */
    public function all_invalid_tags() {
        echo "done!";
    }
}
