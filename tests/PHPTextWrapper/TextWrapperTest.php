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

namespace PHPTextWrapper;

use InvalidArgumentException;
use PHPTextWrapper\Utils\StringUtils;
use PHPUnit\Framework\TestCase;


/**
 * Parent class with utility methods for textwrap tests.
 */
abstract class BaseTestCase extends TestCase
{

    /**
     * @var string $text
     */
    protected $text;

    /**
     * @var TextWrapper
     */
    protected $wrapper;

    /**
     * show
     *
     * @param string|string[] $textin
     * @return string
     */
    function show($textin): string
    {
        if (is_array($textin)) {
            $result = [];
            foreach (range(0, count($textin) - 1) as $i) {
                $result[] = sprintf("  %d: %s", $i, $textin[$i]);
            }
            $result = isset($result) ? implode("\n", $result) : "  no lines";
        } else if (is_string($textin)) {
            $result = sprintf("  %s\n", $textin);
        }
        return $result;
    }

    function check($result, $expect)
    {
        $this->assertEquals(
            $result,
            $expect,
            sprintf(
                'expected:\n%s\nbut got:\n%s',
                $this->show($expect),
                $this->show($result)
            )
        );
    }

    function check_wrap($text, $width, $expect, array $kwargs = array())
    {
        $result = StaticTextWrapper::wrap(
            $text,
            $width,
            $kwargs
        );

        $this->check($result, $expect);
    }

    function check_split($text, $expect)
    {
        $result = $this->wrapper->_split($text);
        $this->assertEquals(
            $result,
            $expect,
            sprintf("\nexpected %r\n" .
                "but got  %r", $expect, $result)
        );
    }
}


class WrapTestCase extends BaseTestCase
{

    protected function setUp(): void
    {
        $this->wrapper = new TextWrapper($width = 45);
    }

    function test_simple()
    {
        # Simple case: just words, spaces, and a bit of punctuation

        $text = "Hello there, how are you this fine day?  I'm glad to hear it!";

        $this->check_wrap(
            $text,
            12,
            [
                "Hello there,",
                "how are you",
                "this fine",
                "day?  I'm",
                "glad to hear",
                "it!"
            ]
        );
        $this->check_wrap(
            $text,
            42,
            [
                "Hello there, how are you this fine day?",
                "I'm glad to hear it!"
            ]
        );
        $this->check_wrap($text, 80, [$text]);
    }

    function test_empty_string()
    {
        # Check that wrapping the empty string returns an empty list.
        $this->check_wrap("", 6, []);
        $this->check_wrap("", 6, [], array('drop_whitespace' => False));
    }

    function test_empty_string_with_initial_indent()
    {
        # Check that the empty string is not indented.
        $this->check_wrap("", 6, [], array('initial_indent' => "++"));
        $this->check_wrap("", 6, [], array('initial_indent' => "++", 'drop_whitespace' => False));
    }

    function test_whitespace()
    {
        # Whitespace munging and end-of-sentence detection

        $text = "This is a paragraph that already has
line breaks. But some of its lines are much longer than the others,
so it needs to be wrapped.
Some lines are \ttabbed too.
What a mess!
";

        $expect = [
            "This is a paragraph that already has line",
            "breaks.  But some of its lines are much",
            "longer than the others, so it needs to be",
            "wrapped.  Some lines are  tabbed too.  What a",
            "mess!"
        ];

        $wrapper = StaticTextWrapper::initialize(45, array('fix_sentence_endings' => True));
        $result = $wrapper->wrap($text);
        $this->check($result, $expect);

        $result = $wrapper->fill($text);
        $this->check($result, implode("\n", $expect));

        $text = "\tTest\tdefault\t\ttabsize.";
        $expect = ["        Test    default         tabsize."];
        $this->check_wrap($text, 80, $expect);

        $text = "\tTest\tcustom\t\ttabsize.";
        $expect = ["    Test    custom      tabsize."];
        $this->check_wrap($text, 80, $expect, array('tab_size' => 4));
    }

    function test_fix_sentence_endings()
    {
        $wrapper = StaticTextWrapper::initialize(60, array('fix_sentence_endings' => True));

        # SF #847346: ensure that fix_sentence_endings=True does the
        # right thing even on input short enough that it doesn't need to
        # be wrapped.
        $text = "A short line. Note the single space.";
        $expect = ["A short line.  Note the single space."];
        $this->check($wrapper->wrap($text), $expect);

        # Test some of the hairy end cases that _fix_sentence_endings()
        # is supposed to handle (the easy stuff is tested in
        # test_whitespace() above).
        $text = "Well, Doctor? What do you think?";
        $expect = ["Well, Doctor?  What do you think?"];
        $this->check($wrapper->wrap($text), $expect);

        $text = "Well, Doctor?\nWhat do you think?";
        $this->check($wrapper->wrap($text), $expect);

        $text = "I say, chaps! Anyone for \"tennis?\"\nHmmph!";
        $expect = ["I say, chaps!  Anyone for \"tennis?\"  Hmmph!"];
        $this->check($wrapper->wrap($text), $expect);

        $wrapper->width = 20;
        $expect = ["I say, chaps!", "Anyone for \"tennis?\"", "Hmmph!"];
        $this->check($wrapper->wrap($text), $expect);

        $text = "And she said, \"Go to hell!\"\nCan you believe that?";
        $expect = [
            "And she said, \"Go to",
            "hell!\"  Can you",
            "believe that?"
        ];
        $this->check($wrapper->wrap($text), $expect);

        $wrapper->width = 60;
        $expect = ['And she said, "Go to hell!"  Can you believe that?'];
        $this->check($wrapper->wrap($text), $expect);

        $text = 'File stdio.h is nice.';
        $expect = ['File stdio.h is nice.'];
        $this->check($wrapper->wrap($text), $expect);
    }

