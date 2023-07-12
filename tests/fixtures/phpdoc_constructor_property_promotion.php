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

use cm_info;
use stdClass;

/**
 * A fixture to verify various phpdoc tags in a general location.
 *
 * @package   local_moodlecheck
 * @copyright 2018 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constructor_property_promotion {
    /**
     * An example of a constructor using constructor property promotion.
     *
     * @param stdClass|cm_info $cm The course module data
     * @param string $name The name
     * @param int|float $size The size
     * @param null|string $description The description
     * @param ?string $content The content
     */
    public function __construct(
        protected stdClass|cm_info $cm,
        protected string $name,
        protected float|int $size,
        protected ?string $description = null,
        protected ?string $content = null
    ) {
    }
}
