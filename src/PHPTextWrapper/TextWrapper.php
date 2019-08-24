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

use PHPTextWrapper\Utils\StringUtils;


// define('PHP_TEXT_WRAPPER_DEBUG', true);

/**
 *
 *   Object for wrapping/filling text.  The public interface consists of
 *   the wrap() and fill() methods; the other methods are just there for
 *   subclasses to override in order to tweak the default behaviour.
 *   If you want to completely replace the main wrapping algorithm,
 *   you'll probably have to override _wrap_chunks().
 *
 *   Several instance attributes control various aspects of wrapping:
 *     width (default: 70)
 *       the maximum width of wrapped lines (unless break_long_words
 *       is false)
 *     initial_indent (default: "")
 *       string that will be prepended to the first line of wrapped
 *       output.  Counts towards the line's width.
 *     subsequent_indent (default: "")
 *       string that will be prepended to all lines save the first
 *       of wrapped output; also counts towards each line's width.
 *     expand_tabs (default: true)
 *       Expand tabs in input text to spaces before further processing.
 *       Each tab will become 0 .. 'tab_size' spaces, depending on its position
 *       in its line.  If false, each tab is treated as a single character.
 *     tab_size (default: 8)
 *       Expand tabs in input text to 0 .. 'tab_size' spaces, unless
 *       'expand_tabs' is false.
 *     replace_whitespace (default: true)
 *       Replace all whitespace characters in the input text by spaces
 *       after tab expansion.  Note that if expand_tabs is false and
 *       replace_whitespace is true, every tab will be converted to a
 *       single space!
 *     fix_sentence_endings (default: false)
 *       Ensure that sentence-ending punctuation is always followed
 *       by two spaces.  Off by default because the algorithm is
 *       (unavoidably) imperfect.
 *     break_long_words (default: true)
 *       Break words longer than 'width'.  If false, those words will not
 *       be broken, and some lines might be longer than 'width'.
 *     break_on_hyphens (default: true)
 *       Allow breaking hyphenated words. If true, wrapping will occur
 *       preferably on whitespaces and right after hyphens part of
 *       compound words.
 *     drop_whitespace (default: true)
 *       Drop leading and trailing whitespace from lines.
 *     max_lines (default: null)
 *       Truncate wrapped lines.
 *     placeholder (default: ' [...]')
 *       Append to the last line of truncated text.
 */
class TextWrapper
{
    # Hardcode the recognized whitespace characters to the US-ASCII
    # whitespace characters.  The main reason for doing this is that
    # some Unicode spaces (like \u00a0) are non-breaking whitespaces.
    const WHITESPACE = "\t\n\x0b\x0c\r ";

    /**
     * @var string $break_long_words
     */
    public $break_long_words;

    /**
     * @var string $break_on_hyphens
     */
    private $break_on_hyphens;

    /**
     * @var string $drop_whitespace
     */
    private $drop_whitespace;

    /**
     * @var string $expand_tabs
     */
    private $expand_tabs;

    /**
     * @var string $fix_sentence_endings
     */
    private $fix_sentence_endings;

    /**
     * @var string $initial_indent
     */
    private $initial_indent;

    /**
     * @var string $max_lines
     */
    private $max_lines;

    /**
     * @var string $placeholder
     */
    private $placeholder;

    /**
     * @var string $replace_whitespace
     */
    private $replace_whitespace;

    /**
     * @var string $sentence_end_re
     */
    private $sentence_end_re;

    /**
     * @var string $subsequent_indent
     */
    private $subsequent_indent;

    /**
     * @var int $tab_size
     */
    private $tab_size;

    /**
     * @var string $unicode_whitespace_trans
     */
    private $unicode_whitespace_trans;

    /**
     * @var string $width
     */
    public $width;

    /**
     * @var string $word_sep_re
     */
    private $word_sep_re;

