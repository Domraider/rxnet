<?php
namespace Rxnet\Data\Loaders;

use League\JsonGuard\Loader;

class LocalLoader implements Loader
{

    /**
     * Load the json schema from the given path.
     *
     * @param string $path The path to load, without the protocol.
     *
     * @return object The object resulting from a json_decode of the loaded path.
     * @throws \League\JsonGuard\Exceptions\SchemaLoadingException
     */
    public function load($path)
    {
        $file = base_path("schemas/{$path}");
        $json = file_get_contents($file);
        $data = json_decode($json);
        return $data;
    }
}