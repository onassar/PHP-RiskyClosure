# PHP-RiskyClosure
Provides a utility class which is used for attempting calls to code that may be unreliable (eg. 3rd party SDKs, remote requests, etc).

Features include:
- Delays between failed attempts
- Multiplier for increasing delays between failed attempts
- Exception access
- Specifying maximum number of attempts
- Defining a log closure to make handling logging application-specific
- Defining a trace log closure to make handling logging application-specific

### Sample Call

``` php
$closure = function() {
    echo $test;
};
$riskyClosure = new RiskyClosure\Base($closure);
$attemptResponse = $riskyClosure->attempt();
echo $attemptResponse;
exit(0);
```

### Sample Call (w/ properties)

``` php
$closure = function() {
    echo $test;
};
$riskyClosure = new RiskyClosure\Base($closure);
$riskyClosure->setDelay(3000);
$riskyClosure->setMaxAttempts(4);
$attemptResponse = $riskyClosure->attempt();
echo $attemptResponse;
exit(0);
```

### Sample Call (w/ callable log function)

``` php
$closure = function() {
    echo $test;
};
$logFunction = array('error_log');
$riskyClosure = new RiskyClosure\Base($closure);
$riskyClosure->setLogFunction($logFunction);
$attemptResponse = $riskyClosure->attempt();
echo $attemptResponse;
exit(0);
```

### Related libraries
- [onassar/PHP-RemoteRequests](https://github.com/onassar/PHP-RemoteRequests)
