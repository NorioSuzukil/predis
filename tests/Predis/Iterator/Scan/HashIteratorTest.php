<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Iterator\Scan;

use \PHPUnit_Framework_TestCase as StandardTestCase;

use Predis\Client;
use Predis\Profile\ServerProfile;

/**
 * @group realm-iterators
 */
class HashIteratorTest extends StandardTestCase
{
    /**
     * @group disconnected
     * @expectedException Predis\NotSupportedException
     * @expectedExceptionMessage The specified server profile does not support the `HSCAN` command.
     */
    public function testThrowsExceptionOnInvalidServerProfile()
    {
        $client = $this->getMock('Predis\ClientInterface');

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.0')));

        $iterator = new HashIterator($client, 'key:hash');
    }

    /**
     * @group disconnected
     */
    public function testIterationOnSingleFetch()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->once())
               ->method('hscan')
               ->with('key:hash', 0, array())
               ->will($this->returnValue(array(0, array(
                    'field:1st' => 'value:1st', 'field:2nd' => 'value:2nd', 'field:3rd' => 'value:3rd',
               ))));

        $iterator = new HashIterator($client, 'key:hash');

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:3rd', $iterator->key());
        $this->assertSame('value:3rd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationOnMultipleFetches()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->at(1))
               ->method('hscan')
               ->with('key:hash', 0, array())
               ->will($this->returnValue(array(2, array(
                    'field:1st' => 'value:1st', 'field:2nd' => 'value:2nd',
               ))));
        $client->expects($this->at(2))
               ->method('hscan')
               ->with('key:hash', 2, array())
               ->will($this->returnValue(array(0, array(
                    'field:3rd' => 'value:3rd',
               ))));

        $iterator = new HashIterator($client, 'key:hash');

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:3rd', $iterator->key());
        $this->assertSame('value:3rd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationWithMultipleFetchesAndHoles()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->at(1))
               ->method('hscan')
               ->with('key:hash', 0, array())
               ->will($this->returnValue(array(2, array(
                    'field:1st' => 'value:1st', 'field:2nd' => 'value:2nd',
               ))));
        $client->expects($this->at(2))
               ->method('hscan')
               ->with('key:hash', 2, array())
               ->will($this->returnValue(array(5, array())));
        $client->expects($this->at(3))
               ->method('hscan')
               ->with('key:hash', 5, array())
               ->will($this->returnValue(array(0, array(
                    'field:3rd' => 'value:3rd',
               ))));

        $iterator = new HashIterator($client, 'key:hash');

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:3rd', $iterator->key());
        $this->assertSame('value:3rd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationWithOptionMatch()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->at(1))
               ->method('hscan')
               ->with('key:hash', 0, array('MATCH' => 'field:*'))
               ->will($this->returnValue(array(2, array(
                    'field:1st' => 'value:1st', 'field:2nd' => 'value:2nd',
               ))));

        $iterator = new HashIterator($client, 'key:hash', 'field:*');

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationWithOptionMatchOnMultipleFetches()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->at(1))
               ->method('hscan')
               ->with('key:hash', 0, array('MATCH' => 'field:*'))
               ->will($this->returnValue(array(1, array(
                    'field:1st' => 'value:1st',
                ))));
        $client->expects($this->at(2))
               ->method('hscan')
               ->with('key:hash', 1, array('MATCH' => 'field:*'))
               ->will($this->returnValue(array(0, array(
                    'field:2nd' => 'value:2nd',
                ))));

        $iterator = new HashIterator($client, 'key:hash', 'field:*');

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationWithOptionCount()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->at(1))
               ->method('hscan')
               ->with('key:hash', 0, array('COUNT' => 2))
               ->will($this->returnValue(array(0, array(
                    'field:1st' => 'value:1st', 'field:2nd' => 'value:2nd',
               ))));

        $iterator = new HashIterator($client, 'key:hash', null, 2);

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationWithOptionCountOnMultipleFetches()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->at(1))
               ->method('hscan')
               ->with('key:hash', 0, array('COUNT' => 1))
               ->will($this->returnValue(array(1, array(
                    'field:1st' => 'value:1st',
                ))));
        $client->expects($this->at(2))
               ->method('hscan')
               ->with('key:hash', 1, array('COUNT' => 1))
               ->will($this->returnValue(array(0, array(
                    'field:2nd' => 'value:2nd',
                ))));

        $iterator = new HashIterator($client, 'key:hash', null, 1);

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationWithOptionsMatchAndCount()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->at(1))
               ->method('hscan')
               ->with('key:hash', 0, array('MATCH' => 'field:*', 'COUNT' => 2))
               ->will($this->returnValue(array(0, array(
                    'field:1st' => 'value:1st', 'field:2nd' => 'value:2nd',
               ))));

        $iterator = new HashIterator($client, 'key:hash', 'field:*', 2);

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationWithOptionsMatchAndCountOnMultipleFetches()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->at(1))
               ->method('hscan')
               ->with('key:hash', 0, array('MATCH' => 'field:*', 'COUNT' => 1))
               ->will($this->returnValue(array(1, array(
                    'field:1st' => 'value:1st',
                ))));
        $client->expects($this->at(2))
               ->method('hscan')
               ->with('key:hash', 1, array('MATCH' => 'field:*', 'COUNT' => 1))
               ->will($this->returnValue(array(0, array(
                    'field:2nd' => 'value:2nd',
                ))));

        $iterator = new HashIterator($client, 'key:hash', 'field:*', 1);

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    /**
     * @group disconnected
     */
    public function testIterationRewindable()
    {
        $client = $this->getMock('Predis\Client', array('getProfile', 'hscan'));

        $client->expects($this->any())
               ->method('getProfile')
               ->will($this->returnValue(ServerProfile::get('2.8')));
        $client->expects($this->exactly(2))
               ->method('hscan')
               ->with('key:hash', 0, array())
               ->will($this->returnValue(array(0, array(
                    'field:1st' => 'value:1st', 'field:2nd' => 'value:2nd',
               ))));

        $iterator = new HashIterator($client, 'key:hash');

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->rewind();

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:1st', $iterator->key());
        $this->assertSame('value:1st', $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('field:2nd', $iterator->key());
        $this->assertSame('value:2nd', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }
}
