<?php

namespace Predis\Commands\Processors;

use Predis\ClientException;
use Predis\Commands\ICommand;
use Predis\Profiles\IServerProfile;

class KeyPrefixProcessor implements ICommandProcessor {
    private $_prefix;
    private $_strategies;

    public function __construct($prefix, IServerProfile $profile = null) {
        if (isset($profile)) {
            $this->checkProfile($profile);
        }
        $this->_prefix = $prefix;
        $this->_strategies = $this->getPrefixStrategies();
    }

    protected function checkProfile(IServerProfile $profile) {
        if (!in_array($profile, $this->getSupportedProfiles())) {
            throw new ClientException("Unsupported profile: $profile");
        }
    }

    protected function getSupportedProfiles() {
        return array('1.2', '2.0', '2.2');
    }

    protected function getPrefixStrategies() {
        $skipLast = function(&$arguments, $prefix) {
            $length = count($arguments);
            for ($i = 0; $i < $length - 1; $i++) {
                $arguments[$i] = "$prefix{$arguments[$i]}";
            }
        };

        $interleavedKeys = function(&$arguments, $prefix) {
            $length = count($arguments);
            for ($i = 0; $i < $length; $i += 2) {
                $arguments[$i] = "$prefix{$arguments[$i]}";
            }
        };

        $zunionstore = function(&$arguments, $prefix) {
            $arguments[0] = "$prefix{$arguments[0]}";
            $length = ((int) $arguments[1]) + 2;
            for ($i = 2; $i < $length; $i++) {
                $arguments[$i] = "$prefix{$arguments[$i]}";
            }
        };

        $sort = function(&$arguments, $prefix) {
            $arguments[0] = "$prefix{$arguments[0]}";
            if (count($arguments) === 1) {
                return;
            }
            foreach ($arguments[1] as $modifier => &$value) {
                switch (strtoupper($modifier)) {
                    case 'BY':
                    case 'STORE':
                        $value = "$prefix$value";
                        break;
                    case 'GET':
                        if (is_array($value)) {
                            foreach ($value as &$getItem) {
                                $getItem = "$prefix$getItem";
                            }
                        }
                        else {
                            $value = "$prefix$value";
                        }
                        break;
                }
            }
        };

        $debug = function(&$arguments, $prefix) {
            if (count($arguments) === 3 && strtoupper($arguments[1]) == 'OBJECT') {
                $arguments[2] = "$prefix{$arguments[2]}";
            }
        };

        $cmdSingleKey = array(
            'type', 'exists', 'move', 'expire', 'persist', 'sort', 'expireat', 'ttl', 'append',
            'getrange', 'setnx', 'decr', 'getset', 'setrange', 'decrby', 'incr', 'set', 'strlen',
            'get', 'incrby', 'setbit', 'getbit', 'setex', 'hdel', 'hgetall', 'hlen', 'hset',
            'hexists', 'hincrby', 'hmget', 'hsetnx', 'hget', 'hkeys', 'hmset', 'hvals', 'lindex', 
            'linsert', 'llen', 'lpop', 'lpush', 'lpushx', 'rpushx', 'lrange', 'lrem', 'lset',
            'ltrim', 'rpop', 'rpush', 'rpushx', 'sadd', 'scard', 'sismember', 'smembers', 'spop', 
            'srandmember', 'srem', 'zadd', 'zcard', 'zcount', 'zincrby', 'zrange', 'zrangebyscore', 
            'zrank', 'zrem', 'zremrangebyrank', 'zremrangebyscore', 'zrevrange', 'zrevrangebyscore', 
            'zrevrank', 'zscore', 'publish', 'keys',
        );
        $cmdMultiKeys = array(
            'del', 'rename', 'renamenx', 'mget', 'rpoplpush', 'sdiff', 'sdiffstore', 'sinter', 
            'sinterstore', 'sunion', 'sunionstore', 'subscribe', 'punsubscribe', 'subscribe', 
            'unsubscribe', 'watch', 
        );

        return array_merge(
            array_fill_keys($cmdSingleKey, $this->getSingleKeyStrategy()),
            array_fill_keys($cmdMultiKeys, $this->getMultipleKeysStrategy()),
            array(
                'blpop' => $skipLast, 'brpop' => $skipLast, 'brpoplpush' => $skipLast, 'smove' => $skipLast,
                'mset' => $interleavedKeys, 'msetnx' => $interleavedKeys,
                'zinterstore' => $zunionstore, 'zunionstore' => $zunionstore,
                'sort' => $sort, 'debug' => $debug
            )
        );
    }

    public function setPrefixStrategy($command, $strategy) {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException(
                'The command preprocessor strategy must be a callable object'
            );
        }
        $this->_strategies[$command] = $strategy;
    }

    public function getSingleKeyStrategy() {
        return function(&$arguments, $prefix) {
            $arguments[0] = "$prefix{$arguments[0]}";
        };
    }

    public function getMultipleKeysStrategy() {
        return function(&$arguments, $prefix) {
            foreach ($arguments as &$key) {
                $key = "$prefix$key";
            }
        };
    }

    public function getPrefixStrategy($command) {
        if (isset($this->_strategies[$command])) {
            return $this->_strategies[$command];
        }
    }

    public function setPrefix($prefix) {
        $this->_prefix = $prefix;
    }

    public function getPrefix() {
        return $this->_prefix;
    }

    public function process(ICommand $command) {
        $method = strtolower($command->getId());
        if (isset($this->_strategies[$method])) {
            $arguments = $command->getArguments();
            $this->_strategies[$method]($arguments, $this->_prefix);
            $command->setArguments($arguments);
        }
    }
}
