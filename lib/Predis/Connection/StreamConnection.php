<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Connection;

use Predis\Command\CommandInterface;
use Predis\Response;

/**
 * Standard connection to Redis servers implemented on top of PHP's streams.
 * The connection parameters supported by this class are:
 *
 *  - scheme: it can be either 'tcp' or 'unix'.
 *  - host: hostname or IP address of the server.
 *  - port: TCP port of the server.
 *  - timeout: timeout to perform the connection.
 *  - read_write_timeout: timeout of read / write operations.
 *  - async_connect: performs the connection asynchronously.
 *  - tcp_nodelay: enables or disables Nagle's algorithm for coalescing.
 *  - persistent: the connection is left intact after a GC collection.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class StreamConnection extends AbstractConnection
{
    /**
     * Disconnects from the server and destroys the underlying resource when the
     * garbage collector kicks in only if the connection has not been marked as
     * persistent.
     */
    public function __destruct()
    {
        if (isset($this->parameters->persistent) && $this->parameters->persistent) {
            return;
        }

        $this->disconnect();
    }

    /**
     * {@inheritdoc}
     */
    protected function createResource()
    {
        $initializer = "{$this->parameters->scheme}StreamInitializer";
        $resource = $this->$initializer($this->parameters);

        return $resource;
    }

    /**
     * Initializes a TCP stream resource.
     *
     * @param ConnectionParametersInterface $parameters Initialization parameters for the connection.
     * @return resource
     */
    private function tcpStreamInitializer(ConnectionParametersInterface $parameters)
    {
        $uri = "tcp://{$parameters->host}:{$parameters->port}/";
        $flags = STREAM_CLIENT_CONNECT;

        if (isset($parameters->async_connect) && $parameters->async_connect) {
            $flags |= STREAM_CLIENT_ASYNC_CONNECT;
        }
        if (isset($parameters->persistent) && $parameters->persistent) {
            $flags |= STREAM_CLIENT_PERSISTENT;
        }

        $resource = @stream_socket_client($uri, $errno, $errstr, $parameters->timeout, $flags);

        if (!$resource) {
            $this->onConnectionError(trim($errstr), $errno);
        }

        if (isset($parameters->read_write_timeout)) {
            $rwtimeout = $parameters->read_write_timeout;
            $rwtimeout = $rwtimeout > 0 ? $rwtimeout : -1;
            $timeoutSeconds  = floor($rwtimeout);
            $timeoutUSeconds = ($rwtimeout - $timeoutSeconds) * 1000000;
            stream_set_timeout($resource, $timeoutSeconds, $timeoutUSeconds);
        }

        if (isset($parameters->tcp_nodelay) && version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $socket = socket_import_stream($resource);
            socket_set_option($socket, SOL_TCP, TCP_NODELAY, (int) $parameters->tcp_nodelay);
        }

        return $resource;
    }

    /**
     * Initializes a UNIX stream resource.
     *
     * @param ConnectionParametersInterface $parameters Initialization parameters for the connection.
     * @return resource
     */
    private function unixStreamInitializer(ConnectionParametersInterface $parameters)
    {
        $uri = "unix://{$parameters->path}";
        $flags = STREAM_CLIENT_CONNECT;

        if ($parameters->persistent) {
            $flags |= STREAM_CLIENT_PERSISTENT;
        }

        $resource = @stream_socket_client($uri, $errno, $errstr, $parameters->timeout, $flags);

        if (!$resource) {
            $this->onConnectionError(trim($errstr), $errno);
        }

        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        parent::connect();

        if ($this->initCmds) {
            foreach ($this->initCmds as $command) {
                $this->executeCommand($command);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        if ($this->isConnected()) {
            fclose($this->getResource());
            parent::disconnect();
        }
    }

    /**
     * Performs a write operation over the stream of the buffer containing a
     * command serialized with the Redis wire protocol.
     *
     * @param string $buffer Representation of a command in the Redis wire protocol.
     */
    protected function write($buffer)
    {
        $socket = $this->getResource();

        while (($length = strlen($buffer)) > 0) {
            $written = fwrite($socket, $buffer);

            if ($length === $written) {
                return;
            }

            if ($written === false || $written === 0) {
                $this->onConnectionError('Error while writing bytes to the server');
            }

            $buffer = substr($buffer, $written);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        $socket = $this->getResource();
        $chunk = fgets($socket);

        if ($chunk === false || $chunk === '') {
            $this->onConnectionError('Error while reading line from the server');
        }

        $prefix = $chunk[0];
        $payload = substr($chunk, 1, -2);

        switch ($prefix) {
            case '+':    // inline
                return Response\Status::get($payload);

            case '$':    // bulk
                $size = (int) $payload;

                if ($size === -1) {
                    return null;
                }

                $bulkData = '';
                $bytesLeft = ($size += 2);

                do {
                    $chunk = fread($socket, min($bytesLeft, 4096));

                    if ($chunk === false || $chunk === '') {
                        $this->onConnectionError('Error while reading bytes from the server');
                    }

                    $bulkData .= $chunk;
                    $bytesLeft = $size - strlen($bulkData);
                } while ($bytesLeft > 0);

                return substr($bulkData, 0, -2);

            case '*':    // multi bulk
                $count = (int) $payload;

                if ($count === -1) {
                    return null;
                }

                $multibulk = array();

                for ($i = 0; $i < $count; $i++) {
                    $multibulk[$i] = $this->read();
                }

                return $multibulk;

            case ':':    // integer
                return (int) $payload;

            case '-':    // error
                return new Response\Error($payload);

            default:
                $this->onProtocolError("Unknown prefix: '$prefix'");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeRequest(CommandInterface $command)
    {
        $commandID = $command->getId();
        $arguments = $command->getArguments();

        $cmdlen = strlen($commandID);
        $reqlen = count($arguments) + 1;

        $buffer = "*{$reqlen}\r\n\${$cmdlen}\r\n{$commandID}\r\n";

        for ($i = 0; $i < $reqlen - 1; $i++) {
            $argument = $arguments[$i];
            $arglen = strlen($argument);
            $buffer .= "\${$arglen}\r\n{$argument}\r\n";
        }

        $this->write($buffer);
    }
}
