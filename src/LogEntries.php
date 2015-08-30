<?php

namespace cbschuld;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class LogEntries extends AbstractLogger
{
    /** @var LogEntries */
    private static $_instance = null;

    /** LogEntries server address for receiving logs */
    const LE_ADDRESS = 'tcp://api.logentries.com';
    /** LogEntries server address for receiving logs via TLS */
    const LE_TLS_ADDRESS = 'tls://api.logentries.com';
    /** LogEntries server port for receiving logs by token */
    const LE_PORT = 10000;
    /** LogEntries server port for receiving logs with TLS by token */
    const LE_TLS_PORT = 20000;

    /** @var resource */
    private $_socketResource = null;
    /** @var string the token for LogEntries */
    private $_token = null;
    /** @var string the ip address for DataHub*/
    private $_dataHubIPAddress = "";
    /** @var boolean use DataHub */
    private $_useDataHub = false;
    /** @var int the port number for DataHub */
    private $_dataHubPort = 10000;
    /** @var string hostname to log, if provided it is added to the string or added to the json */
    private $_hostname = "";
    /** @var int seconds before timeout, defaults to the ini setting value of 'default_socket_timeout' */
    private $_connectionTimeout;
    /** @var boolean use a persistent connection */
    private $_persistent = true;
    /** @var boolean use ssl for connection */
    private $_ssl = false;
    /** @var string - error number */
    private $_error_number;
    /** @var string - error description */
    private $_error_string;


    /**
     * Returns the singleton instance of the logger
     * @param $token string token for access to their API
     * @param bool|true $persistent use a persistent connection
     * @param bool|false $ssl use SSL for the connection
     * @param bool|false $dataHubEnabled use the LogEntries DataHub
     * @param string $dataHubIPAddress the IP address for the LogEntries DataHub if in use
     * @param int $dataHubPort the port number for the LogEntries DataHub if in use
     * @param string $hostname the hostname to add to the string or json log entry (optional)
     * @return LogEntries
     */
    public static function getLogger($token, $persistent = true, $ssl = false, $dataHubEnabled = false, $dataHubIPAddress = "", $dataHubPort = 10000, $hostname = "")
    {
        if (!self::$_instance) {
            self::$_instance = new LogEntries($token, $persistent, $ssl, $dataHubEnabled, $dataHubIPAddress, $dataHubPort, $hostname);
        }
        return self::$_instance;
    }

    /**
     * Destroy singleton instance, used in PHPUnit tests
     */
    public static function tearDown()
    {
        self::$_instance = null;
    }

    /**
     * LogEntries constructor which sets up the connection defaults
     * @param $token string token for access to their API
     * @param bool|true $persistent use a persistent connection
     * @param bool|false $ssl use SSL for the connection
     * @param bool|false $dataHubEnabled use the LogEntries DataHub
     * @param string $dataHubIPAddress the IP address for the LogEntries DataHub if in use
     * @param int $dataHubPort the port number for the LogEntries DataHub if in use
     * @param string $hostname the hostname to add to the string or json log entry (optional)
     */
    private function __construct($token, $persistent, $ssl, $dataHubEnabled, $dataHubIPAddress, $dataHubPort, $hostname)
    {

        if (true === $dataHubEnabled) {
            // Check if a DataHub IP Address has been entered
            $this->validateDataHubIP($dataHubIPAddress);

            // set DataHub variable values
            $this->_dataHubIPAddress = $dataHubIPAddress;
            $this->_useDataHub = $dataHubEnabled;
            $this->_dataHubPort = $dataHubPort;

            // if DataHub is being used the logToken should be set to null
            $this->_token = null;
        } else // only validate the token when user is not using Datahub
        {
            $this->validateToken($token);
            $this->_token = $token;
        }
        $this->_hostname = $hostname;
        $this->_persistent = $persistent;
        $this->_ssl = $ssl;
        $this->_connectionTimeout = (double)ini_get('default_socket_timeout');
    }

    /**
     * destructor to close the socket on a persistent connection
     */
    public function __destruct()
    {
        $this->closeSocket();
    }

    /**
     * validates the token provided by the caller is not empty
     * @param $token
     */
    public function validateToken($token)
    {

        if (empty($token)) {
            throw new \InvalidArgumentException('Logentries Token not provided');
        }
    }

    /**
     * validates the ip address provided by the caller is not empty
     * @param $dataHubIPAddress
     */
    public function validateDataHubIP($dataHubIPAddress)
    {
        if (empty($dataHubIPAddress)) {
            throw new \InvalidArgumentException('Logentries Datahub IP Address not provided');
        }
    }

    /**
     * closes the socket/resource
     */
    public function closeSocket()
    {
        if (is_resource($this->_socketResource)) {
            fclose($this->_socketResource);
            $this->_socketResource = null;
        }
    }

