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
 * Renderer for local_moodlecheck
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Renderer for displaying local_moodlecheck
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_moodlecheck_renderer extends plugin_renderer_base {
    
    /**
     * Generates html to display one path validation results (invoked recursively)
     *
     * @param local_moodlecheck_path $path
     * @param int $depth
     * @return string
     */
    public function display_path(local_moodlecheck_path $path, $depth = 0) {
        $output = '';
        //$prefix = str_repeat(' ', $depth);
        $prefix = '';
        $path->validate();
        if ($path->is_dir()) {
            $output .= html_writer::start_tag('li', array('class' => 'directory'));
            $output .= html_writer::tag('span', $prefix. $path->get_path(), array('class' => 'dirname'));
            $output .= html_writer::start_tag('ul', array('class' => 'directory'));
            foreach ($path->get_subpaths() as $subpath) {
                $output .= $this->display_path($subpath, $depth+1);
            }
            $output .= html_writer::end_tag('li');
            $output .= html_writer::end_tag('ul');
        } else if ($path->is_file() && $path->get_file()->needs_validation()) {
            $output .= html_writer::start_tag('li', array('class' => 'file'));
            $output .= html_writer::tag('span', $prefix. $path->get_path(), array('class' => 'filename'));
            $output .= html_writer::start_tag('ul', array('class' => 'file'));
            $output .= $this->display_file_validation($path->get_file(), $depth + 1);
            $output .= html_writer::end_tag('ul');
            $output .= html_writer::end_tag('li');
        }
        return $output;
    }
    
    /**
     * Generates html to display one file validation results
     *
     * @param local_moodlecheck_file $file
     * @param int $depth
     * @return string
     */
    public function display_file_validation(local_moodlecheck_file $file, $depth = 0) {
        $output = '';
        //$prefix = str_repeat(' ', $depth);
        $prefix = '';
        $errors = $file->validate();
        foreach ($errors as $code => $suberrors) {
            foreach ($suberrors as $error) {
                $output .= html_writer::tag('li', $prefix. $error, array('class' => 'errorline'));
            }
        }
        return $output;
    }

}