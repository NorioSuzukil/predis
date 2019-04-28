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
class ErrorResponseTest extends PredisTestCase
{
    /**
     * @group disconnected
     */
    public function testOk()
    {
        $message = 'ERR Operation against a key holding the wrong kind of value';

        $connection = $this->getMock('Predis\Connection\CompositeConnectionInterface');
        $connection
            ->expects($this->never())
            ->method('readLine');
        $connection
            ->expects($this->never())
            ->method('readBuffer');

        $handler = new Handler\ErrorResponse();
        $response = $handler->handle($connection, $message);

        $this->assertInstanceOf('Predis\Response\Error', $response);
        $this->assertSame($message, $response->getMessage());
    }
}
