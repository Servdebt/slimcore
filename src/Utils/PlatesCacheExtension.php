<?php
namespace Servdebt\SlimCore\Utils;
use League\Plates\Extension\ExtensionInterface;

class PlatesCacheExtension implements ExtensionInterface {

    public string $cacheKey;
    public ?int $cacheKeyTtl;
    public ?Redis $redis = null;

    public function register(\League\Plates\Engine $engine): void
    {
        $engine->registerFunction('startCache', [$this, 'startCache']);
        $engine->registerFunction('endCache', [$this, 'endCache']);
    }

    public function startCache(string $cacheKey, ?int $cacheKeyTtl = null): bool
    {
        $this->cacheKey = $cacheKey;
        $this->cacheKeyTtl = $cacheKeyTtl;
        $this->redis = app()->has('redis') ? app()->resolve('redis') : null;

        $content = $this->redis?->get($this->cacheKey);
        if ($content != null) {
            echo $content;
            return false;
        }

        ob_start();
        return true;
    }

    public function endCache(): void
    {
        $content = ob_get_contents();
        $this->redis?->set($this->cacheKey, $content, $this->cacheKeyTtl);
        ob_end_clean();
        echo $content;
    }
}