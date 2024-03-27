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
 * File handling in moodlecheck
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Handles one file being validated
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_moodlecheck_file {
    private const MODIFIERS = [T_ABSTRACT, T_PRIVATE, T_PUBLIC, T_PROTECTED, T_STATIC, T_VAR, T_FINAL, T_CONST];

    protected $filepath = null;
    protected $needsvalidation = null;
    protected $errors = null;
    protected $tokens = null;
    protected $tokenscount = 0;
    protected $classes = null;
    protected $interfaces = null;
    protected $traits = null;
    protected $functions = null;
    protected $filephpdocs = null;
    protected $allphpdocs = null;
    protected $variables = null;

    /**
     * Creates an object from path to the file
     *
     * @param string $filepath
     */
    public function __construct($filepath) {
        $this->filepath = str_replace(DIRECTORY_SEPARATOR, "/", $filepath);
    }

    /**
     * Cleares all cached stuff to free memory
     */
    protected function clear_memory() {
        $this->tokens = null;
        $this->tokenscount = 0;
        $this->classes = null;
        $this->interfaces = null;
        $this->traits = null;
        $this->functions = null;
        $this->filephpdocs = null;
        $this->allphpdocs = null;
        $this->variables = null;
    }

    /**
     * Returns true if this file is inside specified directory
     *
     * @param string $dirpath
     * @return bool
     */
    public function is_in_dir($dirpath) {
        // Normalize dir path to also work with Windows style directory separators...
        $dirpath = str_replace(DIRECTORY_SEPARATOR, "/", $dirpath);
        if (substr($dirpath, -1) != '/') {
            $dirpath .= '/';
        }
        return substr($this->filepath, 0, strlen($dirpath)) == $dirpath;
    }

    /**
     * Retuns true if the file needs validation (is PHP file)
     *
     * @return bool
     */
    public function needs_validation() {
        if ($this->needsvalidation === null) {
            $this->needsvalidation = true;
            $pathinfo = pathinfo($this->filepath);
            if (empty($pathinfo['extension']) || ($pathinfo['extension'] != 'php' && $pathinfo['extension'] != 'inc')) {
                $this->needsvalidation = false;
            }
        }
        return $this->needsvalidation;
    }

    /**
     * Validates a file over registered rules and returns an array of errors
     *
     * @return array
     */
    public function validate() {
        if ($this->errors !== null) {
            return $this->errors;
        }
        $this->errors = [];
        if (!$this->needs_validation()) {
            return $this->errors;
        }
        // If the file doesn't have tokens, has one or misses open tag, report it as one more error and stop processing.
        if (!$this->get_tokens() ||
                count($this->get_tokens()) === 1 ||
                (isset($this->get_tokens()[0][0]) && $this->get_tokens()[0][0] !== T_OPEN_TAG)) {
            $this->errors[] = [
                'line' => 1,
                'severity' => 'error',
                'message' => get_string('error_emptynophpfile', 'local_moodlecheck'),
                'source' => '&#x00d8;',
            ];
            return $this->errors;
        }
        foreach (local_moodlecheck_registry::get_enabled_rules() as $code => $rule) {
            $ruleerrors = $rule->validatefile($this);
            if (count($ruleerrors)) {
                $this->errors = array_merge($this->errors, $ruleerrors);
            }
        }
        $this->clear_memory();
        return $this->errors;
    }

    /**
     * Return the filepath of the file.
     *
     * @return string
     */
    public function get_filepath() {
        return $this->filepath;
    }

    /**
     * Returns a file contents converted to array of tokens.
     *
     * Each token is an array with two elements: code of token and text
     * For simple 1-character tokens the code is -1
     *
     * @return array
     */
    public function &get_tokens() {
        if ($this->tokens === null) {
            $source = file_get_contents($this->filepath);
            $this->tokens = @token_get_all($source);
            $this->tokenscount = count($this->tokens);
            $inquotes = -1;
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if (is_string($this->tokens[$tid])) {
                    // Simple 1-character token.
                    $this->tokens[$tid] = [-1, $this->tokens[$tid]];
                }
                // And now, for the purpose of this project we don't need strings with variables inside to be parsed
                // so when we find string in double quotes that is split into several tokens and combine all content in one token.
                if ($this->tokens[$tid][0] == -1 && $this->tokens[$tid][1] == '"') {
                    if ($inquotes == -1) {
                        $inquotes = $tid;
                        $this->tokens[$tid][0] = T_STRING;
                    } else {
                        $this->tokens[$inquotes][1] .= $this->tokens[$tid][1];
                        $this->tokens[$tid] = [T_WHITESPACE, ''];
                        $inquotes = -1;
                    }
                } else if ($inquotes > -1) {
                    $this->tokens[$inquotes][1] .= $this->tokens[$tid][1];
                    $this->tokens[$tid] = [T_WHITESPACE, ''];
                }
            }
        }
        return $this->tokens;
    }

    /**
     * Returns all artifacts (classes, interfaces, traits) found in file
     *
     * Returns 3 arrays (classes, interfaces and traits) of objects where each element represents an artifact:
     * ->type : token type of the artifact (T_CLASS, T_INTERFACE, T_TRAIT)
     * ->typestring : type of the artifact as a string ('class', 'interface', 'trait')
     * ->name : name of the artifact
     * ->tagpair : array of two elements: id of token { for the class and id of token } (false if not found)
     * ->phpdocs : phpdocs for this artifact (instance of local_moodlecheck_phpdocs or false if not found)
     * ->boundaries : array with ids of first and last token for this artifact.
     * ->hasextends : boolean indicating whether this artifact has an `extends` clause
     * ->hasimplements : boolean indicating whether this artifact has an `implements` clause
     *
     * @return array with 3 elements (classes, interfaces & traits), each being an array.
     */
    public function get_artifacts() {
        $types = [T_CLASS, T_INTERFACE, T_TRAIT]; // We are interested on these.
        $artifacts = array_combine($types, $types);
        if ($this->classes === null) {
            $this->classes = [];
            $this->interfaces = [];
            $this->traits = [];
            $tokens = &$this->get_tokens();
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if (isset($artifacts[$this->tokens[$tid][0]])) {
                    if ($this->previous_nonspace_token($tid) === "::") {
                        // Skip use of the ::class special constant.
                        continue;
                    }

                    if ($this->previous_nonspace_token($tid) == 'new') {
                        // This looks to be an anonymous class.

                        $tpid = $tid; // Let's keep the original $tid and use own for anonymous searches.
                        if ($this->next_nonspace_token($tpid) == '(') {
                            // It may be an anonymous class with parameters, let's skip them
                            // by advancing till we find the corresponding bracket closing token.
                            $level = 0; // To control potential nesting of brackets within the params.
                            while ($tpid = $this->next_nonspace_token($tpid, true)) {
                                if ($this->tokens[$tpid][1] == '(') {
                                    $level++;
                                }
                                if ($this->tokens[$tpid][1] == ')') {
                                    $level--;
                                    // We are back to level 0, we are done (have walked over all params).
                                    if ($level === 0) {
                                        $tpid = $tpid;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($this->next_nonspace_token($tpid) == '{') {
                            // An anonymous class in the format `new class {`.
                            continue;
                        }

                        if ($this->next_nonspace_token($tpid) == 'extends') {
                            // An anonymous class in the format `new class extends otherclasses {`.
                            continue;
                        }

                        if ($this->next_nonspace_token($tpid) == 'implements') {
                            // An anonymous class in the format `new class implements someinterface {`.
                            continue;
                        }
                    }
                    $artifact = new stdClass();
                    $artifact->type = $artifacts[$this->tokens[$tid][0]];
                    $artifact->typestring = $this->tokens[$tid][1];

                    $artifact->tid = $tid;
                    $artifact->name = $this->next_nonspace_token($tid);
                    $artifact->phpdocs = $this->find_preceeding_phpdoc($tid);
                    $artifact->tagpair = $this->find_tag_pair($tid, '{', '}');

                    $artifact->hasextends = false;
                    $artifact->hasimplements = false;

                    if ($artifact->tagpair) {
                        // Iterate over the remaining tokens in the class definition (until opening {).
                        foreach (array_slice($this->tokens, $tid, $artifact->tagpair[0] - $tid) as $token) {
                            if ($token[0] == T_EXTENDS) {
                                $artifact->hasextends = true;
                            }
                            if ($token[0] == T_IMPLEMENTS) {
                                $artifact->hasimplements = true;
                            }
                        }
                    }

                    $artifact->boundaries = $this->find_object_boundaries($artifact);
                    switch ($artifact->type) {
                        case T_CLASS:
                            $this->classes[] = $artifact;
                            break;
                        case T_INTERFACE:
                            $this->interfaces[] = $artifact;
                            break;
                        case T_TRAIT:
                            $this->traits[] = $artifact;
                            break;
                    }
                }
            }
        }
        return [T_CLASS => $this->classes, T_INTERFACE => $this->interfaces, T_TRAIT => $this->traits];
    }

    /**
     * Like {@see get_artifacts()}, but returns classes, interfaces and traits in a single flat array.
     *
     * @return stdClass[]
     * @see get_artifacts()
     */
    public function get_artifacts_flat(): array {
        $artifacts = $this->get_artifacts();
        return array_merge($artifacts[T_CLASS], $artifacts[T_INTERFACE], $artifacts[T_TRAIT]);
    }

    /**
     * Returns all classes found in file
     *
     * Returns array of objects where each element represents a class:
     * $class->name : name of the class
     * $class->tagpair : array of two elements: id of token { for the class and id of token } (false if not found)
     * $class->phpdocs : phpdocs for this class (instance of local_moodlecheck_phpdocs or false if not found)
     * $class->boundaries : array with ids of first and last token for this class
     */
    public function &get_classes() {
        return $this->get_artifacts()[T_CLASS];
    }

    /**
     * Returns all functions (including class methods) found in file
     *
     * Returns array of objects where each element represents a function:
     * $function->tid : token id of the token 'function'
     * $function->name : name of the function
     * $function->phpdocs : phpdocs for this function (instance of local_moodlecheck_phpdocs or false if not found)
     * TODO: Delete this because it's not used anymore (2023). See #97
     * $function->class : containing class object (false if this is not a class method)
     * $function->owner : containing artifact object (class, interface, trait, or false if this is not a method)
     * $function->fullname : name of the function with class name (if applicable)
     * $function->accessmodifiers : tokens like static, public, protected, abstract, etc.
     * $function->tagpair : array of two elements: id of token { for the function and id of token } (false if not found)
     * $function->argumentstoken : array of tokens found inside function arguments
     * $function->arguments : array of function arguments where each element is [typename, variablename]
     * $function->boundaries : array with ids of first and last token for this function
     *
     * @return array
     */
    public function &get_functions() {
        if ($this->functions === null) {
            $this->functions = [];
            $tokens = &$this->get_tokens();
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if ($this->tokens[$tid][0] == T_USE) {
                    // Skip the entire use statement, to avoid interpreting "use function" as a function.
                    $tid = $this->end_of_statement($tid);
                    continue;
                }

                if ($this->tokens[$tid][0] == T_FUNCTION) {
                    $function = new stdClass();
                    $function->tid = $tid;
                    $function->fullname = $function->name = $this->next_nonspace_token($tid, false, ['&']);

                    // Skip anonymous functions.
                    if ($function->name == '(') {
                        continue;
                    }
                    $function->phpdocs = $this->find_preceeding_phpdoc($tid);
                    $function->class = $this->is_inside_class($tid);
                    $function->owner = $this->is_inside_artifact($tid);
                    if ($function->owner !== false) {
                        $function->fullname = $function->owner->name . '::' . $function->name;
                    }
                    $function->accessmodifiers = $this->find_access_modifiers($tid);
                    if (!in_array(T_ABSTRACT, $function->accessmodifiers)) {
                        $function->tagpair = $this->find_tag_pair($tid, '{', '}');
                    } else {
                        $function->tagpair = false;
                    }

                    $argumentspair = $this->find_tag_pair($tid, '(', ')', ['{', ';']);
                    if ($argumentspair !== false && $argumentspair[1] - $argumentspair[0] > 1) {
                        $function->argumentstokens = $this->break_tokens_by(
                            array_slice($tokens, $argumentspair[0] + 1, $argumentspair[1] - $argumentspair[0] - 1) );
                    } else {
                        $function->argumentstokens = [];
                    }
                    $function->arguments = [];
                    foreach ($function->argumentstokens as $argtokens) {
                        // If the token is completely empty then it's not an argument. This happens, for example, with
                        // trailing commas in parameters, allowed since PHP 8.0 and break_tokens_by() returns it that way.
                        if (empty($argtokens)) {
                            continue;
                        }
                        $possibletypes = [];
                        $variable = null;
                        $splat = false;

                        if (PHP_VERSION_ID < 80000) {
                            $maxindex = array_key_last($argtokens);
                            // In PHP 7.4 and earlier, the namespace was parsed separately, for example:
                            // \core\course would be come '\', 'core', '\', 'course'.
                            // From PHP 8.0 this becomes '\core\course'.
                            // To address this we modify the tokens to match the PHP 8.0 format.
                            // This is a bit of a hack, but it works.
                            // Note: argtokens contains arrays of [token index, string content, line number].
                            for ($j = 0; $j < $maxindex; $j++) {
                                if ($argtokens[$j][0] === T_NS_SEPARATOR || $argtokens[$j][0] === T_STRING) {
                                    $argtokens[$j][0] = T_STRING;
                                    $initialtoken = $j;
                                    for ($namespacesearch = $j + 1; $namespacesearch < $maxindex; $namespacesearch++) {
                                        switch ($argtokens[$namespacesearch][0]) {
                                            case T_STRING:
                                            case T_NS_SEPARATOR:
                                                break;
                                            default:
                                                break 2;
                                        }
                                        $argtokens[$initialtoken][1] .= $argtokens[$namespacesearch][1];
                                        unset($argtokens[$namespacesearch]);
                                        $j = $namespacesearch;
                                    }
                                }
                            }
                        }
                        $argtokens = array_values($argtokens);

                        for ($j = 0; $j < count($argtokens); $j++) {
                            if (version_compare(PHP_VERSION, '8.1.0') >= 0) {
                                // T_READONLY introduced in PHP 8.1.
                                if ($argtokens[$j][0] === T_READONLY) {
                                    continue;
                                }
                            }
                            switch ($argtokens[$j][0]) {
                                // Skip any whitespace, or argument visibility.
                                case T_COMMENT:
                                case T_DOC_COMMENT:
                                case T_WHITESPACE:
                                case T_PUBLIC:
                                case T_PROTECTED:
                                case T_PRIVATE:
                                    continue 2;
                                case T_VARIABLE:
                                    // The variale name, adding in the vardiadic if required.
                                    $variable = ($splat) ? '...' . $argtokens[$j][1] : $argtokens[$j][1];
                                    continue 2;
                                case T_ELLIPSIS:
                                    // For example ...$example
                                    // Variadic function.
                                    $splat = true;
                                    continue 2;
                            }
                            switch ($argtokens[$j][1]) {
                                case '|':
                                    // Union types.
                                case '&':
                                    // Return by reference.
                                    continue 2;
                                case '?':
                                    // Nullable type.
                                    $possibletypes[] = 'null';
                                    continue 2;
                                case '=':
                                    // Default value.
                                    $j = count($argtokens);
                                    continue 2;
                            }

                            $possibletypes[] = $argtokens[$j][1];
                        }

                        $type = implode('|', $possibletypes);

                        $function->arguments[] = [$type, $variable];
                    }
                    $function->boundaries = $this->find_object_boundaries($function);
                    $this->functions[] = $function;
                }
            }
        }
        return $this->functions;
    }

    /**
     * Returns all class properties (variables) found in file
     *
     * Returns array of objects where each element represents a variable:
     * $variable->tid : token id of the token with variable name
     * $variable->name : name of the variable (starts with $)
     * $variable->phpdocs : phpdocs for this variable (instance of local_moodlecheck_phpdocs or false if not found)
     * $variable->class : containing class object
     * $variable->fullname : name of the variable with class name (i.e. classname::$varname)
     * $variable->accessmodifiers : tokens like static, public, protected, abstract, etc.
     * $variable->boundaries : array with ids of first and last token for this variable
     *
     * @return array
     */
    public function &get_variables() {
        if ($this->variables === null) {
            $this->variables = [];
            $this->get_tokens();
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if ($this->tokens[$tid][0] == T_VARIABLE && ($class = $this->is_inside_class($tid)) &&
                        !$this->is_inside_function($tid)) {
                    $variable = new stdClass;
                    $variable->tid = $tid;
                    $variable->name = $this->tokens[$tid][1];
                    $variable->class = $class;
                    $variable->fullname = $class->name . '::' . $variable->name;

                    $beforetype = $this->skip_preceding_type($tid);
                    $variable->accessmodifiers = $this->find_access_modifiers($beforetype);
                    $variable->phpdocs = $this->find_preceeding_phpdoc($beforetype);

                    $variable->boundaries = $this->find_object_boundaries($variable);
                    $this->variables[] = $variable;
                }
            }
        }
        return $this->variables;
    }

    /**
     * Finds and returns object boundaries
     *
     * $obj is an object representing function, class or variable. This function
     * returns token ids for the very first token applicable to this object
     * to the very last
     *
     * @param stdClass $obj
     * @return array
     */
    public function find_object_boundaries($obj) {
        $boundaries = [$obj->tid, $obj->tid];
        $tokens = &$this->get_tokens();
        if (!empty($obj->tagpair)) {
            $boundaries[1] = $obj->tagpair[1];
        } else {
            // Find the next ; char.
            for ($i = $boundaries[1]; $i < $this->tokenscount; $i++) {
                if ($tokens[$i][1] == ';') {
                    $boundaries[1] = $i;
                    break;
                }
            }
        }
        if (isset($obj->phpdocs) && $obj->phpdocs instanceof local_moodlecheck_phpdocs) {
            $boundaries[0] = $obj->phpdocs->get_original_token_id();
        } else {
            // Walk back until we meet one of the characters that means that we are outside of the object.
            for ($i = $boundaries[0] - 1; $i >= 0; $i--) {
                $token = $tokens[$i];
                if (in_array($token[0], [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG])) {
                    break;
                } else if (in_array($token[1], ['{', '}', '(', ';', ',', '['])) {
                    break;
                }
            }
            // Walk forward to the next meaningful token skipping all spaces and comments.
            for ($i = $i + 1; $i < $boundaries[0]; $i++) {
                if (!in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                    break;
                }
            }
            $boundaries[0] = $i;
        }
        return $boundaries;
    }

    /**
     * Checks if the token with id $tid in inside some class
     *
     * @param int $tid
     * @return stdClass|false containing class or false if this is not a member
     */
    public function is_inside_class($tid) {
        $classes = &$this->get_classes();
        $classescnt = count($classes);
        for ($clid = 0; $clid < $classescnt; $clid++) {
            if ($classes[$clid]->boundaries[0] <= $tid && $classes[$clid]->boundaries[1] >= $tid) {
                return $classes[$clid];
            }
        }
        return false;
    }

    /**
     * Checks if the token with id $tid in inside some artifact (class, interface, or trait).
     *
     * @param int $tid
     * @return stdClass|false containing artifact or false if this is not a member
     */
    public function is_inside_artifact(int $tid) {
        $artifacts = $this->get_artifacts_flat();
        foreach ($artifacts as $artifact) {
            if ($artifact->boundaries[0] <= $tid && $artifact->boundaries[1] >= $tid) {
                return $artifact;
            }
        }
        return false;
    }

    /**
     * Checks if the token with id $tid in inside some function or class method
     *
     * @param int $tid
     * @return stdClass|false containing function or false if this is not inside a function
     */
    public function is_inside_function($tid) {
        $functions = &$this->get_functions();
        $functionscnt = count($functions);
        for ($fid = 0; $fid < $functionscnt; $fid++) {
            if ($functions[$fid]->boundaries[0] <= $tid && $functions[$fid]->boundaries[1] >= $tid) {
                return $functions[$fid];
            }
        }
        return false;
    }

    /**
     * Checks if token with id $tid is a whitespace
     *
     * @param int $tid
     * @return boolean
     */
    public function is_whitespace_token($tid) {
        $this->get_tokens();
        return (isset($this->tokens[$tid][0]) && $this->tokens[$tid][0] == T_WHITESPACE);
    }

    /**
     * Returns how many line feeds are in this token
     *
     * @param int $tid
     * @return int
     */
    public function is_multiline_token($tid) {
        $this->get_tokens();
        return substr_count($this->tokens[$tid][1], "\n");
    }

    /**
     * Returns the first token which is not whitespace following the token with id $tid
     *
     * Also returns false if no meaningful token found till the end of file
     *
     * @param int $tid
     * @param bool $returnid
     * @param array $alsoignore
     * @return int|false
     */
    public function next_nonspace_token($tid, $returnid = false, $alsoignore = []) {
        $this->get_tokens();
        for ($i = $tid + 1; $i < $this->tokenscount; $i++) {
            if (!$this->is_whitespace_token($i) && !in_array($this->tokens[$i][1], $alsoignore)) {
                if ($returnid) {
                    return $i;
                } else {
                    return $this->tokens[$i][1];
                }
            }
        }
        return false;
    }

    /**
     * Returns the first token which is not whitespace before the token with id $tid
     *
     * Also returns false if no meaningful token found till the beginning of file
     *
     * @param int $tid
     * @param bool $returnid
     * @param array $alsoignore
     * @return int|false
     */
    public function previous_nonspace_token($tid, $returnid = false, $alsoignore = []) {
        $this->get_tokens();
        for ($i = $tid - 1; $i > 0; $i--) {
            if (!$this->is_whitespace_token($i) && !in_array($this->tokens[$i][1], $alsoignore)) {
                if ($returnid) {
                    return $i;
                } else {
                    return $this->tokens[$i][1];
                }
            }
        }
        return false;
    }

    /**
     * Returns the next semicolon or close tag following $tid, or the last token of the file, whichever comes first.
     *
     * @param int $tid starting token
     * @return int index of the next semicolon or close tag following $tid, or the last token of the file, whichever
     *                 comes first
     */
    public function end_of_statement($tid) {
        for (; $tid < $this->tokenscount; $tid++) {
            if ($this->tokens[$tid][1] == ";" || $this->tokens[$tid][0] == T_CLOSE_TAG) {
                // Semicolons and close tags (?&gt;) end statements.
                return $tid;
            }
        }
        // EOF also ends statements.
        return $tid;
    }

    /**
     * Returns all modifiers (private, public, static, ...) preceeding token with id $tid
     *
     * @param int $tid
     * @return array
     */
    public function find_access_modifiers($tid) {
        $tokens = &$this->get_tokens();
        $modifiers = [];
        for ($i = $tid - 1; $i >= 0; $i--) {
            if ($this->is_whitespace_token($i)) {
                // Skip.
                continue;
            } else if (in_array($tokens[$i][0], self::MODIFIERS)) {
                $modifiers[] = $tokens[$i][0];
            } else {
                break;
            }
        }
        return $modifiers;
    }

    /**
     * Finds phpdocs preceeding the token with id $tid
     *
     * skips words abstract, private, public, protected and non-multiline whitespaces
     *
     * @param int $tid
     * @return local_moodlecheck_phpdocs|false
     */
    public function find_preceeding_phpdoc($tid) {
        $tokens = &$this->get_tokens();
        $modifiers = $this->find_access_modifiers($tid);

        for ($i = $tid - 1; $i >= 0; $i--) {
            if ($this->is_whitespace_token($i)) {
                if ($this->is_multiline_token($i) > 1) {
                    // More that one line feed means that no phpdocs for this element exists.
                    return false;
                }
            } else if ($tokens[$i][0] == T_DOC_COMMENT) {
                return $this->get_phpdocs($i);
            } else if (in_array($tokens[$i][0], $modifiers)) {
                // Just skip.
                continue;
            } else if (in_array($tokens[$i][1], ['{', '}', ';'])) {
                // This means that no phpdocs exists.
                return false;
            } else if ($tokens[$i][0] == T_COMMENT) {
                // This probably needed to be doc_comment.
                return false;
            } else {
                // No idea what it is!
                // TODO: change to debugging
                // echo "************ Unknown preceeding token id = {$tokens[$i][0]}, text = '{$tokens[$i][1]}' **************<br>".
                return false;
            }
        }
        return false;
    }

    /**
     * Skips any tokens that _could be_ part of a type of a typed property definition.
     *
     * @param int $tid the token before which a type is expected
     * @return int the token id (`< $tid`) directly before the first token of the type. If there is no type, this will
     *             be the token directly preceding `$tid`.
     */
    private function skip_preceding_type(int $tid): int {
        for ($i = $tid - 1; $i >= 0; $i--) {
            if ($this->is_whitespace_token($i)) {
                continue;
            }

            $token = $this->tokens[$i];

            if (in_array($token[0], self::MODIFIERS)) {
                // This looks like the last modifier. Return the token after it.
                return $i + 1;
            } else if (in_array($token[1], ['{', '}', ';'])) {
                // We've gone past the beginning of the statement. This isn't possible in valid PHP, but still...
                // Return the first token of the statement we were in.
                return $i + 1;
            }

            // This is something else. Let's assume it to be part of the property's type and skip it.
        }

        // We've gone all the way to the start of the file, which shouldn't be possible in valid PHP.
        return 0;
    }

    /**
     * Finds the next pair of matching open and close symbols (usually some sort of brackets)
     *
     * @param int $startid id of token where we start looking from
     * @param string $opensymbol opening symbol (, { or [
     * @param string $closesymbol closing symbol ), } or ] respectively
     * @param array $breakifmeet array of symbols that are not allowed not preceed the $opensymbol
     * @return array|false array of ids of two corresponding tokens or false if not found
     */
    public function find_tag_pair($startid, $opensymbol, $closesymbol, $breakifmeet = []) {
        $openid = false;
        $counter = 0;
        // Also break if we find closesymbol before opensymbol.
        $breakifmeet[] = $closesymbol;
        for ($i = $startid; $i < $this->tokenscount; $i++) {
            if ($openid === false && in_array($this->tokens[$i][1], $breakifmeet)) {
                return false;
            } else if ($openid !== false && $this->tokens[$i][1] == $closesymbol) {
                $counter--;
                if ($counter == 0) {
                    return [$openid, $i];
                }
            } else if ($this->tokens[$i][1] == $opensymbol) {
                if ($openid === false) {
                    $openid = $i;
                }
                $counter++;
            }
        }
        return false;
    }

    /**
     * Finds the next pair of matching open and close symbols (usually some sort of brackets)
     *
     * @param array $tokens array of tokens to parse
     * @param int $startid id of token where we start looking from
     * @param string $opensymbol opening symbol (, { or [
     * @param string $closesymbol closing symbol ), } or ] respectively
     * @param array $breakifmeet array of symbols that are not allowed not preceed the $opensymbol
     * @return array|false array of ids of two corresponding tokens or false if not found
     */
    public function find_tag_pair_inlist(&$tokens, $startid, $opensymbol, $closesymbol, $breakifmeet = []) {
        $openid = false;
        $counter = 0;
        // Also break if we find closesymbol before opensymbol.
        $breakifmeet[] = $closesymbol;
        $tokenscount = count($tokens);
        for ($i = $startid; $i < $tokenscount; $i++) {
            if ($openid === false && in_array($tokens[$i][1], $breakifmeet)) {
                return false;
            } else if ($openid !== false && $tokens[$i][1] == $closesymbol) {
                $counter--;
                if ($counter == 0) {
                    return [$openid, $i];
                }
            } else if ($tokens[$i][1] == $opensymbol) {
                if ($openid === false) {
                    $openid = $i;
                }
                $counter++;
            }
        }
        return false;
    }

    /**
     * Locates the file-level phpdocs and returns it
     *
     * @return string|false either the contents of phpdocs or false if not found
     */
    public function find_file_phpdocs() {
        $tokens = &$this->get_tokens();
        if ($this->filephpdocs === null) {
            $found = false;
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if (in_array($tokens[$tid][0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT])) {
                    // All allowed before the file-level phpdocs.
                    $found = false;
                } else if ($tokens[$tid][0] == T_DOC_COMMENT) {
                    $found = $tid;
                    break;
                } else {
                    // Found something else.
                    break;
                }
            }
            if ($found !== false) {
                // Now let's check that this is not phpdocs to the next function or class or define.
                $nexttokenid = $this->next_nonspace_token($tid, true);
                if ($nexttokenid !== false) { // Still tokens to look.
                    $nexttoken = $this->tokens[$nexttokenid];
                    if ($this->is_whitespace_token($tid + 1) && $this->is_multiline_token($tid + 1) > 1) {
                        // At least one empty line follows, it's all right.
                        $found = $tid;
                    } else if (in_array($nexttoken[0],
                            [T_DOC_COMMENT, T_COMMENT, T_REQUIRE_ONCE, T_REQUIRE, T_IF, T_INCLUDE_ONCE, T_INCLUDE])) {
                        // Something non-documentable following, ok.
                        $found = $tid;
                    } else if ($nexttoken[0] == T_STRING && $nexttoken[1] == 'defined') {
                        // Something non-documentable following.
                        $found = $tid;
                    } else if (in_array($nexttoken[0], [T_CLASS, T_ABSTRACT, T_INTERFACE, T_FUNCTION])) {
                        // This is the doc comment to the following class/function.
                        $found = false;
                    }
                    // TODO: change to debugging.
                    // } else {
                    // echo "************ "
                    // echo "Unknown token following the first phpdocs in "
                    // echo "{$this->filepath}: id = {$nexttoken[0]}, text = '{$nexttoken[1]}'"
                    // echo " **************<br>"
                    // }.
                }
            }
            $this->filephpdocs = $this->get_phpdocs($found);
        }
        return $this->filephpdocs;
    }

    /**
     * Returns all parsed phpdocs block found in file
     *
     * @return array
     */
    public function &get_all_phpdocs() {
        if ($this->allphpdocs === null) {
            $this->allphpdocs = [];
            $this->get_tokens();
            for ($id = 0; $id < $this->tokenscount; $id++) {
                if (($this->tokens[$id][0] == T_DOC_COMMENT || $this->tokens[$id][0] === T_COMMENT)) {
                    $this->allphpdocs[$id] = new local_moodlecheck_phpdocs($this->tokens[$id], $id);
                }
            }
        }
        return $this->allphpdocs;
    }

    /**
     * Returns one parsed phpdocs block found in file
     *
     * @param int $tid token id of phpdocs
     * @return local_moodlecheck_phpdocs
     */
    public function get_phpdocs($tid) {
        if ($tid === false) {
            return false;
        }
        $this->get_all_phpdocs();
        if (isset($this->allphpdocs[$tid])) {
            return $this->allphpdocs[$tid];
        } else {
            return false;
        }
    }

    /**
     * Given an array of tokens breaks them into chunks by $separator
     *
     * @param array $tokens
     * @param string $separator one-character separator (usually comma)
     * @return array of arrays of tokens
     */
    public function break_tokens_by($tokens, $separator = ',') {
        $rv = [];
        if (!count($tokens)) {
            return $rv;
        }
        $rv[] = [];
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][1] == $separator) {
                $rv[] = [];
            } else {
                $nextpair = false;
                if ($tokens[$i][1] == '(') {
                    $nextpair = $this->find_tag_pair_inlist($tokens, $i, '(', ')');
                } else if ($tokens[$i][1] == '[') {
                    $nextpair = $this->find_tag_pair_inlist($tokens, $i, '[', ']');
                } else if ($tokens[$i][1] == '{') {
                    $nextpair = $this->find_tag_pair_inlist($tokens, $i, '{', '}');
                }
                if ($nextpair !== false) {
                    // Skip to the end of the tag pair.
                    for ($j = $i; $j <= $nextpair[1]; $j++) {
                        $rv[count($rv) - 1][] = $tokens[$j];
                    }
                    $i = $nextpair[1];
                } else {
                    $rv[count($rv) - 1][] = $tokens[$i];
                }
            }
        }
        // Now trim whitespaces.
        for ($i = 0; $i < count($rv); $i++) {
            if (count($rv[$i]) && $rv[$i][0][0] == T_WHITESPACE) {
                array_shift($rv[$i]);
            }
            if (count($rv[$i]) && $rv[$i][count($rv[$i]) - 1][0] == T_WHITESPACE) {
                array_pop($rv[$i]);
            }
        }
        return $rv;
    }

    /**
     * Returns line number for the token with specified id
     *
     * @param int $tid id of the token
     */
    public function get_line_number($tid) {
        $tokens = &$this->get_tokens();
        if (count($tokens[$tid]) > 2) {
            return $tokens[$tid][2];
        } else if ($tid == 0) {
            return 1;
        } else {
            return $this->get_line_number($tid - 1) + count(preg_split('/\n/', $tokens[$tid - 1][1])) - 1;
        }
    }
}