    function test_wrap_short()
    {
        # Wrapping to make short lines longer

        $text = "This is a\nshort paragraph.";

        $this->check_wrap($text, 20, [
            "This is a short",
            "paragraph."
        ]);
        $this->check_wrap($text, 40, ["This is a short paragraph."]);
    }


    function test_wrap_short_1line()
    {
        # Test endcases

        $text = "This is a short line.";

        $this->check_wrap($text, 30, ["This is a short line."]);
        $this->check_wrap(
            $text,
            30,
            ["(1) This is a short line."],
            array('initial_indent' => "(1) ")
        );
    }

    function test_hyphenated()
    {
        # Test breaking hyphenated words

        $text = ("this-is-a-useful-feature-for-" .
            "reformatting-posts-from-tim-peters'ly");

        $this->check_wrap(
            $text,
            40,
            [
                "this-is-a-useful-feature-for-",
                "reformatting-posts-from-tim-peters'ly"
            ]
        );
        $this->check_wrap(
            $text,
            41,
            [
                "this-is-a-useful-feature-for-",
                "reformatting-posts-from-tim-peters'ly"
            ]
        );
        $this->check_wrap(
            $text,
            42,
            [
                "this-is-a-useful-feature-for-reformatting-",
                "posts-from-tim-peters'ly"
            ]
        );

        # The test tests current behavior but is not testing parts of the API.
        $expect = explode('|', "this-|is-|a-|useful-|feature-|for-|" .
            "reformatting-|posts-|from-|tim-|peters'ly");
        $this->check_wrap($text, 1, $expect, array('break_long_words' => False));
        $this->check_split($text, $expect);

        $this->check_split('e-mail', ['e-mail']);
        $this->check_split('Jelly-O', ['Jelly-O']);
        # The test tests current behavior but is not testing parts of the API.
        $this->check_split('half-a-crown', explode('|', 'half-|a-|crown'));
    }

    function test_hyphenated_numbers()
    {
        # Test that hyphenated numbers (eg. dates) are not broken like words.
        $text = ("Python 1.0.0 was released on 1994-01-26.  Python 1.0.1 was\n" .
            "released on 1994-02-15.");

        $this->check_wrap($text, 30, [
            'Python 1.0.0 was released on',
            '1994-01-26.  Python 1.0.1 was',
            'released on 1994-02-15.'
        ]);
        $this->check_wrap($text, 40, [
            'Python 1.0.0 was released on 1994-01-26.',
            'Python 1.0.1 was released on 1994-02-15.'
        ]);
        $this->check_wrap($text, 1, StringUtils::split($text), array('break_long_words' => False));

        $text = "I do all my shopping at 7-11.";
        $this->check_wrap($text, 25, [
            "I do all my shopping at",
            "7-11."
        ]);
        $this->check_wrap($text, 27, [
            "I do all my shopping at",
            "7-11."
        ]);
        $this->check_wrap($text, 29, ["I do all my shopping at 7-11."]);
        $this->check_wrap($text, 1, StringUtils::split($text), array('break_long_words' => False));
    }

    function test_em_dash()
    {
        # Test text with em-dashes
        $text = "Em-dashes should be written -- thus.";
        $this->check_wrap(
            $text,
            25,
            [
                "Em-dashes should be",
                "written -- thus."
            ]
        );

        # Probe the boundaries of the properly written em-dash,
        # ie. " -- ".
        $this->check_wrap(
            $text,
            29,
            [
                "Em-dashes should be written",
                "-- thus."
            ]
        );
        $expect = [
            "Em-dashes should be written --",
            "thus."
        ];
        $this->check_wrap($text, 30, $expect);
        $this->check_wrap($text, 35, $expect);
        $this->check_wrap(
            $text,
            36,
            ["Em-dashes should be written -- thus."]
        );

        # The improperly written em-dash is handled too, because
        # it's adjacent to non-whitespace on both sides.
        $text = "You can also do--this or even---this.";
        $expect = [
            "You can also do",
            "--this or even",
            "---this."
        ];
        $this->check_wrap($text, 15, $expect);
        $this->check_wrap($text, 16, $expect);
        $expect = [
            "You can also do--",
            "this or even---",
            "this."
        ];
        $this->check_wrap($text, 17, $expect);
        $this->check_wrap($text, 19, $expect);
        $expect = [
            "You can also do--this or even",
            "---this."
        ];
        $this->check_wrap($text, 29, $expect);
        $this->check_wrap($text, 31, $expect);
        $expect = [
            "You can also do--this or even---",
            "this."
        ];
        $this->check_wrap($text, 32, $expect);
        $this->check_wrap($text, 35, $expect);

        # All of the above behaviour could be deduced by probing the
        # _split() method.
        $text = "Here's an -- em-dash and--here's another---and another!";
        $expect = [
            "Here's", " ", "an", " ", "--", " ", "em-", "dash", " ",
            "and", "--", "here's", " ", "another", "---",
            "and", " ", "another!"
        ];
        $this->check_split($text, $expect);

        $text = "and then--bam!--he was gone";
        $expect = [
            "and", " ", "then", "--", "bam!", "--",
            "he", " ", "was", " ", "gone"
        ];
        $this->check_split($text, $expect);
    }

