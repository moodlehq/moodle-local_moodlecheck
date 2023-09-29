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
 * A collection of valid types for testing
 *
 * This file should have no errors when checked with either PHPStan or Psalm.
 * Having just valid code in here means it can be easily checked with other checkers,
 * to verify we are actually checking against correct examples.
 *
 * @package   local_moodlecheck
 * @copyright 2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author    James Calder
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (or CC BY-SA v4 or later)
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A parent class
 */
class types_valid_parent {
}

/**
 * An interface
 */
interface types_valid_interface {
}

/**
 * A collection of valid types for testing
 *
 * @package   local_moodlecheck
 * @copyright 2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author    James Calder
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (or CC BY-SA v4 or later)
 */
class types_valid extends types_valid_parent {

    /** @var array<int, string> */
    public const ARRAY_CONST = [ 1 => 'one', 2 => 'two' ];
    /** @var int */
    public const INT_ONE = 1;
    /** @var int */
    public const INT_TWO = 2;
    /** @var float */
    public const FLOAT_1_0 = 1.0;
    /** @var float */
    public const FLOAT_2_0 = 2.0;
    /** @var string */
    public const STRING_HELLO = "Hello";
    /** @var string */
    public const STRING_WORLD = "World";
    /** @var bool */
    public const BOOL_FALSE = false;
    /** @var bool */
    public const BOOL_TRUE = true;


    /**
     * Basic type equivalence
     * @param bool $bool
     * @param int $int
     * @param float $float
     * @param string $string
     * @param object $object
     * @param self $self
     * @param parent $parent
     * @param types_valid $specificclass
     * @param callable $callable
     * @return void
     */
    public function basic_type_equivalence(
        bool $bool,
        int $int,
        float $float,
        string $string,
        object $object,
        self $self,
        parent $parent,
        types_valid $specificclass,
        callable $callable
    ): void {
    }

    /**
     * Types not supported natively (as of PHP 7.2)
     * @param array<int> $parameterisedarray
     * @param resource $resource
     * @param static $static
     * @param iterable<int> $parameterisediterable
     * @param array-key $arraykey
     * @param scalar $scalar
     * @param mixed $mixed
     * @return never
     */
    public function non_native_types($parameterisedarray, $resource, $static, $parameterisediterable,
            $arraykey, $scalar, $mixed) {
        throw new \Exception();
    }

    /**
     * Parameter modifiers
     * @param object &$reference
     * @param int ...$splat
     */
    public function parameter_modifiers(
        object &$reference,
        int ...$splat): void {
    }

    /**
     * Boolean types
     * @param bool|boolean $bool
     * @param true|false $literal
     */
    public function boolean_types(bool $bool, bool $literal): void {
    }

    /**
     * Integer types
     * @param int|integer $int
     * @param positive-int|negative-int|non-positive-int|non-negative-int $intrange1
     * @param int<0, 100>|int<min, 100>|int<50, max>|int<-100, max> $intrange2
     * @param 234|-234 $literal1
     * @param int-mask<1, 2, 4> $intmask1
     */
    public function integer_types(int $int, int $intrange1, int $intrange2,
        int $literal1, int $intmask1): void {
    }

    /**
     * Integer types complex
     * @param 1_000|-1_000 $literal2
     * @param int-mask<types_valid::INT_ONE, types_valid::INT_TWO> $intmask2
     * @param int-mask-of<types_valid::INT_*>|int-mask-of<key-of<types_valid::ARRAY_CONST>> $intmask3
     */
    public function integer_types_complex(int $literal2, int $intmask2, int $intmask3): void {
    }

    /**
     * Float types
     * @param float|double $float
     * @param 1.0|-1.0 $literal
     */
    public function float_types(float $float, float $literal): void {
    }

    /**
     * String types
     * @param string $string
     * @param class-string|class-string<types_valid> $classstring1
     * @param callable-string|numeric-string|non-empty-string|non-falsy-string|truthy-string|literal-string $other
     * @param 'foo'|'bar' $literal
     */
    public function string_types(string $string, string $classstring1, string $other, string $literal): void {
    }

    /**
     * String types complex
     * @param class-string<types_valid|types_valid_interface> $classstring2
     * @param '\'' $stringwithescape
     */
    public function string_types_complex(string $classstring2, string $stringwithescape): void {
    }