    /**
     * is the connection option set for persistent connections
     * @return bool|true
     */
    public function isPersistent()
    {
        return $this->_persistent;
    }

    /**
     * is the connection setup for SSL
     * @return bool|false
     */
    public function isTLS()
    {
        return $this->_ssl;
    }

    /**
     * get the port number for the connection
     * @return int
     */
    public function getPort()
    {
        if ($this->isTLS()) {
            return self::LE_TLS_PORT;
        } elseif ($this->isDataHub()) {
            return $this->_dataHubPort;
        } else {
            return self::LE_PORT;
        }
    }

    /**
     * returns true if the LogEntries DataHub is in use
     * @return bool|false
     */
    public function isDataHub()
    {
        return $this->_useDataHub;
    }

    /**
     * gets the address for the connection
     * @return string the address for the connection
     */
    public function getAddress()
    {
        if ($this->isTLS() && !$this->isDataHub()) {
            return self::LE_TLS_ADDRESS;
        } elseif ($this->isDataHub()) {
            return $this->_dataHubIPAddress;
        } else {
            return self::LE_ADDRESS;
        }
    }

    /**
     * returns true if the system is connected to LogEntries
     * @return bool
     */
    public function isConnected()
    {
        return is_resource($this->_socketResource) && !feof($this->_socketResource);
    }

    /**
     * create the connection socket
     */
    private function createSocket()
    {
        $port = $this->getPort();

        $address = $this->getAddress();

        if ($this->isPersistent()) {
            $resource = $this->getSocketPersistent($port, $address);
        } else {
            $resource = $this->getSocket($port, $address);
        }

        if (is_resource($resource) && !feof($resource)) {
            $this->_socketResource = $resource;
        }
    }

    /**
     * creates a persistent socket connection
     * @param $port
     * @param $address
     * @return resource
     */
    private function getSocketPersistent($port, $address)
    {
        return @pfsockopen($address, $port, $this->_error_number, $this->_error_string, $this->_connectionTimeout);
    }

    /**
     * creates a non-persistent socket connection
     * @param $port
     * @param $address
     * @return resource
     */
    private function getSocket($port, $address)
    {
        return @fsockopen($address, $port, $this->_error_number, $this->_error_string, $this->_connectionTimeout);
    }

    /**
     * write the line parameter to the socket (this also prepends the user's token per the LogEntries API)
     * @param $line
     */
    public function writeToSocket($line)
    {
        if ($this->isConnected() || $this->connectIfNotConnected()) {
            fputs($this->_socketResource, $this->_token . $line);
        }
    }

    /**
     * replaces all PHP_EOL characters with unique int(13)
     * @param $line
     * @return mixed
     */
    private function substituteNewline($line)
    {
        $unicodeChar = chr(13);
        $newLine = str_replace(PHP_EOL, $unicodeChar, $line);

        return $newLine;
    }

    /**
     * if not connected attempt to connect, returns true if connection is established, otherwise false
     * @return bool connection was established
     */
    private function connectIfNotConnected()
    {
        if ($this->isConnected()) {
            return true;
        }
        $this->connect();
        return $this->isConnected();
    }

    /**
     * connect to LogEntries by opening a socket connection (TCP)
     */
    private function connect()
    {
        $this->createSocket();
    }

    /**
     * returns true if the incoming string is json
     * @param $string
     * @return bool
     */
    private function isJSON($string)
    {
        return is_string($string) && is_object(json_decode($string)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }


    /**
     * Logs with an arbitrary level.
     *
     * @param string $level
     * @param string $message a textual message or encoded JSON
     * @param array $context context regarding the message, if this is included it will
     *                       added to the message as json or included in the json payload
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        $this->connectIfNotConnected();

        if ($this->isJSON($message)) {
            $json = json_decode($message, true);
            if ("" != $this->_hostname) {
                $json["hostname"] = $this->_hostname;
            }
            $json["level"] = $level;
            if (count($context) > 0) {
                $json["context"] = $context;
            }
            $message = json_encode($json);
        } else {
            $message = strtoupper($level) . " - " . $message;
            if ("" != $this->_hostname) {
                $message = "hostname={$this->_hostname} - " . $message;
                if (count($context) > 0) {
                    $message .= " - " . json_encode($context);
                }
            }
        }
        $this->writeToSocket($this->substituteNewline($message) . PHP_EOL);
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function emergency($message, array $context = array())
    {
        return $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function alert($message, array $context = array())
    {
        return $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function critical($message, array $context = array())
    {
        return $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function error($message, array $context = array())
    {
        return $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function warning($message, array $context = array())
    {
        return $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public
    function notice($message, array $context = array())
    {
        return $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function info($message, array $context = array())
    {
        return $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function debug($message, array $context = array())
    {
        return $this->log(LogLevel::DEBUG, $message, $context);
    }

}