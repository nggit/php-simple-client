<?php

# PHP Simple Client.
# https://github.com/nggit/php-simple-client
# Copyright (c) 2019 nggit.

namespace Nggit\PHPSimpleClient;

class Client
{
    protected $options = array('debug' => false, 'maxredirs' => -1, 'timeout' => 30);

    public function __construct($options = array())
    {
        $this->options = array_merge($this->options, $options);
    }

    public function __call($name, $args)
    {
        $client = 'Nggit\\PHPSimpleClient\\' . ucfirst(strtolower($name));
        if (!class_exists($client)) {
            throw new \Exception("$name backend is missing or not supported");
        }
        $this->options = array_combine(array_keys($this->options), array_slice($args, 0, count($this->options)) + array_values($this->options));
        return new $client($this->options['debug'], $this->options['maxredirs'], $this->options['timeout']);
    }

    // supported backends: curl, stream
    public static function create($backend = 'stream', $options = array())
    {
        $client = new self($options);
        return $client->$backend();
    }
}