    /**
     * Array types
     * @param types_valid[]|array<types_valid>|array<int, string> $genarray1
     * @param non-empty-array<types_valid>|non-empty-array<int, types_valid> $genarray2
     * @param list<types_valid>|non-empty-list<types_valid> $list
     * @param array{'foo': int, "bar": string}|array{'foo': int, "bar"?: string}|array{int, int} $shapes1
     * @param array{0: int, 1?: int}|array{foo: int, bar: string} $shapes2
     */
    public function array_types(array $genarray1, array $genarray2, array $list,
        array $shapes1, array $shapes2): void {
    }

    /**
     * Array types complex
     * @param array<array-key, string>|array<1|2, string>|array<types_valid::INT_*, string> $genarray3
     */
    public function array_types_complex(array $genarray3): void {
    }

    /**
     * Object types
     * @param object $object
     * @param object{'foo': int, "bar": string}|object{'foo': int, "bar"?: string} $shapes1
     * @param object{foo: int, bar?: string} $shapes2
     * @param types_valid $class
     * @param self|parent|static|$this $relative
     * @param Traversable<int>|Traversable<int, int> $traversable1
     * @param \Closure|\Closure(int, int): string $closure
     */
    public function object_types(object $object, object $shapes1, object $shapes2, object $class,
        object $relative, object $traversable1, object $closure): void {
    }

    /**
     * Object types complex
     * @param Traversable<1|2, types_valid|types_valid_interface>|Traversable<types_valid::INT_*, string> $traversable2
     */
    public function object_types_complex(object $traversable2): void {
    }

    /**
     * Never type
     * @return never|never-return|never-returns|no-return
     */
    public function never_type() {
        throw new \Exception();
    }

    /**
     * Void type
     * @param null $standalonenull
     * @param ?int $explicitnullable
     * @param ?int $implicitnullable
     * @return void
     */
    public function void_type(
        $standalonenull,
        ?int $explicitnullable,
        int $implicitnullable=null
    ): void {
    }

    /**
     * User-defined type
     * @param types_valid|\types_valid $class
     */
    public function user_defined_type(types_valid $class): void {
    }

    /**
     * Callable types
     * @param callable|callable(int, int): string|callable(int, int=): string $callable1
     * @param callable(int $foo, string $bar): void $callable2
     * @param callable(float ...$floats): (int|null)|callable(float...): (int|null) $callable3
     * @param \Closure|\Closure(int, int): string $closure
     * @param callable-string $callablestring
     */
    public function callable_types(callable $callable1, callable $callable2, callable $callable3,
        callable $closure, callable $callablestring): void {
    }

    /**
     * Iterable types
     * @param array<int> $array
     * @param iterable<types_valid>|iterable<int, types_valid> $iterable1
     * @param Traversable<types_valid>|Traversable<int, types_valid> $traversable1
     */
    public function iterable_types(iterable $array, iterable $iterable1, iterable $traversable1): void {
    }

    /**
     * Iterable types complex
     * @param iterable<1|2, types_valid>|iterable<types_valid::INT_*, string> $iterable2
     * @param Traversable<1|2, types_valid>|Traversable<types_valid::INT_*, string> $traversable2
     */
    public function iterable_types_complex(iterable $iterable2, iterable $traversable2): void {
    }

    /**
     * Key and value of
     * @param key-of<types_valid::ARRAY_CONST> $keyof1
     * @param value-of<types_valid::ARRAY_CONST> $valueof1
     */
    public function key_and_value_of(int $keyof1, string $valueof1): void {
    }

    /**
     * Key and value of complex
     * @param key-of<types_valid::ARRAY_CONST|array<int, string>> $keyof2
     * @param value-of<types_valid::ARRAY_CONST|array<int, string>> $valueof2
     */
    public function key_and_value_of_complex(int $keyof2, string $valueof2): void {
    }

    /**
     * Conditional return types
     * @param int $size
     * @return ($size is positive-int ? non-empty-array<string> : array<string>)
     */
    public function conditional_return(int $size): array {
        return ($size > 0) ? array_fill(0, $size, "entry") : [];
    }

