<?php
namespace Phparser;

/**
 * Grammar parser class.
 *
 */
class Parser
{
    /**
     * Sequence of read tokens.
     *
     * @var array
     */
    protected $_sequence = [];

    /**
     * Stack of shifted tokens
     *
     * @var array
     */
    protected $_stokens = [];

    /**
     * SLR parser stack
     *
     * @var array
     */
    protected $_stack = [];


    /**
     * Instance of the grammar being parsed.
     *
     * @var \Phparser\Grammar
     */
    protected $_grammar = null;

    /**
     * Lexer instance which provides tokens to this parser.
     *
     * @var \Phparser\Lexer
     */
    protected $_lexer = null;

    /**
     * Used internally, can be accessed using the log() method.
     *
     * @var array
     */
    protected $_log = [];

    /**
     * Parser constructor
     *
     * @param \Phparser\Lexer $lexer Lexer instance.
     * @param \Phparser\Grammar $grammar Grammar instance.
     */
    public function __construct($lexer, $grammar)
    {
        $this->_grammar = $grammar;
        $this->_lexer = $lexer;
    }

    /**
     * Starts the parsing process.
     *
     * @param string $input String input to parse.
     * @return bool True on success, false otherwise.
     */
    public function run($input)
    {
        $this->_init($input);

        $error = false;
        $i = 0;

        while (!$error && $i < 100) {
            $i++;
            $this->_log();
            $action = $this->_action();

            if ($action == 'err') {
                $error = true;
            } elseif (strpos($action, 's') !== false) {
                $this->_shift($action);
            } elseif (strpos($action, 'r') !== false) {
                $this->_reduce($action);
            } elseif (strpos($action, 'acc') !== false) {
                break;
            }
        }

        return ($error != false);
    }

    /**
     * Gets internal log content.
     *
     * @return array
     */
    public function trace()
    {
        return $this->_log;
    }

    /**
     * Gets the syntax tree.
     *
     * @return array
     */
    public function tree()
    {
        return $this->_stokens;
    }

    /**
     * Initializes some internal required for the parsing process, stack, grammar,
     * etc.
     *
     * @param string $input The string to parse.
     * @return void
     */
    protected function _init($input)
    {
        $this->_sequence = $this->_lexer->run($input);
        $this->_stack[] = 0;
        $this->_input = $this->_sequence[0];

        $validTokens = array_filter($this->_lexer->tokens());
        $grammarTerminals = $this->_grammar->terminals();
        $diff = array_diff($grammarTerminals, $validTokens); // check unused tokens?
    }

    /**
     * Gets the next action to perform by the automate, shift, reduce, etc.
     *
     * @return string Action
     */
    protected function _action()
    {
        $top = end($this->_stack);
        $token = $this->_input;
        $info = $this->_grammar->parseTable()[$top];
        $action = $info[$token->id()];
        $action = !empty($action) ? $action : 'err';
        return $action;
    }

    /**
     * Performs a shift action.
     *
     * @param string $to State where to shift. e.g. `s6` for "shift to state 6".
     * @return void
     */
    protected function _shift($to)
    {
        $to = intval(str_replace('s', '', $to));
        $this->_stack[] = $to;
        $this->_stokens[] = array_shift($this->_sequence);
        $this->_input = array_values($this->_sequence)[0];
    }

    /**
     * Performs a reduce action.
     *
     * @param string $to Rule to reduce by. e.g. `r2` for "reduce by rule 2".
     * @return void
     */
    protected function _reduce($by)
    {
        $by = intval(str_replace('r', '', $by));
        $rule = $this->_grammar->productions()[$by];
        $ruleRight = array_values($rule)[0];
        $ruleLeft = array_keys($rule)[0];
        $rightCount = count(explode(' ', $ruleRight));

        for ($i = 0; $i < $rightCount; $i++) {
            array_pop($this->_stack);
        }

        $this->_stack[] = $this->_goto($ruleLeft);

        $productionTokens = [];
        for ($i = 0; $i < $rightCount; $i++) {
            $productionTokens[] = array_pop($this->_stokens);
        }

        $args = ['rule' => $rule, 'tokens' => array_reverse($productionTokens)];
        $this->_stokens[] = $args;
        $this->_grammar->reduceBy($by, $args);
    }

    /**
     * Gets the state where to "goto".
     *
     * @param string $variable Production variable
     * @return int
     */
    protected function _goto($variable)
    {
        $top = end($this->_stack);
        return $this->_grammar->parseTable()[$top][$variable];
    }

    /**
     * Logs a message.
     *
     * @param null|string $msg Message to log, or empty to insert a trace log.
     * @return void
     */
    protected function _log($msg = null)
    {
        if (!$msg) {
            $stack = implode(' ', $this->_stack);
            $input = implode(' ', $this->_sequence);
            $action = $this->_action();
            $this->_log[] = sprintf('stack: %s | input: ^%s | action: %s', $stack, $input, $action);
        } else {
            $this->_log[] = $msg;
        }
    }
}
