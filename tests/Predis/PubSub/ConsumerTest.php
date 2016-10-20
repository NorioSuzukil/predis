<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\PubSub;

use PredisTestCase;
use Predis\Client;
use Predis\Profile;
use Predis\PubSub\Consumer as PubSubConsumer;

/**
 * @group realm-pubsub
 */
class ConsumerTest extends PredisTestCase
{
    /**
     * @group disconnected
     * @expectedException Predis\NotSupportedException
     * @expectedExceptionMessage The current profile does not support PUB/SUB related commands
     */
    public function testPubSubConsumerRequirePubSubRelatedCommand()
    {
        $profile = $this->getMock('Predis\Profile\ProfileInterface');
        $profile->expects($this->any())
                ->method('supportsCommands')
                ->will($this->returnValue(false));

        $client = new Client(null, array('profile' => $profile));
        $pubsub = new PubSubConsumer($client);
    }

    /**
     * @group disconnected
     * @expectedException Predis\NotSupportedException
     * @expectedExceptionMessage Cannot initialize a PUB/SUB consumer when using aggregated connections
     */
    public function testPubSubConsumerDoesNotWorkOnClusters()
    {
        $cluster = $this->getMock('Predis\Connection\ClusterConnectionInterface');

        $client = new Client($cluster);
        $pubsub = new PubSubConsumer($client);
    }

    /**
     * @group disconnected
     */
    public function testConstructorWithoutSubscriptionsDoesNotStartConsumer()
    {
        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');

        $client = $this->getMock('Predis\Client', array('executeCommand'), array($connection));
        $client->expects($this->never())->method('executeCommand');

        $pubsub = new PubSubConsumer($client);
    }

    /**
     * @group disconnected
     */
    public function testConstructorWithSubscriptionsStartsConsumer()
    {
        $profile = Profile\Factory::get(REDIS_SERVER_VERSION);

        $cmdSubscribe = $profile->createCommand('subscribe', array('channel:foo'));
        $cmdPsubscribe = $profile->createCommand('psubscribe', array('channels:*'));

        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');
        $connection->expects($this->exactly(2))->method('writeRequest');

        $client = $this->getMock('Predis\Client', array('createCommand', 'writeRequest'), array($connection));
        $client->expects($this->exactly(2))
               ->method('createCommand')
               ->with($this->logicalOr($this->equalTo('subscribe'), $this->equalTo('psubscribe')))
               ->will($this->returnCallback(function ($id, $args) use ($profile) {
                   return $profile->createCommand($id, $args);
               }));

        $options = array('subscribe' => 'channel:foo', 'psubscribe' => 'channels:*');
        $pubsub = new PubSubConsumer($client, $options);
    }

    /**
     * @group disconnected
     */
    public function testStoppingConsumerWithTrueClosesConnection()
    {
        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');

        $client = $this->getMock('Predis\Client', array('disconnect'), array($connection));
        $client->expects($this->exactly(1))->method('disconnect');

        $pubsub = new PubSubConsumer($client, array('subscribe' => 'channel:foo'));

        $connection->expects($this->never())->method('writeRequest');

        $pubsub->stop(true);
    }

    /**
     * @group disconnected
     */
    public function testStoppingConsumerWithFalseSendsUnsubscriptions()
    {
        $profile = Profile\Factory::get(REDIS_SERVER_VERSION);
        $classUnsubscribe = $profile->getCommandClass('unsubscribe');
        $classPunsubscribe = $profile->getCommandClass('punsubscribe');

        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');

        $client = $this->getMock('Predis\Client', array('disconnect'), array($connection));

        $options = array('subscribe' => 'channel:foo', 'psubscribe' => 'channels:*');
        $pubsub = new PubSubConsumer($client, $options);

        $connection->expects($this->exactly(2))
                   ->method('writeRequest')
                   ->with($this->logicalOr(
                       $this->isInstanceOf($classUnsubscribe),
                       $this->isInstanceOf($classPunsubscribe)
                   ));

        $pubsub->stop(false);
    }

    /**
     * @group disconnected
     */
    public function testIsNotValidWhenNotSubscribed()
    {
        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');
        $client = $this->getMock('Predis\Client', array('disconnect'), array($connection));

        $pubsub = new PubSubConsumer($client);

        $this->assertFalse($pubsub->valid());
        $this->assertNull($pubsub->next());
    }

