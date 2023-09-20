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
 * Global constants and the Collection|Type[] construct aren't supported,
 * because this would require looking up type and global definitions.
 *
 * @package     local_moodlecheck
 * @copyright   2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author      James Calder
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (or CC BY-SA v4 or later)
 */
class type_parser {

    /** @var string the text to be parsed */
    protected $text;

    /** @var string the text to be parsed, with case retained */
    protected $textwithcase;

    /** @var bool when we encounter an unknown type, should we go wide or narrow */
    protected $gowide;

    /** @var object{startpos: non-negative-int, endpos: non-negative-int, text: ?non-empty-string}[] next tokens */
    protected $nexts;

    /** @var ?non-empty-string the next token */
    protected $next;

    /**
     * Parse a type and possibly variable name
     *
     * @param string $text the text to parse
     * @param 0|1|2|3 $getwhat what to get 0=type only 1=also var 2=also modifiers (& ...) 3=also default
     * @param bool $gowide if we can't determine the type, should we assume wide (for native type) or narrow (for PHPDoc)?
     * @return array{?non-empty-string, ?non-empty-string, string, bool} the simplified type, variable, remaining text,
     *                                                                   and whether the type is explicitly nullable
     */
    public function parse_type_and_var(string $text, int $getwhat, bool $gowide): array {

        // Initialise variables.
        $this->text = strtolower($text);
        $this->textwithcase = $text;
        $this->gowide = $gowide;
        $this->nexts = [];
        $this->next = $this->next();

        // Try to parse type.
        $savednexts = $this->nexts;
        try {
            $type = $this->parse_any_type();
            $explicitnullable = strpos("|{$type}|", "|void|") !== false; // For code smell check.
            if (!($this->next == null || $getwhat >= 1
                    || ctype_space(substr($this->text, $this->nexts[0]->startpos - 1, 1))
                    || in_array($this->next, [',', ';', ':', '.']))) {
                // Code smell check.
                throw new \Exception("Warning parsing type, no space after type.");
            }
        } catch (\Exception $e) {
            $this->nexts = $savednexts;
            $this->next = $this->next();
            $type = null;
            $explicitnullable = false;
        }

        // Try to parse variable.
        if ($getwhat >= 1) {
            $savednexts = $this->nexts;
            try {
                $variable = '';
                if ($getwhat >= 2) {
                    if ($this->next == '&') {
                        // Not adding this for code smell check,
                        // because the checker previously disallowed pass by reference & in PHPDocs,
                        // so adding this would be a nusiance for people who changed their PHPDocs
                        // to conform to the previous rules.
                        $this->parse_token('&');
                    }
                    if ($this->next == '...') {
                        // Add to variable name for code smell check.
                        $variable .= $this->parse_token('...');
                    }
                }
                if (!($this->next != null && $this->next[0] == '$')) {
                    throw new \Exception("Error parsing type, expected variable, saw \"{$this->next}\".");
                }
                $variable .= $this->next(0, true);
                assert($variable != '');
                $this->parse_token();
                if (!($this->next == null || $getwhat >= 3 && $this->next == '='
                        || ctype_space(substr($this->text, $this->nexts[0]->startpos - 1, 1))
                        || in_array($this->next, [',', ';', ':', '.']))) {
                    // Code smell check.
                    throw new \Exception("Warning parsing type, no space after variable name.");
                }
                if ($getwhat >= 3) {
                    if ($this->next == '=' && $this->next(1) == 'null' && $type != null) {
                        $type = $type . '|void';
                    }
                }
            } catch (\Exception $e) {
                $this->nexts = $savednexts;
                $this->next = $this->next();
                $variable = null;
            }
        } else {
            $variable = null;
        }

        return [$type, $variable, trim(substr($text, $this->nexts[0]->startpos)), $explicitnullable];
    }

