<?php

namespace Servdebt\SlimCore\Utils;
use Predis\Client;
use Predis\ClientInterface;
use Traversable;

class Redis
{
    public Client $client;

    public int $redisReads = 0;
    public int $redisWrites = 0;


    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }


    public function get(string $key, mixed $default = null, bool $uncompressData = true): mixed
    {
        $item = $this->client->get($this->canonicalize($key));
        $item = $uncompressData ? $this->uncompress($item) : $item;
        $this->redisReads++;

        if (!empty($item)) {
            return $item;
        } else {
            return $default;
        }
    }


    public function set(string $key, mixed $value, ?int $ttl = null, bool $compressData = true): bool
    {
        $value = $compressData ? $this->compress($value) : $value;
        $key = $this->canonicalize($key);
        $this->redisWrites++;

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


    public function delete(string $key): bool
    {
        $this->redisWrites++;

        return $this->client->del($this->canonicalize($key)) == 1;
    }


    public function clear(): bool
    {
        $this->client->flushdb();

        return true; // FlushDB never fails.
    }


    public function getMultiple(Traversable|array $keys, mixed $default = null, bool $uncompressData = true): array
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new \Exception("Keys must be an array or a \\Traversable instance.");
        }

        $result = [];
        foreach ($keys as $key) {
            $val = $this->get($key, $default);
            $this->redisReads++;
            $result[$key] = $uncompressData ? $this->uncompress($val) : $val;
        }

        return $result;
    }


    public function setMultiple(Traversable|array $values, $ttl = null, bool $compressData = true): bool
    {
        if (!is_array($values) && !$values instanceof Traversable) {
            throw new \Exception("Values must be an array or a \\Traversable instance.");
        }

        try {
            $redis = $this;
            $this->client->transaction(function ($tx) use ($values, $ttl, $redis, $compressData) {
                foreach ($values as $key => $value) {
                    $val = $compressData ? $this->compress($value) : $value;
                    if (!$redis->set($key, $val, $ttl)) {
                        throw new \Exception();
                    }
                    $this->redisWrites++;
                }});

        } catch (\Exception $e) {
            return false;
        }

        return true;
    }


    public function deleteMultiple(Traversable|array $keys): bool
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new \Exception("Keys must be an array or a \\Traversable instance.");
        }

        try {
            $redis = $this;
            $this->client->transaction(function ($tx) use ($keys, $redis) {
                foreach ($keys as $key) {
                    if (!$redis->delete($key)) {
                        throw new \Exception();
                    }
                    $this->redisWrites++;
                }});
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }


    public function has(string $key): bool
    {
        if (!is_string($key)) {
            throw new \Exception("Provided key is not a legal string.");
        }
        $this->redisReads++;

        return $this->client->exists($this->canonicalize($key)) === 1;
    }

    /* Queues */

    public function enqueue(string $queue, $values, bool $compressData = true): int
    {
        if (!is_array($values)) $values = [$values];

        for ($i=0; $i<count($values); ++$i) {
            $values[$i] = $compressData ? $this->compress($values[$i]) : $values[$i];
        }

        $this->redisWrites++;

        return $this->client->rpush($this->canonicalize($queue), $values);
    }


    public function dequeue(string $queue, bool $uncompressData = true): mixed
    {
        $var = $this->client->lpop($this->canonicalize($queue));
        $this->redisReads++;

        return $uncompressData ? $this->uncompress($var[1]) : $var[1];
    }


    public function dequeueWait($queue, $timeout = 30, bool $uncompressData = true): mixed
    {
        do {
            $var = $this->client->blpop($this->canonicalize($queue), $timeout);
            if (empty($var)) $this->client->ping();
            $this->redisReads++;

        } while ($var != null);

        return $uncompressData ? $this->uncompress($var[1]) : $var[1];
    }


    /* PUB SUB */

    /**
     * Publish a message to a channel.
     *
     * @param string $channel
     * @param mixed $message
     */
    public function publish(mixed $channel, mixed $message): int
    {
        $message = $this->compress($message);

        return $this->client->publish($this->canonicalize($channel), $message);
    }


    /**
     * Subscribe a handler to a channel.
     *
     * @param string $channel
     * @param callable $handler
     */
    public function subscribe(mixed $channel, callable $handler): void
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


    public function testRateLimit(string $key, int $window, int $limit): mixed
    {
        $this->redisReads++;
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


    private function compress(mixed $value): string|false
    {
        return serialize($value);
    }


    private function uncompress($value): mixed
    {
        return unserialize((string)$value);
    }


    private function canonicalize(string $string): string
    {
        return str_replace(' ', '_', $string);
    }

}