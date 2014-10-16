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

use Predis\Network\IConnection;
use Predis\Network\IConnectionCluster;

/**
 * Defines a few helper methods.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class Helpers
{
    /**
     * Checks if the specified connection represents a cluster.
     *
     * @param IConnection $connection Connection object.
     * @return Boolean
     */
    public static function isCluster(IConnection $connection)
    {
        return $connection instanceof IConnectionCluster;
    }

    /**
     * Offers a generic and reusable method to handle exceptions generated by
     * a connection object.
     *
     * @param CommunicationException $exception Exception.
     */
    public static function onCommunicationException(CommunicationException $exception)
    {
        if ($exception->shouldResetConnection()) {
            $connection = $exception->getConnection();
            if ($connection->isConnected()) {
                $connection->disconnect();
            }
        }

        throw $exception;
    }

    /**
     * Normalizes the arguments array passed to a Redis command.
     *
     * @param array $arguments Arguments for a command.
     * @return array
     */
    public static function filterArrayArguments(Array $arguments)
    {
        if (count($arguments) === 1 && is_array($arguments[0])) {
            return $arguments[0];
        }

        return $arguments;
    }

    /**
     * Normalizes the arguments array passed to a variadic Redis command.
     *
     * @param array $arguments Arguments for a command.
     * @return array
     */
    public static function filterVariadicValues(Array $arguments)
    {
        if (count($arguments) === 2 && is_array($arguments[1])) {
            return array_merge(array($arguments[0]), $arguments[1]);
        }

        return $arguments;
    }

    /**
     * Returns only the hashable part of a key (delimited by "{...}"), or the
     * whole key if a key tag is not found in the string.
     *
     * @param string $key A key.
     * @return string
     */
    public static function extractKeyTag($key)
    {
        $start = strpos($key, '{');
        if ($start !== false) {
            $end = strpos($key, '}', $start);
            if ($end !== false) {
                $key = substr($key, ++$start, $end - $start);
            }
        }

        return $key;
    }
}