    /**
     * @var string $word_sep_simple_re
     */
    private $word_sep_simple_re;

    function __construct(
        int $width = 70,
        string $initial_indent = "",
        string $subsequent_indent = "",
        bool $expand_tabs = True,
        bool $replace_whitespace = True,
        bool $fix_sentence_endings = False,
        bool $break_long_words = True,
        bool $drop_whitespace = True,
        bool $break_on_hyphens = True,
        int $tab_size = 8,
        ?int $max_lines = null,
        string $placeholder = ' [...]'
    ) {
        $this->width = $width;
        $this->initial_indent = $initial_indent;
        $this->subsequent_indent = $subsequent_indent;
        $this->expand_tabs = $expand_tabs;
        $this->replace_whitespace = $replace_whitespace;
        $this->fix_sentence_endings = $fix_sentence_endings;
        $this->break_long_words = $break_long_words;
        $this->drop_whitespace = $drop_whitespace;
        $this->break_on_hyphens = $break_on_hyphens;
        $this->tab_size = $tab_size;
        $this->max_lines = $max_lines;
        $this->placeholder = $placeholder;

        $this->_init();
    }

    private function _init()
    {
        $this->unicode_whitespace_trans = array();

        foreach (str_split(self::WHITESPACE) as $x) {
            $this->unicode_whitespace_trans[$x] = ' ';
        }

        # This funky little regex is just the trick for splitting
        # text up into word-wrappable chunks.  E.g.
        #   "Hello there -- you goof-ball, use the -b option!"
        # splits into
        #   Hello/ /there/ /--/ /you/ /goof-/ball,/ /use/ /the/ /-b/ /option!
        # (after stripping out empty strings).
        $word_punct = '[\w!"\'&.,?]';
        $letter = '[^\d\W]';
        $whitespace = sprintf('[%s]', self::WHITESPACE);
        $no_whitespace = '[^' . substr($whitespace, 1);

        $dummy = '/(%(ws)s+|(?<=%(wp)s)-{2,}(?=\w)|%(nws)s+?(?:-(?:(?<=%(lt)s{2}-)|(?<=%(lt)s-%(lt)s-))(?=%(lt)s-?%(lt)s)|(?=%(ws)s|\Z)
        |(?<=%(wp)s)(?=-{2,}\w)))/';
        $this->word_sep_re = StringUtils::sprintfn($dummy,
            array(
                'wp' => $word_punct,
                'lt' => $letter,
                'ws' => $whitespace,
                'nws' => $no_whitespace
            )
        );

        unset($word_punct);
        unset($letter);
        unset($no_whitespace);

        # This less funky little regex just split on recognized spaces. E.g.
        #   "Hello there -- you goof-ball, use the -b option!"
        # splits into
        #   Hello/ /there/ /--/ /you/ /goof-ball,/ /use/ /the/ /-b/ /option!/
        $this->word_sep_simple_re = (sprintf('(%s+)', $whitespace));
        unset($whitespace);

        # XXX this is not locale- or charset-aware -- string.lowercase
        # is US-ASCII only (and therefore English-only)
        $this->sentence_end_re = ('/[a-z]' .     # lowercase letter
            '[\.\!\?]' .                    # sentence-ending punct.
            '[\"\']?' .                     # optional end-of-quote
            '\Z/');                         # end of chunk
    }

    # -- Private methods -----------------------------------------------
    # (possibly useful for subclasses to override)

    /**
     * _munge_whitespace(text : string) -> string
     *
     * Munge whitespace in text: expand tabs and convert all other
     * whitespace characters to spaces.  Eg. " foo\\tbar\\n\\nbaz"
     * becomes " foo    bar  baz".
     *
     * @return string
     */
    private function _munge_whitespace(string $text): string
    {
        #macro PHP_TEXT_WRAPPER_DEBUG; echo PHP_EOL. str_repeat('-', 50). ' _munge_whitespace '. str_repeat('-', 50). PHP_EOL. PHP_EOL;
        #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_munge_whitespace -> text = " . json_encode($text) . PHP_EOL;

        if ($this->expand_tabs) {
            #macro PHP_TEXT_WRAPPER_DEBUG; echo '$this->tab_size = '.$this->tab_size.PHP_EOL;
            $text = StringUtils::expandTabs($text, $this->tab_size);
            #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_munge_whitespace -> expand_tabs -> text = " . json_encode($text) . PHP_EOL;
        }

        if ($this->replace_whitespace) {
            $text = strtr($text, $this->unicode_whitespace_trans);
            #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_munge_whitespace -> replace_whitespace -> text = " . json_encode($text) . PHP_EOL;
        }

        return $text;
    }

    /**
     * _split(text : string) -> [string]
     *
     *  Split the text to wrap into indivisible chunks.  Chunks are
     *  not quite the same as words; see _wrap_chunks() for full
     *  details.  As an example, the text
     *    Look, goof-ball -- use the -b option!
     *  breaks into the following chunks:
     *    'Look,', ' ', 'goof-', 'ball', ' ', '--', ' ',
     *    'use', ' ', 'the', ' ', '-b', ' ', 'option!'
     *  if break_on_hyphens is True, or in:
     *    'Look,', ' ', 'goof-ball', ' ', '--', ' ',
     *    'use', ' ', 'the', ' ', '-b', ' ', option!'
     *  otherwise.
     *
     * @return string[]
     */
    public function _split($text)
    {
        #macro PHP_TEXT_WRAPPER_DEBUG; echo PHP_EOL. str_repeat('-', 50). ' _split '. str_repeat('-', 50). PHP_EOL. PHP_EOL;
        #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_split -> text = " . json_encode($text) . PHP_EOL;

        if ($this->break_on_hyphens == true) {
            $chunks = preg_split($this->word_sep_re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_split -> break_on_hyphens -> chunks = " . json_encode($text) . PHP_EOL;
        } else {
            $chunks = preg_split($this->word_sep_simple_re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_split -> no-break_on_hyphens -> chunks = " . json_encode($text) . PHP_EOL;
        }

        $chunks = array_values(array_filter($chunks, function ($c) {
            return isset($c) && is_string($c) && strlen($c);
        }));

        return $chunks;
    }

    /**
     * _fix_sentence_endings(chunks : [string])
     *
     *  Correct for sentence endings buried in 'chunks'.  Eg. when the
     *  original text contains "... foo.\\nBar ...", munge_whitespace()
     *  and split() will convert that to [..., "foo.", " ", "Bar", ...]
     *  which has one too few spaces; this method simply changes the one
     *  space to two.
     *
     * @param string[] $chunks
     */
    private function _fix_sentence_endings(array $chunks)
    {
        #macro PHP_TEXT_WRAPPER_DEBUG; echo PHP_EOL. str_repeat('-', 50). ' _fix_sentence_endings '. str_repeat('-', 50). PHP_EOL. PHP_EOL;

        $i = 0;
        #macro PHP_TEXT_WRAPPER_DEBUG; echo "\tthis->sentence_end_re = " . json_encode($this->sentence_end_re) . PHP_EOL;
        #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t _fix_sentence_endings -> chunks = " . json_encode($chunks) . PHP_EOL;
        while ($i < count($chunks) - 1) {
            if ($chunks[$i + 1] == " " && preg_match($this->sentence_end_re, $chunks[$i])) {
                $chunks[$i + 1] = "  ";
                $i += 2;
            } else {
                $i += 1;
            }
        }

        #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t _fix_sentence_endings -> chunks (final) = " . json_encode($chunks) . PHP_EOL;

        return $chunks;
    }

    /**
     * _handle_long_word(chunks : [string],
     *                       cur_line : [string],
     *                       cur_len : int, width : int)
     *
     *  Handle a chunk of text (most likely a word, not whitespace) that
     *  is too long to fit in any line.
     *
     * @param [type] $reversed_chunks
     * @param [type] $cur_line
     * @param [type] $cur_len
     * @param [type] $width
     * @return void
     */
    private function _handle_long_word(&$reversed_chunks, &$cur_line, $cur_len, $width)
    {
        # Figure out when indent is larger than the specified width, and make
        # sure at least one character is stripped off on every pass
        if ($width < 1) {
            $space_left = 1;
        } else {
            $space_left = $width - $cur_len;
        }

        # If we're allowed to break long words, then do so: put as much
        # of the next chunk onto the current line as will fit.
        if ($this->break_long_words) {
            $cur_line[] = (substr(end($reversed_chunks), 0, $space_left));
            $reversed_chunks[count($reversed_chunks) - 1] = substr(end($reversed_chunks), $space_left);
        }

        # Otherwise, we have to preserve the long word intact.  Only add
        # it to the current line if there's nothing already there --
        # that minimizes how much we violate the width constraint.
        else if (isset($cur_line) && !count($cur_line)) {
            $cur_line[] = array_pop($reversed_chunks);
        }

        # If we're not allowed to break long words, and there's already
        # text on the current line, do nothing.  Next time through the
        # main loop of _wrap_chunks(), we'll wind up here again, but
        # cur_len will be zero, so the next line will be entirely
        # devoted to the long word that we can't handle right now.
    }

    /**
     * _wrap_chunks(chunks : [string]) -> [string]
     *
     *  Wrap a sequence of text chunks and return a list of lines of
     *  length '$this->width' or less.  (If 'break_long_words' is false,
     *  some lines may be longer than this.)  Chunks correspond roughly
     *  to words and the whitespace between them: each chunk is
     *  indivisible (modulo 'break_long_words'), but a line break can
     *  come between any two chunks.  Chunks should not have internal
     *  whitespace; ie. a chunk is either all whitespace or a "word".
     *  Whitespace chunks will be removed from the beginning and end of
     *  lines, but apart from that whitespace is preserved.
     *
     * @return string[]
     */
    private function _wrap_chunks($chunks)
    {
        #macro PHP_TEXT_WRAPPER_DEBUG; echo PHP_EOL. str_repeat('-', 50). ' _wrap_chunks '. str_repeat('-', 50). PHP_EOL. PHP_EOL;
        #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_wrap_chunks -> chunks = " . json_encode($chunks) . PHP_EOL;

        $lines = [];
        if ($this->width <= 0) {
            throw new \InvalidArgumentException(sprintf("invalid width %s (must be > 0)", $this->width));
        }
        if ($this->max_lines != null) {
            if ($this->max_lines > 1) {
                $indent = $this->subsequent_indent;
            } else {
                $indent = $this->initial_indent;
            }
            if (strlen($indent) + strlen(ltrim($this->placeholder)) > $this->width) {
                throw new \InvalidArgumentException("placeholder too large for max width");
            }
        }

        # Arrange in reverse order so items can be efficiently popped
        # from a stack of chucks.
        $chunks = array_reverse($chunks);

        #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_wrap_chunks -> revered-chunks: " . json_encode($chunks) . PHP_EOL;
        #macro PHP_TEXT_WRAPPER_DEBUG;  echo "_wrap_chunks -> indent" . json_encode($indent) . PHP_EOL;

        while ($chunks) {

            # Start the list of chunks that will make up the current line.
            # cur_len is just the length of all the chunks in cur_line.
            $cur_line = [];
            $cur_len = 0;

            # Figure out which static string will prefix this line.
            if ($lines) {
                $indent = $this->subsequent_indent;
            } else {
                $indent = $this->initial_indent;
            }

            #macro PHP_TEXT_WRAPPER_DEBUG; echo "> indent" . json_encode($indent) . PHP_EOL;

            # Maximum width for this line.
            $width = $this->width - (is_array($indent) ? count($indent) : strlen($indent));

            # First chunk on line is whitespace -- drop it, unless this
            # is the very beginning of the text (ie. no lines started yet).
            if ($this->drop_whitespace && trim(end($chunks)) == '' && $lines) {
                array_pop($chunks);
            }

            while ($chunks) {
                $l = strlen(end($chunks));

                #macro PHP_TEXT_WRAPPER_DEBUG; echo PHP_EOL."\t\t> cur_line" . json_encode($cur_line) . PHP_EOL;

                # Can at least squeeze this chunk onto the current line.
                if ($cur_len + $l <= $width) {
                    $cur_line[] = array_pop($chunks);
                    $cur_len += $l;
                }

                # Nope, this line is full.
                else {
                    break;
                }
            }

            #macro PHP_TEXT_WRAPPER_DEBUG; echo PHP_EOL."\t\t ## cur_line" . json_encode($cur_line) . PHP_EOL.PHP_EOL;

            # The current line is full, and the next chunk is too big to
            # fit on *any* line (not just this one).
            if ($chunks && strlen(end($chunks)) > $width) {
                $this->_handle_long_word($chunks, $cur_line, $cur_len, $width);
                $cur_len = array_sum(array_map(function($line){
                    return strlen($line);
                }, $cur_line));
            }

            # If the last chunk on this line is all whitespace, drop it.
            if ($this->drop_whitespace && $cur_line && trim(end($cur_line)) == '') {
                $cur_len -= strlen(end($cur_line));
                array_pop($cur_line);
            }

            if ($cur_line) {
                if (
                    ($this->max_lines === null)
                    || (count($lines) + 1 < $this->max_lines)
                    || (!$chunks
                        || $this->drop_whitespace
                        && count($chunks) == 1
                        && !trim($chunks[0]))
                    && $cur_len <= $width
                ) {
                    #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t> cur_line = ".json_encode($cur_line).PHP_EOL;
                    # Convert current line back to a string and store it in
                    # list of all lines (return value).
                    $lines[]=($indent . implode('', $cur_line));
                } else {
                    $broke = false;
                    while ($cur_line) {
                        if (
                            trim(end($cur_line)) &&
                            ($cur_len + strlen($this->placeholder)) <= $width
                        ) {
                            $cur_line[] = ($this->placeholder);
                            #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t> cur_line = ".json_encode($cur_line).PHP_EOL;
                            $lines[] = ($indent . implode('', $cur_line));
                            $broke = true;
                            break;
                        }
                        $cur_len -= strlen(end($cur_line));
                        array_pop($cur_line);
                    }
                    if (!$broke) {
                        if ($lines) {
                            $prev_line = rtrim(end($lines));
                            if (
                                strlen($prev_line) + strlen($this->placeholder) <= $this->width
                            ) {
                                $lines[count($lines) - 1] = $prev_line . $this->placeholder;
                                break;
                            }
                        }
                        $lines[] = ($indent . ltrim($this->placeholder));
                    }
                    break;
                }
            }
        }

        #macro PHP_TEXT_WRAPPER_DEBUG; echo "lines: " . json_encode($lines) . PHP_EOL;

        return $lines;
    }

    private function _split_chunks($text)
    {
        #macro PHP_TEXT_WRAPPER_DEBUG; echo PHP_EOL. str_repeat('-', 50). ' _split_chunks '. str_repeat('-', 50). PHP_EOL. PHP_EOL;
        #macro PHP_TEXT_WRAPPER_DEBUG; echo "\t_split_chunks -> text = " . json_encode($text) . PHP_EOL;
        $text = $this->_munge_whitespace($text);
        return $this->_split($text);
    }

    # -- Public interface ----------------------------------------------

    /**
     *  wrap(text : string) -> [string]
     *
     *  Reformat the single paragraph in 'text' so it fits in lines of
     *  no more than '$this->width' columns, and return a list of wrapped
     *  lines.  Tabs in 'text' are expanded with string.expandtabs(),
     *  and all other whitespace characters (including newline) are
     *  converted to space.
     *
     * @param string $text
     * @return string[]
     */
    function wrap(string $text)
    {
        $chunks = $this->_split_chunks($text);
        #macro PHP_TEXT_WRAPPER_DEBUG; echo PHP_EOL."wrap -> splitted chunks: " . json_encode($chunks) . PHP_EOL;
        if ($this->fix_sentence_endings) {
            $chunks = $this->_fix_sentence_endings($chunks);
        }
        return $this->_wrap_chunks($chunks);
    }

    /**
     * fill(text : string) -> string
     *
     * Reformat the single paragraph in 'text' to fit in lines of no
     * more than '$this->width' columns, and return a new string
     * containing the entire wrapped paragraph.
     *
     * @param string $text
     * @return string
     */
    function fill(string $text)
    {
        return implode("\n", $this->wrap($text));
    }
}

class StaticTextWrapper
{
    static function initialize($width, array $kwargs) : TextWrapper{
        $initial_indent = isset($kwargs['initial_indent']) ? $kwargs['initial_indent'] : "";
        $subsequent_indent = isset($kwargs['subsequent_indent']) ? $kwargs['subsequent_indent'] : "";
        $expand_tabs = isset($kwargs['expand_tabs']) ? $kwargs['expand_tabs'] : True;
        $replace_whitespace = isset($kwargs['replace_whitespace']) ? $kwargs['replace_whitespace'] : True;
        $fix_sentence_endings = isset($kwargs['fix_sentence_endings']) ? $kwargs['fix_sentence_endings'] : False;
        $break_long_words = isset($kwargs['break_long_words']) ? $kwargs['break_long_words'] : True;
        $drop_whitespace = isset($kwargs['drop_whitespace']) ? $kwargs['drop_whitespace'] : True;
        $break_on_hyphens = isset($kwargs['break_on_hyphens']) ? $kwargs['break_on_hyphens'] : True;
        $tab_size = isset($kwargs['tab_size']) ? $kwargs['tab_size'] : 8;
        $max_lines = isset($kwargs['max_lines']) ? $kwargs['max_lines'] : null;
        $placeholder = isset($kwargs['placeholder']) ? $kwargs['placeholder'] : ' [...]';

        return new TextWrapper($width,
            $initial_indent,
            $subsequent_indent,
            $expand_tabs,
            $replace_whitespace,
            $fix_sentence_endings,
            $break_long_words,
            $drop_whitespace,
            $break_on_hyphens,
            $tab_size,
            $max_lines,
            $placeholder
        );
    }

    # -- Convenience interface ---------------------------------------------

    /**
     * Wrap a single paragraph of text, returning a list of wrapped lines.
     *
     *  Reformat the single paragraph in 'text' so it fits in lines of no
     *  more than 'width' columns, and return a list of wrapped lines.  By
     *  default, tabs in 'text' are expanded with string->expandtabs(), and
     *  all other whitespace characters (including newline) are converted to
     *  space.  See TextWrapper class for available keyword args to customize
     *  wrapping behaviour.
     */
    static function wrap($text, $width = 70, array $kwargs = array())
    {
        $w = self::initialize($width, $kwargs);
        return $w->wrap($text);
    }

    /**
     * Fill a single paragraph of text, returning a new string.
     *
     *  Reformat the single paragraph in 'text' to fit in lines of no more
     *  than 'width' columns, and return a new string containing the entire
     *  wrapped paragraph.  As with wrap(), tabs are expanded and other
     *  whitespace characters converted to space.  See TextWrapper class for
     *  available keyword args to customize wrapping behaviour.
     */
    static function fill($text, $width = 70, array $kwargs = array())
    {
        $w = self::initialize($width, $kwargs);
        return $w->fill($text);
    }

    /**
     * Collapse and truncate the given text to fit in the given width.
     *
     *  The text first has its whitespace collapsed.  If it then fits in
     *  the *width*, it is returned as is.  Otherwise, as many words
     *  as possible are joined and then the placeholder is appended::
     *
     *      >>> textwrap->shorten("Hello  world!", width=12)
     *      'Hello world!'
     *      >>> textwrap->shorten("Hello  world!", width=11)
     *      'Hello [...]'
     */
    static function shorten($text, $width, array $kwargs = array())
    {
        if(!isset($kwargs)){
            $kwargs = array();
        }
        $kwargs['max_lines'] = 1;
        $w = self::initialize($width, $kwargs);
        return $w->fill(implode(' ', StringUtils::split(trim($text))));
    }


    # -- Loosely related functionality -------------------------------------

    /**
     * Remove any common leading whitespace from every line in `text`.
     *
     *  This can be used to make triple-quoted strings line up with the left
     *  edge of the display, while still presenting them in the source code
     *  in indented form.
     *
     *  Note that tabs and spaces are both treated as whitespace, but they
     *  are not equal: the lines "  hello" and "\\thello" are
     *  considered to have no common leading whitespace.
     *
     *  Entirely blank lines are normalized to a newline character.
     *
     */
    static function dedent($text)
    {
        $_whitespace_only_re = '/^[ \t]+$/m';
        $_leading_whitespace_re = '/(^[ \t]*)(?:[^ \t\n])/m';

        /**
         * @var string|null $margin Look for the longest leading string
         * of spaces and tabs common to all lines.
         */
        $margin = null;
        $text = preg_replace($_whitespace_only_re, '', $text);

        $result = array();
        preg_match_all($_leading_whitespace_re, $text, $result);
        $indents = $result[1];

        foreach ($indents as $indent) {
            if ($margin === null) {
                $margin = $indent;
            }

            # Current line more deeply indented than previous winner:
            # no change (previous winner is still on top).
            else if (StringUtils::starts_with($indent, $margin)) { }

            # Current line consistent with and no deeper than previous winner:
            # it's the new winner.
            else if (StringUtils::starts_with($margin, $indent)) {
                $margin = $indent;
            }

            # Find the largest common whitespace between current line and previous
            # winner.
            else {
                foreach (StringUtils::zip($margin, $indent) as $i => list($x, $y)) {
                    if ($x != $y) {
                        $margin = StringUtils::slice($margin, null, $i);
                        break;
                    }
                }
            }
        }

        # sanity check (testing/debugging only)
        if (0 && $margin) {
            foreach (explode('\n', $text) as $line) {
                assert(
                    '!$line || $line->startswith($margin)',
                    sprintf("line = %r, margin = %r", $line, $margin)
                );
            }
        }

        if ($margin) {
            $text = preg_replace('/^' . $margin.'/m', '', $text);
        }
        return $text;
    }

    /**
     * Adds 'prefix' to the beginning of selected lines in 'text'.
     *
     *  If 'predicate' is provided, 'prefix' will only be added to the lines
     *  where 'predicate(line)' is True. If 'predicate' is not provided,
     *  it will default to adding 'prefix' to all non-empty lines that do not
     *  consist solely of whitespace characters.
     */
    static function indent($text, $prefix, $predicate = null)
    {
        if (!isset($predicate)) {
            $predicate = function ($line)
            {
                return trim($line);
            };
        }

        return implode('', iterator_to_array(self::indent_prefixed_lines($text, $prefix, $predicate)));
    }

    private static function indent_prefixed_lines($text, $prefix, $predicate)
    {
        $lines = StringUtils::split_lines($text);
        foreach ($lines as $line) {
            yield ($predicate($line) ? ($prefix . $line) : $line);
        }
    }
}