    function test_unix_options()
    {
        # Test that Unix-style command-line options are wrapped correctly.
        # Both Optik (OptionParser) and Docutils rely on this behaviour!

        $text = "You should use the -n option, or --dry-run in its long form.";
        $this->check_wrap(
            $text,
            20,
            [
                "You should use the",
                "-n option, or --dry-",
                "run in its long",
                "form."
            ]
        );
        $this->check_wrap(
            $text,
            21,
            [
                "You should use the -n",
                "option, or --dry-run",
                "in its long form."
            ]
        );
        $expect = [
            "You should use the -n option, or",
            "--dry-run in its long form."
        ];
        $this->check_wrap($text, 32, $expect);
        $this->check_wrap($text, 34, $expect);
        $this->check_wrap($text, 35, $expect);
        $this->check_wrap($text, 38, $expect);
        $expect = [
            "You should use the -n option, or --dry-",
            "run in its long form."
        ];
        $this->check_wrap($text, 39, $expect);
        $this->check_wrap($text, 41, $expect);
        $expect = [
            "You should use the -n option, or --dry-run",
            "in its long form."
        ];
        $this->check_wrap($text, 42, $expect);

        # Again, all of the above can be deduced from _split().
        $text = "the -n option, or --dry-run or --dryrun";
        $expect = [
            "the", " ", "-n", " ", "option,", " ", "or", " ",
            "--dry-", "run", " ", "or", " ", "--dryrun"
        ];
        $this->check_split($text, $expect);
    }

    function test_funky_hyphens()
    {
        # Screwy edge cases cooked up by David Goodger.  All reported
        # in SF bug #596434.
        $this->check_split("what the--hey!", ["what", " ", "the", "--", "hey!"]);
        $this->check_split("what the--", ["what", " ", "the--"]);
        $this->check_split("what the--.", ["what", " ", "the--."]);
        $this->check_split("--text--.", ["--text--."]);

        # When I first read bug #596434, this is what I thought David
        # was talking about.  I was wrong; these have always worked
        # fine.  The real problem is tested in test_funky_parens()
        # below...
        $this->check_split("--option", ["--option"]);
        $this->check_split("--option-opt", ["--option-", "opt"]);
        $this->check_split(
            "foo --option-opt bar",
            ["foo", " ", "--option-", "opt", " ", "bar"]
        );
    }

    function test_punct_hyphens()
    {
        # Oh bother, SF #965425 found another problem with hyphens --
        # hyphenated words in single quotes weren't handled correctly.
        # In fact, the bug is that *any* punctuation around a hyphenated
        # word was handled incorrectly, except for a leading "--", which
        # was special-cased for Optik and Docutils.  So test a variety
        # of styles of punctuation around a hyphenated word.
        # (Actually this is based on an Optik bug report, #813077).
        $this->check_split(
            "the 'wibble-wobble' widget",
            ['the', ' ', "'wibble-", "wobble'", ' ', 'widget']
        );
        $this->check_split(
            'the "wibble-wobble" widget',
            ['the', ' ', '"wibble-', 'wobble"', ' ', 'widget']
        );
        $this->check_split(
            "the (wibble-wobble) widget",
            ['the', ' ', "(wibble-", "wobble)", ' ', 'widget']
        );
        $this->check_split(
            "the ['wibble-wobble'] widget",
            ['the', ' ', "['wibble-", "wobble']", ' ', 'widget']
        );

        # The test tests current behavior but is not testing parts of the API.
        $this->check_split(
            "what-d'you-call-it.",
            explode('|', "what-d'you-|call-|it.")
        );
    }

    function test_funky_parens()
    {
        # Second part of SF bug #596434: long option strings inside
        # parentheses.
        $this->check_split(
            "foo (--option) bar",
            ["foo", " ", "(--option)", " ", "bar"]
        );

        # Related stuff -- make sure parens work in simpler contexts.
        $this->check_split(
            "foo (bar) baz",
            ["foo", " ", "(bar)", " ", "baz"]
        );
        $this->check_split(
            "blah (ding dong), wubba",
            [
                "blah", " ", "(ding", " ", "dong),",
                " ", "wubba"
            ]
        );
    }

    function test_drop_whitespace_false()
    {
        # Check that drop_whitespace=False preserves whitespace.
        # SF patch #1581073
        $text = " This is a    sentence with     much whitespace.";
        $this->check_wrap(
            $text,
            10,
            [
                " This is a",
                "    ",
                "sentence ",
                "with     ",
                "much white",
                "space."
            ],
            array('drop_whitespace' => False)
        );
    }

    function test_drop_whitespace_false_whitespace_only()
    {
        # Check that drop_whitespace=False preserves a whitespace-only string.
        $this->check_wrap("   ", 6, ["   "], array('drop_whitespace' => False));
    }