    /**
     * Conditional return types complex 1
     * @param types_valid::INT_*|types_valid::STRING_* $x
     * @return ($x is types_valid::INT_* ? types_valid::INT_* : types_valid::STRING_*)
     */
    public function conditional_return_complex_1($x) {
        return $x;
    }

    /**
     * Conditional return types complex 2
     * @param 1|2|'Hello'|'World' $x
     * @return ($x is 1|2 ? 1|2 : 'Hello'|'World')
     */
    public function conditional_return_complex_2($x) {
        return $x;
    }

    /**
     * Constant enumerations
     * @param types_valid::BOOL_FALSE|types_valid::BOOL_TRUE|types_valid::BOOL_* $bool
     * @param types_valid::INT_ONE $int1
     * @param types_valid::INT_ONE|types_valid::INT_TWO $int2
     * @param self::INT_* $int3
     * @param types_valid::* $mixed
     * @param types_valid::FLOAT_1_0|types_valid::FLOAT_2_0 $float
     * @param types_valid::STRING_HELLO $string
     * @param types_valid::ARRAY_CONST $array
     */
    public function constant_enumerations(bool $bool, int $int1, int $int2, int $int3, $mixed,
        float $float, string $string, array $array): void {
    }

    /**
     * Basic structure
     * @param ?int $nullable
     * @param int|string $union
     * @param types_valid&object{additionalproperty: string} $intersection
     * @param (int) $brackets
     * @param int[] $arraysuffix

     */
    public function basic_structure(
        ?int $nullable,
        $union,
        object $intersection,
        int $brackets,
        array $arraysuffix
    ): void {
    }

    /**
     * Structure combinations
     * @param int|float|string $multipleunion
     * @param types_valid&object{additionalproperty: string}&\Traversable<int> $multipleintersection
     * @param ((int)) $multiplebracket
     * @param int[][] $multiplearray
     * @param ?(int) $nullablebracket1
     * @param (?int) $nullablebracket2
     * @param ?int[] $nullablearray
     * @param (int|float) $unionbracket1
     * @param int|(float) $unionbracket2
     * @param int|int[] $unionarray
     * @param (types_valid&object{additionalproperty: string}) $intersectionbracket1
     * @param types_valid&(object{additionalproperty: string}) $intersectionbracket2
     * @param (int)[] $bracketarray1
     * @param (int[]) $bracketarray2
     * @param int|(types_valid&object{additionalproperty: string}) $dnf
     */
    public function structure_combos(
        $multipleunion,
        object $multipleintersection,
        int $multiplebracket,
        array $multiplearray,
        ?int $nullablebracket1,
        ?int $nullablebracket2,
        ?array $nullablearray,
        $unionbracket1,
        $unionbracket2,
        $unionarray,
        object $intersectionbracket1,
        object $intersectionbracket2,
        array $bracketarray1,
        array $bracketarray2,
        $dnf
    ): void {
    }

    /**
     * Inheritance
     * @param types_valid $basic
     * @param self|static|$this $relative1
     * @param types_valid $relative2
     */
    public function inheritance(
        types_valid_parent $basic,
        parent $relative1,
        parent $relative2
    ): void {
    }

    /**
     * Built-in classes
     * @param Traversable<string>|Iterator|Generator|IteratorAggregate $traversable
     * @param Iterator|Generator $iterator
     * @param Throwable|Exception|Error $throwable
     * @param Exception|ErrorException $exception
     * @param Error|ArithmeticError|AssertionError|ParseError|TypeError $error
     * @param ArithmeticError|DivisionByZeroError $arithmeticerror
     * @param CompileError|ParseError $compileerror
     */
    public function builtin_classes(
        Traversable $traversable, Iterator $iterator,
        Throwable $throwable, Exception $exception, Error $error,
        ArithmeticError $arithmeticerror, CompileError $compileerror
    ): void {
    }

    /**
     * SPL classes
     * @param Iterator|SeekableIterator<int, string>|ArrayIterator $iterator
     * @param SeekableIterator<int, string>|ArrayIterator $seekableiterator
     * @param Countable|ArrayIterator $countable
     */
    public function spl_classes(
        Iterator $iterator, SeekableIterator $seekableiterator, Countable $countable
    ): void {
    }

}
