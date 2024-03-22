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

use local_moodlecheck_path;
use local_moodlecheck_registry;

/**
 * Contains unit tests for covering "moodle" PHPDoc rules.
 *
 * @package    local_moodlecheck
 * @category   test
 * @copyright  2018 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class moodlecheck_rules_test extends \advanced_testcase {
    public function setUp(): void {
        global $CFG;
        parent::setUp();
        // Add the moodlecheck machinery.
        require_once($CFG->dirroot . '/local/moodlecheck/locallib.php');
        // Load all files from rules directory.
        if ($dh = opendir($CFG->dirroot . '/local/moodlecheck/rules')) {
            while (($file = readdir($dh)) !== false) {
                if ($file != '.' && $file != '..') {
                    $pathinfo = pathinfo($file);
                    if (isset($pathinfo['extension']) && $pathinfo['extension'] == 'php') {
                        require_once($CFG->dirroot . '/local/moodlecheck/rules/' . $file);
                    }
                }
            }
            closedir($dh);
        }
        // Load all rules.
        local_moodlecheck_registry::enable_all_rules();
    }

    /**
     * Verify the ::class constant is not reported as phpdoc problem.
     *
     * @covers \local_moodlecheck_file::get_classes
     * @covers \local_moodlecheck_file::previous_nonspace_token
     */
    public function test_constantclass(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path('local/moodlecheck/tests/fixtures/constantclass.php ', null);
        $result = $output->display_path($path, 'xml');

        // Convert results to XML Objext.
        $xmlresult = new \DOMDocument();
        $xmlresult->loadXML($result);

        // Let's verify we have received a xml with file top element and 2 children.
        $xpath = new \DOMXpath($xmlresult);
        $found = $xpath->query("//file/error");

        // TODO: Change to DOMNodeList::count() when php71 support is gone.
        $this->assertSame(0, $found->length);
    }

    /**
     * Ensure that token_get_all() does not return PHP Warnings.
     *
     * @covers \local_moodlecheck_file::get_tokens
     */
    public function test_get_tokens(): void {
        global $PAGE;

        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path('local/moodlecheck/tests/fixtures/unfinished.php ', null);

        $this->expectOutputString('');
        $result = $output->display_path($path, 'xml');
    }

    /**
     * Verify various phpdoc tags in general directories.
     *
     * @covers ::local_moodlecheck_functionarguments
     */
    public function test_phpdoc_tags_general(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path('local/moodlecheck/tests/fixtures/phpdoc_tags_general.php ', null);
        $result = $output->display_path($path, 'xml');

        // Convert results to XML Objext.
        $xmlresult = new \DOMDocument();
        $xmlresult->loadXML($result);

        // Let's verify we have received a xml with file top element and 8 children.
        $xpath = new \DOMXpath($xmlresult);
        $found = $xpath->query("//file/error");
        // TODO: Change to DOMNodeList::count() when php71 support is gone.
        $this->assertSame(10, $found->length);

        // Also verify various bits by content.
        $this->assertStringContainsString('incomplete_param_annotation has incomplete parameters list', $result);
        $this->assertStringContainsString('missing_param_defintion has incomplete parameters list', $result);
        $this->assertStringContainsString('missing_param_annotation has incomplete parameters list', $result);
        $this->assertStringContainsString('incomplete_param_definition has incomplete parameters list', $result);
        $this->assertStringContainsString('incomplete_param_annotation1 has incomplete parameters list', $result);
        $this->assertStringContainsString('mismatch_param_types has incomplete parameters list', $result);
        $this->assertStringContainsString('mismatch_param_types1 has incomplete parameters list', $result);
        $this->assertStringContainsString('mismatch_param_types2 has incomplete parameters list', $result);
        $this->assertStringContainsString('mismatch_param_types3 has incomplete parameters list', $result);
        $this->assertStringContainsString('incomplete_return_annotation has incomplete parameters list', $result);
        $this->assertStringNotContainsString('@deprecated', $result);
        $this->assertStringNotContainsString('correct_param_types', $result);
        $this->assertStringNotContainsString('correct_return_type', $result);
    }

    /**
     * Verify that constructor property promotion is supported.
     *
     * @covers ::local_moodlecheck_functionarguments
     */
    public function test_phpdoc_constructor_property_promotion(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path('local/moodlecheck/tests/fixtures/phpdoc_constructor_property_promotion.php ', null);
        $result = $output->display_path($path, 'xml');

        // Convert results to XML Objext.
        $xmlresult = new \DOMDocument();
        $xmlresult->loadXML($result);

        // Let's verify we have received a xml with file top element and 8 children.
        $xpath = new \DOMXpath($xmlresult);
        $found = $xpath->query("//file/error");

        // TODO: Change to DOMNodeList::count() when php71 support is gone.
        $this->assertSame(0, $found->length);
        $this->assertStringNotContainsString('constructor_property_promotion::__construct has incomplete parameters list', $result);
    }

    /**
     * Verify that constructor property promotion supports readonly properties.
     *
     * @covers ::local_moodlecheck_functionarguments
     * @requires PHP >= 8.1
     */
    public function test_phpdoc_constructor_property_promotion_readonly(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path(
            'local/moodlecheck/tests/fixtures/phpdoc_constructor_property_promotion_readonly.php',
            null
        );
        $result = $output->display_path($path, 'xml');

        // Convert results to XML Objext.
        $xmlresult = new \DOMDocument();
        $xmlresult->loadXML($result);

        // Let's verify we have received a xml with file top element and 8 children.
        $xpath = new \DOMXpath($xmlresult);
        $found = $xpath->query("//file/error");

        $this->assertCount(0, $found);
        $this->assertStringNotContainsString('constructor_property_promotion::__construct has incomplete parameters list', $result);
    }

    /**
     * Verify that constructor property promotion is supported.
     *
     * @covers ::local_moodlecheck_functionarguments
     */
    public function test_phpdoc_union_types(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');

        $path = new local_moodlecheck_path('local/moodlecheck/tests/fixtures/phpdoc_method_union_types.php ', null);
        $result = $output->display_path($path, 'xml');

        // Convert results to XML Objext.
        $xmlresult = new \DOMDocument();
        $xmlresult->loadXML($result);

        // Let's verify we have received a xml with file top element and 8 children.
        $xpath = new \DOMXpath($xmlresult);
        $found = $xpath->query("//file/error");

        // TODO: Change to DOMNodeList::count() when php71 support is gone.
        $this->assertSame(0, $found->length);
        $this->assertStringNotContainsString(
            'constructor_property_promotion::__construct has incomplete parameters list',
            $result
        );
        $this->assertStringNotContainsString(
            'Phpdocs for function union_types::method_oneline has incomplete parameters list',
            $result
        );
        $this->assertStringNotContainsString(
            'Phpdocs for function union_types::method_oneline_multi has incomplete parameters list',
            $result
        );
        $this->assertStringNotContainsString(
            'Phpdocs for function union_types::method_multiline has incomplete parameters list',
            $result
        );
        $this->assertStringNotContainsString(
            'Phpdocs for function union_types::method_union_order_does_not_matter has incomplete parameters list',
            $result
        );
        $this->assertStringNotContainsString(
            'Phpdocs for function union_types::method_union_containing_array has incomplete parameters list',
            $result
        );
    }

    /**
     * Verify various phpdoc tags can be used inline.
     *
     * @covers ::local_moodlecheck_phpdocsuncurlyinlinetag
     * @covers ::local_moodlecheck_phpdoccontentsinlinetag
     */
    public function test_phpdoc_tags_inline(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path('local/moodlecheck/tests/fixtures/phpdoc_tags_inline.php ', null);
        $result = $output->display_path($path, 'xml');

        // Convert results to XML Objext.
        $xmlresult = new \DOMDocument();
        $xmlresult->loadXML($result);

        // Let's verify we have received a xml with file top element and 8 children.
        $xpath = new \DOMXpath($xmlresult);
        $found = $xpath->query("//file/error");
        // TODO: Change to DOMNodeList::count() when php71 support is gone.
        $this->assertSame(6, $found->length);

        // Also verify various bits by content.
        $this->assertStringContainsString('Invalid inline phpdocs tag @param found', $result);
        $this->assertStringContainsString('Invalid inline phpdocs tag @throws found', $result);
        $this->assertStringContainsString('Inline phpdocs tag {@link tags need to have a valid URL} with incorrect', $result);
        $this->assertStringContainsString('Inline phpdocs tag {@see $this-&gt;tagrules[&#039;url&#039;]} with incorrect', $result);
        $this->assertStringContainsString('Inline phpdocs tag not enclosed with curly brackets @see found', $result);
        $this->assertStringContainsString(
            'It must match {@link [valid URL] [description (optional)]} or {@see [valid FQSEN] [description (optional)]}',
            $result
        );
        $this->assertStringNotContainsString('{@link https://moodle.org}', $result);
        $this->assertStringNotContainsString('{@see has_capability}', $result);
        $this->assertStringNotContainsString('ba8by}', $result);
    }

    /**
     * Verify that empty files and files without PHP aren't processed
     *
     * @dataProvider empty_nophp_files_provider
     * @param   string $path
     *
     * @covers \local_moodlecheck_file::validate
     */
    public function test_empty_nophp_files($file): void {
        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path($file, null);
        $result = $output->display_path($path, 'xml');

        // Convert results to XML Object.
        $xmlresult = new \DOMDocument();
        $xmlresult->loadXML($result);

        // Let's verify we have received a xml with file top element and 1 children.
        $xpath = new \DOMXpath($xmlresult);
        $found = $xpath->query("//file/error");
        // TODO: Change to DOMNodeList::count() when php71 support is gone.
        $this->assertSame(1, $found->length);

        // Also verify various bits by content.
        $this->assertStringContainsString('The file is empty or doesn&#039;t contain PHP code. Skipped.', $result);
    }

    /**
     * Data provider for test_empty_nophp_files()
     *
     * @return array
     */
    public static function empty_nophp_files_provider(): array {
        return [
            'empty' => ['local/moodlecheck/tests/fixtures/empty.php'],
            'nophp' => ['local/moodlecheck/tests/fixtures/nophp.php'],
        ];
    }

    /**
     * Verify that method parameters are correctly interpreted no matter the definition style.
     *
     * @covers ::local_moodlecheck_functionarguments
     */
    public function test_j_method_multiline(): void {
        $file = __DIR__ . "/fixtures/phpdoc_method_multiline.php";

        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path($file, null);
        $result = $output->display_path($path, 'xml');

        // Convert results to XML Object.
        $xmlresult = new \DOMDocument();
        $xmlresult->loadXML($result);

        $xpath = new \DOMXpath($xmlresult);
        $found = $xpath->query('//file/error[@source="functionarguments"]');
        // TODO: Change to DOMNodeList::count() when php71 support is gone.
        $this->assertSame(0, $found->length); // All examples in fixtures are ok.
    }

    /**
     * Verify that the text format shown information about the severity of the problem (error vs warning)
     *
     * @covers \local_moodlecheck_renderer
     */
    public function test_text_format_errors_and_warnings(): void {
        $file = __DIR__ . "/fixtures/error_and_warning.php";

        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path($file, null);
        $result = $output->display_path($path, 'text');

        $this->assertStringContainsString('tests/fixtures/error_and_warning.php', $result);
    }

    /**
     * Verify that the html format shown information about the severity of the problem (error vs warning)
     *
     * @covers \local_moodlecheck_renderer
     */
    public function test_html_format_errors_and_warnings(): void {
        $file = __DIR__ . "/fixtures/error_and_warning.php";

        global $PAGE;
        $output = $PAGE->get_renderer('local_moodlecheck');
        $path = new local_moodlecheck_path($file, null);
        $result = $output->display_path($path, 'html');

        $this->assertStringContainsString('tests/fixtures/error_and_warning.php</span>', $result);
    }
}
