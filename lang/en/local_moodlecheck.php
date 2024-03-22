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
 * Strings for local_moodlecheck
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Moodle PHPdoc check';
$string['path'] = 'Path(s)';
$string['ignorepath'] = 'Subpaths to ignore';
$string['path_help'] = 'Specify one or more files and/or directories to check as local paths from Moodle installation directory';
$string['check'] = 'Check';
$string['checkallrules'] = 'Check over all rules';
$string['checkselectedrules'] = 'Check over selected rules';
$string['error_default'] = 'Error: {$a}';
$string['linenum']  = 'Line <b>{$a}</b>: ';
$string['notificationerror'] = 'Found {$a} errors';
$string['notificationsuccess'] = 'Well done!';
$string['privacy:metadata'] = 'The Moodle PHPdoc check plugin does not store any personal data.';

$string['error_emptynophpfile'] = 'The file is empty or doesn\'t contain PHP code. Skipped.';

$string['rule_noinlinephpdocs'] = 'There are no comments starting with three or more slashes';
$string['error_noinlinephpdocs'] = 'Found comment starting with three or more slashes';

$string['error_phpdocsinvalidinlinetag'] = 'Invalid inline phpdocs tag <b>{$a->tag}</b> found';
$string['rule_phpdocsinvalidinlinetag'] = 'Inline phpdocs tags are valid';

$string['error_phpdocsuncurlyinlinetag'] = 'Inline phpdocs tag not enclosed with curly brackets <b>{$a->tag}</b> found';
$string['rule_phpdocsuncurlyinlinetag'] = 'Inline phpdocs tags are enclosed with curly brackets';

$string['error_phpdoccontentsinlinetag'] = 'Inline phpdocs tag <b>{$a->tag}</b> with incorrect contents found. It must match {@link [valid URL] [description (optional)]} or {@see [valid FQSEN] [description (optional)]}';
$string['rule_phpdoccontentsinlinetag'] = 'Inline phpdocs tags have correct contents';

$string['error_functionarguments'] = 'Phpdocs for function <b>{$a->function}</b> has incomplete parameters list';
$string['rule_functionarguments'] = 'Phpdocs for functions properly define all parameters';

$string['rule_categoryvalid'] = 'Category tag is valid';
$string['error_categoryvalid'] = 'Category <b>{$a->category}</b> is not valid';
