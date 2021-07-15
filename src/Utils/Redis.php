<?php

namespace Servdebt\SlimCore\Utils;
use Servdebt\SlimCore\App;
use Predis\Client;
use Predis\ClientInterface;
use Traversable;

class Redis
{
    /** @var Client */
    public $client;


    /**
     * Redis constructor.
     *
     * @param ClientInterface $client A Predis Client.
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }


    public function get(string $key, $default = null)
    {
        $item = $this->uncompress($this->client->get($this->canonicalize($key)));

        if (!empty($item)) {
            return $item;
        } else {
            return $default;
        }
    }


    public function set(string $key, $value, $ttl = null)
    {
        $value = $this->compress($value);
        $key = $this->canonicalize($key);

        if ($ttl === null) {
            return $this->client->set($key, $value) == 'OK';
        }

        if ($ttl instanceof \DateInterval) {
            return $this->client->setex($key, $ttl->s, $value) == 'OK';
        }

        if (is_integer($ttl)) {
            return $this->client->setex($key, $ttl, $value) == 'OK';
        }

        throw new \Exception("TTL must be an integer or an instance of \\DateInterval");
    }


    public function delete(string $key)
    {
        return $this->client->del($this->canonicalize($key)) == 1;
    }


    public function clear()
    {
        $this->client->flushdb();

        return true; // FlushDB never fails.
    }


    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new \Exception("Keys must be an array or a \\Traversable instance.");
        }

        $result = array();
        foreach ($keys as $key) {
            $result[$key] = $this->uncompress($this->get($key, $default));
        }

        return $result;
    }


    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values) && !$values instanceof Traversable) {
            throw new \Exception("Values must be an array or a \\Traversable instance.");
        }

        try {
            $redis = $this;
            $responses = $this->client->transaction(function ($tx) use ($values, $ttl, $redis) {
                foreach ($values as $key => $value) {
                    if (!$redis->set($key, $this->compress($value), $ttl)) {
                        throw new \Exception();
                    }
                }});
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }


    public function deleteMultiple($keys)
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new \Exception("Keys must be an array or a \\Traversable instance.");
        }

        try {
            $redis = $this;
            $transaction = $this->client->transaction(function ($tx) use ($keys, $redis) {
                foreach ($keys as $key) {
                    if (!$redis->delete($key)) {
                        throw new \Exception();
                    }
                }});
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }


    public function has($key)
    {
        if (!is_string($key)) {
            throw new \Exception("Provided key is not a legal string.");
        }

        return $this->client->exists($this->canonicalize($key)) === 1;
    }

    /* Queues */

    public function enqueue($queue, $values)
    {
        if (!is_array($values)) $values = [$values];

        if (app()->env == App::DEVELOPMENT) {

            foreach ($values as $val) {
                // dispatch to processor
                $obj = new $val['class'];
                $obj->{$val['method']}((object)$val['payload']);
            }

            return count($values);
        }

        for ($i=0; $i<count($values); ++$i) {
            $values[$i] = $this->compress($values[$i]);
        }

        return $this->client->rpush($this->canonicalize($queue), $values);
    }


    public function dequeue($queue)
    {
        $var = $this->client->lpop($this->canonicalize($queue));

        return $this->uncompress($var[1]);
    }


    public function dequeueWait($queue, $timeout = 30)
    {
        $var = null;

        while (1) {
            $var = $this->client->blpop($this->canonicalize($queue), $timeout);

            if ($var != null) break;
            $this->client->ping();
        }

        return $this->uncompress($var[1]);
    }


    /* PUB SUB */

    /**
     * Publish a message to a channel.
     *
     * @param string $channel
     * @param mixed $message
     */
    public function publish($channel, $message)
    {
        $message = $this->compress($message);

        $this->client->publish($this->canonicalize($channel), $message);
    }


    /**
     * Subscribe a handler to a channel.
     *
     * @param string $channel
     * @param callable $handler
     */
    public function subscribe($channel, callable $handler)
    {
        $loop = $this->client->pubSubLoop();

        $loop->subscribe($channel);

        foreach ($loop as $message) {
            /** @var \stdClass $message */
            if ($message->kind === 'message') {
                call_user_func($handler, $this->uncompress($message->payload));
            }
        }

        unset($loop);
    }


    public function testRateLimit($key, $window, $limit)
    {
        $script = <<<'LUA'
local token = KEYS[1]
local now = tonumber(KEYS[2])
local window = tonumber(KEYS[3])
local limit = tonumber(KEYS[4])
local clearBefore = now - window
redis.call('ZREMRANGEBYSCORE', token, 0, clearBefore)
local amount = redis.call('ZCARD', token)

if amount < limit then
    redis.call('ZADD', token, now, now)
end
redis.call('EXPIRE', token, window)

return limit - amount
LUA;

        return $this->client->eval($script, 4, $key, microtime(true), $window, $limit);
    }


    /* Utils */


    /**
     * @param mixed $value
     * @return string
     */
    private function compress($value)
    {
        $value = serialize($value);

        return strlen($value) == 0 ? $value : gzcompress($value);
    }


    /**
     * @param mixed $value
     * @return mixed
     */
    private function uncompress($value)
    {
        $value = strlen($value) == 0 ? $value : gzuncompress($value);

        return unserialize($value);
    }


    /**
     * Canonicalizes a string.
     *
     * In practice, it replaces whitespaces for underscores, as PSR-16 defines we must allow
     * any valid PHP string, and Redis won't allow key names with whitespaces.
     *
     * @param string $string String to be canonicalized
     * @return string Canonical string
     */
    private function canonicalize(string $string)
    {
        return str_replace(' ', '_', $string);
    }

}