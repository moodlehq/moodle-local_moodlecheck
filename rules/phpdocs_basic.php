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
 * Registering rules for phpdocs checking
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

local_moodlecheck_registry::add_rule('noemptysecondline')->set_callback('local_moodlecheck_noemptysecondline')
    ->set_severity('warning');
local_moodlecheck_registry::add_rule('filephpdocpresent')->set_callback('local_moodlecheck_filephpdocpresent');
local_moodlecheck_registry::add_rule('classesdocumented')->set_callback('local_moodlecheck_classesdocumented');
local_moodlecheck_registry::add_rule('functionsdocumented')->set_callback('local_moodlecheck_functionsdocumented');
local_moodlecheck_registry::add_rule('variablesdocumented')->set_callback('local_moodlecheck_variablesdocumented');
local_moodlecheck_registry::add_rule('constsdocumented')->set_callback('local_moodlecheck_constsdocumented');
local_moodlecheck_registry::add_rule('definesdocumented')->set_callback('local_moodlecheck_definesdocumented');
local_moodlecheck_registry::add_rule('noinlinephpdocs')->set_callback('local_moodlecheck_noinlinephpdocs');
local_moodlecheck_registry::add_rule('phpdocsfistline')->set_callback('local_moodlecheck_phpdocsfistline');
local_moodlecheck_registry::add_rule('functiondescription')->set_callback('local_moodlecheck_functiondescription');
local_moodlecheck_registry::add_rule('functionarguments')->set_callback('local_moodlecheck_functionarguments');
local_moodlecheck_registry::add_rule('variableshasvar')->set_callback('local_moodlecheck_variableshasvar');
local_moodlecheck_registry::add_rule('definedoccorrect')->set_callback('local_moodlecheck_definedoccorrect');
local_moodlecheck_registry::add_rule('filehascopyright')->set_callback('local_moodlecheck_filehascopyright');
local_moodlecheck_registry::add_rule('filehaslicense')->set_callback('local_moodlecheck_filehaslicense');
local_moodlecheck_registry::add_rule('phpdocsinvalidtag')->set_callback('local_moodlecheck_phpdocsinvalidtag');
local_moodlecheck_registry::add_rule('phpdocsnotrecommendedtag')->set_callback('local_moodlecheck_phpdocsnotrecommendedtag')
    ->set_severity('warning');
local_moodlecheck_registry::add_rule('phpdocsinvalidpathtag')->set_callback('local_moodlecheck_phpdocsinvalidpathtag')
    ->set_severity('warning');
local_moodlecheck_registry::add_rule('phpdocsinvalidinlinetag')->set_callback('local_moodlecheck_phpdocsinvalidinlinetag');
local_moodlecheck_registry::add_rule('phpdocsuncurlyinlinetag')->set_callback('local_moodlecheck_phpdocsuncurlyinlinetag');
local_moodlecheck_registry::add_rule('phpdoccontentsinlinetag')->set_callback('local_moodlecheck_phpdoccontentsinlinetag');

