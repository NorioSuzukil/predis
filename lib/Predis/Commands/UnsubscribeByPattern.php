<?php

namespace Predis\Commands;

class UnsubscribeByPattern extends Command {
    public function canBeHashed()  { return false; }
    public function getCommandId() { return 'PUNSUBSCRIBE'; }
}
