<?php

/**
 * apDeliveryCacheRedis for Revive Adserver
 *
 * This file includes a derivative version of:
 *
 * Redisent, a Redis interface for the modest
 * @author Justin Poliey <justin@getglue.com>
 * @copyright 2009-2012 Justin Poliey <justin@getglue.com>
 * @license http://www.opensource.org/licenses/ISC The ISC License
 * @package Redisent
 */

/**
 * Wraps native Redis errors in friendlier PHP exceptions
 */
class RedisException extends Exception
{
}

/**
 * Redisent, a Redis interface for the modest among us
 */
class Redis
{
    /**
     * Socket connection to the Redis server
     * @var resource
     * @access private
     */
    private $__sock;

    /**
     * The structure representing the data source of the Redis server
     * @var array
     * @access public
     */
    public $dsn;

    /**
     * Flag indicating whether or not commands are being pipelined
     * @var boolean
     * @access private
     */
    private $pipelined = false;

    /**
     * Flag indicating whether or not commands are being executed as a transaction
     * @var boolean
     * @access private
     */
    private $transaction = false;

    /**
     * The queue of commands to be sent to the Redis server
     * @var array
     * @access private
     */
    private $queue = [];

    /**
     * Creates a Redisent connection to the Redis server at the address specified by {@link $dsn}.
     * The default connection is to the server running on localhost on port 6379.
     * @param string $host The hostanme (or unix socket)
     * @param string $port The port number (ignored for unix socket)
     * @param float $timeout The connection timeout in seconds
     */
    public function __construct($host, $port = null, $timeout = null)
    {
        if (empty($host)) {
            throw new RedisException("Invalid hostname");
        }
        if ($host[0] == '/') {
            // Socket
            $host = 'unix://' . $host;
            $port = -1;
        } elseif (empty($port)) {
            $port = 6379;
        }
        $timeout = $timeout ?: ini_get("default_socket_timeout");
        $this->__sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($this->__sock === false) {
            throw new RedisException("{$errno} - {$errstr}");
        }
        if (isset($this->dsn['pass'])) {
            $this->auth($this->dsn['pass']);
        }
    }

    public function __destruct()
    {
        fclose($this->__sock);
    }

    /**
     * Returns the Redisent instance ready for pipelining.
     * Redis commands can now be chained, and the array of the responses will be returned when {@link uncork} is called.
     * @see uncork
     * @access public
     */
    public function pipeline()
    {
        $this->pipelined = true;
        return $this;
    }

    /**
     * Flushes the commands in the pipeline queue to Redis and returns the responses.
     * @see pipeline
     * @access public
     */
    public function uncork()
    {
        /* Open a Redis connection and execute the queued commands */
        foreach ($this->queue as $command) {
            for ($written = 0; $written < strlen($command); $written += $fwrite) {
                $fwrite = fwrite($this->__sock, substr($command, $written));
                if ($fwrite === false || $fwrite <= 0) {
                    throw new RedisException('Failed to write entire command to stream');
                }
            }
        }

        // Read in the results from the pipelined commands
        $responses = [];
        for ($i = 0; $i < count($this->queue); $i++) {
            $responses[] = $this->readResponse();
        }

        // Clear the queue and return the response
        $this->queue = [];
        if ($this->pipelined) {
            $this->pipelined = false;
            if ($this->transaction) {
                $this->transaction = false;
                $responses = end($responses);
            }
            return $responses;
        } else {
            return $responses[0];
        }
    }

    public function __call($name, $args)
    {
        return $this->_call($name, $args);
    }

    private function _call($name, $args = [])
    {
        if (!is_array($args)) {
            $args = [];
        }

        /* Build the Redis unified protocol command */
        array_unshift($args, strtoupper($name));
        $command = sprintf('*%d%s%s%s', count($args), "\r\n", implode("\r\n", array_map(
            [$this, '_writeStr'],
            $args
        )), "\r\n");

        /* Add it to the pipeline queue */
        $this->queue[] = $command;

        if ($this->pipelined) {
            return $this;
        } else {
            return $this->uncork();
        }
    }

    public function _writeStr($str)
    {
        return sprintf('$%d%s%s', strlen($str), "\r\n", $str);
    }

    private function readResponse()
    {
        /* Parse the response based on the reply identifier */
        $reply = trim(fgets($this->__sock, 512));
        switch (substr($reply, 0, 1)) {
            /* Error reply */
            case '-':
                throw new RedisException(trim(substr($reply, 4)));
                break;
            /* Inline reply */
            case '+':
                $response = substr(trim($reply), 1);
                if ($response === 'OK') {
                    $response = true;
                }
                break;
            /* Bulk reply */
            case '$':
                $response = null;
                if ($reply == '$-1') {
                    break;
                }
                $read = 0;
                $size = intval(substr($reply, 1));
                if ($size > 0) {
                    do {
                        $block_size = ($size - $read) > 1024 ? 1024 : ($size - $read);
                        $r = fread($this->__sock, $block_size);
                        if ($r === false) {
                            throw new Exception('Failed to read response from stream');
                        } else {
                            $read += strlen($r);
                            $response .= $r;
                        }
                    } while ($read < $size);
                }
                fread($this->__sock, 2); /* discard crlf */
                break;
            /* Multi-bulk reply */
            case '*':
                $count = intval(substr($reply, 1));
                if ($count == '-1') {
                    return null;
                }
                $response = [];
                for ($i = 0; $i < $count; $i++) {
                    $response[] = $this->readResponse();
                }
                break;
            /* Integer reply */
            case ':':
                $response = intval(substr(trim($reply), 1));
                break;
            default:
                throw new RedisException("Unknown response: {$reply}");
                break;
        }
        /* Party on */
        return $response;
    }

    public function watch()
    {
        return $this->_call('watch', func_get_args());
    }

    public function unwatch()
    {
        return $this->_call('unwatch', func_get_args());
    }

    public function multi()
    {
        $this->transaction = true;
        return $this->pipeline()->_call('multi');
    }

    public function discard()
    {
        return $this->_call('discard')->uncork();
    }

    public function exec()
    {
        return $this->_call('exec')->uncork();
    }
}