/**
 * Checks if the first line in the file has open tag and second line is not empty
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_noemptysecondline(local_moodlecheck_file $file) {
    $tokens = &$file->get_tokens();
    if ($tokens[0][0] == T_OPEN_TAG && !$file->is_whitespace_token(1) && $file->is_multiline_token(0) == 1) {
        return [];
    }
    return [['line' => 2]];
}

/**
 * Checks if file-level phpdocs block is present
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_filephpdocpresent(local_moodlecheck_file $file) {
    // This rule doesn't apply if the file is 1-artifact file (see #66).
    $artifacts = $file->get_artifacts();
    if (count($artifacts[T_CLASS]) + count($artifacts[T_INTERFACE]) + count($artifacts[T_TRAIT]) === 1) {
        return [];
    }
    if ($file->find_file_phpdocs() === false) {
        $tokens = &$file->get_tokens();
        for ($i = 0; $i < 90; $i++) {
            if (isset($tokens[$i]) && !in_array($tokens[$i][0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT])) {
                return [['line' => $file->get_line_number($i)]];
            }
        }
        // For some reason we cound not find the line number.
        return [['line' => '']];
    }
    return [];
}

/**
 * Checks if all classes have phpdocs blocks
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_classesdocumented(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_classes() as $class) {
        if ($class->phpdocs === false) {
            $errors[] = ['class' => $class->name, 'line' => $file->get_line_number($class->boundaries[0])];
        }
    }
    return $errors;
}

/**
 * Checks if all functions have phpdocs blocks.
 *
 * For methods of a class which extends another or implements interfaces, a violation in only a warning, since the
 * method might be overriding a sufficiently documented method. In all other cases, it is an error.
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_functionsdocumented(local_moodlecheck_file $file) {
    $isphpunitfile = preg_match('#/tests/.+_test\.php$#', $file->get_filepath());
    $errors = [];
    foreach ($file->get_functions() as $function) {
        if ($function->phpdocs === false) {
            // Exception is made for plain phpunit test methods MDLSITE-3282, MDLSITE-3856.
            $istestmethod = (strpos($function->name, 'test_') === 0 ||
                            stripos($function->name, 'setup') === 0 ||
                            stripos($function->name, 'teardown') === 0);

            $isinsubclass = $function->owner && ($function->owner->hasextends || $function->owner->hasimplements);

            if (!($isphpunitfile && $istestmethod)) {
                $error = [
                    'function' => $function->fullname,
                    'line' => $file->get_line_number($function->boundaries[0]),
                ];

                if ($isinsubclass) {
                    $error["severity"] = "warning";
                }

                $errors[] = $error;
            }
        }
    }
    return $errors;
}

/**
 * Checks if all variables have phpdocs blocks
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_variablesdocumented(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_variables() as $variable) {
        if ($variable->phpdocs === false) {
            $errors[] = ['variable' => $variable->fullname, 'line' => $file->get_line_number($variable->tid)];
        }
    }
    return $errors;
}

/**
 * Checks if all constants have phpdocs blocks
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_constsdocumented(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_constants() as $object) {
        if ($object->phpdocs === false) {
            $errors[] = ['object' => $object->fullname, 'line' => $file->get_line_number($object->tid)];
        }
    }
    return $errors;
}

/**
 * Checks if all variables have phpdocs blocks
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_definesdocumented(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_defines() as $object) {
        if ($object->phpdocs === false) {
            $errors[] = ['object' => $object->fullname, 'line' => $file->get_line_number($object->tid)];
        }
    }
    return $errors;
}

/**
 * Checks that no comment starts with three or more slashes
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_noinlinephpdocs(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_all_phpdocs() as $phpdocs) {
        if ($phpdocs->is_inline()) {
            $errors[] = ['line' => $phpdocs->get_line_number($file)];
        }
    }
    return $errors;
}

/**
 * Check that all the phpdoc tags used are valid ones
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_phpdocsinvalidtag(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_all_phpdocs() as $phpdocs) {
        foreach ($phpdocs->get_tags() as $tag) {
            $tag = preg_replace('|^@([^\s]*).*|s', '$1', $tag);
            if (!in_array($tag, local_moodlecheck_phpdocs::$validtags)) {
                $errors[] = [
                    'line' => $phpdocs->get_line_number($file, '@' . $tag),
                    'tag' => '@' . $tag, ];
            }
        }
    }
    return $errors;
}

/**
 * Check that all the phpdoc tags used are recommended ones
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_phpdocsnotrecommendedtag(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_all_phpdocs() as $phpdocs) {
        foreach ($phpdocs->get_tags() as $tag) {
            $tag = preg_replace('|^@([^\s]*).*|s', '$1', $tag);
            if (in_array($tag, local_moodlecheck_phpdocs::$validtags) &&
                    !in_array($tag, local_moodlecheck_phpdocs::$recommendedtags)) {
                $errors[] = [
                    'line' => $phpdocs->get_line_number($file, '@' . $tag),
                    'tag' => '@' . $tag, ];
            }
        }
    }
    return $errors;
}

/**
 * Check that all the path-restricted phpdoc tags used are in place
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_phpdocsinvalidpathtag(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_all_phpdocs() as $phpdocs) {
        foreach ($phpdocs->get_tags() as $tag) {
            $tag = preg_replace('|^@([^\s]*).*|s', '$1', $tag);
            if (in_array($tag, local_moodlecheck_phpdocs::$validtags) &&
                    in_array($tag, local_moodlecheck_phpdocs::$recommendedtags) &&
                    isset(local_moodlecheck_phpdocs::$pathrestrictedtags[$tag])) {
                // Verify file path matches some of the valid paths for the tag.
                if (!preg_filter(local_moodlecheck_phpdocs::$pathrestrictedtags[$tag], '$0', $file->get_filepath())) {
                    $errors[] = [
                        'line' => $phpdocs->get_line_number($file, '@' . $tag),
                        'tag' => '@' . $tag, ];
                }
            }
        }
    }
    return $errors;
}

/**
 * Check that all the inline phpdoc tags found are valid
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_phpdocsinvalidinlinetag(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_all_phpdocs() as $phpdocs) {
        if ($inlinetags = $phpdocs->get_inline_tags(false)) {
            foreach ($inlinetags as $inlinetag) {
                if (!in_array($inlinetag, local_moodlecheck_phpdocs::$inlinetags)) {
                    $errors[] = [
                        'line' => $phpdocs->get_line_number($file, '@' . $inlinetag),
                        'tag' => '@' . $inlinetag, ];
                }
            }
        }
    }
    return $errors;
}

/**
 * Check that all the valid inline tags are properly enclosed with curly brackets
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_phpdocsuncurlyinlinetag(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_all_phpdocs() as $phpdocs) {
        if ($inlinetags = $phpdocs->get_inline_tags(false)) {
            $curlyinlinetags = $phpdocs->get_inline_tags(true);
            // The difference will tell us which ones are nor enclosed by curly brackets.
            foreach ($curlyinlinetags as $remove) {
                foreach ($inlinetags as $k => $v) {
                    if ($v === $remove) {
                        unset($inlinetags[$k]);
                        break;
                    }
                }
            }
            foreach ($inlinetags as $inlinetag) {
                if (in_array($inlinetag, local_moodlecheck_phpdocs::$inlinetags)) {
                    $errors[] = [
                        'line' => $phpdocs->get_line_number($file, ' @' . $inlinetag),
                        'tag' => '@' . $inlinetag, ];
                }
            }
        }
    }
    return $errors;
}

/**
 * Check that all the valid inline curly tags have correct contents.
 *
 * @link https://docs.phpdoc.org/3.0/guide/references/phpdoc/inline-tags/link.html#link phpDocumentor@link
 * @link https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/see.html#see          phpDocumentor@see
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_phpdoccontentsinlinetag(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_all_phpdocs() as $phpdocs) {
        if ($curlyinlinetags = $phpdocs->get_inline_tags(true, true)) {
            foreach ($curlyinlinetags as $curlyinlinetag) {
                // Split into tag and URL/FQSEN. Limit of 3 because the 3rd part can be the description.
                list($tag, $uriorfqsen) = explode(' ', $curlyinlinetag, 3);
                if (in_array($tag, local_moodlecheck_phpdocs::$inlinetags)) {
                    switch ($tag) {
                        case 'link':
                            // Must be a correct URL with optional description.
                            if (!filter_var($uriorfqsen, FILTER_VALIDATE_URL)) {
                                $errors[] = [
                                    'line' => $phpdocs->get_line_number($file, ' {@' . $curlyinlinetag),
                                    'tag' => '{@' . $curlyinlinetag . '}', ];
                            }
                            break;
                        case 'see': // Must be 1-word (with some chars allowed - FQSEN only.
                            if (str_word_count($uriorfqsen, 0, '\()-_:>$012345789') !== 1) {
                                $errors[] = [
                                    'line' => $phpdocs->get_line_number($file, ' {@' . $curlyinlinetag),
                                    'tag' => '{@' . $curlyinlinetag . '}', ];
                            }
                            break;
                    }
                }
            }
        }
    }
    return $errors;
}

/**
 * Makes sure that file-level phpdocs and all classes have one-line short description
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_phpdocsfistline(local_moodlecheck_file $file) {
    $errors = [];

    if (($phpdocs = $file->find_file_phpdocs()) && !$file->find_file_phpdocs()->get_shortdescription()) {
        $errors[] = [
            'line' => $phpdocs->get_line_number($file),
            'object' => 'file',
        ];
    }
    foreach ($file->get_classes() as $class) {
        if ($class->phpdocs && !$class->phpdocs->get_shortdescription()) {
            $errors[] = [
                'line' => $class->phpdocs->get_line_number($file),
                'object' => 'class '.$class->name,
            ];
        }
    }
    return $errors;
}

/**
 * Makes sure that all functions have descriptions
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_functiondescription(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_functions() as $function) {
        if ($function->phpdocs !== false && !strlen($function->phpdocs->get_description())) {
            $errors[] = [
                'line' => $function->phpdocs->get_line_number($file),
                'object' => $function->name,
            ];
        }
    }
    return $errors;
}

/**
 * Checks that all functions have proper arguments in phpdocs
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_functionarguments(local_moodlecheck_file $file) {
    $errors = [];

    foreach ($file->get_functions() as $function) {
        if ($function->phpdocs !== false) {
            $documentedarguments = $function->phpdocs->get_params();
            $match = (count($documentedarguments) == count($function->arguments));
            for ($i = 0; $match && $i < count($documentedarguments); $i++) {
                if (count($documentedarguments[$i]) < 2) {
                    // Must be at least type and parameter name.
                    $match = false;
                } else {
                    $expectedtype = local_moodlecheck_normalise_function_type((string) $function->arguments[$i][0]);
                    $expectedparam = (string)$function->arguments[$i][1];
                    $documentedtype = local_moodlecheck_normalise_function_type((string) $documentedarguments[$i][0]);
                    $documentedparam = $documentedarguments[$i][1];

                    $typematch = $expectedtype === $documentedtype;
                    $parammatch = $expectedparam === $documentedparam;
                    if ($typematch && $parammatch) {
                        continue;
                    }

                    // Documented types can be a collection (| separated).
                    foreach (explode('|', $documentedtype) as $documentedtype) {
                        // Ignore null. They cannot match any type in function.
                        if (trim($documentedtype) === 'null') {
                            continue;
                        }

                        if (strlen($expectedtype) && $expectedtype !== $documentedtype) {
                            // It could be a type hinted array.
                            if ($expectedtype !== 'array' || substr($documentedtype, -2) !== '[]') {
                                $match = false;
                            }
                        } else if ($documentedtype === 'type') {
                            $match = false;
                        } else if ($expectedparam !== $documentedparam) {
                            $match = false;
                        }
                    }
                }
            }
            $documentedreturns = $function->phpdocs->get_params('return');
            for ($i = 0; $match && $i < count($documentedreturns); $i++) {
                if (empty($documentedreturns[$i][0]) || $documentedreturns[$i][0] == 'type') {
                    $match = false;
                }
            }
            if (!$match) {
                $errors[] = [
                    'line' => $function->phpdocs->get_line_number($file, '@param'),
                    'function' => $function->fullname, ];
            }
        }
    }
    return $errors;
}

/**
 * Normalise function type to be able to compare it.
 *
 * @param string $typelist
 * @return string
 */
