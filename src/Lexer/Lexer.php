<?php
namespace Phparser\Lexer;

use Phparser\Lexer\Token;

/**
 * Lexer class for extracting tokens from a given input.
 *
 */
class Lexer
{
    /**
     * Regex pattern, used internally.
     *
     * @var string
     */
    protected $_regex = '';

    /**
     * Offset to token.
     *
     * @var int
     */
    protected $_offsetToToken = 0;

    /**
     * List of patterns a tokens. Example:
     *
     * ```php
     * [
     *     '[0-9]+' => 'TK_NUMBER',
     *     '\+' => 'TK_ADD',
     *     '\-' => 'TK_SUB',
     *     '\s+|\n+|\t+' => '', // consume the rest
     * ]
     * ```
     *
     * @var array
     */
    protected $_tokenMap = [];

    /**
     * Lexer constructor.
     *
     * @param array $tokenMap List of patterns and tokens
     */
    public function __construct(array $tokenMap)
    {
        $this->_tokenMap = $tokenMap;
    }

    /**
     * Starts the lexical analysis.
     *
     * @param string $string The string to analyze
     * @return array Sequence of tokens
     */
    public function run($string)
    {
        $this->_regex = '/(' . implode(')|(', array_keys($this->_tokenMap)) . ')/iA';
        $this->_offsetToToken = array_values($this->_tokenMap);
        return $this->_lex($string);
    }

    /**
     * Gets a list of all valid tokens. e.g. `TK_ADD`, `TK_SUB`, etc.
     *
     * @return array
     */
    public function tokens()
    {
        return array_values($this->_tokenMap);
    }

    /**
     * Used internally, this is the methods which actually extract the tokens.
     *
     * @param string $string The input string
     * @return array
     */
    protected function _lex($string)
    {
        $tokens = [];
        $offset = 0;

        while (isset($string[$offset])) {
            if (!preg_match($this->_regex, $string, $matches, null, $offset)) {
                throw new \Exception(sprintf('Unexpected character "%s"', $string[$offset]));
            }

            for ($i = 1; '' === $matches[$i]; ++$i) {
            }
            if ($this->_offsetToToken[$i - 1] != '') {
                $tokens[] = new Token($this->_offsetToToken[$i - 1], $matches[0]);
            }

            $offset += strlen($matches[0]);
        }

        $tokens[] = new Token('$', '$');
        return $tokens;
    }
}