    function test_drop_whitespace_false_whitespace_only_with_indent()
    {
        # Check that a whitespace-only string gets indented (when
        # drop_whitespace is False).
        $this->check_wrap("   ", 6, ["     "], array(
            'drop_whitespace' => False,
            'initial_indent' => "  "
        ));
    }

    function test_drop_whitespace_whitespace_only()
    {
        # Check drop_whitespace on a whitespace-only string.
        $this->check_wrap("  ", 6, []);
    }

    function test_drop_whitespace_leading_whitespace()
    {
        # Check that drop_whitespace does not drop leading whitespace (if
        # followed by non-whitespace).
        # SF bug #622849 reported inconsistent handling of leading
        # whitespace; let's test that a bit, shall we?
        $text = " This is a sentence with leading whitespace.";
        $this->check_wrap(
            $text,
            50,
            [" This is a sentence with leading whitespace."]
        );
        $this->check_wrap(
            $text,
            30,
            [" This is a sentence with", "leading whitespace."]
        );
    }

    function test_drop_whitespace_whitespace_line()
    {
        # Check that drop_whitespace skips the whole line if a non-leading
        # line consists only of whitespace.
        $text = "abcd    efgh";
        # Include the result for drop_whitespace=False for comparison.
        $this->check_wrap(
            $text,
            6,
            ["abcd", "    ", "efgh"],
            array('drop_whitespace' => False)
        );
        $this->check_wrap($text, 6, ["abcd", "efgh"]);
    }

    function test_drop_whitespace_whitespace_only_with_indent()
    {
        # Check that initial_indent is not applied to a whitespace-only
        # string.  This checks a special case of the fact that dropping
        # whitespace occurs before indenting.
        $this->check_wrap("  ", 6, [], array('initial_indent' => "++"));
    }

    function test_drop_whitespace_whitespace_indent()
    {
        # Check that drop_whitespace does not drop whitespace indents.
        # This checks a special case of the fact that dropping whitespace
        # occurs before indenting.
        $this->check_wrap(
            "abcd efgh",
            6,
            ["  abcd", "  efgh"],
            array('initial_indent' => "  ", 'subsequent_indent' => "  ")
        );
    }

    function test_split()
    {
        # Ensure that the standard _split() method works as advertised
        # in the comments

        $text = "Hello there -- you goof-ball, use the -b option!";

        $result = $this->wrapper->_split($text);
        $this->check(
            $result,
            [
                "Hello", " ", "there", " ", "--", " ", "you", " ", "goof-",
                "ball,", " ", "use", " ", "the", " ", "-b", " ",  "option!"
            ]
        );
    }

    function test_break_on_hyphens()
    {
        # Ensure that the break_on_hyphens attributes work
        $text = "yaba daba-doo";
        $this->check_wrap(
            $text,
            10,
            ["yaba daba-", "doo"],
            array('break_on_hyphens' => True)
        );
        $this->check_wrap(
            $text,
            10,
            ["yaba", "daba-doo"],
            array('break_on_hyphens' => False)
        );
    }

    function test_bad_width()
    {
        $this->expectException(\InvalidArgumentException::class);

        # Ensure that width <= 0 is caught.
        $text = "Whatever, it doesn't matter.";

        $this->check_wrap($text, 0, '');
        $this->check_wrap($text, -1, '');
    }

    function test_no_split_at_umlaut()
    {
        $text = "Die Empf\xe4nger-Auswahl";
        $this->check_wrap($text, 13, ["Die", "Empf\xe4nger-", "Auswahl"]);
    }

    function test_umlaut_followed_by_dash()
    {
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );

        $text = "aa \xe4\xe4-\xe4\xe4";
        $this->check_wrap($text, 7, ["aa \xe4\xe4-", "\xe4\xe4"]);
    }

    function test_non_breaking_space()
    {
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );

        $text = 'This is a sentence with non-breaking\N{NO-BREAK SPACE}space.';

        $this->check_wrap(
            $text,
            20,
            [
                'This is a sentence',
                'with non-',
                'breaking\N{NO-BREAK SPACE}space.'
            ],
            array('break_on_hyphens' => True)
        );

        $this->check_wrap(
            $text,
            20,
            [
                'This is a sentence',
                'with',
                'non-breaking\N{NO-BREAK SPACE}space.'
            ],
            array('break_on_hyphens' => False)
        );
    }

    function test_narrow_non_breaking_space()
    {
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );

        $text = ('This is a sentence with non-breaking' .
            '\N{NARROW NO-BREAK SPACE}space.');

        $this->check_wrap(
            $text,
            20,
            [
                'This is a sentence',
                'with non-',
                'breaking\N{NARROW NO-BREAK SPACE}space.'
            ],
            array('break_on_hyphens' => True)
        );

        $this->check_wrap(
            $text,
            20,
            [
                'This is a sentence',
                'with',
                'non-breaking\N{NARROW NO-BREAK SPACE}space.'
            ],
            array('break_on_hyphens' => False)
        );
    }
}


class MaxLinesTestCase extends BaseTestCase
{

    function __construct()
    {
        parent::__construct();
        $this->text = "Hello there, how are you this fine day?  I'm glad to hear it!";
    }

