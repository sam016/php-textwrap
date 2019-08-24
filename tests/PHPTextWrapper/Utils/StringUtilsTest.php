<?php

declare(strict_types=1);
/*
 * This file is part of PHPTextWrapper.
 *
 * (c) sam016 <varun@sam016.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPTextWrapper\Utils;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Error\Warning as PHPUnit_Error_Warning;

class SprintfTest extends TestCase
{
    function testReturnsValidStringWhenValidFormattingPassed()
    {
        $formatted = StringUtils::sprintfn('Hello, I am %(first)s %(second)s, a professional %(third)s.', array(
            'first' => 'John',
            'second' => 'Doe',
            'third' => 'Assassin',
        ));

        $this->assertEquals($formatted, 'Hello, I am John Doe, a professional Assassin.');
    }

    function testReturnsValidStringWhenRepeatedNamedArgsUsed()
    {
        $formatted = StringUtils::sprintfn('Hello, I am %(first)s %(second)s, a professional %(third)s. Do you want to be Mrs. %(second)s?', array(
            'first' => 'John',
            'second' => 'Doe',
            'third' => 'Assassin',
        ));

        $this->assertEquals($formatted, 'Hello, I am John Doe, a professional Assassin. Do you want to be Mrs. Doe?');
    }

    function testThrowsErrorWhenNamedArgumentNotPassed()
    {
        try {
            $formatted = StringUtils::sprintfn('Hello, I am %(first)s %(second)s, a professional %(third)s.', array(
                'first' => 'John',
                'second' => 'Doe',
            ));
        } catch (\Exception $ex) {
            $this->assertInstanceOf(PHPUnit_Error_Warning::class, $ex);
            $this->assertEquals($ex->getMessage(), "sprintfn(): Missing argument 'third'");
            $this->assertEquals($formatted, false);
        }
    }
}

class ExpandTabsTest extends TestCase
{
    function testExpandsTabsCorrectly()
    {
        $input = "123\t12345\t1234\t1\n12\t1234\t123\t1";
        $expected =
            "123       12345     1234      1\n" .
            '12        1234      123       1';

        $result = StringUtils::expandTabs($input, 10);

        $this->assertEquals($result, $expected);
    }
}
