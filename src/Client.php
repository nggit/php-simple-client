<?php

# PHP Simple Client.
# https://github.com/nggit/php-simple-client
# Copyright (c) 2019 nggit.

namespace Nggit\PHPSimpleClient;

class Client
{
    public function __call($name, $args)
    {
        $client = 'Nggit\\PHPSimpleClient\\' . $name;
        if (!class_exists($client)) {
            throw new \Exception("Class $client not found!");
        }
        return new $client($args[0]['debug'], $args[0]['maxredirs'], $args[0]['timeout']);
    }

    // supported backends: curl, stream
    public static function create($backend = 'stream', $options = array())
    {
        $client  = new self();
        $backend = ucfirst(strtolower($backend));
        return $client->$backend($options + array('debug' => false, 'maxredirs' => -1, 'timeout' => 30));
    }
}