    function test_simple()
    {
        $this->check_wrap(
            $this->text,
            12,
            ["Hello [...]"],
            array('max_lines' => 0)
        );
        $this->check_wrap(
            $this->text,
            12,
            ["Hello [...]"],
            array('max_lines' => 1)
        );
        $this->check_wrap(
            $this->text,
            12,
            [
                "Hello there,",
                "how [...]"
            ],
            array('max_lines' => 2)
        );
        $this->check_wrap(
            $this->text,
            13,
            [
                "Hello there,",
                "how are [...]"
            ],
            array('max_lines' => 2)
        );
        $this->check_wrap($this->text, 80, [$this->text], array('max_lines' => 1));
        $this->check_wrap(
            $this->text,
            12,
            [
                "Hello there,",
                "how are you",
                "this fine",
                "day?  I'm",
                "glad to hear",
                "it!"
            ],
            array('max_lines' => 6)
        );
    }

    function test_spaces()
    {
        # strip spaces before placeholder
        $this->check_wrap(
            $this->text,
            12,
            [
                "Hello there,",
                "how are you",
                "this fine",
                "day? [...]"
            ],
            array('max_lines' => 4)
        );

        # placeholder at the start of line
        $this->check_wrap(
            $this->text,
            6,
            [
                "Hello",
                "[...]"
            ],
            array('max_lines' => 2)
        );

        # final spaces
        $this->check_wrap(
            $this->text . str_repeat(' ', 10),
            12,
            [
                "Hello there,",
                "how are you",
                "this fine",
                "day?  I'm",
                "glad to hear",
                "it!"
            ],
            array('max_lines' => 6)
        );
    }

    function test_placeholder()
    {
        $this->check_wrap(
            $this->text,
            12,
            ["Hello..."],
            array(
                'max_lines' => 1,
                'placeholder' => '...'
            )
        );
        $this->check_wrap(
            $this->text,
            12,
            [
                "Hello there,",
                "how are..."
            ],
            array(
                'max_lines' => 2,
                'placeholder' => '...'
            )
        );

        # long placeholder and indentation
        $this->expectException(\InvalidArgumentException::class);

        StaticTextWrapper::wrap(
            $this->text,
            16,
            array(
                'initial_indent' => '    ',
                'max_lines' => 1,
                'placeholder' => ' [truncated]...'
            )
        );

        StaticTextWrapper::wrap(
            $this->text,
            16,
            array(
                'subsequent_indent' => '    ',
                'max_lines' => 2,
                'placeholder' => ' [truncated]...'
            )
        );

        $this->check_wrap(
            $this->text,
            16,
            [
                "    Hello there,",
                "  [truncated]..."
            ],
            array(
                'max_lines' => 2,
                'initial_indent' => '    ',
                'subsequent_indent' => '  ',
                'placeholder' => ' [truncated]...'
            )
        );
        $this->check_wrap(
            $this->text,
            16,
            ["  [truncated]..."],
            array(
                'max_lines' => 1,
                'initial_indent' => '  ',
                'subsequent_indent' => '    ',
                'placeholder' => ' [truncated]...'
            )
        );
        $this->check_wrap($this->text, 80, [$this->text], array('placeholder' => str_repeat('.' , 1000)));
    }

    function test_placeholder_backtrack()
    {
        # Test special case when max_lines insufficient, but what
        # would be last wrapped line so long the placeholder cannot
        # be added there without violence. So, textwrap backtracks,
        # adding placeholder to the penultimate line.
        $text = 'Good grief Python features are advancing quickly!';
        $this->check_wrap(
            $text,
            12,
            ['Good grief', 'Python*****'],
            array(
                'max_lines' => 3,
                'placeholder' => '*****'
            )
        );
    }
}


class LongWordTestCase  extends BaseTestCase
{

    protected function setUp(): void
    {
        $this->wrapper = new TextWrapper();
        $this->text = 'Did you say "supercalifragilisticexpialidocious?"
How *do* you spell that odd word, anyways?
';
    }

    function test_break_long()
    {
        # Wrap text with long words and lots of punctuation

        $this->check_wrap(
            $this->text,
            30,
            [
                'Did you say "supercalifragilis',
                'ticexpialidocious?" How *do*',
                'you spell that odd word,',
                'anyways?'
            ]
        );
        $this->check_wrap(
            $this->text,
            50,
            [
                'Did you say "supercalifragilisticexpialidocious?"',
                'How *do* you spell that odd word, anyways?'
            ]
        );

        # SF bug 797650.  Prevent an infinite loop by making sure that at
        # least one character gets split off on every pass.
        $this->check_wrap(
            str_repeat('-', 10) . 'hello',
            10,
            [
                '----------',
                '               h',
                '               e',
                '               l',
                '               l',
                '               o'
            ],
            array('subsequent_indent' => str_repeat(' ',  15))
        );

        # bug 1146.  Prevent a long word to be wrongly wrapped when the
        # preceding word is exactly one character shorter than the width
        $this->check_wrap(
            $this->text,
            12,
            [
                'Did you say ',
                '"supercalifr',
                'agilisticexp',
                'ialidocious?',
                '" How *do*',
                'you spell',
                'that odd',
                'word,',
                'anyways?'
            ]
        );
    }