function local_moodlecheck_normalise_function_type(string $typelist): string {
    // Normalise a nullable type to `null|type` as these are just shorthands.
    $typelist = str_replace(
        '?',
        'null|',
        $typelist
    );

    // PHP 8 treats namespaces as single token. So we are going to undo this here
    // and continue returning only the final part of the namespace. Someday we'll
    // move to use full namespaces here, but not for now (we are doing the same,
    // in other parts of the code, when processing phpdoc blocks).
    $types = explode('|', $typelist);

    // Namespaced typehint, potentially sub-namespaced.
    // We need to strip namespacing as this area just isn't that smart.
    $types = array_map(
        function($type) {
            if (strpos((string)$type, '\\') !== false) {
                $type = substr($type, strrpos($type, '\\') + 1);
            }
            return $type;
        },
        $types
    );
    sort($types);

    return implode('|', $types);
}

/**
 * Checks that all variables have proper \var token in phpdoc block
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_variableshasvar(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_variables() as $variable) {
        if ($variable->phpdocs !== false) {
            $documentedvars = $variable->phpdocs->get_params('var', 2);
            if (!count($documentedvars) || $documentedvars[0][0] == 'type') {
                $errors[] = [
                    'line' => $variable->phpdocs->get_line_number($file, '@var'),
                    'variable' => $variable->fullname, ];
            }
        }
    }
    return $errors;
}

/**
 * Checks that all define statement have constant name in phpdoc block
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_definedoccorrect(local_moodlecheck_file $file) {
    $errors = [];
    foreach ($file->get_defines() as $object) {
        if ($object->phpdocs !== false) {
            if (!preg_match('/^\s*'.$object->name.'\s+-\s+(.*)/', $object->phpdocs->get_description(), $matches) ||
                    !strlen(trim($matches[1]))) {
                $errors[] = ['line' => $object->phpdocs->get_line_number($file), 'object' => $object->fullname];
            }
        }
    }
    return $errors;
}

/**
 * Makes sure that files have copyright tag
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_filehascopyright(local_moodlecheck_file $file) {
    $phpdocs = $file->find_file_phpdocs();
    if ($phpdocs && !count($phpdocs->get_tags('copyright', true))) {
        return [['line' => $phpdocs->get_line_number($file, '@copyright')]];
    }
    return [];
}

/**
 * Makes sure that files have license tag
 *
 * @param local_moodlecheck_file $file
 * @return array of found errors
 */
function local_moodlecheck_filehaslicense(local_moodlecheck_file $file) {
    $phpdocs = $file->find_file_phpdocs();
    if ($phpdocs && !count($phpdocs->get_tags('license', true))) {
        return [['line' => $phpdocs->get_line_number($file, '@license')]];
    }
    return [];
}