/**
 * Handles one phpdocs
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_moodlecheck_phpdocs {
    /** @var array static property storing the list of valid,
     * well known, phpdocs tags, always accepted.
     * @link http://manual.phpdoc.org/HTMLSmartyConverter/HandS/ */
    public static $validtags = [
        // Behat tags.
        'Given',
        'Then',
        'When',
        // PHPUnit tags.
        'codeCoverageIgnore',
        'codeCoverageIgnoreStart',
        'codeCoverageIgnoreEnd',
        'covers',
        'coversDefaultClass',
        'coversNothing',
        'dataProvider',
        'depends',
        'group',
        'requires',
        'runTestsInSeparateProcesses',
        'runInSeparateProcess',
        'testWith',
        'uses',
        // PHPDoc tags.
        'abstract',
        'access',
        'author',
        'category',
        'copyright',
        'deprecated',
        'example',
        'final',
        'filesource',
        'global',
        'ignore',
        'internal',
        'license',
        'link',
        'method',
        'name',
        'package',
        'param',
        'property',
        'property-read',
        'property-write',
        'return',
        'see',
        'since',
        'static',
        'staticvar',
        'subpackage',
        'throws',
        'todo',
        'tutorial',
        'uses',
        'var',
        'version',
    ];

    /** @var array static property storing the list of phpdoc tags
     * allowed to be used inline within Moodle phpdocs. */
    public static $inlinetags = [
        'link',
        'see',
    ];
    /** @var array stores the original token for this phpdocs */
    protected $originaltoken = null;
    /** @var int stores id the original token for this phpdocs */
    protected $originaltid = null;
    /** @var string text of phpdocs with trimmed start/end tags
     * as well as * in the beginning of the lines */
    protected $trimmedtext = null;
    /** @var boolean whether the phpdocs contains text after the tokens
     * (possible in phpdocs but not recommended in Moodle) */
    protected $brokentext = false;
    /** @var string the description found in phpdocs */
    protected $description;
    /** @var array array of string where each string
     * represents found token (may be also multiline) */
    protected $tokens;

    /**
     * Constructor. Creates an object and parses it
     *
     * @param array $token corresponding token parsed from file
     * @param int $tid id of token in the file
     */
    public function __construct($token, $tid) {
        $this->originaltoken = $token;
        $this->originaltid = $tid;
        if (preg_match('|^///|', $token[1])) {
            $this->trimmedtext = substr($token[1], 3);
        } else {
            $this->trimmedtext = preg_replace(['|^\s*/\*+|', '|\*+/\s*$|'], '', $token[1]);
            $this->trimmedtext = preg_replace('|\n[ \t]*\*|', "\n", $this->trimmedtext);
        }
        $lines = preg_split('/\n/', $this->trimmedtext);

        $this->tokens = [];
        $this->description = '';
        $istokenline = false;
        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('|^\s*\@(\w+)|', $lines[$i])) {
                // First line of token.
                $istokenline = true;
                $this->tokens[] = $lines[$i];
            } else if (strlen(trim($lines[$i])) && $istokenline) {
                // Second/third line of token description.
                $this->tokens[count($this->tokens) - 1] .= "\n". $lines[$i];
            } else {
                // This is part of description.
                if (strlen(trim($lines[$i])) && !empty($this->tokens)) {
                    // Some text appeared AFTER tokens.
                    $this->brokentext = true;
                }
                $this->description .= $lines[$i]."\n";
                $istokenline = false;
            }
        }
        foreach ($this->tokens as $i => $token) {
            $this->tokens[$i] = trim($token);
        }
        $this->description = trim($this->description);
    }

    /**
     * Returns all tags found in phpdocs
     *
     * Returns array of found tokens. Each token is an unparsed string that
     * may consist of multiple lines.
     * Asterisk in the beginning of the lines are trimmed out
     *
     * @param string $tag if specified only tokens matching this tag are returned
     *   in this case the token itself is excluded from string
     * @param bool $nonempty if true return only non-empty tags
     * @return array
     */
    public function get_tags($tag = null, $nonempty = false) {
        if ($tag === null) {
            return $this->tokens;
        } else {
            $rv = [];
            foreach ($this->tokens as $token) {
                if (preg_match('/^\s*\@'.$tag.'\s([^\0]*)$/', $token.' ', $matches) && (!$nonempty || strlen(trim($matches[1])))) {
                    $rv[] = trim($matches[1]);
                }
            }
            return $rv;
        }
    }

    /**
     * Returns all tags found in phpdocs
     *
     * @deprecated use get_tags()
     * @param string $tag
     * @param bool $nonempty
     * @return array
     */
    public function get_tokens($tag = null, $nonempty = false) {
        return get_tags($tag, $nonempty);
    }

    /**
     * Returns the description without tokens found in phpdocs
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Returns true if part of the text is after any of the tokens
     *
     * @return bool
     */
    public function is_broken_description() {
        return $this->brokentext;
    }

    /**
     * Returns the original token storing this phpdocs
     *
     * @return array
     */
    public function get_original_token() {
        return $this->originaltoken;
    }

    /**
     * Returns the id for original token storing this phpdocs
     *
     * @return int
     */
    public function get_original_token_id() {
        return $this->originaltid;
    }

    /**
     * Returns list of parsed param tokens found in phpdocs
     *
     * Each element is [typename, variablename, variabledescription]
     *
     * @param string $tag tag name to look for. Usually param but may be var for variables
     * @param int $splitlimit maximum number of chunks to return
     * @return array
     */
    public function get_params($tag = 'param', $splitlimit = 3) {
        $params = [];

        foreach ($this->get_tags($tag) as $token) {
            $params[] = preg_split('/\s+/', trim($token), $splitlimit); // AKA 'type $name multi-word description'.
        }

        foreach ($params as $key => $param) {
            if (strpos($param[0], '?') !== false) {
                $param[0] = str_replace('?', 'null|', $param[0]);
            }
            $types = explode('|', $param[0]);
            $types = array_map(function($type): string {
                // Normalise array types such as `string[]` to `array`.
                if (substr($type, -2) == '[]') {
                    return 'array';
                }
                return $type;
            }, $types);
            sort($types);
            $params[$key][0] = implode('|', $types);
        }
        return $params;
    }

    /**
     * Returns the line number where this phpdoc occurs in the file
     *
     * @param local_moodlecheck_file $file
     * @param string $substring if specified the line number of first occurence of $substring is returned
     * @return int
     */
    public function get_line_number(local_moodlecheck_file $file, $substring = null) {
        $line0 = $file->get_line_number($this->get_original_token_id());
        if ($substring === null) {
            return $line0;
        } else {
            $chunks = preg_split('!' . preg_quote($substring, '!') . '!', $this->originaltoken[1]);
            if (count($chunks) > 1) {
                $lines = preg_split('/\n/', $chunks[0]);
                return $line0 + count($lines) - 1;
            } else {
                return $line0;
            }
        }
    }

    /**
     * Returns all the inline tags found in the phpdoc
     *
     * This method returns all the phpdocs tags found inline,
     * embed into the phpdocs contents. Only valid tags are
     * considered See {@link self::$validtags}.
     *
     * @param bool $withcurly if true, only tags properly enclosed
     *        with curly brackets are returned. Else all the inline tags are returned.
     * @param bool $withcontent if true, the contents after the tag are also returned.
     *        Else, the tags are returned both as key and values (for BC).
     *
     * @return array inline tags found in the phpdoc, with contents if specified.
     */
    public function get_inline_tags($withcurly = true, $withcontent = false) {
        $inlinetags = [];
        // Trim the non-inline phpdocs tags.
        $text = preg_replace('|^\s*@?|m', '', $this->trimmedtext);
        if ($withcurly) {
            $regex = '#{@([a-z\-]*)(.*?)[}\n]#';
        } else {
            $regex = '#@([a-z\-]*)(.*?)[}\n]#';
        }
        if (preg_match_all($regex, $text, $matches)) {
            // Filter out invalid ones, can be ignored.
            foreach ($matches[1] as $key => $tag) {
                if (in_array($tag, self::$validtags)) {
                    if ($withcontent && isset($matches[2][$key])) {
                        // Let's add the content.
                        $inlinetags[] = $tag . ' ' . trim($matches[2][$key]);
                    } else {
                        // Just the tag, without content.
                        $inlinetags[] = $tag;
                    }
                }
            }
        }
        return $inlinetags;
    }
}