    function test_nobreak_long()
    {
        # Test with break_long_words disabled
        $this->wrapper->break_long_words = 0;
        $this->wrapper->width = 30;
        $expect = [
            'Did you say',
            '"supercalifragilisticexpialidocious?"',
            'How *do* you spell that odd',
            'word, anyways?'
        ];
        $result = $this->wrapper->wrap($this->text);
        $this->check($result, $expect);

        # Same thing with kwargs passed to standalone wrap() function.
        $result = StaticTextWrapper::wrap($this->text, $width = 30, array('break_long_words' => false));
        $this->check($result, $expect);
    }

    function test_max_lines_long()
    {
        $this->check_wrap(
            $this->text,
            12,
            [
                'Did you say ',
                '"supercalifr',
                'agilisticexp',
                '[...]'
            ],
            array('max_lines' => 4)
        );
    }
}


class IndentTestCases extends BaseTestCase
{

    # called before each test method
    protected function setUp(): void
    {
        $this->text = "This paragraph will be filled, first without any indentation,".
        "\nand then with some (including a hanging indent).";
    }


    /**
     * Test the fill() method
     *
     * @return void
     */
    function test_fill()
    {
        $expect = "This paragraph will be filled, first" .
            "\nwithout any indentation, and then with" .
            "\nsome (including a hanging indent).";

        $result = StaticTextWrapper::fill($this->text, 40);
        $this->check($result, $expect);
    }


    function test_initial_indent()
    {
        # Test initial_indent parameter

        $expect = [
            "     This paragraph will be filled,",
            "first without any indentation, and then",
            "with some (including a hanging indent)."
        ];
        $result = StaticTextWrapper::wrap($this->text, 40, array('initial_indent' => "     "));
        $this->check($result, $expect);

        $expect = implode("\n", $expect);
        $result = StaticTextWrapper::fill($this->text, 40, array('initial_indent' => "     "));
        $this->check($result, $expect);
    }


    function test_subsequent_indent()
    {
        # Test subsequent_indent parameter

        $expect =
            "  * This paragraph will be filled, first" .
            "\n    without any indentation, and then" .
            "\n    with some (including a hanging" .
            "\n    indent).";

        $result = StaticTextWrapper::fill(
            $this->text,
            40,
            array('initial_indent' => "  * ", 'subsequent_indent' => "    ")
        );
        $this->check($result, $expect);
    }
}


# Despite the similar names, DedentTestCase is *not* the inverse
# of IndentTestCase!
class DedentTestCase extends TestCase
{

    /**
     * assert that dedent() has no effect on 'text'
     *
     * @param [type] $text
     * @return void
     */
    function assertUnchanged($text)
    {
        $this->assertEquals($text, StaticTextWrapper::dedent($text));
    }

    function test_dedent_nomargin()
    {
        # No lines indented.
        $text = "Hello there.\nHow are you?\nOh good, I'm glad.";
        $this->assertUnchanged($text);

        # Similar, with a blank line.
        $text = "Hello there.\n\nBoo!";
        $this->assertUnchanged($text);

        # Some lines indented, but overall margin is still zero.
        $text = "Hello there.\n  This is indented.";
        $this->assertUnchanged($text);

        # Again, add a blank line.
        $text = "Hello there.\n\n  Boo!\n";
        $this->assertUnchanged($text);
    }

    function test_dedent_even()
    {
        # All lines indented by two spaces.
        $text = "  Hello there.\n  How are ya?\n  Oh good.";
        $expect = "Hello there.\nHow are ya?\nOh good.";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        # Same, with blank lines.
        $text = "  Hello there.\n\n  How are ya?\n  Oh good.\n";
        $expect = "Hello there.\n\nHow are ya?\nOh good.\n";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        # Now indent one of the blank lines.
        $text = "  Hello there.\n  \n  How are ya?\n  Oh good.\n";
        $expect = "Hello there.\n\nHow are ya?\nOh good.\n";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));
    }

    function test_dedent_uneven()
    {
        # Lines indented unevenly.
        $text =
'        def foo():
            while 1:
                return foo
        ';
        $expect =
'def foo():
    while 1:
        return foo
';
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        # Uneven indentation with a blank line.
        $text = "  Foo\n    Bar\n\n   Baz\n";
        $expect = "Foo\n  Bar\n\n Baz\n";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        # Uneven indentation with a whitespace-only line.
        $text = "  Foo\n    Bar\n \n   Baz\n";
        $expect = "Foo\n  Bar\n\n Baz\n";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));
    }

    function test_dedent_declining()
    {
        # Uneven indentation with declining indent level.
        $text = "     Foo\n    Bar\n";  # 5 spaces, then 4
        $expect = " Foo\nBar\n";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        # Declining indent level with blank line.
        $text = "     Foo\n\n    Bar\n";  # 5 spaces, blank, then 4
        $expect = " Foo\n\nBar\n";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        # Declining indent level with whitespace only line.
        $text = "     Foo\n    \n    Bar\n";  # 5 spaces, then 4, then 4
        $expect = " Foo\n\nBar\n";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));
    }

    # dedent() should not mangle internal tabs
    function test_dedent_preserve_internal_tabs()
    {
        $text = "  hello\tthere\n  how are\tyou?";
        $expect = "hello\tthere\nhow are\tyou?";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        # make sure that it preserves tabs when it's not making any
        # changes at all
        $this->assertEquals($expect, StaticTextWrapper::dedent($expect));
    }

    # dedent() should not mangle tabs in the margin (i.e.
    # tabs and spaces both count as margin, but are *not*
    # considered equivalent)
    function test_dedent_preserve_margin_tabs()
    {
        $text = "  hello there\n\thow are you?";
        $this->assertUnchanged($text);

        # same effect even if we have 8 spaces
        $text = "        hello there\n\thow are you?";
        $this->assertUnchanged($text);

        # dedent() only removes whitespace that can be uniformly removed!
        $text = "\thello there\n\thow are you?";
        $expect = "hello there\nhow are you?";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        $text = "  \thello there\n  \thow are you?";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        $text = "  \t  hello there\n  \t  how are you?";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        $text = "  \thello there\n  \t  how are you?";
        $expect = "hello there\n  how are you?";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));

        # test margin is smaller than smallest indent
        $text = "  \thello there\n   \thow are you?\n \tI'm fine, thanks";
        $expect = " \thello there\n  \thow are you?\n\tI'm fine, thanks";
        $this->assertEquals($expect, StaticTextWrapper::dedent($text));
    }
}