    /**
     * @group disconnected
     */
    public function testReadsMessageFromConnection()
    {
        $rawmessage = array('message', 'channel:foo', 'message from channel');

        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');
        $connection->expects($this->once())->method('read')->will($this->returnValue($rawmessage));

        $client = new Client($connection);
        $pubsub = new PubSubConsumer($client, array('subscribe' => 'channel:foo'));

        $message = $pubsub->current();
        $this->assertSame('message', $message->kind);
        $this->assertSame('channel:foo', $message->channel);
        $this->assertSame('message from channel', $message->payload);
    }

    /**
     * @group disconnected
     */
    public function testReadsPmessageFromConnection()
    {
        $rawmessage = array('pmessage', 'channel:*', 'channel:foo', 'message from channel');

        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');
        $connection->expects($this->once())->method('read')->will($this->returnValue($rawmessage));

        $client = new Client($connection);
        $pubsub = new PubSubConsumer($client, array('psubscribe' => 'channel:*'));

        $message = $pubsub->current();
        $this->assertSame('pmessage', $message->kind);
        $this->assertSame('channel:*', $message->pattern);
        $this->assertSame('channel:foo', $message->channel);
        $this->assertSame('message from channel', $message->payload);
    }

    /**
     * @group disconnected
     */
    public function testReadsSubscriptionMessageFromConnection()
    {
        $rawmessage = array('subscribe', 'channel:foo', 1);

        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');
        $connection->expects($this->once())->method('read')->will($this->returnValue($rawmessage));

        $client = new Client($connection);
        $pubsub = new PubSubConsumer($client, array('subscribe' => 'channel:foo'));

        $message = $pubsub->current();
        $this->assertSame('subscribe', $message->kind);
        $this->assertSame('channel:foo', $message->channel);
        $this->assertSame(1, $message->payload);
    }

    /**
     * @group disconnected
     */
    public function testReadsUnsubscriptionMessageFromConnection()
    {
        $rawmessage = array('unsubscribe', 'channel:foo', 1);

        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');
        $connection->expects($this->once())->method('read')->will($this->returnValue($rawmessage));

        $client = new Client($connection);
        $pubsub = new PubSubConsumer($client, array('subscribe' => 'channel:foo'));

        $message = $pubsub->current();
        $this->assertSame('unsubscribe', $message->kind);
        $this->assertSame('channel:foo', $message->channel);
        $this->assertSame(1, $message->payload);
    }

    /**
     * @group disconnected
     */
    public function testUnsubscriptionMessageWithZeroChannelCountInvalidatesConsumer()
    {
        $rawmessage = array('unsubscribe', 'channel:foo', 0);

        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');
        $connection->expects($this->once())->method('read')->will($this->returnValue($rawmessage));

        $client = new Client($connection);
        $pubsub = new PubSubConsumer($client, array('subscribe' => 'channel:foo'));

        $this->assertTrue($pubsub->valid());

        $message = $pubsub->current();
        $this->assertSame('unsubscribe', $message->kind);
        $this->assertSame('channel:foo', $message->channel);
        $this->assertSame(0, $message->payload);

        $this->assertFalse($pubsub->valid());
    }

    /**
     * @group disconnected
     */
    public function testGetUnderlyingClientInstance()
    {
        $connection = $this->getMock('Predis\Connection\SingleConnectionInterface');

        $client = new Client($connection);
        $pubsub = new PubSubConsumer($client);

        $this->assertSame($client, $pubsub->getClient());
    }

    // ******************************************************************** //
    // ---- INTEGRATION TESTS --------------------------------------------- //
    // ******************************************************************** //

    /**
     * @group connected
     */
    public function testPubSubAgainstRedisServer()
    {
        $parameters = array(
            'host' => REDIS_SERVER_HOST,
            'port' => REDIS_SERVER_PORT,
            'database' => REDIS_SERVER_DBNUM,
            // Prevents suite from handing on broken test
            'read_write_timeout' => 2,
        );

        $options = array('profile' => REDIS_SERVER_VERSION);
        $messages = array();

        $producer = new Client($parameters, $options);
        $producer->connect();

        $consumer = new Client($parameters, $options);
        $consumer->connect();

        $pubsub = new PubSubConsumer($consumer);
        $pubsub->subscribe('channel:foo');

        $producer->publish('channel:foo', 'message1');
        $producer->publish('channel:foo', 'message2');
        $producer->publish('channel:foo', 'QUIT');

        foreach ($pubsub as $message) {
            if ($message->kind !== 'message') {
                continue;
            }
            $messages[] = ($payload = $message->payload);
            if ($payload === 'QUIT') {
                $pubsub->stop();
            }
        }

        $this->assertSame(array('message1', 'message2', 'QUIT'), $messages);
        $this->assertFalse($pubsub->valid());
        $this->assertTrue($consumer->ping());
    }
}
