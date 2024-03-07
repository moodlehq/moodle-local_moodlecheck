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
 * Registering rules for checking phpdocs related to package and category tags
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

local_moodlecheck_registry::add_rule('categoryvalid')->set_callback('local_moodlecheck_categoryvalid');

/**
 * Checks that wherever the category token is specified it is valid
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_categoryvalid(local_moodlecheck_file $file) {
    $errors = [];
    $allowedcategories = local_moodlecheck_get_categories($file);
    foreach ($file->get_all_phpdocs() as $phpdoc) {
        foreach ($phpdoc->get_tags('category') as $category) {
            if (!in_array($category, $allowedcategories)) {
                $errors[] = ['line' => $phpdoc->get_line_number($file, '@category'), 'category' => $category];
            }
        }
    }
    return $errors;
}

/**
 * Reads the list of Core APIs from internet (or local copy) and returns the list of categories
 *
 * @param bool $forceoffline Disable fetching from the live docs site, useful for testing.
 *
 * @return array
 */
function &local_moodlecheck_get_categories($forceoffline = false) {
    global $CFG;
    static $allcategories = [];
    if (empty($allcategories)) {
        $lastsavedtime = get_user_preferences('local_moodlecheck_categoriestime');
        $lastsavedvalue = get_user_preferences('local_moodlecheck_categoriesvalue');
        if ($lastsavedtime > time() - 24 * 60 * 60) {
            // Update only once per day.
            $allcategories = explode(',', $lastsavedvalue);
        } else {
            $allcategories = [];
            $filecontent = false;
            if (!$forceoffline) {
                $filecontent = @file_get_contents("https://moodledev.io/docs/apis");
            }
            if (empty($filecontent)) {
                $filecontent = file_get_contents($CFG->dirroot . '/local/moodlecheck/rules/coreapis.txt');
            }
            // Remove newlines, easier for the regular expression.
            $filecontent = preg_replace('|[\r\n]|', '', $filecontent);
            preg_match_all('|<h3[^>]+>\s*.+?API\s*\(([^\)]+)\)\s*<a|i', $filecontent, $matches);
            foreach ($matches[1] as $match) {
                $allcategories[] = trim(strip_tags(strtolower($match)));
            }
            set_user_preference('local_moodlecheck_categoriestime', time());
            set_user_preference('local_moodlecheck_categoriesvalue', join(',', $allcategories));
        }
    }
    return $allcategories;
}