# The examples used for tests. If any of these change, the expected
# results in the various test cases must also be updated.
# The roundtrip cases are separate, because textwrap.dedent doesn't
# handle Windows line endings
$ROUNDTRIP_CASES = [
    # Basic test case
    "Hi.\nThis is a test.\nTesting.",
    # Include a blank line
    "Hi.\nThis is a test.\n\nTesting.",
    # Include leading and trailing blank lines
    "\nHi.\nThis is a test.\nTesting.\n",
];
$CASES = array_merge($ROUNDTRIP_CASES, array(
    # Use Windows line endings
    "Hi.\r\nThis is a test.\r\nTesting.\r\n",
    # Pathological case
    "\nHi.\r\nThis is a test.\n\r\nTesting.\r\n\n",
));

# Test textwrap.indent
class IndentTestCase extends TestCase
{

    function test_indent_nomargin_default()
    {
        # indent should do nothing if 'prefix' is empty.
        global $CASES;

        foreach ($CASES as $text) {
            $this->assertEquals(StaticTextWrapper::indent($text, ''), $text);
        }
    }

    function test_indent_nomargin_explicit_default()
    {
        # The same as test_indent_nomargin, but explicitly requesting
        # the default behaviour by passing null as the predicate
        global $CASES;

        foreach ($CASES as $text) {
            $this->assertEquals(StaticTextWrapper::indent($text, '', null), $text);
        }
    }

    function test_indent_nomargin_all_lines()
    {
        # The same as test_indent_nomargin, but using the optional
        # predicate argument
        global $CASES;

        foreach ($CASES as $text) {
            $this->assertEquals(StaticTextWrapper::indent($text, '', function () {
                return true;
            }), $text);
        }
    }

    /**
     * Explicitly skip indenting any lines
     *
     * @return void
     */
    function test_indent_no_lines()
    {
        global $CASES;

        foreach ($CASES as $text) {
            $this->assertEquals(StaticTextWrapper::indent($text, '    ', function () {
                return false;
            }), $text);
        }
    }

    function test_roundtrip_spaces()
    {
        # A whitespace prefix should roundtrip with dedent
        global $ROUNDTRIP_CASES;

        foreach ($ROUNDTRIP_CASES as $text) {
            $this->assertEquals(StaticTextWrapper::dedent(StaticTextWrapper::indent($text, '    ')), $text);
        }
    }

    function test_roundtrip_tabs()
    {
        # A whitespace prefix should roundtrip with dedent
        global $ROUNDTRIP_CASES;

        foreach ($ROUNDTRIP_CASES as $text) {
            $this->assertEquals(StaticTextWrapper::dedent(StaticTextWrapper::indent($text, "\t\t")), $text);
        }
    }

    function test_roundtrip_mixed()
    {
        # A whitespace prefix should roundtrip with dedent
        global $ROUNDTRIP_CASES;

        foreach ($ROUNDTRIP_CASES as $text) {
            $this->assertEquals(StaticTextWrapper::dedent(StaticTextWrapper::indent($text, " \t  \t ")), $text);
        }
    }

    function test_indent_default()
    {
        global $CASES;

        # Test default indenting of lines that are not whitespace only
        $prefix = '  ';
        $expected = [
            # Basic test case
            "  Hi.\n  This is a test.\n  Testing.",
            # Include a blank line
            "  Hi.\n  This is a test.\n\n  Testing.",
            # Include leading and trailing blank lines
            "\n  Hi.\n  This is a test.\n  Testing.\n",
            # Use Windows line endings
            "  Hi.\r\n  This is a test.\r\n  Testing.\r\n",
            # Pathological case
            "\n  Hi.\r\n  This is a test.\n\r\n  Testing.\r\n\n",
        ];

        foreach (array_combine($CASES, $expected) as $text => $expect) {
            $this->assertEquals(StaticTextWrapper::indent($text, $prefix), $expect);
        }
    }

