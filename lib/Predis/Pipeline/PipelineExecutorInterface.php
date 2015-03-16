<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Pipeline;

use Predis\Connection\ConnectionInterface;

/**
 * Defines a strategy to write a list of commands to the network
 * and read back their replies.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
interface PipelineExecutorInterface
{
    /**
     * Writes a list of commands to the network and reads back their replies.
     *
     * @param ConnectionInterface $connection Connection to Redis.
     * @param array $commands List of commands.
     * @return array
     */
    public function execute(ConnectionInterface $connection, Array &$commands);
}
