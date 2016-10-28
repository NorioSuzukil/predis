<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis;

use Predis\Connection\SingleConnectionInterface;

/**
 * Base exception class for network-related errors.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
abstract class CommunicationException extends PredisException
{
    private $connection;

    /**
     * @param SingleConnectionInterface $connection Connection that generated the exception.
     * @param string $message Error message.
     * @param int $code Error code.
     * @param \Exception $innerException Inner exception for wrapping the original error.
     */
    public function __construct(
        SingleConnectionInterface $connection,
        $message = null,
        $code = null,
        \Exception $innerException = null
    ) {
        parent::__construct($message, $code, $innerException);
        $this->connection = $connection;
    }

    /**
     * Gets the connection that generated the exception.
     *
     * @return SingleConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Indicates if the receiver should reset the underlying connection.
     *
     * @return Boolean
     */
    public function shouldResetConnection()
    {
        return true;
    }

    /**
     * Helper method to handle exceptions generated by a connection object.
     *
     * @param CommunicationException $exception Exception.
     */
    public static function handle(CommunicationException $exception)
    {
        if ($exception->shouldResetConnection()) {
            $connection = $exception->getConnection();

            if ($connection->isConnected()) {
                $connection->disconnect();
            }
        }

        throw $exception;
    }
}