    function test_indent_explicit_default()
    {
        global $CASES;

        # Test default indenting of lines that are not whitespace only
        $prefix = '  ';
        $expected = [
            # Basic test case
            "  Hi.\n  This is a test.\n  Testing.",
            # Include a blank line
            "  Hi.\n  This is a test.\n\n  Testing.",
            # Include leading and trailing blank lines
            "\n  Hi.\n  This is a test.\n  Testing.\n",
            # Use Windows line endings
            "  Hi.\r\n  This is a test.\r\n  Testing.\r\n",
            # Pathological case
            "\n  Hi.\r\n  This is a test.\n\r\n  Testing.\r\n\n",
        ];
        foreach (array_combine($CASES, $expected) as $text => $expect) {
            $this->assertEquals(StaticTextWrapper::indent($text, $prefix, null), $expect);
        }
    }

    function test_indent_all_lines()
    {
        global $CASES;

        # Add 'prefix' to all lines, including whitespace-only ones.
        $prefix = '  ';
        $expected = [
            # Basic test case
            "  Hi.\n  This is a test.\n  Testing.",
            # Include a blank line
            "  Hi.\n  This is a test.\n  \n  Testing.",
            # Include leading and trailing blank lines
            "  \n  Hi.\n  This is a test.\n  Testing.\n",
            # Use Windows line endings
            "  Hi.\r\n  This is a test.\r\n  Testing.\r\n",
            # Pathological case
            "  \n  Hi.\r\n  This is a test.\n  \r\n  Testing.\r\n  \n",
        ];

        foreach (array_combine($CASES, $expected) as $text => $expect) {
            $this->assertEquals(StaticTextWrapper::indent($text, $prefix, function () {
                return true;
            }), $expect);
        }
    }

    function test_indent_empty_lines()
    {
        global $CASES;

        # Add 'prefix' solely to whitespace-only lines.
        $prefix = '  ';
        $expected = [
            # Basic test case
            "Hi.\nThis is a test.\nTesting.",
            # Include a blank line
            "Hi.\nThis is a test.\n  \nTesting.",
            # Include leading and trailing blank lines
            "  \nHi.\nThis is a test.\nTesting.\n",
            # Use Windows line endings
            "Hi.\r\nThis is a test.\r\nTesting.\r\n",
            # Pathological case
            "  \nHi.\r\nThis is a test.\n  \r\nTesting.\r\n  \n",
        ];

        foreach (array_combine($CASES, $expected) as $text => $expect) {
            $this->assertEquals(StaticTextWrapper::indent($text, $prefix, function ($line) {
                return !trim($line);
            }), $expect);
        }
    }
}

class ShortenTestCase extends BaseTestCase
{

    function check_shorten($text, $width, $expect, array $kwargs = array())
    {
        $result = StaticTextWrapper::shorten($text, $width, $kwargs);
        $this->check($result, $expect);
    }

    function test_simple()
    {
        # Simple case: just words, spaces, and a bit of punctuation
        $text = "Hello there, how are you this fine day? I'm glad to hear it!";

        $this->check_shorten($text, 18, "Hello there, [...]");
        $this->check_shorten($text, strlen($text), $text);
        $this->check_shorten(
            $text,
            strlen($text) - 1,
            "Hello there, how are you this fine day? " .
                "I'm glad to [...]"
        );
    }

    function test_placeholder()
    {
        $text = "Hello there, how are you this fine day? I'm glad to hear it!";

        $this->check_shorten($text, 17, "Hello there,$$", array('placeholder' => '$$'));
        $this->check_shorten($text, 18, "Hello there, how$$", array('placeholder' => '$$'));
        $this->check_shorten($text, 18, "Hello there, $$", array('placeholder' => ' $$'));
        $this->check_shorten($text, strlen($text), $text, array('placeholder' => '$$'));
        $this->check_shorten(
            $text,
            strlen($text) - 1,
            "Hello there, how are you this fine day? " .
                "I'm glad to hear$$",
            array('placeholder' => '$$')
        );
    }

    function test_empty_string()
    {
        $this->check_shorten("", 6, "");
    }

    function test_whitespace()
    {
        # Whitespace collapsing
        $text = "
            This is a  paragraph that  already has
            line breaks and \t tabs too.";
        $this->check_shorten(
            $text,
            62,
            "This is a paragraph that already has line " .
                "breaks and tabs too."
        );
        $this->check_shorten(
            $text,
            61,
            "This is a paragraph that already has line " .
                "breaks and [...]"
        );

        $this->check_shorten("hello      world!  ", 12, "hello world!");
        $this->check_shorten("hello      world!  ", 11, "hello [...]");
        # The leading space is trimmed from the placeholder
        # (it would be ugly otherwise).
        $this->check_shorten("hello      world!  ", 10, "[...]");
    }

    function test_width_too_small_for_placeholder()
    {
        StaticTextWrapper::shorten(str_repeat("x", 20), 8, array('width' => 8, 'placeholder' => "(......)"));

        $this->expectException(\InvalidArgumentException::class);
        StaticTextWrapper::shorten(str_repeat("x", 20), 8, array('width' => 8, 'placeholder' => "(.......)"));
    }

    function test_first_word_too_long_but_placeholder_fits()
    {
        $this->check_shorten("Helloo", 5, "[...]");
    }
}
