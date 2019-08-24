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


class StringUtils
{
    /**
     * version of sprintf for cases where named arguments are desired (python syntax)
     *
     * with sprintf: sprintf('second: %2$s ; first: %1$s', '1st', '2nd');
     *
     * with sprintfn: sprintfn('second: %(second)s ; first: %(first)s', array(
     *  'first' => '1st',
     *  'second'=> '2nd'
     * ));
     *
     * @param string $format sprintf format string, with any number of named arguments
     * @param array $args array of [ 'arg_name' => 'arg value', ... ] replacements to be made
     * @return string|false result of sprintf call, or bool false on error
     */
    static function sprintfn($format, array $args = array()): string
    {
        // map of argument names to their corresponding sprintf numeric argument value
        $arg_nums = array_slice(array_flip(array_keys(array(0 => 0) + $args)), 1);

        // find the next named argument. each search starts at the end of the previous replacement.
        for ($pos = 0; preg_match('/(?<=%)\(([a-zA-Z_]\w*)\)s/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
            $arg_pos = $match[0][1];
            $arg_len = strlen($match[0][0]);
            $arg_key = $match[1][0];

            // programmer did not supply a value for the named argument found in the format string
            if (!array_key_exists($arg_key, $arg_nums)) {
                user_error("sprintfn(): Missing argument '${arg_key}'", E_USER_WARNING);
                return false;
            }

            // replace the named argument with the corresponding numeric one
            $format = substr_replace($format, $replace = $arg_nums[$arg_key] . '$s', $arg_pos, $arg_len);
            $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
        }

        return vsprintf($format, array_values($args));
    }

    /**
     * Returns the portion of string specified by the start and length parameters.
     *
     * @param string $text
     * @param int $start
     * @param int $length
     * @return string
     */
    static function slice(string $text, ?int $start, ?int $stop = null): string
    {
        $start = $start ?: 0;

        if (isset($stop)) {
            return substr($text, $start, $stop - $start);
        } else {
            return substr($text, $start);
        }
    }

    /**
     * Expand tabs in the string
     *
     * > $input = "123\t12345\t1234\t1\n12\t1234\t123\t1";
     * > expandTabs($input, 10)
     * > 123       12345     1234      1
     * > 12        1234      123       1
     *
     * @param string $str
     * @param int $tabLength
     * @return string
     */
    static function expandTabs(string $str, int $tabLength = 8): string
    {
        $result = "";
        $pos = 0;
        $strlen = strlen($str);

        for ($i = 0; $i < $strlen; $i++) {
            $char = $str[$i];
            if ($char == "\t") {
                # instead of the tab character, append the
                # number of spaces to the next tab stop
                $char = str_repeat(" ", $tabLength - $pos % $tabLength);
                $pos = 0;
            } else if ($char == "\n") {
                $pos = 0;
            } else {
                $pos += 1;
            }
            $result .= $char;
        }

        return $result;
    }

    static function split_lines($text, $keep_line_breaks = true)
    {
        if (!$keep_line_breaks) {
            return preg_split('/\n/m', $text, -1);
        }

        $splits = preg_split('/(\n)/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $lines = array();

        for ($i = 0, $n = count($splits) - 1; $i < $n; $i += 2) {
            $lines[] = $splits[$i] . $splits[$i + 1];
        }

        if ($splits[$n] != '') {
            $lines[] = $splits[$n];
        }

        return $lines;
    }

    static function split($text, $separator = '\s+', $limit = -1)
    {
        return preg_split('/' . $separator . '/', $text, $limit);
    }

    // Function to check the string is ends
    // with given substring or not
    static function ends_with($string, $endString)
    {
        $len = strlen($endString);
        if ($len == 0) {
            return true;
        }
        return (substr($string, -$len) === $endString);
    }

    // Function to check string starting
    // with given substring
    static function starts_with($string, $startString)
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }

    static function zip($str1, $str2)
    {
        $len_str1 = strlen($str1);
        $len_str2 = strlen($str2);
        $len_min = min($len_str1, $len_str2);

        $result = array();
        for ($ind = 0; $ind < $len_min; $ind++) {
            $result[] = array($str1[$ind], $str2[$ind]);
        }

        return $result;
    }
}
