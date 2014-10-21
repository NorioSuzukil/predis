<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Network;

use \PHPUnit_Framework_TestCase as StandardTestCase;

use Predis\ConnectionParameters;
use Predis\Profiles\ServerProfile;

/**
 * @group ext-phpiredis
 */
class PhpiredisConnectionTest extends ConnectionTestCase
{
    /**
     * @group disconnected
     */
    public function testConstructorDoesNotOpenConnection()
    {
        $connection = new PhpiredisConnection($this->getParameters());

        $this->assertFalse($connection->isConnected());
    }

    /**
     * @group disconnected
     */
    public function testExposesConnectionParameters()
    {
        $parameters = $this->getParameters();
        $connection = new PhpiredisConnection($parameters);

        $this->assertSame($parameters, $connection->getParameters());
    }

    /**
     * @group disconnected
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid scheme: udp
     */
    public function testThrowsExceptionOnInvalidScheme()
    {
        $parameters = $this->getParameters(array('scheme' => 'udp'));
        $connection = new PhpiredisConnection($parameters);
    }

    // ******************************************************************** //
    // ---- INTEGRATION TESTS --------------------------------------------- //
    // ******************************************************************** //

    /**
     * @group connected
     */
    public function testExecutesCommandsOnServer()
    {
        $connection = $this->getConnection($profile, true);

        $cmdPing   = $profile->createCommand('ping');
        $cmdEcho   = $profile->createCommand('echo', array('echoed'));
        $cmdGet    = $profile->createCommand('get', array('foobar'));
        $cmdRpush  = $profile->createCommand('rpush', array('metavars', 'foo', 'hoge', 'lol'));
        $cmdLrange = $profile->createCommand('lrange', array('metavars', 0, -1));

        $this->assertTrue($connection->executeCommand($cmdPing));
        $this->assertSame('echoed', $connection->executeCommand($cmdEcho));
        $this->assertNull($connection->executeCommand($cmdGet));
        $this->assertSame(3, $connection->executeCommand($cmdRpush));
        $this->assertSame(array('foo', 'hoge', 'lol'), $connection->executeCommand($cmdLrange));
    }

    /**
     * @group connected
     * @expectedException Predis\Protocol\ProtocolException
     * @expectedExceptionMessage Protocol error, got "P" as reply type byte
     */
    public function testThrowsExceptionOnProtocolDesynchronizationErrors()
    {
        $connection = $this->getConnection($profile);
        $socket = $connection->getResource();

        $connection->writeCommand($profile->createCommand('ping'));
        socket_read($socket, 1);

        $connection->read();
    }

    // ******************************************************************** //
    // ---- HELPER METHODS ------------------------------------------------ //
    // ******************************************************************** //

    /**
     * {@inheritdoc}
     */
    protected function getConnection(&$profile = null, $initialize = false, Array $parameters = array())
    {
        $parameters = $this->getParameters($parameters);
        $profile = $this->getProfile();

        $connection = new PhpiredisConnection($parameters);

        if ($initialize) {
            $connection->pushInitCommand($profile->createCommand('select', array($parameters->database)));
            $connection->pushInitCommand($profile->createCommand('flushdb'));
        }

        return $connection;
    }
}
