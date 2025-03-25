<?php

namespace Servdebt\SlimCore\Filesystem;
use League\Flysystem\PathNormalizer;
use League\Flysystem\FilesystemAdapter;

class Filesystem extends \League\Flysystem\Filesystem
{
    protected $adapter;
    
    public function __construct(
        FilesystemAdapter $adapter,
        array $config = [],
        PathNormalizer $pathNormalizer = null
    ){
        parent::__construct($adapter, $config, $pathNormalizer);
        
        $this->adapter = $adapter;
    }
    
    public function bulkDelete(array $files): bool
    {
        
        if(!method_exists($this->adapter, 'bulkDelete')){
            throw new \Exception('Adapter does not implement bulk delete');
        }
        
        return $this->adapter->bulkDelete($files);      
    }
    
}
