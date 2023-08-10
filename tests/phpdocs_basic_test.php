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
 * Contains unit tests for covering "moodle" PHPDoc rules.
 *
 * @package    local_moodlecheck
 * @category   test
 * @copyright  2023 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class phpdocs_basic_test extends \advanced_testcase {

    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/moodlecheck/locallib.php');
        require_once($CFG->dirroot . '/local/moodlecheck/rules/phpdocs_basic.php');
    }

    /**
     * Test that normalisation of the method and docblock params works as expected.
     *
     * @dataProvider local_moodlecheck_normalise_function_type_provider
     * @param string $inputtype The input type.
     * @param string $expectedtype The expected type.
     * @covers ::local_moodlecheck_normalise_function_type
     */
    public function test_local_moodlecheck_normalise_function_type(string $inputtype, string $expectedtype): void {
        $this->assertEquals(
            $expectedtype,
            local_moodlecheck_normalise_function_type($inputtype)
        );
    }

    public static function local_moodlecheck_normalise_function_type_provider(): array {
        return [
            'Simple case' => [
                'stdClass', 'stdClass',
            ],

            'Fully-qualified stdClass' => [
                '\stdClass', 'stdClass',
            ],

            'Fully-qualified namespaced item' => [
                \core_course\local\some\type_of_item::class,
                'type_of_item',
            ],

            'Unioned simple case' => [
                'stdClass|object', 'object|stdClass',
            ],

            'Unioned fully-qualfied case' => [
                '\stdClass|\object', 'object|stdClass',
            ],

            'Unioned fully-qualfied namespaced item' => [
                '\stdClass|\core_course\local\some\type_of_item',
                'stdClass|type_of_item',
            ],

            'Nullable fully-qualified type' => [
                '?\core-course\local\some\type_of_item',
                'null|type_of_item',
            ],

            'Nullable fully-qualified type z-a' => [
                '?\core-course\local\some\alpha_item',
                'alpha_item|null',
            ],
        ];
    }
}
