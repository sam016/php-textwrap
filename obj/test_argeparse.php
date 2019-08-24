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
        if (isset($stop)) {
            return substr($text, $start, $stop - $start);
        } else {
            return substr($text, $start);
        }

        // if (strlen($text) >= $start) {
        //     if ($start > 0) {
        //         return false;
        //     } else {
        //         return self::substring($text, $start);
        //     }
        // }

        // if (!isset($length)) {
        //     return self::substring($text, $start);
        // } else if ($length > 0) {
        //     return self::substring($text, $start, $start + $length);
        // } else {
        //     return self::substring($text, $start, $length);
        // }
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
    static function expandTabs(string $str, int $tabLength = 8):string
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

    static function split_lines($text, $keep_line_breaks=true){
        if (!$keep_line_breaks) {
            return preg_split('\n', $text, -1);
        }

        $splits = preg_split('\n', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $lines = array();

        for ($i = 0, $n = count($splits) - 1; $i < $n; $i += 2) {
            $lines[] = $splits[$i] . $splits[$i + 1];
        }

        if ($splits[$n] != '') {
            $lines[] = $splits[$n];
        }

        return $lines;
    }
}




$_whitespace_only_re = '^[ \t]+$';
$_leading_whitespace_re = '(^[ \t]*)(?:[^ \t\n])';





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
 *       Each tab will become 0 .. 'tabSize' spaces, depending on its position
 *       in its line.  If false, each tab is treated as a single character.
 *     tabSize (default: 8)
 *       Expand tabs in input text to 0 .. 'tabSize' spaces, unless
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
    private $break_long_words;

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
    private $width;

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
        echo PHP_EOL. str_repeat('-', 50). ' _munge_whitespace '. str_repeat('-', 50). PHP_EOL. PHP_EOL;
        echo "\t_munge_whitespace -> text = " . json_encode($text) . PHP_EOL;

        if ($this->expand_tabs) {
            echo '$this->tab_size = '.$this->tab_size.PHP_EOL;
            $text = StringUtils::expandTabs($text, $this->tab_size);
            echo "\t_munge_whitespace -> expand_tabs -> text = " . json_encode($text) . PHP_EOL;
        }

        if ($this->replace_whitespace) {
            $text = strtr($text, $this->unicode_whitespace_trans);
            echo "\t_munge_whitespace -> replace_whitespace -> text = " . json_encode($text) . PHP_EOL;
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
    private function _split($text)
    {
        echo PHP_EOL. str_repeat('-', 50). ' _split '. str_repeat('-', 50). PHP_EOL. PHP_EOL;

        echo "\t_split -> text = " . json_encode($text) . PHP_EOL;

        if ($this->break_on_hyphens == true) {
            $chunks = preg_split($this->word_sep_re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            echo "\t_split -> break_on_hyphens -> chunks = " . json_encode($text) . PHP_EOL;
        } else {
            $chunks = preg_split($this->word_sep_simple_re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            echo "\t_split -> no-break_on_hyphens -> chunks = " . json_encode($text) . PHP_EOL;
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
        echo PHP_EOL. str_repeat('-', 50). ' _fix_sentence_endings '. str_repeat('-', 50). PHP_EOL. PHP_EOL;

        $i = 0;
        // echo "\tthis->sentence_end_re = " . json_encode($this->sentence_end_re) . PHP_EOL;
        echo "\t _fix_sentence_endings -> chunks = " . json_encode($chunks) . PHP_EOL;
        while ($i < count($chunks) - 1) {
            if ($chunks[$i + 1] == " " && preg_match($this->sentence_end_re, $chunks[$i])) {
                $chunks[$i + 1] = "  ";
                $i += 2;
            } else {
                $i += 1;
            }
        }

        echo "\t _fix_sentence_endings -> chunks (final) = " . json_encode($chunks) . PHP_EOL;
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
    private function _handle_long_word($reversed_chunks, $cur_line, $cur_len, $width)
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
            $reversed_chunks[count($reversed_chunks) - 1] = substr(end($reversed_chunks), $space_left - 1);
        }

        # Otherwise, we have to preserve the long word intact.  Only add
        # it to the current line if there's nothing already there --
        # that minimizes how much we violate the width constraint.
        else if (!isset($cur_line)) {
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
        echo PHP_EOL. str_repeat('-', 50). ' _wrap_chunks '. str_repeat('-', 50). PHP_EOL. PHP_EOL;

        echo "\t_wrap_chunks -> chunks = " . json_encode($chunks) . PHP_EOL;

        $lines = [];
        if ($this->width <= 0) {
            throw new Exception("invalid width %r (must be > 0)" % $this->width);
        }
        if ($this->max_lines != null) {
            if ($this->max_lines > 1) {
                $indent = $this->subsequent_indent;
            } else {
                $indent = $this->initial_indent;
            }
            if (strlen($indent) + strlen(ltrim($this->placeholder)) > $this->width) {
                throw new Exception("placeholder too large for max width");
            }
        }

        # Arrange in reverse order so items can be efficiently popped
        # from a stack of chucks.
        $chunks = array_reverse($chunks);

        // TODO: remove me
        echo "\t_wrap_chunks -> revered-chunks: " . json_encode($chunks) . PHP_EOL;
        // TODO: remove me
        // echo "_wrap_chunks -> indent" . json_encode($indent) . PHP_EOL;

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

            // TODO: remove me
            // echo "> indent" . json_encode($indent) . PHP_EOL;

            # Maximum width for this line.
            $width = $this->width - (is_array($indent) ? count($indent) : strlen($indent));

            # First chunk on line is whitespace -- drop it, unless this
            # is the very beginning of the text (ie. no lines started yet).
            if ($this->drop_whitespace && trim(end($chunks)) == '' && $lines) {
                array_pop($chunks);
            }

            while ($chunks) {
                $l = strlen(end($chunks));

                echo PHP_EOL."\t\t> cur_line" . json_encode($cur_line) . PHP_EOL;

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

            echo PHP_EOL."\t\t ## cur_line" . json_encode($cur_line) . PHP_EOL.PHP_EOL;

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
                    $this->max_lines == null ||
                    count($lines) + 1 < $this->max_lines || (!$chunks ||
                        $this->drop_whitespace &&
                        count($chunks) == 1 &&
                        !trim($chunks[0])) && $cur_len <= $width
                ) {
                    # Convert current line back to a string and store it in
                    # list of all lines (return value).
                    echo "\t> cur_line = ".json_encode($cur_line).PHP_EOL;
                    $lines[]=($indent . implode('', $cur_line));
                } else {
                    if ($cur_line) {
                        while ($cur_line) {
                            if (
                                trim(end($cur_line)) &&
                                $cur_len + strlen($this->placeholder) <= $width
                            ) {
                                $cur_line[] = ($this->placeholder);
                                echo "\t> cur_line = ".json_encode($cur_line).PHP_EOL;
                                $lines[] = ($indent . implode('', $cur_line));
                                break;
                            }
                            $cur_len -= strlen(end($cur_line));
                            array_pop($cur_line);
                        }
                    } else {
                        if ($lines) {
                            $prev_line = end($lines)->rstrip();
                            if (
                                strlen($prev_line) + strlen($this->placeholder) <=
                                $this->width
                            ) {
                                $lines[count($lines) - 1] = $prev_line + $this->placeholder;
                                break;
                            }
                        }
                        $lines[] = ($indent + $this->placeholder->lstrip());
                    }
                    break;
                }
            }
        }

        // TODO: remove me
        echo "lines: " . json_encode($lines) . PHP_EOL;

        return $lines;
    }

    private function _split_chunks($text)
    {
        echo PHP_EOL. str_repeat('-', 50). ' _split_chunks '. str_repeat('-', 50). PHP_EOL. PHP_EOL;

        echo "\t_split_chunks -> text = " . json_encode($text) . PHP_EOL;
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
        // TODO: remove me
        echo PHP_EOL."wrap -> splitted chunks: " . json_encode($chunks) . PHP_EOL;
        if ($this->fix_sentence_endings) {
            $this->_fix_sentence_endings($chunks);
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
    static function wrap($text, $width = 70, array $kwargs)
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
    static function fill($text, $width = 70, array $kwargs)
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
    static function shorten($text, $width, array $kwargs)
    {
        if(!isset($kwargs)){
            $kwargs = array();
        }
        $kwargs['max_lines'] = 1;
        $w = self::initialize($width, $kwargs);
        return $w->fill(' ' . join(explode(' ', trim($text))));
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
        global $_whitespace_only_re, $_leading_whitespace_re;
        # Look for the longest leading string of spaces and tabs common to
        # all lines.
        $margin = null;
        $text = str_replace('', $text, $_whitespace_only_re);

        preg_match_all($_leading_whitespace_re, $text, $result);
        $indents = $result[0];

        foreach ($indents as $indent) {
            if ($margin == null) {
                $margin = $indent;
            }

            # Current line more deeply indented than previous winner:
            # no change (previous winner is still on top).
            else if ($indent->startswith($margin)) { }

            # Current line consistent with and no deeper than previous winner:
            # it's the new winner.
            else if ($margin->startswith($indent)) {
                $margin = $indent;
            }

            # Find the largest common whitespace between current line and previous
            # winner.
            else {
                // TODO: Fix the following
                // foreach (array_merge($margin, $indent) as $i => list($x, $y)) {
                //     if (x != y) {
                //         $margin = StringUtils::slice($margin, null, $i);
                //         break;
                //     }
                // }
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
            $text = preg_replace('(?m)^' + $margin, '', $text);
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
        if ($predicate == null) {
            function predicate($line)
            {
                return trim($line);
            }
        }

        function prefixed_lines()
        {
            global $text, $prefix;
            foreach (StringUtils::split_lines($text) as $line) {
                yield (predicate($line) ? $prefix + $line : $line);
            }
        }

        return implode('', array(prefixed_lines()));
    }
}

function test1() {
    $text = "This is a paragraph that already has
line breaks.  But some of its lines are much longer than the others,
so it needs to be wrapped.
Some lines are \ttabbed too.
What a mess!
";
    $wrapper = StaticTextWrapper::initialize(45, array('fix_sentence_endings' => True));
    $result = $wrapper->wrap($text);
    echo '$result '.json_encode($result).PHP_EOL;

    $expected = array(
        "This is a paragraph that already has line",
        "breaks.  But some of its lines are much",
        "longer than the others, so it needs to be",
        "wrapped.  Some lines are  tabbed too. What a",
        "mess!"
    );
    echo '$expected '.json_encode($expected).PHP_EOL;
    print_r($result==$expected? 'same': 'oops');
}

function test2() {
    $text = "
This is a paragraph that already has
line breaks.  But some of its lines are much longer than the others,
so it needs to be wrapped.
Some lines are \ttabbed too.
What a mess!
";

    $WHITESPACE = "\t\n\x0b\x0c\r ";

    $word_punct = '[\w!"\'&.,?]';
    $letter = '[^\d\W]';
    $whitespace = sprintf('[%s]', $WHITESPACE);
    $no_whitespace = '[^' . substr($whitespace, 1);

    $dummy = '/(%(ws)s+|(?<=%(wp)s)-{2,}(?=\w)|%(nws)s+?(?:-(?:(?<=%(lt)s{2}-)|(?<=%(lt)s-%(lt)s-))(?=%(lt)s-?%(lt)s)|(?=%(ws)s|\Z)|(?<=%(wp)s)(?=-{2,}\w)))/';
    $word_sep_re = StringUtils::sprintfn($dummy,
        array(
            'wp' => $word_punct,
            'lt' => $letter,
            'ws' => $whitespace,
            'nws' => $no_whitespace
        )
    );

    echo '$word_sep_re = '.json_encode($word_sep_re).PHP_EOL.PHP_EOL;

    $result = preg_split($word_sep_re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    echo '$result = '.json_encode($result).PHP_EOL.PHP_EOL;

    $result =array_values(array_filter($result, function($item){return !!$item;}));
    echo '$result = '.json_encode($result).PHP_EOL.PHP_EOL;
}

function test3() {

    $WHITESPACE = "\t\n\x0b\x0c\r ";

    $translation = array();
    $uspace = ord(' ');

    foreach (str_split($WHITESPACE) as $x) {
        $translation[$x] = ' ';
    }

    echo '$translation = '.json_encode($translation).PHP_EOL;

    unset($uspace);

    // $translation = array("92" => 32,"116" => 32,"110" => 32,"120" => 32,"48" => 32,"98" => 32,"99" => 32,"114" => 32,"32" => 32);
    foreach($translation as $key=>$value){
        echo json_encode(chr($key)).' => '.json_encode(chr($value)).PHP_EOL;
    }
    $result = strtr("Jacok\nis my anme", $translation);

    echo '$result = '.json_encode($result).PHP_EOL.PHP_EOL;
}

function test4() {
    $text = "\tTest\tcustom\t\ttabsize.";
    $expect = ["    Test    custom      tabsize."];

    $width = 80;
    $kwargs = array('tab_size' => 4);

    $result = StaticTextWrapper::wrap(
            $text,
            $width,
            $kwargs
        );

    echo "result = ".json_encode($result);
}

function test5() {
    $text = "This is a short line.";

    $width = 30;
    $kwargs = array('initial_indent' => "(1) ");

    test($text, $width, $kwargs);
}

function test($text, $width, $kwargs){
    $result = StaticTextWrapper::wrap(
            $text,
            $width,
            $kwargs
        );

    echo "result = ".json_encode($result);
}

function test_narrow_non_breaking_space()
{
    $text = ('This is a sentence with non-breaking' .
        '\N{NARROW NO-BREAK SPACE}space.');

    test($text, 20, array('break_on_hyphens' => True));
}

test_narrow_non_breaking_space();
