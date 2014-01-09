<?php

namespace Predis\Commands;

use Predis\Command;

class ZSetAdd extends Command {
    public function getCommandId() { return 'ZADD'; }
    public function parseResponse($data) { return (bool) $data; }
}
