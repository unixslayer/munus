<?php

declare(strict_types=1);

namespace Munus\Collection;

use Munus\Control\Option;
use Munus\Exception\UnsupportedOperationException;
use Munus\Value;
use Munus\Value\Comparator;

/**
 * An abstraction for inherently recursive, multi-valued data structures. The order of elements is determined by
 * Iterator, which may vary each time it is called.
 *
 * @template T
 * @template-extends Value<T>
 */
abstract class Traversable extends Value implements \IteratorAggregate
{
    /**
     * Computes the number of elements of this traversable.
     */
    abstract public function length(): int;

    /**
     * @throws \RuntimeException if is empty
     *
     * @return T
     */
    abstract public function head();

    /**
     * @throws \RuntimeException if is empty
     *
     * @return Traversable<T>
     */
    abstract public function tail();

    /**
     * @template U
     *
     * @param callable(T): U $mapper
     *
     * @return Traversable<U>
     */
    abstract public function map(callable $mapper);

    /**
     * Returns a new Traversable consisting of all elements which satisfy the given predicate.
     *
     * @param callable(T):bool $predicate
     *
     * @return Traversable<T>
     */
    abstract public function filter(callable $predicate);

    /**
     * @return Traversable<T>
     */
    abstract public function take(int $n);

    /**
     * @return Traversable<T>
     */
    abstract public function drop(int $n);

    /**
     * Drops elements while the predicate holds for the current element.
     *
     * @param callable(T):bool $predicate
     *
     * @return Traversable<T>
     */
    abstract public function dropWhile(callable $predicate);

    /**
     * Drops elements until the predicate holds for the current element.
     *
     * @param callable(T):bool $predicate
     *
     * @return Traversable<T>
     */
    public function dropUntil(callable $predicate)
    {
        return $this->dropWhile(function ($value) use ($predicate) {
            return !$predicate($value);
        });
    }

    /**
     * Returns a new Traversable consisting of all elements which do not satisfy the given predicate.
     *
     * @param callable(T):bool $predicate
     *
     * @return Traversable<T>
     */
    public function filterNot(callable $predicate)
    {
        return $this->filter(function ($value) use ($predicate) {
            return !$predicate($value);
        });
    }

    /**
     * @return T
     */
    public function get()
    {
        return $this->head();
    }

    public function isSingleValued(): bool
    {
        return false;
    }

    /**
     * @return Iterator<T>
     */
    public function iterator(): Iterator
    {
        return new Iterator($this);
    }

    /**
     * @param callable(T): bool $predicate
     *
     * @return Option<T>
     */
    public function find(callable $predicate): Option
    {
        $iterator = $this->iterator();
        while ($iterator->hasNext()) {
            $next = $iterator->next();
            if ($predicate($next) === true) {
                return Option::some($next);
            }
        }

        return Option::none();
    }

    public function equals($object): bool
    {
        if ($object === $this) {
            return true;
        }

        if (!is_object($object) || !$object instanceof Traversable) {
            return false;
        }

        $iterator1 = $object->iterator();
        $iterator2 = $this->iterator();

        while ($iterator1->hasNext() && $iterator2->hasNext()) {
            if (!Comparator::equals($iterator1->next(), $iterator2->next())) {
                return false;
            }
        }

        return $iterator1->hasNext() === $iterator2->hasNext();
    }

    public function reduce(callable $operation)
    {
        return $this->iterator()->reduce($operation);
    }

    /**
     * @template U
     *
     * @param U               $zero
     * @param callable(U,T):U $combine
     *
     * @return U
     */
    public function fold($zero, callable $combine)
    {
        return $this->iterator()->fold($zero, $combine);
    }

    /**
     * @return int|float
     */
    public function sum()
    {
        return $this->fold(0, function ($sum, $x) {
            if (!is_numeric($x)) {
                throw new UnsupportedOperationException('not numeric value');
            }

            return $sum + ($x * 1);
        });
    }

    /**
     * @return int|float
     */
    public function product()
    {
        return $this->fold(1, function ($product, $x) {
            if (!is_numeric($x)) {
                throw new UnsupportedOperationException('not numeric value');
            }

            return $product * ($x * 1);
        });
    }

    public function average(): float
    {
        if ($this->isEmpty()) {
            throw new UnsupportedOperationException('division by zero not possible');
        }

        return (float) ($this->sum() / $this->length());
    }

    /**
     * @return Option<T>
     */
    public function min(): Option
    {
        if ($this->isEmpty()) {
            return Option::none();
        }

        return Option::of($this->fold($this->head(), function ($min, $x) {
            return $min <= $x ? $min : $x;
        }));
    }

    /**
     * @return Option<T>
     */
    public function max(): Option
    {
        if ($this->isEmpty()) {
            return Option::none();
        }

        return Option::of($this->fold($this->head(), function ($max, $x) {
            return $max >= $x ? $max : $x;
        }));
    }

    /**
     * Counts the elements which satisfy the given predicate.
     *
     * @param callable(T): bool $predicate
     */
    public function count(callable $predicate): int
    {
        return $this->fold(0, function ($count, $value) use ($predicate) {
            return $predicate($value) === true ? ++$count : $count;
        });
    }

    /**
     * @return Iterator<T>
     */
    public function getIterator()
    {
        return $this->iterator();
    }
}