    /**
     * Compare types
     *
     * @param ?non-empty-string $widetype the type that should be wider, e.g. PHP type
     * @param ?non-empty-string $narrowtype the type that should be narrower, e.g. PHPDoc type
     * @return bool whether $narrowtype has the same or narrower scope as $widetype
     */
    public static function compare_types(?string $widetype, ?string $narrowtype): bool {
        if ($narrowtype == null) {
            return false;
        } else if ($widetype == null || $widetype == 'mixed' || $narrowtype == 'never') {
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
                assert($narrowsingle != '');
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
     * @param non-empty-string $basetype
     * @return non-empty-string[] super types
     */
    protected static function super_types(string $basetype): array {
        if ($basetype == 'int') {
            $supertypes = ['array-key', 'number', 'scaler'];
        } else if ($basetype == 'string') {
            $supertypes = ['array-key', 'scaler'];
        } else if ($basetype == 'callable-string') {
            $supertypes = ['callable', 'string', 'array-key', 'scalar'];
        } else if ($basetype == 'float') {
            $supertypes = ['number', 'scalar'];
        } else if (in_array($basetype, ['array-key', 'number', 'bool'])) {
            $supertypes = ['scalar'];
        } else if ($basetype == 'array') {
            $supertypes = ['iterable'];
        } else if ($basetype == 'Traversable') {
            $supertypes = ['iterable', 'object'];
        } else if ($basetype == 'Closure') {
            $supertypes = ['callable', 'object'];
        } else if (in_array($basetype, ['self', 'parent', 'static'])
                || ctype_upper($basetype[0]) || $basetype[0] == '_') {
            $supertypes = ['object'];
        } else {
            $supertypes = [];
        }
        return $supertypes;
    }

    /**
     * Prefetch next token
     *
     * @param non-negative-int $lookahead
     * @param bool $getcase
     * @return ?non-empty-string
     */
    protected function next(int $lookahead = 0, bool $getcase = false): ?string {

        // Fetch any more tokens we need.
        while (count($this->nexts) < $lookahead + 1) {

            $startpos = $this->nexts ? end($this->nexts)->endpos : 0;
            $stringunterminated = false;

            // Ignore whitespace.
            while ($startpos < strlen($this->text) && ctype_space($this->text[$startpos])) {
                $startpos++;
            }

            $firstchar = ($startpos < strlen($this->text)) ? $this->text[$startpos] : null;

            // Deal with different types of tokens.
            if ($firstchar == null) {
                // No more tokens.
                $endpos = $startpos;
            } else if (ctype_alpha($firstchar) || $firstchar == '_' || $firstchar == '$' || $firstchar == '\\') {
                // Identifier token.
                $endpos = $startpos;
                do {
                    $endpos = $endpos + 1;
                    $nextchar = ($endpos < strlen($this->text)) ? $this->text[$endpos] : null;
                } while ($nextchar != null && (ctype_alnum($nextchar) || $nextchar == '_'
                                            || $firstchar != '$' && ($nextchar == '-' || $nextchar == '\\')));
            } else if (ctype_digit($firstchar)
                        || $firstchar == '-' && strlen($this->text) >= $startpos + 2 && ctype_digit($this->text[$startpos + 1])) {
                // Number token.
                $nextchar = $firstchar;
                $havepoint = false;
                $endpos = $startpos;
                do {
                    $havepoint = $havepoint || $nextchar == '.';
                    $endpos = $endpos + 1;
                    $nextchar = ($endpos < strlen($this->text)) ? $this->text[$endpos] : null;
                } while ($nextchar != null && (ctype_digit($nextchar) || $nextchar == '.' && !$havepoint || $nextchar == '_'));
            } else if ($firstchar == '"' || $firstchar == '\'') {
                // String token.
                $endpos = $startpos + 1;
                $nextchar = ($endpos < strlen($this->text)) ? $this->text[$endpos] : null;
                while ($nextchar != $firstchar && $nextchar != null) { // There may be unterminated strings.
                    if ($nextchar == '\\' && strlen($this->text) >= $endpos + 2) {
                        $endpos = $endpos + 2;
                    } else {
                        $endpos++;
                    }
                    $nextchar = ($endpos < strlen($this->text)) ? $this->text[$endpos] : null;
                }
                if ($nextchar != null) {
                    $endpos++;
                } else {
                    $stringunterminated = true;
                }
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
            $next = substr($this->text, $startpos, $endpos - $startpos);
            assert ($next !== false);
            if ($stringunterminated) {
                // If we have an unterminated string, we've reached the end of usable tokens.
                $next = '';
            }
            $this->nexts[] = (object)['startpos' => $startpos, 'endpos' => $endpos,
                'text' => ($next !== '') ? $next : null];
        }

        // Return the needed token.
        if ($getcase) {
            $next = substr($this->textwithcase, $this->nexts[$lookahead]->startpos,
                $this->nexts[$lookahead]->endpos - $this->nexts[$lookahead]->startpos);
            assert($next !== false);
            return ($next !== '') ? $next : null;
        } else {
            return $this->nexts[$lookahead]->text;
        }
    }

    /**
     * Fetch the next token
     *
     * @param ?non-empty-string $expect the expected text
     * @return non-empty-string
     */
    protected function parse_token(?string $expect = null): string {

        $next = $this->next;

        // Check we have the expected token.
        if ($expect != null && $next != $expect) {
            throw new \Exception("Error parsing type, expected \"{$expect}\", saw \"{$next}\".");
        } else if ($next == null) {
            throw new \Exception("Error parsing type, unexpected end.");
        }

        // Prefetch next token.
        $this->next(1);

        // Return consumed token.
        array_shift($this->nexts);
        $this->next = $this->next();
        return $next;
    }

    /**
     * Parse a list of types seperated by | and/or &, single nullable type, or conditional return type
     *
     * @param bool $inbrackets are we immediately inside brackets?
     * @return non-empty-string the simplified type
     */
    protected function parse_any_type(bool $inbrackets = false): string {

        if ($inbrackets && $this->next !== null && $this->next[0] == '$' && $this->next(1) == 'is') {
            // Conditional return type.
            $this->parse_token();
            $this->parse_token('is');
            $this->parse_any_type();
            $this->parse_token('?');
            $firsttype = $this->parse_any_type();
            $this->parse_token(':');
            $secondtype = $this->parse_any_type();
            $uniontypes = array_merge(explode('|', $firsttype), explode('|', $secondtype));
        } else if ($this->next == '?') {
            // Single nullable type.
            $this->parse_token('?');
            $uniontypes = explode('|', $this->parse_single_type());
            $uniontypes[] = 'void';
        } else {
            // Union list.
            $uniontypes = [];
            do {
                // Intersection list.
                $unioninstead = null;
                $intersectiontypes = [];
                do {
                    $singletype = $this->parse_single_type();
                    if (strpos($singletype, '|') !== false) {
                        $intersectiontypes[] = $this->gowide ? 'mixed' : 'never';
                        $unioninstead = $singletype;
                    } else {
                        $intersectiontypes = array_merge($intersectiontypes, explode('&', $singletype));
                    }
                    // We have to figure out whether a & is for intersection or pass by reference.
                    $nextnext = $this->next(1);
                    $havemoreintersections = $this->next == '&'
                        && !(in_array($nextnext, ['...', '=', ',', ')', null])
                            || $nextnext != null && $nextnext[0] == '$');
                    if ($havemoreintersections) {
                        $this->parse_token('&');
                    }
                } while ($havemoreintersections);
                if (count($intersectiontypes) > 1 && $unioninstead !== null) {
                    throw new \Exception("Error parsing type, non-DNF.");
                } else if (count($intersectiontypes) <= 1 && $unioninstead !== null) {
                    $uniontypes = array_merge($uniontypes, explode('|', $unioninstead));
                } else {
                    // Tidy and store intersection list.
                    if (count($intersectiontypes) > 1) {
                        foreach ($intersectiontypes as $intersectiontype) {
                            assert ($intersectiontype != '');
                            $supertypes = static::super_types($intersectiontype);
                            if (!($intersectiontype == 'object' || in_array('object', $supertypes))) {
                                throw new \Exception("Error parsing type, intersection can only be used with objects.");
                            }
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
                    }
                    array_push($uniontypes, implode('&', $intersectiontypes));
                }
                // Check for more union items.
                $havemoreunions = $this->next == '|';
                if ($havemoreunions) {
                    $this->parse_token('|');
                }
            } while ($havemoreunions);
        }

        // Tidy and return union list.
        if (count($uniontypes) > 1) {
            if ((in_array('int', $uniontypes) || in_array('number', $uniontypes)) && in_array('string', $uniontypes)) {
                $uniontypes[] = 'array-key';
            }
            if ((in_array('int', $uniontypes) || in_array('array-key', $uniontypes)) && in_array('float', $uniontypes)) {
                $uniontypes[] = 'number';
            }
            if (in_array('bool', $uniontypes) && in_array('number', $uniontypes) && in_array('array-key', $uniontypes)) {
                $uniontypes[] = 'scalar';
            }
            if (in_array('Traversable', $uniontypes) && in_array('array', $uniontypes)) {
                $uniontypes[] = 'iterable';
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
            foreach ($uniontypes as $uniontype) {
                assert ($uniontype != '');
                foreach ($uniontypes as $key => $uniontype2) {
                    assert($uniontype2 != '');
                    if ($uniontype2 != $uniontype && static::compare_types($uniontype, $uniontype2)) {
                        unset($uniontypes[$key]);
                    }
                }
            }
        }
        $type = implode('|', $uniontypes);
        assert($type != '');
        return $type;

    }

    /**
     * Parse a single type, possibly array type
     *
     * @return non-empty-string the simplified type
     */
    protected function parse_single_type(): string {
        if ($this->next == '(') {
            $this->parse_token('(');
            $type = $this->parse_any_type(true);
            $this->parse_token(')');
        } else {
            $type = $this->parse_basic_type();
        }
        while ($this->next == '[' && $this->next(1) == ']') {
            // Array suffix.
            $this->parse_token('[');
            $this->parse_token(']');
            $type = 'array';
        }
        return $type;
    }

    /**
     * Parse a basic type
     *
     * @return non-empty-string the simplified type
     */
    protected function parse_basic_type(): string {

        $next = $this->next;
        if ($next == null) {
            throw new \Exception("Error parsing type, expected type, saw end.");
        }
        $nextchar = $next[0];

        if (in_array($next, ['bool', 'boolean', 'true', 'false'])) {
            // Bool.
            $this->parse_token();
            $type = 'bool';
        } else if (in_array($next, ['int', 'integer', 'positive-int', 'negative-int',
                                    'non-positive-int', 'non-negative-int',
                                    'int-mask', 'int-mask-of'])
                || (ctype_digit($nextchar) || $nextchar == '-') && strpos($next, '.') === false) {
            // Int.
            $inttype = $this->parse_token();
            if ($inttype == 'int' && $this->next == '<') {
                // Integer range.
                $this->parse_token('<');
                $next = $this->next;
                if ($next == null
                        || !($next == 'min' || (ctype_digit($next[0]) || $next[0] == '-') && strpos($next, '.') === false)) {
                    throw new \Exception("Error parsing type, expected int min, saw \"{$next}\".");
                }
                $this->parse_token();
                $this->parse_token(',');
                $next = $this->next;
                if ($next == null
                        || !($next == 'max' || (ctype_digit($next[0]) || $next[0] == '-') && strpos($next, '.') === false)) {
                    throw new \Exception("Error parsing type, expected int max, saw \"{$next}\".");
                }
                $this->parse_token();
                $this->parse_token('>');
            } else if ($inttype == 'int-mask') {
                // Integer mask.
                $this->parse_token('<');
                do {
                    $mask = $this->parse_basic_type();
                    if (!static::compare_types('int', $mask)) {
                        throw new \Exception("Error parsing type, invalid int mask.");
                    }
                    $haveseperator = $this->next == ',';
                    if ($haveseperator) {
                        $this->parse_token(',');
                    }
                } while ($haveseperator);
                $this->parse_token('>');
            } else if ($inttype == 'int-mask-of') {
                // Integer mask of.
                $this->parse_token('<');
                $mask = $this->parse_basic_type();
                if (!static::compare_types('int', $mask)) {
                    throw new \Exception("Error parsing type, invalid int mask.");
                }
                $this->parse_token('>');
            }
            $type = 'int';
        } else if (in_array($next, ['float', 'double'])
                || (ctype_digit($nextchar) || $nextchar == '-') && strpos($next, '.') !== false) {
            // Float.
            $this->parse_token();
            $type = 'float';
        } else if (in_array($next, ['string', 'class-string', 'numeric-string', 'literal-string',
                                    'non-empty-string', 'non-falsy-string', 'truthy-string'])
                    || $nextchar == '"' || $nextchar == '\'') {
            // String.
            $strtype = $this->parse_token();
            if ($strtype == 'class-string' && $this->next == '<') {
                $this->parse_token('<');
                $stringtype = $this->parse_any_type();
                if (!static::compare_types('object', $stringtype)) {
                    throw new \Exception("Error parsing type, class-string type isn't class.");
                }
                $this->parse_token('>');
            }
            $type = 'string';
        } else if ($next == 'callable-string') {
            // Callable-string.
            $this->parse_token('callable-string');
            $type = 'callable-string';
        } else if (in_array($next, ['array', 'non-empty-array', 'list', 'non-empty-list'])) {
            // Array.
            $arraytype = $this->parse_token();
            if ($this->next == '<') {
                // Typed array.
                $this->parse_token('<');
                $firsttype = $this->parse_any_type();
                if ($this->next == ',') {
                    if (in_array($arraytype, ['list', 'non-empty-list'])) {
                        throw new \Exception("Error parsing type, lists cannot have keys specified.");
                    }
                    $key = $firsttype;
                    if (!static::compare_types('array-key', $key)) {
                        throw new \Exception("Error parsing type, invalid array key.");
                    }
                    $this->parse_token(',');
                    $value = $this->parse_any_type();
                } else {
                    $key = null;
                    $value = $firsttype;
                }
                $this->parse_token('>');
            } else if ($this->next == '{') {
                // Array shape.
                if (in_array($arraytype, ['non-empty-array', 'non-empty-list'])) {
                    throw new \Exception("Error parsing type, non-empty-arrays cannot have shapes.");
                }
                $this->parse_token('{');
                do {
                    $next = $this->next;
                    if ($next != null
                            && (ctype_alpha($next) || $next[0] == '_' || $next[0] == '\'' || $next[0] == '"'
                                || (ctype_digit($next[0]) || $next[0] == '-') && strpos($next, '.') === false)
                            && ($this->next(1) == ':' || $this->next(1) == '?' && $this->next(2) == ':')) {
                        $this->parse_token();
                        if ($this->next == '?') {
                            $this->parse_token('?');
                        }
                        $this->parse_token(':');
                    }
                    $this->parse_any_type();
                    $havecomma = $this->next == ',';
                    if ($havecomma) {
                        $this->parse_token(',');
                    }
                } while ($havecomma);
                $this->parse_token('}');
            }
            $type = 'array';
        } else if ($next == 'object') {
            // Object.
            $this->parse_token('object');
            if ($this->next == '{') {
                // Object shape.
                $this->parse_token('{');
                do {
                    $next = $this->next;
                    if ($next == null
                        || !(ctype_alpha($next) || $next[0] == '_' || $next[0] == '\'' || $next[0] == '"')) {
                        throw new \Exception("Error parsing type, invalid object key.");
                    }
                    $this->parse_token();
                    if ($this->next == '?') {
                        $this->parse_token('?');
                    }
                    $this->parse_token(':');
                    $this->parse_any_type();
                    $havecomma = $this->next == ',';
                    if ($havecomma) {
                        $this->parse_token(',');
                    }
                } while ($havecomma);
                $this->parse_token('}');
            }
            $type = 'object';
        } else if ($next == 'resource') {
            // Resource.
            $this->parse_token('resource');
            $type = 'resource';
        } else if (in_array($next, ['never', 'never-return', 'never-returns', 'no-return'])) {
            // Never.
            $this->parse_token();
            $type = 'never';
        } else if (in_array($next, ['void', 'null'])) {
            // Void.
            $this->parse_token();
            $type = 'void';
        } else if ($next == 'self') {
            // Self.
            $this->parse_token('self');
            $type = 'self';
        } else if ($next == 'parent') {
            // Parent.
            $this->parse_token('parent');
            $type = 'parent';
        } else if (in_array($next, ['static', '$this'])) {
            // Static.
            $this->parse_token();
            $type = 'static';
        } else if (in_array($next, ['callable', 'closure', '\closure'])) {
            // Callable.
            $callabletype = $this->parse_token();
            if ($this->next == '(') {
                $this->parse_token('(');
                while ($this->next != ')') {
                    $this->parse_any_type();
                    if ($this->next == '&') {
                        $this->parse_token('&');
                    }
                    if ($this->next == '...') {
                        $this->parse_token('...');
                    }
                    if ($this->next == '=') {
                        $this->parse_token('=');
                    }
                    $nextchar = ($this->next != null) ? $this->next[0] : null;
                    if ($nextchar == '$') {
                        $this->parse_token();
                    }
                    if ($this->next != ')') {
                        $this->parse_token(',');
                    }
                }
                $this->parse_token(')');
                $this->parse_token(':');
                if ($this->next == '?') {
                    $this->parse_any_type();
                } else {
                    $this->parse_single_type();
                }
            }
            if ($callabletype == 'callable') {
                $type = 'callable';
            } else {
                $type = 'Closure';
            }
        } else if ($next == 'mixed') {
            // Mixed.
            $this->parse_token('mixed');
            $type = 'mixed';
        } else if ($next == 'iterable') {
            // Iterable (Traversable|array).
            $this->parse_token('iterable');
            if ($this->next == '<') {
                $this->parse_token('<');
                $firsttype = $this->parse_any_type();
                if ($this->next == ',') {
                    $key = $firsttype;
                    $this->parse_token(',');
                    $value = $this->parse_any_type();
                } else {
                    $key = null;
                    $value = $firsttype;
                }
                $this->parse_token('>');
            }
            $type = 'iterable';
        } else if ($next == 'array-key') {
            // Array-key (int|string).
            $this->parse_token('array-key');
            $type = 'array-key';
        } else if ($next == 'number') {
            // Number (int|float).
            $this->parse_token('number');
            $type = 'number';
        } else if ($next == 'scalar') {
            // Scalar can be (bool|int|float|string).
            $this->parse_token('scalar');
            $type = 'scalar';
        } else if ($next == 'key-of') {
            // Key-of.
            $this->parse_token('key-of');
            $this->parse_token('<');
            $iterable = $this->parse_any_type();
            if (!(static::compare_types('iterable', $iterable) || static::compare_types('object', $iterable))) {
                throw new \Exception("Error parsing type, can't get key of non-iterable.");
            }
            $this->parse_token('>');
            $type = $this->gowide ? 'mixed' : 'never';
        } else if ($next == 'value-of') {
            // Value-of.
            $this->parse_token('value-of');
            $this->parse_token('<');
            $iterable = $this->parse_any_type();
            if (!(static::compare_types('iterable', $iterable) || static::compare_types('object', $iterable))) {
                throw new \Exception("Error parsing type, can't get value of non-iterable.");
            }
            $this->parse_token('>');
            $type = $this->gowide ? 'mixed' : 'never';
        } else if ((ctype_alpha($next[0]) || $next[0] == '_' || $next[0] == '\\')
                && strpos($next, '-') === false && strpos($next, '\\\\') === false) {
            // Class name.
            $type = $this->parse_token();
            $lastseperatorpos = strrpos($type, '\\');
            if ($lastseperatorpos !== false) {
                $type = substr($type, $lastseperatorpos + 1);
                if ($type == '') {
                    throw new \Exception("Error parsing type, class name has trailing slash.");
                }
            }
            $type = ucfirst($type);
            assert($type != '');
            if ($this->next == '<') {
                // Collection / Traversable.
                $this->parse_token('<');
                $firsttype = $this->parse_any_type();
                if ($this->next == ',') {
                    $key = $firsttype;
                    $this->parse_token(',');
                    $value = $this->parse_any_type();
                } else {
                    $key = null;
                    $value = $firsttype;
                }
                $this->parse_token('>');
            }
        } else {
            throw new \Exception("Error parsing type, unrecognised type.");
        }

        // Suffix.
        // We can't embed this in the class name section, because it could apply to relative classes.
        if ($this->next == '::' && (in_array('object', static::super_types($type)))) {
            // Class constant.
            $this->parse_token('::');
            $nextchar = ($this->next == null) ? null : $this->next[0];
            $haveconstantname = $nextchar != null && (ctype_alpha($nextchar) || $nextchar == '_');
            if ($haveconstantname) {
                $this->parse_token();
            }
            if ($this->next == '*' || !$haveconstantname) {
                $this->parse_token('*');
            }
            $type = $this->gowide ? 'mixed' : 'never';
        }

        assert(strpos($type, '|') === false && strpos($type, '&') === false);
        return $type;
    }

}
