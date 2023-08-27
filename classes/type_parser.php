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

    /** @var string the type to be parsed */
    protected $type;

    /** @var non-negative-int the position of the next token */
    protected $nextpos;

    /** @var ?non-empty-string the next token */
    protected $nexttoken;

    /** @var non-negative-int the position after the next token */
    protected $nextnextpos;

    /**
     * Parse the whole type
     *
     * @param string $intype the type to parse
     * @param bool $getvar whether to get variable name
     * @return array{?non-empty-string, ?non-empty-string, string} the simplified type, variable, and remaining text
     */
    public function parse_type_and_var(string $intype, bool $getvar = true): array {

        // Initialise variables.
        $this->type = strtolower($intype);
        $this->nextnextpos = 0;
        $this->prefetch_next_token();

        // Try to parse type.
        $savednextpos = $this->nextpos;
        $savednexttoken = $this->nexttoken;
        $savednextnextpos = $this->nextnextpos;
        try {
            $outtype = $this->parse_dnf_type();
            if ($this->nextpos < strlen($intype) && !ctype_space($intype[$this->nextpos])
                && !($this->nexttoken == '&' || $this->nexttoken == '...')) {
                throw new \Error("Error parsing type, no space at end of type");
            }
        } catch (\Error $e) {
            $this->nextpos = $savednextpos;
            $this->nexttoken = $savednexttoken;
            $this->nextnextpos = $savednextnextpos;
            $outtype = null;
        }

        // Try to parse variable.
        if ($getvar) {
            $savednextpos = $this->nextpos;
            $savednexttoken = $this->nexttoken;
            $savednextnextpos = $this->nextnextpos;
            try {
                if ($this->nexttoken == '&') {
                    $this->parse_token('&');
                }
                if ($this->nexttoken == '...') {
                    $this->parse_token('...');
                }
                if (!($this->nexttoken != null && $this->nexttoken[0] == '$')) {
                    throw new \Error("Error parsing type, expected variable, saw {$this->nexttoken}");
                }
                $variable = $this->parse_token();
                if ($this->nextpos < strlen($intype) && !ctype_space($intype[$this->nextpos]) && $this->nexttoken != '=') {
                    throw new \Error("Error parsing type, no space at end of variable");
                }
            } catch (\Error $e) {
                $this->nextpos = $savednextpos;
                $this->nexttoken = $savednexttoken;
                $this->nextnextpos = $savednextnextpos;
                $variable = null;
            }
        } else {
            $variable = null;
        }

        return [$outtype, $variable, trim(substr($intype, $this->nextpos))];
    }

    /**
     * Compare types
     *
     * @param ?string $widetypes the type that should be wider
     * @param ?string $narrowtypes the type that should be narrower
     * @return bool whether $narrowtypes has the same or narrower scope as $widetypes
     */
    public static function compare_types(?string $widetypes, ?string $narrowtypes): bool {
        if ($narrowtypes == null || $narrowtypes == '') {
            return false;
        } else if ($widetypes == null || $widetypes == '' || $widetypes == 'mixed'
                || $narrowtypes == 'never') {
            return true;
        }

        $wideintersections = explode('|', $widetypes);
        $narrowintersections = explode('|', $narrowtypes);

        // We have to match all documented intersections.
        $haveallintersections = true;
        foreach ($narrowintersections as $narrowintersection) {
            $narrowsingles = explode('&', $narrowintersection);

            // If the expected types are super types, that should match.
            $narrowadditions = [];
            foreach ($narrowsingles as $narrowsingle) {
                $supertypes = static::super_types($narrowsingle);
                $narrowadditions = array_merge($narrowadditions, $supertypes);
            }
            $narrowsingles = array_merge($narrowsingles, $narrowadditions);
            sort($narrowsingles);
            $narrowsingles = array_unique($narrowsingles);

            // We need to look in each expected intersection.
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
        while ($startpos < strlen($this->type) && ctype_space($this->type[$startpos])) {
            $startpos++;
        }

        $firstchar = ($startpos < strlen($this->type)) ? $this->type[$startpos] : null;

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
                $nextchar = ($endpos < strlen($this->type)) ? $this->type[$endpos] : null;
            } while ($nextchar != null && (ctype_digit($nextchar) || $nextchar == '.' && !$havepoint));
        } else if (ctype_alpha($firstchar) || $firstchar == '_' || $firstchar == '$' || $firstchar == '\\') {
            // Identifier token.
            $endpos = $startpos;
            do {
                $endpos = $endpos + 1;
                $nextchar = ($endpos < strlen($this->type)) ? $this->type[$endpos] : null;
            } while ($nextchar != null && (ctype_alnum($nextchar) || $nextchar == '_'
                                        || $firstchar != '$' && ($nextchar == '-' || $nextchar == '\\')));
        } else if ($firstchar == '"' || $firstchar == "'") {
            // String token.
            $endpos = $startpos;
            $nextchar = $firstchar;
            do {
                if ($nextchar == null) {
                    throw new \Error('Error parsing type, unterminated string');
                }
                $endpos = $endpos + 1;
                $lastchar = $nextchar;
                $nextchar = ($endpos < strlen($this->type)) ? $this->type[$endpos] : null;
            } while ($lastchar != $firstchar || $endpos == $startpos + 1);
        } else if (strlen($this->type) >= $startpos + 3 && substr($this->type, $startpos, 3) == '...') {
            // Splat.
            $endpos = $startpos + 3;
        } else if (strlen($this->type) >= $startpos + 2 && substr($this->type, $startpos, 2) == '::') {
            // Scope resolution operator.
            $endpos = $startpos + 2;
        } else {
            // Other symbol token.
            $endpos = $startpos + 1;
        }

        // Store token.
        $this->nextpos = $this->nextnextpos;
        $this->nexttoken = ($endpos > $startpos) ? substr($this->type, $startpos, $endpos - $startpos) : null;
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
            throw new \Error("Error parsing type, expected {$expect}, saw {$nexttoken}");
        } else if ($nexttoken == null) {
            throw new \Error("Error parsing type, unexpected end");
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
                    while ($nextnextpos < strlen($this->type) && ctype_space($this->type[$nextnextpos])) {
                        $nextnextpos++;
                    }
                    $nextnextchar = ($nextnextpos < strlen($this->type)) ? $this->type[$nextnextpos] : null;
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

        // Parse name part.
        $nextchar = ($this->nexttoken == null) ? null : $this->nexttoken[0];
        if ($nextchar == '"' || $nextchar == "'" || $nextchar >= '0' && $nextchar <= '9' || $nextchar == '-'
                || $nextchar == '$' || $nextchar == '\\' || $nextchar != null && ctype_alpha($nextchar) || $nextchar == '_') {
            $name = $this->parse_token();
        } else {
            throw new \Error("Error parsing type, expecting name, saw {$this->nexttoken}");
        }

        // Parse details part.
        if ($name == 'bool' || $name == 'boolean' || $name == 'true' || $name == 'false') {
            // Parse bool details.
            $name = 'bool';
        } else if ($name == 'int' || $name == 'integer'
                || $name == 'positive-int' || $name == 'negative-int'
                || $name == 'non-positive-int' || $name == 'non-negative-int'
                || $name == 'non-zero-int'
                || $name == 'int-mask' || $name == 'int-mask-of'
                || ($name[0] >= '0' && $name[0] <= '9' || $name[0] == '-') && strpos($name, '.') === false) {
            // Parse int details.
            if ($name == 'int' && $this->nexttoken == '<') {
                // Parse integer range.
                $this->parse_token('<');
                $nexttoken = $this->nexttoken;
                if (!($nexttoken != null && ($nexttoken[0] >= '0' && $nexttoken[0] <= '9' || $nexttoken[0] == '-')
                        || $nexttoken == 'min')) {
                    throw new \Error("Error parsing type, expected int min, saw {$nexttoken}");
                };
                $this->parse_token();
                $this->parse_token(',');
                $nexttoken = $this->nexttoken;
                if (!($nexttoken != null && ($nexttoken[0] >= '0' && $nexttoken[0] <= '9' || $nexttoken[0] == '-')
                        || $nexttoken == 'max')) {
                    throw new \Error("Error parsing type, expected int max, saw {$nexttoken}");
                };
                $this->parse_token();
                $this->parse_token('>');
            } else if ($name == 'int-mask' || $name == 'int-mask-of') {
                // Parse integer mask.
                $this->parse_token('<');
                $nextchar = ($this->nexttoken != null) ? $this->nexttoken[0] : null;
                if (ctype_digit($nextchar) || $name == 'int-mask') {
                    do {
                        if (!($nextchar != null && ctype_digit($nextchar) && strpos($this->nexttoken, '.') === false)) {
                            throw new \Error("Error parsing type, expected int mask, saw {$this->nexttoken}");
                        }
                        $this->parse_token();
                        $haveseperator = ($name == 'int-mask') && ($this->nexttoken == ',')
                                        || ($name == 'int-mask-of') && ($this->nexttoken == '|');
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
            $name = 'int';
        } else if ($name == 'float' || $name == 'double'
                || ($name[0] >= '0' && $name[0] <= '9' || $name[0] == '-') && strpos($name, '.') !== false) {
            // Parse float details.
            $name = 'float';
        } else if ($name == 'string' || $name == 'class-string'
                    || $name == 'numeric-string' || $name == 'non-empty-string'
                    || $name == 'non-falsy-string' || $name == 'truthy-string'
                    || $name == 'literal-string'
                    || $name[0] == '"' || $name[0] == "'") {
            // Parse string details.
            if ($name == 'class-string' && $this->nexttoken == '<') {
                $this->parse_token('<');
                $classname = $this->parse_single_type();
                if (!($classname[0] >= 'A' && $classname[0] <= 'Z' || $classname[0] == '_')) {
                    throw new \Error('Error parsing type, class string type isn\'t class name');
                }
                $this->parse_token('>');
            }
            $name = 'string';
        } else if ($name == 'callable-string') {
            // Parse callable-string details.
            $name = 'callable-string';
        } else if ($name == 'array' || $name == 'non-empty-array'
                    || $name == 'list' || $name == 'non-empty-list') {
            // Parse array details.
            if ($this->nexttoken == '<') {
                // Typed array.
                $this->parse_token('<');
                $firsttype = $this->parse_dnf_type();
                if ($this->nexttoken == ',') {
                    if ($name == 'list' || $name == 'non-empty-list') {
                        throw new \Error('Error parsing type, lists cannot have keys specified');
                    }
                    $key = $firsttype;
                    if (!in_array($key, [null, 'int', 'string', 'callable-string', 'array-key'])) {
                        throw new \Error('Error parsing type, invalid array key');
                    }
                    $this->parse_token(',');
                    $value = $this->parse_dnf_type();
                } else {
                    $key = null;
                    $value = $firsttype;
                }
                $this->parse_token('>');
            } else if ($this->nexttoken == '{') {
                // Array shape.
                if ($name == 'list' || $name == 'non-empty-list') {
                    throw new \Error('Error parsing type, lists cannot have shapes');
                }
                $this->parse_token('{');
                do {
                    $key = null;
                    $savednextpos = $this->nextpos;
                    $savednexttoken = $this->nexttoken;
                    $savednextnextpos = $this->nextnextpos;
                    try {
                        $key = $this->parse_token();
                        if (!(ctype_alpha($key) || $key[0] == '_' || $key[0] == "'" || $key[0] == '"'
                                || (ctype_digit($key) || $key[0] == '-') && strpos($key, '.') === false)) {
                            throw new \Error('Error parsing type, invalid array key');
                        }
                        if ($this->nexttoken == '?') {
                            $this->parse_token('?');
                        }
                        $this->parse_token(':');
                    } catch (\Error $e) {
                        $this->nextpos = $savednextpos;
                        $this->nexttoken = $savednexttoken;
                        $this->nextnextpos = $savednextnextpos;
                    }
                    $this->parse_dnf_type();
                    $havecomma = $this->nexttoken == ',';
                    if ($havecomma) {
                        $this->parse_token(',');
                    }
                } while ($havecomma);
                $this->parse_token('}');
            }
            $name = 'array';
        } else if ($name == 'object') {
            // Parse object details.
            if ($this->nexttoken == '{') {
                // Object shape.
                $this->parse_token('{');
                do {
                    $key = $this->parse_token();
                    if (!(ctype_alpha($key) || $key[0] == '_' || $key[0] == "'" || $key[0] == '"')) {
                        throw new \Error('Error parsing type, invalid array key');
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
            $name = 'object';
        } else if ($name == 'resource') {
            // Parse resource details.
            $name = 'resource';
        } else if ($name == 'never' || $name == 'never-return' || $name == 'never-returns' || $name == 'no-return') {
            // Parse never details.
            $name = 'never';
        } else if ($name == 'void' || $name == 'null') {
            // Parse void details.
            $name = 'void';
        } else if ($name == 'self') {
            // Parse self details.
            $name = 'self';
        } else if ($name == 'parent') {
            // Parse parent details.
            $name = 'parent';
        } else if ($name == 'static' || $name == '$this') {
            // Parse static details.
            $name = 'static';
        } else if ($name == 'callable') {
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
                        $nextchar = ($this->nexttoken != null) ? $this->nexttoken[0] : null;
                        if ($nextchar == '$') {
                            $this->parse_token();
                        } else {
                            throw new \Error("Error parsing type, expected var name, saw {$this->nexttoken}");
                        }
                    }
                    if ($this->nexttoken != ')') {
                        if ($splat) {
                            throw new \Error("Error parsing type, expected end of param list, saw {$this->nexttoken}");
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
            $name = 'callable';
        } else if ($name == 'mixed') {
            // Parse mixed details.
            $name = 'mixed';
        } else if ($name == 'iterable') {
            // Parse iterable details (Traversable|array).
            if ($this->nexttoken == '<') {
                $this->parse_token('<');
                $this->parse_dnf_type();
                $this->parse_token('>');
            }
            $name = 'iterable';
        } else if ($name == 'array-key') {
            // Parse array-key details (int|string).
            $name = 'array-key';
        } else if ($name == 'scalar') {
            // Parse scalar details (bool|int|float|string).
            $name = 'scalar';
        } else if ($name == 'key-of') {
            // Parse key-of details.
            $this->parse_token('<');
            $this->parse_dnf_type();
            $this->parse_token('>');
            $name = 'array-key';
        } else if ($name == 'value-of') {
            // Parse value-of details.
            $this->parse_token('<');
            $this->parse_dnf_type();
            $this->parse_token('>');
            $name = 'mixed';
        } else {
            // Check valid class name.
            if (strpos($name, '$') !== false || strpos($name, '-') !== false || strpos($name, '\\\\') !== false) {
                throw new \Error('Error parsing type, invalid class name');
            }
            $lastseperatorpos = strrpos($name, '\\');
            if ($lastseperatorpos !== false) {
                $name = substr($name, $lastseperatorpos + 1);
            }
            if ($name == '') {
                throw new \Error('Error parsing type, class name has trailing slash');
            }
            $name = ucfirst($name);
        }

        if ($this->nexttoken == '::' && ($name == 'object' || in_array('object', static::super_types($name)))) {
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
            $name = 'mixed';
        } else if ($this->nexttoken == '[') {
            // Parse array suffix.
            $this->parse_token('[');
            $this->parse_token(']');
            $name = 'array';
        }

        return $name;
    }

}
