<?php

namespace Predis\Commands;

class Subscribe extends Command {
    public function canBeHashed()  { return false; }
    public function getCommandId() { return 'SUBSCRIBE'; }
}
