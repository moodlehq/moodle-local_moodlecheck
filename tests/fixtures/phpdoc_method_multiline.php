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
 * Class to verify that "multiline" method declarations are working ok.
 *
 * @package local_moodlecheck
 */
class something {

    /**
     * One function, what else.
     *
     * @param string $plugin A plugin name.
     * @param int $direction A direction.
     * @return array
     */
    public function function_oneline(string $plugin, int $direction): array {
        // Do something.
    }

    /**
     * One function, what else.
     *
     * @param string $plugin A plugin name.
     * @param int $direction A direction.
     * @return array
     */
    public function function_multiline1(string $plugin,
            int $direction): array {
        // Do something.
    }

    /**
     * One function, what else.
     *
     * @param string $plugin A plugin name.
     * @param int $direction A direction.
     * @return array
     */
    public function function_multiline2(string $plugin, int $direction)
    : array {
        // Do something.
    }

    /**
     * One function, what else.
     *
     * @param string $plugin A plugin name.
     * @param int $direction A direction.
     * @return array
     */
    public function function_multiline3(
            string $plugin,
            int $direction): array {
        // Do something.
    }

    /**
     * One function, what else.
     *
     * @param string $plugin A plugin name.
     * @param int $direction A direction.
     * @return array
     */
    public function function_multiline4(
        string $plugin,
        int $direction
    ): array {
        // Do something.
    }

    /**
     * One function, what else.
     *
     * @param string $plugin A plugin name.
     * @param int $direction A direction.
     * @return array
     */
    public function function_multiline5(
        string $plugin,
        int $direction,
    ): array {
        // Do something.
    }
}
