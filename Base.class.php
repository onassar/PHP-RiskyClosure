<?php

    // Namespace overhead
    namespace onassar\RiskyClosure;

    /**
     * Base
     * 
     * @link    https://github.com/onassar/PHP-RiskyClosure
     * @author  Oliver Nassar <onassar@gmail.com>
     */
    class Base
    {
        /**
         * _closure
         * 
         * Reference to the closure that's being attempted.
         * 
         * @access  protected
         * @var     null|\Closure (default: null)
         */
        protected $_closure = null;

        /**
         * _currentAttempt
         * 
         * Tracks the current closure attempts (to ensure ending attempts once
         * the maximum number of attempts have been attempted, and to log this
         * to the system).
         * 
         * @access  protected
         * @var     int (default: 0)
         */
        protected $_currentAttempt = 0;

        /**
         * _exceptions
         * 
         * @access  protected
         * @var     array (default: array())
         */
        protected $_exceptions = array();

        /**
         * _failedAttemptDelay
         * 
         * The delay (in milliseconds) to wait between failed closure attempts.
         * 
         * @access  protected
         * @var     int (default: 2000)
         */
        protected $_failedAttemptDelay = 2000;

        /**
         * _failedAttemptDelayMultiplier
         * 
         * Multiplier (stored as a float) which is used to determine delays
         * between closure reattempts. This is used to give some "breathing"
         * room to reattempts.
         * 
         * @access  protected
         * @var     float (default: 1.25)
         */
        protected $_failedAttemptDelayMultiplier = 1.25;

        /**
         * _failedAttemptLoggingEvaluator
         * 
         * @access  protected
         * @var     null|callable (default: null)
         */
        protected $_failedAttemptLoggingEvaluator = null;

        /**
         * _lastException
         * 
         * A reference to the last exception that was thrown/triggered while
         * attempting to call a call. This can be useful in middleware-logic to
         * check what went wrong when attempting to call a closure.
         * 
         * @access  protected
         * @var     null|Exception (default: null)
         */
        protected $_lastException = null;

        /**
         * _logFunction
         * 
         * @access  protected
         * @var     null|callable (default: null)
         */
        protected $_logFunction = null;

        /**
         * _maxAttempts
         * 
         * The maximum number of times a closure should be attempted before
         * abandoning it.
         * 
         * @access  protected
         * @var     int (default: 1)
         */
        protected $_maxAttempts = 1;

        /**
         * _quiet
         * 
         * Whether or not messages should be logged to the system when closures
         * fail.
         * 
         * @access  protected
         * @var     bool (default: false)
         */
        protected $_quiet = false;

        /**
         * _traceLogFunction
         * 
         * callable that is only used when an attempt completely fails and the
         * trace ought to be logged. This is useful for being able to route
         * failed attempts (with the trace) to a specific logging function or
         * to do more detailed error handling (eg. email or log the trace).
         * 
         * @access  protected
         * @var     null|callable (default: null)
         */
        protected $_traceLogFunction = null;

        /**
         * __construct
         * 
         * @access  public
         * @param   \Closure $closure
         * @return  void
         */
        public function __construct(\Closure $closure)
        {
            $this->_closure = $closure;
        }

        /**
         * _attemptClosure
         * 
         * Runs the closure. If an error is encountered, it's converted to an
         * ErrorException and thrown.
         * 
         * If no exception is caught (including an ErrorException), a
         * numerically-indexed array is returned with null as as the first
         * value (denoting no exception occurred), and the closure response as
         * the second value.
         * 
         * Otherwise an array with the exception as the first value and null as
         * the second value is returned.
         * 
         * In both cases, the error handler is restored before anything is
         * returned.
         * 
         * @see     http://php.net/manual/en/class.errorexception.php
         * @access  protected
         * @return  array
         */
        protected function _attemptClosure(): array
        {
            $this->_currentAttempt++;
            $this->_setErrorHandler();
            try {
                $closure = $this->_closure;
                $args = array();
                $response = call_user_func_array($closure, $args);
                $this->_restoreErrorHandler();
                $attemptResponse = array(null, $response);
                return $attemptResponse;
            } catch (\Exception $exception) {
                $this->_lastException = $exception;
                array_push($this->_exceptions, $exception);
            }
            $this->_restoreErrorHandler();
            $attemptResponse = array($exception, null);
            return $attemptResponse;
        }

        /**
         * _getFailedClosureAttemptLogMessage
         * 
         * @access  protected
         * @return  string
         */
        protected function _getFailedClosureAttemptLogMessage(): string
        {
            $currentAttempt = $this->_currentAttempt;
            $maxAttempts = $this->_maxAttempts;
            $msg = 'Failed closure attempt';
            $attempt = '(' . ($currentAttempt) . ' of ' . ($maxAttempts) . ')';
            $msg = ($msg) . ' ' . ($attempt);
            return $msg;
        }

        /**
         * _getSleepDelay
         * 
         * Returns the number of milliseconds that any sleep call should wait
         * between failed attempts, which is based on a multiplier.
         * 
         * For example, after the first failed attempt, it waits whatever the
         * defined $failedAttemptDelay is. After that, it uses the attempt
         * count to increase exponentially how long it waits. The logic is that
         * if an attempt fails multiple times, we want to give the receiving
         * server a bit more time after each attempt to respond.
         * 
         * @access  protected
         * @return  float
         */
        protected function _getSleepDelay(): float
        {
            $failedAttemptDelay = $this->_failedAttemptDelay;
            $currentAttempt = $this->_currentAttempt;
            if ($currentAttempt === 1) {
                return $failedAttemptDelay;
            }
            $failedAttemptDelayMultiplier = $this->_failedAttemptDelayMultiplier;
            $exp = $currentAttempt - 1;
            $failedAttemptDelay = ($failedAttemptDelay) * pow($failedAttemptDelayMultiplier, $exp);
            $failedAttemptDelay = floor($failedAttemptDelay);
            return $failedAttemptDelay;
        }

        /**
         * _getTrace
         * 
         * @access  protected
         * @return  array
         */
        protected function _getTrace(): array
        {
            $exception = new \Exception();
            $exception = $this->_lastException ?? $exception;
            $trace = $exception->getTraceAsString();
            $trace = explode("\n", $trace);
            $trace = array_reverse($trace);
            foreach ($trace as &$frame) {
                $frame = preg_replace('/^#[0-9]+ /', '', $frame);
            }
            return $trace;
        }

        /**
         * _handleFailedClosureAttempts
         * 
         * @access  protected
         * @return  void
         */
        protected function _handleFailedClosureAttempts(): void
        {
            $this->_logFailedAttempt();
            $this->_currentAttempt = 0;
        }

        /**
         * _handleSuccessfulClosureAttempt
         * 
         * @access  protected
         * @return  void
         */
        protected function _handleSuccessfulClosureAttempt(): void
        {
            $this->_logSuccessfulClosureAttempt();
            $this->_currentAttempt = 0;
        }

        /**
         * _log
         * 
         * @access  protected
         * @param   array $values,...
         * @return  bool
         */
        protected function _log(... $values): bool
        {
            if ($this->_quiet === true) {
                return false;
            }
            if ($this->_logFunction === null) {
                foreach ($values as $value) {
                    error_log($value);
                }
                return false;
            }
            $closure = $this->_logFunction;
            $args = $values;
            call_user_func_array($closure, $args);
            return true;
        }

        /**
         * _logFailedAttempt
         * 
         * @access  protected
         * @return  bool
         */
        protected function _logFailedAttempt(): bool
        {
            $valid = $this->_validFailedAttemptLog();
            if ($valid === false) {
                return false;
            }
            $msg = $this->_getFailedClosureAttemptLogMessage();
            $this->_log($msg);
            $exception = $this->_lastException;
            $msg = $exception->getMessage();
            $this->_log($msg);
            $finalFailedClosureAttempt = $this->_currentAttempt === $this->_maxAttempts;
            if ($finalFailedClosureAttempt === true) {
                $this->_logFailedFinalAttempt();
            }
            return true;
        }

        /**
         * _logFailedAttemptSleep
         * 
         * @access  protected
         * @return  bool
         */
        protected function _logFailedAttemptSleep(): bool
        {
            $valid = $this->_validFailedAttemptLog();
            if ($valid === false) {
                return false;
            }
            $sleepDelay = $this->_getSleepDelay();
            $msg = 'Going to sleep for ' . ($sleepDelay) . 'ms';
            $this->_log($msg);
            return true;
        }

        /**
         * _logFailedFinalAttempt
         * 
         * @access  protected
         * @return  bool
         */
        protected function _logFailedFinalAttempt(): bool
        {
            $className = get_called_class();
            $msg = ($className) . ' failed';
            $this->_log($msg);
            $trace = $this->_getTrace();
            $this->_logTrace($trace);
            return true;
        }

        /**
         * _logSuccessfulClosureAttempt
         * 
         * @access  protected
         * @return  bool
         */
        protected function _logSuccessfulClosureAttempt(): bool
        {
            $currentAttempt = $this->_currentAttempt;
            if ($currentAttempt === 1) {
                return false;
            }
            $msg = 'Subsequent success on attempt #' . ($currentAttempt);
            $this->_log($msg);
            return true;
        }

        /**
         * _logTrace
         * 
         * @access  protected
         * @param   array $trace
         * @return  bool
         */
        protected function _logTrace(array $trace): bool
        {
            if ($this->_quiet === true) {
                return false;
            }
            if ($this->_traceLogFunction === null) {
                $trace = implode("\n", $trace);
                error_log($trace);
                return false;
            }
            $closure = $this->_traceLogFunction;
            $args = array($trace, $this);
            call_user_func_array($closure, $args);
            return true;
        }

        /**
         * _reattemptClosure
         * 
         * @access  protected
         * @return  array
         */
        protected function _reattemptClosure(): array
        {
            $this->_logFailedAttempt();
            $this->_logFailedAttemptSleep();
            $this->_sleep();
            $closureAttemptResponse = $this->attempt();
            return $closureAttemptResponse;
        }

        /**
         * _restoreErrorHandler
         * 
         * @access  protected
         * @return  void
         */
        protected function _restoreErrorHandler(): void
        {
            restore_error_handler();
        }

        /**
         * _setErrorHandler
         * 
         * @link    https://www.php.net/manual/en/function.set-error-handler.php
         * @see     http://php.net/manual/en/class.errorexception.php
         * @access  protected
         * @return  void
         */
        protected function _setErrorHandler(): void
        {
            set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext) {
                $args = array($errstr, 0, $errno, $errfile, $errline);
                throw new \ErrorException(... $args);
            });
        }

        /**
         * _sleep
         * 
         * @access  protected
         * @return  void
         */
        protected function _sleep(): void
        {
            $sleepDelay = $this->_getSleepDelay();
            usleep($sleepDelay * 1000);
        }

        /**
         * _validFailedAttemptLog
         * 
         * @access  protected
         * @return  bool
         */
        protected function _validFailedAttemptLog(): bool
        {
            $callback = $this->_failedAttemptLoggingEvaluator;
            if ($callback === null) {
                return true;
            }
            $params = array($this);
            $valid = call_user_func_array($callback, $params);
            return $valid;
        }

        /**
         * attempt
         * 
         * Acts as wrapper for the above protected method, however also handles
         * any potential sequential attempts (and the delay associated with
         * those attempts).
         * 
         * @access  public
         * @return  array
         */
        public function attempt(): array
        {
            // Attempt
            $closureAttemptResponse = $this->_attemptClosure();
            list($exception, $response) = $closureAttemptResponse;

            // Handle successful attempt
            if ($exception === null) {
                $this->_handleSuccessfulClosureAttempt();
                return $closureAttemptResponse;
            }

            // Handle failed attempt
            $finalFailedClosureAttempt = $this->_currentAttempt === $this->_maxAttempts;
            if ($finalFailedClosureAttempt === true) {
                $this->_handleFailedClosureAttempts();
                return $closureAttemptResponse;
            }
            $closureAttemptResponse = $this->_reattemptClosure();
            return $closureAttemptResponse;
        }

        /**
         * getCurrentAttempt
         * 
         * @access  public
         * @return  int
         */
        public function getCurrentAttempt(): int
        {
            $currentAttempt = $this->_currentAttempt;
            return $currentAttempt;
        }

        /**
         * getLastException
         * 
         * @access  public
         * @return  null|\Exception
         */
        public function getLastException(): ?\Exception
        {
            $exception = $this->_lastException;
            return $exception;
        }

        /**
         * getMaxAttempts
         * 
         * @access  public
         * @return  int
         */
        public function getMaxAttempts(): int
        {
            $maxAttempts = $this->_maxAttempts;
            return $maxAttempts;
        }

        /**
         * setFailedAttemptDelay
         * 
         * @access  public
         * @param   null|int $failedAttemptDelay
         * @return  void
         */
        public function setFailedAttemptDelay(?int $failedAttemptDelay): void
        {
            $this->_failedAttemptDelay = $failedAttemptDelay ?? $this->_failedAttemptDelay;
        }

        /**
         * setFailedAttemptDelayMultiplier
         * 
         * @access  public
         * @param   null|int $failedAttemptDelayMultiplier
         * @return  void
         */
        public function setFailedAttemptDelayMultiplier(?int $failedAttemptDelayMultiplier): void
        {
            $this->_failedAttemptDelayMultiplier = $failedAttemptDelayMultiplier ?? $this->_failedAttemptDelayMultiplier;
        }

        /**
         * setFailedAttemptLoggingEvaluator
         * 
         * @access  public
         * @param   null|callable $callback
         * @return  void
         */
        public function setFailedAttemptLoggingEvaluator(?callable $callback): void
        {
            $this->_failedAttemptLoggingEvaluator = $callback;
        }

        /**
         * setLogFunction
         * 
         * @access  public
         * @param   callable $logFunction
         * @return  void
         */
        public function setLogFunction(callable $logFunction): void
        {
            $this->_logFunction = $logFunction;
        }

        /**
         * setMaxAttempts
         * 
         * @access  public
         * @param   null|int $maxAttempts
         * @return  void
         */
        public function setMaxAttempts(?int $maxAttempts): void
        {
            $this->_maxAttempts = $maxAttempts ?? $this->_maxAttempts;
        }

        /**
         * setQuiet
         * 
         * @access  public
         * @param   null|bool $quiet
         * @return  void
         */
        public function setQuiet(?bool $quiet): void
        {
            $this->_quiet = $quiet ?? $this->_quiet;
        }

        /**
         * setTraceLogFunction
         * 
         * @access  public
         * @param   callable $traceLogFunction
         * @return  void
         */
        public function setTraceLogFunction(callable $traceLogFunction): void
        {
            $this->_traceLogFunction = $traceLogFunction;
        }
    }
