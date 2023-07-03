<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Protocol\Text;

use PredisTestCase;

/**
 *
 */
class StreamableMultiBulkResponseTest extends PredisTestCase
{
    /**
     * @group disconnected
     */
    public function testOk()
    {
        $handler = new Handler\StreamableMultiBulkResponse();

        $connection = $this->getMockBuilder('Predis\Connection\CompositeConnectionInterface')->getMock();

        $connection->expects($this->never())->method('readLine');
        $connection->expects($this->never())->method('readBuffer');

        $this->assertInstanceOf('Predis\Response\Iterator\MultiBulk', $handler->handle($connection, '1'));
    }

    /**
     * @group disconnected
     */
    public function testInvalid()
    {
        $this->expectException('Predis\Protocol\ProtocolException');
        $this->expectExceptionMessage("Cannot parse 'invalid' as a valid length for a multi-bulk response");

        $handler = new Handler\StreamableMultiBulkResponse();

        $connection = $this->getMockBuilder('Predis\Connection\CompositeConnectionInterface')->getMock();

        $connection->expects($this->never())->method('readLine');
        $connection->expects($this->never())->method('readBuffer');

        $handler->handle($connection, 'invalid');
    }
}
