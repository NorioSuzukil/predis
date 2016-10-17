<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Command;

use PHPUnit_Framework_TestCase as StandardTestCase;

/**
 * @group commands
 * @group realm-key
 */
class KeyTimeToLiveTest extends CommandTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getExpectedCommand()
    {
        return 'Predis\Command\KeyTimeToLive';
    }

    /**
     * {@inheritdoc}
     */
    protected function getExpectedId()
    {
        return 'TTL';
    }

    /**
     * @group disconnected
     */
    public function testFilterArguments()
    {
        $arguments = array('key', 10);
        $expected = array('key', 10);

        $command = $this->getCommand();
        $command->setArguments($arguments);

        $this->assertSame($expected, $command->getArguments());
    }

    /**
     * @group disconnected
     */
    public function testParseResponse()
    {
        $command = $this->getCommand();

        $this->assertSame(100, $command->parseResponse(100));
    }

    /**
     * @group connected
     */
    public function testReturnsTTL()
    {
        $redis = $this->getClient();

        $redis->set('foo', 'bar');
        $redis->expire('foo', 10);

        $this->assertSame(10, $redis->ttl('foo'));
    }

    /**
     * @group connected
     */
    public function testReturnsLessThanZeroOnNonExpiringKeys()
    {
        $redis = $this->getClient();

        $redis->set('foo', 'bar');
        $this->assertSame(-1, $redis->ttl('foo'));
    }

    /**
     * @group connected
     * @todo TTL changed in Redis >= 2.8 to return -2 on non existing keys, we
     *       should handle this case with a better solution than the current one.
     */
    public function testReturnsLessThanZeroOnNonExistingKeys()
    {
        $redis = $this->getClient();

        $this->assertLessThanOrEqual(-1, $redis->ttl('foo'));
    }
}
