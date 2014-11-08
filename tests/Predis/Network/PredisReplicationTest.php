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
 *
 */
class PredisReplicationTest extends StandardTestCase
{
    /**
     * @group disconnected
     */
    public function testAddingConnectionsToReplication()
    {
        $master = $this->getMockConnection('tcp://host1?alias=master');
        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave2 = $this->getMockConnection('tcp://host3?alias=slave2');

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);
        $replication->add($slave2);

        $this->assertSame($master, $replication->getConnectionById('master'));
        $this->assertSame($slave1, $replication->getConnectionById('slave1'));
        $this->assertSame($slave2, $replication->getConnectionById('slave2'));

        $this->assertSame($master, $replication->getMaster());
        $this->assertSame(array($slave1, $slave2), $replication->getSlaves());
    }

    /**
     * @group disconnected
     */
    public function testRemovingConnectionsFromReplication()
    {
        $master = $this->getMockConnection('tcp://host1?alias=master');
        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave2 = $this->getMockConnection('tcp://host3?alias=slave2');

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $this->assertTrue($replication->remove($slave1));
        $this->assertFalse($replication->remove($slave2));

        $this->assertSame($master, $replication->getMaster());
        $this->assertSame(array(), $replication->getSlaves());
    }

    /**
     * @group disconnected
     * @expectedException RuntimeException
     * @expectedExceptionMessage Replication needs a master and at least one slave
     */
    public function testThrowsExceptionOnEmptyReplication()
    {
        $replication = new PredisReplication();
        $replication->connect();
    }

    /**
     * @group disconnected
     * @expectedException RuntimeException
     * @expectedExceptionMessage Replication needs a master and at least one slave
     */
    public function testThrowsExceptionOnMissingMaster()
    {
        $replication = new PredisReplication();
        $replication->add($this->getMockConnection('tcp://host2?alias=slave1'));

        $replication->connect();
    }

    /**
     * @group disconnected
     * @expectedException RuntimeException
     * @expectedExceptionMessage Replication needs a master and at least one slave
     */
    public function testThrowsExceptionOnMissingSlave()
    {
        $replication = new PredisReplication();
        $replication->add($this->getMockConnection('tcp://host1?alias=master'));

        $replication->connect();
    }

    /**
     * @group disconnected
     */
    public function testConnectForcesConnectionToOneOfSlaves()
    {
        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->never())->method('connect');

        $slave = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave->expects($this->once())->method('connect');

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave);

        $replication->connect();
    }

    /**
     * @group disconnected
     */
    public function testIsConnectedReturnsTrueIfAtLeastOneConnectionIsOpen()
    {
        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->never())->method('isConnected')->will($this->returnValue(false));

        $slave = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave->expects($this->once())->method('isConnected')->will($this->returnValue(true));

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave);
        $replication->connect();

        $this->assertTrue($replication->isConnected());
    }

    /**
     * @group disconnected
     */
    public function testIsConnectedReturnsFalseIfAllConnectionsAreClosed()
    {
        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->any())->method('isConnected')->will($this->returnValue(false));

        $slave = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave->expects($this->any())->method('isConnected')->will($this->returnValue(false));

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave);

        $this->assertFalse($replication->isConnected());

        $replication->connect();
        $replication->disconnect();

        $this->assertFalse($replication->isConnected());
    }

    /**
     * @group disconnected
     */
    public function testDisconnectForcesCurrentConnectionToDisconnect()
    {
        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->once())->method('disconnect');

        $slave = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave->expects($this->once())->method('disconnect');

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave);

        $replication->disconnect();
    }

    /**
     * @group disconnected
     */
    public function testCanSwitchConnectionByAlias()
    {
        $master = $this->getMockConnection('tcp://host1?alias=master');
        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $this->assertNull($replication->getCurrent());

        $replication->switchTo('master');
        $this->assertSame($master, $replication->getCurrent());
        $replication->switchTo('slave1');
        $this->assertSame($slave1, $replication->getCurrent());
    }

    /**
     * @group disconnected
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The specified connection is not valid
     */
    public function testThrowsErrorWhenSwitchingToUnknownConnection()
    {
        $replication = new PredisReplication();
        $replication->add($this->getMockConnection('tcp://host1?alias=master'));
        $replication->add($this->getMockConnection('tcp://host2?alias=slave1'));

        $replication->switchTo('unknown');
    }

    /**
     * @group disconnected
     */
    public function testUsesSlavesOnReadOnlyCommands()
    {
        $profile = ServerProfile::getDefault();

        $master = $this->getMockConnection('tcp://host1?alias=master');
        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $cmd = $profile->createCommand('exists', array('foo'));
        $this->assertSame($slave1, $replication->getConnection($cmd));

        $cmd = $profile->createCommand('get', array('foo'));
        $this->assertSame($slave1, $replication->getConnection($cmd));
    }

    /**
     * @group disconnected
     */
    public function testUsesMasterOnWriteCommands()
    {
        $profile = ServerProfile::getDefault();

        $master = $this->getMockConnection('tcp://host1?alias=master');
        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $cmd = $profile->createCommand('set', array('foo', 'bar'));
        $this->assertSame($master, $replication->getConnection($cmd));

        $cmd = $profile->createCommand('get', array('foo'));
        $this->assertSame($master, $replication->getConnection($cmd));
    }

    /**
     * @group disconnected
     */
    public function testSwitchesFromSlaveToMasterOnWriteCommands()
    {
        $profile = ServerProfile::getDefault();

        $master = $this->getMockConnection('tcp://host1?alias=master');
        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $cmd = $profile->createCommand('exists', array('foo'));
        $this->assertSame($slave1, $replication->getConnection($cmd));

        $cmd = $profile->createCommand('set', array('foo', 'bar'));
        $this->assertSame($master, $replication->getConnection($cmd));

        $cmd = $profile->createCommand('exists', array('foo'));
        $this->assertSame($master, $replication->getConnection($cmd));
    }

    /**
     * @group disconnected
     */
    public function testWritesCommandToCorrectConnection()
    {
        $profile = ServerProfile::getDefault();
        $cmdExists = $profile->createCommand('exists', array('foo'));
        $cmdSet = $profile->getDefault()->createCommand('set', array('foo', 'bar'));

        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->once())->method('writeCommand')->with($cmdSet);

        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave1->expects($this->once())->method('writeCommand')->with($cmdExists);

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $replication->writeCommand($cmdExists);
        $replication->writeCommand($cmdSet);
    }

    /**
     * @group disconnected
     */
    public function testReadsCommandFromCorrectConnection()
    {
        $profile = ServerProfile::getDefault();
        $cmdExists = $profile->createCommand('exists', array('foo'));
        $cmdSet = $profile->getDefault()->createCommand('set', array('foo', 'bar'));

        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->once())->method('readResponse')->with($cmdSet);

        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave1->expects($this->once())->method('readResponse')->with($cmdExists);

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $replication->readResponse($cmdExists);
        $replication->readResponse($cmdSet);
    }

    /**
     * @group disconnected
     */
    public function testExecutesCommandOnCorrectConnection()
    {
        $profile = ServerProfile::getDefault();
        $cmdExists = $profile->createCommand('exists', array('foo'));
        $cmdSet = $profile->getDefault()->createCommand('set', array('foo', 'bar'));

        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->once())->method('executeCommand')->with($cmdSet);

        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave1->expects($this->once())->method('executeCommand')->with($cmdExists);

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $replication->executeCommand($cmdExists);
        $replication->executeCommand($cmdSet);
    }

    /**
     * @group disconnected
     * @expectedException Predis\NotSupportedException
     * @expectedExceptionMessage The command INFO is not allowed in replication mode
     */
    public function testThrowsExceptionOnNonSupportedCommand()
    {
        $cmd = ServerProfile::getDefault()->createCommand('info');

        $replication = new PredisReplication();
        $replication->add($this->getMockConnection('tcp://host1?alias=master'));
        $replication->add($this->getMockConnection('tcp://host2?alias=slave1'));

        $replication->getConnection($cmd);
    }

    /**
     * @group disconnected
     */
    public function testCanOverrideReadOnlyFlagForCommands()
    {
        $profile = ServerProfile::getDefault();
        $cmdSet = $profile->createCommand('set', array('foo', 'bar'));
        $cmdGet = $profile->createCommand('get', array('foo'));

        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->once())->method('executeCommand')->with($cmdGet);

        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave1->expects($this->once())->method('executeCommand')->with($cmdSet);

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $replication->setCommandReadOnly($cmdSet->getId(), true);
        $replication->setCommandReadOnly($cmdGet->getId(), false);

        $replication->executeCommand($cmdSet);
        $replication->executeCommand($cmdGet);
    }

    /**
     * @group disconnected
     */
    public function testAcceptsCallableToOverrideReadOnlyFlagForCommands()
    {
        $profile = ServerProfile::getDefault();
        $cmdExistsFoo = $profile->createCommand('exists', array('foo'));
        $cmdExistsBar = $profile->createCommand('exists', array('bar'));

        $master = $this->getMockConnection('tcp://host1?alias=master');
        $master->expects($this->once())->method('executeCommand')->with($cmdExistsBar);

        $slave1 = $this->getMockConnection('tcp://host2?alias=slave1');
        $slave1->expects($this->once())->method('executeCommand')->with($cmdExistsFoo);

        $replication = new PredisReplication();
        $replication->add($master);
        $replication->add($slave1);

        $replication->setCommandReadOnly('exists', function($cmd) {
            list($arg1) = $cmd->getArguments();
            return $arg1 === 'foo';
        });

        $replication->executeCommand($cmdExistsFoo);
        $replication->executeCommand($cmdExistsBar);
    }

    // ******************************************************************** //
    // ---- HELPER METHODS ------------------------------------------------ //
    // ******************************************************************** //

    /**
     * Returns a base mocked connection from Predis\Network\IConnectionSingle.
     *
     * @param mixed $parameters Optional parameters.
     * @return mixed
     */
    protected function getMockConnection($parameters = null)
    {
        $connection = $this->getMock('Predis\Network\IConnectionSingle');

        if ($parameters) {
            $parameters = new ConnectionParameters($parameters);
            $hash = "{$parameters->host}:{$parameters->port}";

            $connection->expects($this->any())
                       ->method('getParameters')
                       ->will($this->returnValue($parameters));
            $connection->expects($this->any())
                       ->method('__toString')
                       ->will($this->returnValue($hash));
        }

        return $connection;
    }
}
