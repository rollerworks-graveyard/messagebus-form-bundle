<?php

declare(strict_types=1);

namespace Rollerworks\Bundle\MessageBusFormBundle\Test;

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\Comparator\ComparisonFailure;
use SebastianBergmann\Comparator\Factory as ComparatorFactory;
use Symfony\Component\Form\FormError;
use function is_array;
use function is_object;
use function iterator_to_array;
use function sprintf;
use function trim;

final class IsFormErrorsEqual extends Constraint
{
    /** @var mixed */
    private $value;

    /**
     * @param FormError[]|FormError $expected
     */
    public function __construct($expected)
    {
        parent::__construct();

        $this->value = $this->ensureArray($expected);
    }

    public function evaluate($other, $description = '', $returnResult = false)
    {
        $other = $this->ensureArray($other);

        $comparatorFactory = new ComparatorFactory();
        $comparatorFactory->register(new FormErrorComparator());

        try {
            $comparator = $comparatorFactory->getComparatorFor($this->value, $other);
            $comparator->assertEquals($this->value, $other);
        } catch (ComparisonFailure $f) {
            if ($returnResult) {
                return false;
            }

            throw new ExpectationFailedException(trim($description . "\n" . $f->getMessage()), $f);
        }

        return true;
    }

    public function toString(): string
    {
        return sprintf('is equal to %s', $this->exporter->export($this->value));
    }

    private function ensureArray($other): array
    {
        if (is_object($other) && $other instanceof FormError) {
            $other = [$other];
        } elseif (! is_array($other)) {
            $other = iterator_to_array($other);
        }

        return $other;
    }
}
