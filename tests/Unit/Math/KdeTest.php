<?php

namespace PhpBench\Tests\Tests\Unit\Unit\Math;

use PhpBench\Math\SciPyKde;
use PhpBench\Math\Statistics;
use PhpBench\Math\Kde;

class KdeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * It should evaluate a kernel distribution estimate over a given space.
     *
     * @dataProvider provideEvaluate
     */
    public function testEvaluate($dataSet, $space, $bwMethod, $expected)
    {
        $kde = new Kde($dataSet, $bwMethod);
        $result = $kde->evaluate($space);

        // round result
        $result = array_map(function ($v) { return round($v, 8); }, $result);

        $this->assertEquals($expected, $result);
    }

    public function provideEvaluate()
    {
        return array(
            array(
                array(
                    10, 20, 15, 5
                ),
                Statistics::linspace(0, 9, 10),
                'silverman',
                array(
                    0.01537595, 0.0190706, 0.02299592, 0.02700068, 0.03092369, 0.0346125, 0.03794007, 0.0408159, 0.04318983, 0.04504829
                ),
            ),
            array(
                array(
                    10, 20, 15, 5
                ),
                Statistics::linspace(0, 3, 4),
                'scott',
                array(
                    0.01480612,  0.01869787,  0.02286675,  0.02713209
                ),
            ),
            array(
                array(
                    10, 20, 15, 5
                ),
                Statistics::linspace(0, 3, 4),
                'silverman',
                array(
                    0.01537595, 0.0190706, 0.02299592, 0.02700068,
                ),
            ),
        );
    }

    /**
     * It should throw an exception if an invalid bandwidth method is given.
     *
     * @expectedException InvalidArgumentException
     */
    public function testInvalidBandwidth()
    {
        new Kde(array(1,2), 'foo');
    }

    /**
     * It should throw an exception if the data set has zero elements.
     *
     * @expectedException OutOfBoundsException
     */
    public function testNoElements()
    {
        new Kde(array());
    }

    /**
     * It should throw an exception if the data set has only a single element.
     *
     * @expectedException OutOfBoundsException
     */
    public function testOneElement()
    {
        new Kde(array(1));
    }
}
