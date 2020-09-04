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
         * _delay
         * 
         * The delay (in milliseconds) to wait between closure attempts.
         * 
         * @access  protected
         * @var     int (default: 2000)
         */
        protected $_delay = 2000;

        /**
         * _delayMultiplier
         * 
         * Multiplier (stored as a float) which is used to determine delays
         * between closure reattempts. This is used to give some "breathing"
         * room to reattempts.
         * 
         * @access  protected
         * @var     float (default: 1.25)
         */
        protected $_delayMultiplier = 1.25;

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
         * _logTraceFunction
         * 
         * callable that is only used when an attempt completely fails and the
         * trace ought to be logged. This is useful for being able to route
         * failed attempts (with the trace) to a specific logging function or
         * to do more detailed error handling (eg. email or log the trace).
         * 
         * @access  protected
         * @var     null|callable (default: null)
         */
        protected $_logTraceFunction = null;

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
         * _useDelayMultiplier
         * 
         * @access  protected
         * @var     bool (default: true)
         */
        protected $_useDelayMultiplier = true;

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
                $response = call_user_func($closure, $args);
                $this->_restoreErrorHandler();
                $attemptResponse = array(null, $response);
                return $attemptResponse;
            } catch (\Exception $exception) {
                $this->_lastException = $exception;
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
         * @access  protected
         * @return  float
         */
        protected function _getSleepDelay(): float
        {
            $delay = $this->_delay;
            $currentAttempt = $this->_currentAttempt;
            if ($currentAttempt === 1) {
                return $delay;
            }
            if ($this->_useDelayMultiplier === false) {
                return $delay;
            }
            $delayMultiplier = $this->_delayMultiplier;
            $exp = $currentAttempt - 1;
            $delay = ($delay) * pow($delayMultiplier, $exp);
            $delay = floor($delay);
            return $delay;
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
            $trace = $exception->getTraceAsString();
            $trace = explode("\n", $trace);
            return $trace;
        }

        /**
         * _handleFailedClosureAttempt
         * 
         * @access  protected
         * @return  void
         */
        protected function _handleFailedClosureAttempt(): void
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
         * _logFailedFinalAttempt
         * 
         * @access  protected
         * @return  bool
         */
        protected function _logFailedFinalAttempt(): bool
        {
            $msg = 'onassar\\RiskyClosure\\Base failed';
            $this->_log($msg);
            $trace = $this->_getTrace();
            $this->_logTrace($trace);
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
            $delay = $this->_getSleepDelay();
            $msg = 'Going to sleep for ' . ($delay);
            $this->_log($msg);
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
            if ($this->_logTraceFunction === null) {
                $trace = implode("\n", $trace);
                error_log($trace);
                return false;
            }
            $closure = $this->_logTraceFunction;
            $args = array($trace);
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
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
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
            $delay = $this->_getSleepDelay();
            usleep($delay * 1000);
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
                $this->_handleFailedClosureAttempt();
                return $closureAttemptResponse;
            }
            $closureAttemptResponse = $this->_reattemptClosure();
            return $closureAttemptResponse;
        }

        /**
         * getLastException
         * 
         * @access  public
         * @return  null|Exception
         */
        public function getLastException(): ?Exception
        {
            $exception = $this->_lastException;
            return $exception;
        }

        /**
         * setDelay
         * 
         * @access  public
         * @param   null|int $delay
         * @return  void
         */
        public function setDelay(?int $delay): void
        {
            $this->_delay = $delay ?? $this->_delay;
        }

        /**
         * setDelayMultiplier
         * 
         * @access  public
         * @param   null|int $delayMultiplier
         * @return  void
         */
        public function setDelayMultiplier(?int $delayMultiplier): void
        {
            $this->_delayMultiplier = $delayMultiplier ?? $this->_delayMultiplier;
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
         * setLogTraceFunction
         * 
         * @access  public
         * @param   callable $logTraceFunction
         * @return  void
         */
        public function setLogTraceFunction(callable $logTraceFunction): void
        {
            $this->_logTraceFunction = $logTraceFunction;
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
    }
