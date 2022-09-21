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
 * Main interface to Moodle PHP code check
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_once($CFG->dirroot. '/local/moodlecheck/locallib.php');

// Include all files from rules directory.
if ($dh = opendir($CFG->dirroot. '/local/moodlecheck/rules')) {
    while (($file = readdir($dh)) !== false) {
        if ($file != '.' && $file != '..') {
            $pathinfo = pathinfo($file);
            if (isset($pathinfo['extension']) && $pathinfo['extension'] == 'php') {
                require_once($CFG->dirroot. '/local/moodlecheck/rules/'. $file);
            }
        }
    }
    closedir($dh);
}

$pathlist = optional_param('path', '', PARAM_RAW);
$ignore = optional_param('ignorepath', '', PARAM_NOTAGS);
$checkall = optional_param('checkall', 'all', PARAM_NOTAGS);
$rules = optional_param_array('rule', [], PARAM_NOTAGS);

$pageparams = array();
if ($pathlist) {
    $pageparams['path'] = $pathlist;
}
if ($ignore) {
    $pageparams['ignorepath'] = $ignore;
}
if ($checkall) {
    $pageparams['checkall'] = $checkall;
}
if ($rules) {
    foreach ($rules as $name => $value) {
        $pageparams['rule[' . $name . ']'] = $value;
    }
}

admin_externalpage_setup('local_moodlecheck', $pageparams);

$form = new local_moodlecheck_form(new moodle_url('/local/moodlecheck/'));
$form->set_data((object)$pageparams);
if ($data = $form->get_data()) {
    redirect(new moodle_url('/local/moodlecheck/', $pageparams));
}

$output = $PAGE->get_renderer('local_moodlecheck');

echo $output->header();

if ($pathlist) {
    $paths = preg_split('/\s*\n\s*/', trim((string)$pathlist), -1, PREG_SPLIT_NO_EMPTY);
    $ignorepaths = preg_split('/\s*\n\s*/', trim((string)$ignore), -1, PREG_SPLIT_NO_EMPTY);
    if (isset($checkall) && $checkall == 'selected' && isset($rules)) {
        foreach ($rules as $code => $value) {
            local_moodlecheck_registry::enable_rule($code);
        }
    } else {
        local_moodlecheck_registry::enable_all_rules();
    }

    // Store result for later output.
    $result = [];

    foreach ($paths as $filename) {
        $path = new local_moodlecheck_path($filename, $ignorepaths);
        $result[] = $output->display_path($path);
    }

    echo $output->display_summary();

    foreach ($result as $line) {
        echo $line;
    }
}

$form->display();

echo $output->footer();
