<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Commands;

/**
 * @link http://redis.io/commands/dbsize
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class ServerDatabaseSize extends Command
{
    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return 'DBSIZE';
    }

    /**
     * {@inheritdoc}
     */
    protected function onPrefixKeys(Array $arguments, $prefix)
    {
        /* NOOP */
    }

    /**
     * {@inheritdoc}
     */
    protected function canBeHashed()
    {
        return false;
    }
}
