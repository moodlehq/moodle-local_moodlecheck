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
 * Type parser
 *
 * Checks that PHPDoc types are well formed, and returns a simplified version if so, or null otherwise.
 *
 * @package     local_moodlecheck
 * @copyright   2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author      James Calder
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class type_parser {

    /** @var string the text to be parsed */
    protected $text;

    /** @var bool when we encounter an unknown type, should we go wide or narrow */
    protected $gowide;

    /** @var non-negative-int the position of the next token */
    protected $nextpos;

    /** @var ?non-empty-string the next token */
    protected $nexttoken;

    /** @var non-negative-int the position after the next token */
    protected $nextnextpos;

    /**
     * Parse a type and possibly variable name
     *
     * @param string $text the text to parse
     * @param bool $getvar whether to get variable name
     * @return array{?non-empty-string, ?non-empty-string, string} the simplified type, variable, and remaining text
     */
    public function parse_type_and_var(string $text, bool $getvar = true): array {

        // Initialise variables.
        $this->text = strtolower($text);
        $this->gowide = false;
        $this->nextnextpos = 0;
        $this->prefetch_next_token();

        // Try to parse type.
        $savedstate = [$this->nextpos, $this->nexttoken, $this->nextnextpos];
        try {
            $type = $this->parse_dnf_type();
            if ($this->nextpos < strlen($text)
                    && !(ctype_space($text[$this->nextpos]) || $this->nexttoken == '&' || $this->nexttoken == '...')) {
                throw new \Error("Error parsing type, no space at end of type.");
            }
        } catch (\Error $e) {
            list($this->nextpos, $this->nexttoken, $this->nextnextpos) = $savedstate;
            $type = null;
        }

        // Try to parse variable.
        if ($getvar) {
            $savedstate = [$this->nextpos, $this->nexttoken, $this->nextnextpos];
            try {
                if ($this->nexttoken == '&') {
                    $this->parse_token('&');
                }
                if ($this->nexttoken == '...') {
                    $this->parse_token('...');
                }
                if (!($this->nexttoken != null && $this->nexttoken[0] == '$')) {
                    throw new \Error("Error parsing type, expected variable, saw \"{$this->nexttoken}\".");
                }
                $variable = $this->parse_token();
                if ($this->nextpos < strlen($text) && !(ctype_space($text[$this->nextpos]) || $this->nexttoken == '=')) {
                    throw new \Error("Error parsing type, no space at end of variable.");
                }
            } catch (\Error $e) {
                list($this->nextpos, $this->nexttoken, $this->nextnextpos) = $savedstate;
                $variable = null;
            }
        } else {
            $variable = null;
        }

        return [$type, $variable, trim(substr($text, $this->nextpos))];
    }

    /**
     * Compare types
     *
     * @param ?string $widetype the type that should be wider
     * @param ?string $narrowtype the type that should be narrower
     * @return bool whether $narrowtype has the same or narrower scope as $widetype
     */
    public static function compare_types(?string $widetype, ?string $narrowtype): bool {
        if ($narrowtype == null || $narrowtype == '') {
            return false;
        } else if ($widetype == null || $widetype == '' || $widetype == 'mixed'
                || $narrowtype == 'never') {
            return true;
        }

        $wideintersections = explode('|', $widetype);
        $narrowintersections = explode('|', $narrowtype);

        // We have to match all narrow intersections.
        $haveallintersections = true;
        foreach ($narrowintersections as $narrowintersection) {
            $narrowsingles = explode('&', $narrowintersection);

            // If the wide types are super types, that should match.
            $narrowadditions = [];
            foreach ($narrowsingles as $narrowsingle) {
                $supertypes = static::super_types($narrowsingle);
                $narrowadditions = array_merge($narrowadditions, $supertypes);
            }
            $narrowsingles = array_merge($narrowsingles, $narrowadditions);
            sort($narrowsingles);
            $narrowsingles = array_unique($narrowsingles);

            // We need to look in each wide intersection.
            $havethisintersection = false;
            foreach ($wideintersections as $wideintersection) {
                $widesingles = explode('&', $wideintersection);

                // And find all parts of one of them.
                $haveallsingles = true;
                foreach ($widesingles as $widesingle) {

                    if (!in_array($widesingle, $narrowsingles)) {
                        $haveallsingles = false;
                        break;
                    }

                }
                if ($haveallsingles) {
                    $havethisintersection = true;
                    break;
                }
            }
            if (!$havethisintersection) {
                $haveallintersections = false;
                break;
            }
        }
        return $haveallintersections;
    }

    /**
     * Get super types
     *
     * @param string $basetype
     * @return non-empty-string[] super types
     */
    protected static function super_types(string $basetype): array {
        if ($basetype == 'int') {
            $supertypes = ['array-key', 'float', 'scalar'];
        } else if ($basetype == 'string') {
            $supertypes = ['array-key', 'scaler'];
        } else if ($basetype == 'callable-string') {
            $supertypes = ['callable', 'string', 'array-key', 'scalar'];
        } else if (in_array($basetype, ['array-key', 'bool', 'float'])) {
            $supertypes = ['scalar'];
        } else if ($basetype == 'array') {
            $supertypes = ['iterable'];
        } else if ($basetype == 'Traversable') {
            $supertypes = ['iterable', 'object'];
        } else if (in_array($basetype, ['self', 'parent', 'static'])
                || $basetype[0] >= 'A' && $basetype[0] <= 'Z' || $basetype[0] == '_') {
            $supertypes = ['object'];
        } else {
            $supertypes = [];
        }
        return $supertypes;
    }

    /**
     * Prefetch next token
     */
    protected function prefetch_next_token(): void {

        $startpos = $this->nextnextpos;

        // Ignore whitespace.
        while ($startpos < strlen($this->text) && ctype_space($this->text[$startpos])) {
            $startpos++;
        }

        $firstchar = ($startpos < strlen($this->text)) ? $this->text[$startpos] : null;

        // Deal with different types of tokens.
        if ($firstchar == null) {
            // No more tokens.
            $endpos = $startpos;
        } else if (ctype_digit($firstchar) || $firstchar == '-') {
            // Number token.
            $nextchar = $firstchar;
            $havepoint = false;
            $endpos = $startpos;
            do {
                $havepoint = $havepoint || $nextchar == '.';
                $endpos = $endpos + 1;
                $nextchar = ($endpos < strlen($this->text)) ? $this->text[$endpos] : null;
            } while ($nextchar != null && (ctype_digit($nextchar) || $nextchar == '.' && !$havepoint));
        } else if (ctype_alpha($firstchar) || $firstchar == '_' || $firstchar == '$' || $firstchar == '\\') {
            // Identifier token.
            $endpos = $startpos;
            do {
                $endpos = $endpos + 1;
                $nextchar = ($endpos < strlen($this->text)) ? $this->text[$endpos] : null;
            } while ($nextchar != null && (ctype_alnum($nextchar) || $nextchar == '_'
                                        || $firstchar != '$' && ($nextchar == '-' || $nextchar == '\\')));
        } else if ($firstchar == '"' || $firstchar == '\'') {
            // String token.
            $endpos = $startpos + 1;
            $nextchar = ($endpos < strlen($this->text)) ? $this->text[$endpos] : null;
            while ($nextchar != $firstchar) {
                if ($nextchar == null) {
                    throw new \Error("Error parsing type, unterminated string.");
                }
                $endpos = $endpos + 1;
                $nextchar = ($endpos < strlen($this->text)) ? $this->text[$endpos] : null;
            }
            $endpos++;
        } else if (strlen($this->text) >= $startpos + 3 && substr($this->text, $startpos, 3) == '...') {
            // Splat.
            $endpos = $startpos + 3;
        } else if (strlen($this->text) >= $startpos + 2 && substr($this->text, $startpos, 2) == '::') {
            // Scope resolution operator.
            $endpos = $startpos + 2;
        } else {
            // Other symbol token.
            $endpos = $startpos + 1;
        }

        // Store token.
        $this->nextpos = $this->nextnextpos;
        $this->nexttoken = ($endpos > $startpos) ? substr($this->text, $startpos, $endpos - $startpos) : null;
        $this->nextnextpos = $endpos;
    }

    /**
     * Fetch the next token
     *
     * @param ?string $expect the expected text
     * @return non-empty-string
     */
    protected function parse_token(?string $expect = null): string {

        $nexttoken = $this->nexttoken;

        // Check we have the expected token.
        if ($expect != null && $nexttoken != $expect) {
            throw new \Error("Error parsing type, expected \"{$expect}\", saw \"{$nexttoken}\".");
        } else if ($nexttoken == null) {
            throw new \Error("Error parsing type, unexpected end.");
        }

        // Prefetch next token.
        $this->prefetch_next_token();

        // Return consumed token.
        return $nexttoken;
    }

    /**
     * Parse a list of types seperated by | and/or &, or a single nullable type
     *
     * @return non-empty-string the simplified type
     */
    protected function parse_dnf_type(): string {
        $uniontypes = [];

        if ($this->nexttoken == '?') {
            // Parse single nullable type.
            $this->parse_token('?');
            array_push($uniontypes, 'void');
            array_push($uniontypes, $this->parse_single_type());
        } else {
            // Parse union list.
            do {
                // Parse intersection list.
                $havebracket = $this->nexttoken == '(';
                if ($havebracket) {
                    $this->parse_token('(');
                }
                $intersectiontypes = [];
                do {
                    array_push($intersectiontypes, $this->parse_single_type());
                    // We have to figure out whether a & is for intersection or pass by reference.
                    // Dirty hack.
                    $nextnextpos = $this->nextnextpos;
                    while ($nextnextpos < strlen($this->text) && ctype_space($this->text[$nextnextpos])) {
                        $nextnextpos++;
                    }
                    $nextnextchar = ($nextnextpos < strlen($this->text)) ? $this->text[$nextnextpos] : null;
                    $haveintersection = $this->nexttoken == '&'
                        && ($havebracket || !in_array($nextnextchar, ['.', '=', '$', ',', ')', null]));
                    if ($haveintersection) {
                        $this->parse_token('&');
                    }
                } while ($haveintersection);
                if ($havebracket) {
                    $this->parse_token(')');
                }
                // Tidy and store intersection list.
                if (in_array('callable', $intersectiontypes) && in_array('string', $intersectiontypes)) {
                    $intersectiontypes[] = 'callable-string';
                }
                foreach ($intersectiontypes as $intersectiontype) {
                    $supertypes = static::super_types($intersectiontype);
                    foreach ($supertypes as $supertype) {
                        $superpos = array_search($supertype, $intersectiontypes);
                        if ($superpos !== false) {
                            unset($intersectiontypes[$superpos]);
                        }
                    }
                }
                sort($intersectiontypes);
                $intersectiontypes = array_unique($intersectiontypes);
                $neverpos = array_search('never', $intersectiontypes);
                if ($neverpos !== false) {
                    $intersectiontypes = ['never'];
                }
                $mixedpos = array_search('mixed', $intersectiontypes);
                if ($mixedpos !== false && count($intersectiontypes) > 1) {
                    unset($intersectiontypes[$mixedpos]);
                }
                // TODO: Check for conflicting types.
                array_push($uniontypes, implode('&', $intersectiontypes));
                // Check for more union items.
                $haveunion = $this->nexttoken == '|';
                if ($haveunion) {
                    $this->parse_token('|');
                }
            } while ($haveunion);
        }

        // Tidy and return union list.
        if ((in_array('int', $uniontypes) || in_array('float', $uniontypes)) && in_array('string', $uniontypes)) {
            $uniontypes[] = 'array-key';
        }
        if (in_array('array-key', $uniontypes) && in_array('bool', $uniontypes) && in_array('float', $uniontypes)) {
            $uniontypes[] = 'scalar';
        }
        if (in_array('Traversable', $uniontypes) && in_array('array', $uniontypes)) {
            $uniontypes[] = 'iterable';
        }
        if (in_array('scalar', $uniontypes) && (in_array('array', $uniontypes) || in_array('iterable', $uniontypes))
                && in_array('object', $uniontypes) && in_array('resource', $uniontypes) && in_array('callable', $uniontypes)
                && in_array('void', $uniontypes)) {
            $uniontypes = ['mixed'];
        }
        sort($uniontypes);
        $uniontypes = array_unique($uniontypes);
        $mixedpos = array_search('mixed', $uniontypes);
        if ($mixedpos !== false) {
            $uniontypes = ['mixed'];
        }
        $neverpos = array_search('never', $uniontypes);
        if ($neverpos !== false && count($uniontypes) > 1) {
            unset($uniontypes[$neverpos]);
        }
        // TODO: Check for redundant types.
        return implode('|', $uniontypes);

    }

    /**
     * Parse a single type, possibly array type
     *
     * @return non-empty-string the simplified type
     */
    protected function parse_single_type(): string {

        // Parse base part.
        $nextchar = ($this->nexttoken == null) ? null : $this->nexttoken[0];
        if ($nextchar == '"' || $nextchar == '\'' || $nextchar >= '0' && $nextchar <= '9' || $nextchar == '-'
                || $nextchar == '$' || $nextchar == '\\' || $nextchar != null && ctype_alpha($nextchar) || $nextchar == '_') {
            $type = $this->parse_token();
        } else {
            throw new \Error("Error parsing type, expecting type, saw \"{$this->nexttoken}\".");
        }

        // Parse details part.
        if (in_array($type, ['bool', 'boolean', 'true', 'false'])) {
            // Parse bool details.
            $type = 'bool';
        } else if (in_array($type, ['int', 'integer',
                                    'positive-int', 'negative-int', 'non-positive-int', 'non-negative-int', 'non-zero-int',
                                    'int-mask', 'int-mask-of'])
                || ($type[0] >= '0' && $type[0] <= '9' || $type[0] == '-') && strpos($type, '.') === false) {
            // Parse int details.
            if ($type == 'int' && $this->nexttoken == '<') {
                // Parse integer range.
                $this->parse_token('<');
                $nexttoken = $this->nexttoken;
                if (!($nexttoken != null && ($nexttoken[0] >= '0' && $nexttoken[0] <= '9' || $nexttoken[0] == '-')
                        || $nexttoken == 'min')) {
                    throw new \Error("Error parsing type, expected int min, saw \"{$nexttoken}\".");
                };
                $this->parse_token();
                $this->parse_token(',');
                $nexttoken = $this->nexttoken;
                if (!($nexttoken != null && ($nexttoken[0] >= '0' && $nexttoken[0] <= '9' || $nexttoken[0] == '-')
                        || $nexttoken == 'max')) {
                    throw new \Error("Error parsing type, expected int max, saw \"{$nexttoken}\".");
                };
                $this->parse_token();
                $this->parse_token('>');
            } else if (in_array($type, ['int-mask', 'int-mask-of'])) {
                // Parse integer mask.
                $this->parse_token('<');
                $nextchar = ($this->nexttoken != null) ? $this->nexttoken[0] : null;
                if (ctype_digit($nextchar) || $type == 'int-mask') {
                    do {
                        if (!($nextchar != null && ctype_digit($nextchar) && strpos($this->nexttoken, '.') === false)) {
                            throw new \Error("Error parsing type, expected int mask, saw \"{$this->nexttoken}\".");
                        }
                        $this->parse_token();
                        $haveseperator = ($type == 'int-mask') && ($this->nexttoken == ',')
                                        || ($type == 'int-mask-of') && ($this->nexttoken == '|');
                        if ($haveseperator) {
                            $this->parse_token();
                        }
                        $nextchar = ($this->nexttoken != null) ? $this->nexttoken[0] : null;
                    } while ($haveseperator);
                } else {
                    $this->parse_single_type();
                }
                $this->parse_token('>');
            }
            $type = 'int';
        } else if (in_array($type, ['float', 'double'])
                || ($type[0] >= '0' && $type[0] <= '9' || $type[0] == '-') && strpos($type, '.') !== false) {
            // Parse float details.
            $type = 'float';
        } else if (in_array($type, ['string', 'class-string', 'numeric-string', 'literal-string',
                                    'non-empty-string', 'non-falsy-string', 'truthy-string'])
                    || $type[0] == '"' || $type[0] == '\'') {
            // Parse string details.
            if ($type == 'class-string' && $this->nexttoken == '<') {
                $this->parse_token('<');
                $classname = $this->parse_single_type();
                if (!($classname[0] >= 'A' && $classname[0] <= 'Z' || $classname[0] == '_')) {
                    throw new \Error("Error parsing type, class string type isn't class name.");
                }
                $this->parse_token('>');
            }
            $type = 'string';
        } else if ($type == 'callable-string') {
            // Parse callable-string details.
            $type = 'callable-string';
        } else if (in_array($type, ['array', 'non-empty-array', 'list', 'non-empty-list'])) {
            // Parse array details.
            if ($this->nexttoken == '<') {
                // Typed array.
                $this->parse_token('<');
                $firsttype = $this->parse_dnf_type();
                if ($this->nexttoken == ',') {
                    if (in_array($type, ['list', 'non-empty-list'])) {
                        throw new \Error("Error parsing type, lists cannot have keys specified.");
                    }
                    $key = $firsttype;
                    $this->parse_token(',');
                    $value = $this->parse_dnf_type();
                } else {
                    $key = null;
                    $value = $firsttype;
                }
                $this->parse_token('>');
            } else if ($this->nexttoken == '{') {
                // Array shape.
                if (in_array($type, ['list', 'non-empty-list'])) {
                    throw new \Error("Error parsing type, lists cannot have shapes.");
                }
                $this->parse_token('{');
                do {
                    $key = null;
                    $savedstate = [$this->nextpos, $this->nexttoken, $this->nextnextpos];
                    try {
                        $key = $this->parse_token();
                        if (!(ctype_alpha($key) || $key[0] == '_' || $key[0] == '\'' || $key[0] == '"'
                                || (ctype_digit($key) || $key[0] == '-') && strpos($key, '.') === false)) {
                            throw new \Error("Error parsing type, invalid array key.");
                        }
                        if ($this->nexttoken == '?') {
                            $this->parse_token('?');
                        }
                        $this->parse_token(':');
                    } catch (\Error $e) {
                        list($this->nextpos, $this->nexttoken, $this->nextnextpos) = $savedstate;
                    }
                    $this->parse_dnf_type();
                    $havecomma = $this->nexttoken == ',';
                    if ($havecomma) {
                        $this->parse_token(',');
                    }
                } while ($havecomma);
                $this->parse_token('}');
            }
            $type = 'array';
        } else if ($type == 'object') {
            // Parse object details.
            if ($this->nexttoken == '{') {
                // Object shape.
                $this->parse_token('{');
                do {
                    $key = $this->parse_token();
                    if (!(ctype_alpha($key) || $key[0] == '_' || $key[0] == '\'' || $key[0] == '"')) {
                        throw new \Error("Error parsing type, invalid array key.");
                    }
                    if ($this->nexttoken == '?') {
                        $this->parse_token('?');
                    }
                    $this->parse_token(':');
                    $this->parse_dnf_type();
                    $havecomma = $this->nexttoken == ',';
                    if ($havecomma) {
                        $this->parse_token(',');
                    }
                } while ($havecomma);
                $this->parse_token('}');
            }
            $type = 'object';
        } else if ($type == 'resource') {
            // Parse resource details.
            $type = 'resource';
        } else if ($type == 'never' || $type == 'never-return' || $type == 'never-returns' || $type == 'no-return') {
            // Parse never details.
            $type = 'never';
        } else if (in_array($type, ['void', 'null'])) {
            // Parse void details.
            $type = 'void';
        } else if ($type == 'self') {
            // Parse self details.
            $type = 'self';
        } else if ($type == 'parent') {
            // Parse parent details.
            $type = 'parent';
        } else if (in_array($type, ['static', '$this'])) {
            // Parse static details.
            $type = 'static';
        } else if ($type == 'callable') {
            // Parse callable details.
            if ($this->nexttoken == '(') {
                $this->parse_token('(');
                $splat = false;
                while ($this->nexttoken != ')') {
                    $this->parse_dnf_type();
                    if ($this->nexttoken == '&') {
                        $this->parse_token('&');
                    }
                    if ($this->nexttoken == '...') {
                        $this->parse_token('...');
                        $splat = true;
                    }
                    if ($this->nexttoken == '=') {
                        $this->parse_token('=');
                    }
                    $nextchar = ($this->nexttoken != null) ? $this->nexttoken[0] : null;
                    if ($nextchar == '$') {
                        $this->parse_token();
                    }
                    if ($this->nexttoken != ')') {
                        if ($splat) {
                            throw new \Error("Error parsing type, expected end of param list, saw \"{$this->nexttoken}\".");
                        }
                        $this->parse_token(',');
                    }
                };
                $this->parse_token(')');
                $this->parse_token(':');
                if ($this->nexttoken == '(') {
                    $this->parse_token('(');
                    $this->parse_dnf_type();
                    $this->parse_token(')');
                } else {
                    if ($this->nexttoken == '?') {
                        $this->parse_token('?');
                    }
                    $this->parse_single_type();
                }
            }
            $type = 'callable';
        } else if ($type == 'mixed') {
            // Parse mixed details.
            $type = 'mixed';
        } else if ($type == 'iterable') {
            // Parse iterable details (Traversable|array).
            if ($this->nexttoken == '<') {
                $this->parse_token('<');
                $this->parse_dnf_type();
                $this->parse_token('>');
            }
            $type = 'iterable';
        } else if ($type == 'array-key') {
            // Parse array-key details (int|string).
            $type = 'array-key';
        } else if ($type == 'scalar') {
            // Parse scalar details (bool|int|float|string).
            $type = 'scalar';
        } else if ($type == 'key-of') {
            // Parse key-of details.
            $this->parse_token('<');
            $this->parse_dnf_type();
            $this->parse_token('>');
            $type = $this->gowide ? 'array-key' : 'never';
        } else if ($type == 'value-of') {
            // Parse value-of details.
            $this->parse_token('<');
            $this->parse_dnf_type();
            $this->parse_token('>');
            $type = $this->gowide ? 'mixed' : 'never';
        } else {
            // Check valid class name.
            if (strpos($type, '$') !== false || strpos($type, '-') !== false || strpos($type, '\\\\') !== false) {
                throw new \Error("Error parsing type, invalid class name.");
            }
            $lastseperatorpos = strrpos($type, '\\');
            if ($lastseperatorpos !== false) {
                $type = substr($type, $lastseperatorpos + 1);
            }
            if ($type == '') {
                throw new \Error("Error parsing type, class name has trailing slash.");
            }
            $type = ucfirst($type);
        }

        // Parse suffix.
        if ($this->nexttoken == '::' && ($type == 'object' || in_array('object', static::super_types($type)))) {
            // Parse class constant.
            $this->parse_token('::');
            $nextchar = ($this->nexttoken == null) ? null : $this->nexttoken[0];
            $haveconstantname = $nextchar != null && (ctype_alpha($nextchar) || $nextchar == '_');
            if ($haveconstantname) {
                $this->parse_token();
            }
            if ($this->nexttoken == '*' || !$haveconstantname) {
                $this->parse_token('*');
            }
            $type = $this->gowide ? 'mixed' : 'never';
        } else if ($this->nexttoken == '[') {
            // Parse array suffix.
            $this->parse_token('[');
            $this->parse_token(']');
            $type = 'array';
        }

        return $type;
    }

}
