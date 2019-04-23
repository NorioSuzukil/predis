<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Command\Redis;

use Predis\Command\Command;

/**
 * @link http://redis.io/commands/geopos
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class GeospatialGeoPos extends Command
{
    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return 'GEOPOS';
    }

    /**
     * {@inheritdoc}
     */
    public function setArguments(array $arguments)
    {
        if (count($arguments) === 2 && is_array($arguments[1])) {
            $members = array_pop($arguments);
            $arguments = array_merge($arguments, $members);
        }

        parent::setArguments($arguments);
    }
}
