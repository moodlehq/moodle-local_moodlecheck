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

namespace local_moodlecheck;

/**
 * A fixture to verify various phpdoc tags in a general location.
 *
 * @package   local_moodlecheck
 * @copyright 2023 Andrew Lyons <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class union_types {
    /**
     * An example of a method on a single line using union types in both the params and return values
     * @param string|int $value
     * @return string|int
     */
    public function method_oneline(string|int $value): string|int {
        // Do something.
        return $value;
    }

    /**
     * An example of a method on a single line using union types in both the params and return values
     *
     * @param string|int $value
     * @param int|float $othervalue
     * @return string|int
     */
    public function method_oneline_multi(string|int $value, int|float $othervalue): string|int {
        // Do something.
        return $value;
    }

    /**
     * An example of a method on a single line using union types in both the params and return values
     *
     * @param string|int $value
     * @param int|float $othervalue
     * @return string|int
     */
    public function method_multiline(
        string|int $value,
        int|float $othervalue,
    ): string|int {
        // Do something.
        return $value;
    }

    /**
     * An example of a method whose union values are not in the same order.

     * @param int|string $value
     * @param int|float $othervalue
     * @return int|string
     */
    public function method_union_order_does_not_matter(
        string|int $value,
        float|int $othervalue,
    ): string|int {
        // Do something.
        return $value;
    }

    /**
     * An example of a method which uses strings, or an array of strings.
     *
     * @param string|string[] $arrayofstrings
     * @return string[]|string
     */
    public function method_union_containing_array(
        string|array $arrayofstrings,
    ): string|array {
        return [
            'example',
        ];
    }
}
