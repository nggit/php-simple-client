<?php

# PHP Simple Client (PHP Stream).
# https://github.com/nggit/php-simple-client
# Copyright (c) 2019 nggit.

namespace Nggit\PHPSimpleClient;

class Stream
{
    protected $debug;
    protected $maxredirs;
    protected $timeout;
    protected $url;
    protected $host;
    protected $netloc;
    protected $path;
    protected $handle;
    protected $socket;
    protected $options   = array(
                               'ssl' => array(
                                            'verify_peer'      => false,
                                            'verify_peer_name' => false
                                        )
                           );
    protected $request   = array(
                               'cookie'  => array(),
                               'headers' => array(
                                                'Connection' => 'Connection: close'
                                            ),
                               'options' => array(
                                                'headers' => array()
                                            )
                           );
    protected $response  = array(
                               'status' => array()
                           );

    public function __construct($debug = false, $maxredirs = -1, $timeout = 30)
    {
        $this->debug     = $debug;
        $this->maxredirs = $maxredirs;
        $this->timeout   = $timeout;
    }

    protected function open()
    {
        if (!($this->handle = stream_socket_client($this->socket, $errno, $errstr, $this->timeout,
                                                   STREAM_CLIENT_CONNECT, stream_context_create($this->options)))) {
            throw new \Exception("$errstr ($errno)");
        }
    }

    protected function close() {
        fclose($this->handle);
    }

    public function setHeaders($headers = array())
    {
        foreach ($headers as $header) {
            $this->request['headers'][str_replace(' ', '-', ucwords(str_replace('-', ' ', substr($header, 0, strpos($header, ':')))))] = $header;
        }
        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        if (!strpos($url, '://') || !($url = parse_url($url))) {
            throw new \Exception('Invalid URL or not an absolute URL');
        }
        $this->host = $url['host'];
        if (stripos($this->url, 'https://') === 0) {
            $transport = 'ssl';
            $port      = 443;
        } else {
            $transport = 'tcp';
            $port      = 80;
        }
        if (isset($url['port'])) {
            $port         = $url['port'];
            $this->netloc = $this->host . ':' . $port;
        } else {
            $this->netloc = $this->host;
        }
        $this->path   = isset($url['path']) ? substr($this->url, strpos($this->url, $this->netloc) + strlen($this->netloc)) : '/';
        $this->socket = $transport . '://' . $url['host'] . ':' . $port;
        return $this;
    }

    public function setMaxRedirs($maxredirs)
    {
        $this->maxredirs = $maxredirs;
        return $this;
    }

    public function setTimeOut($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function parseCookie($cookie)
    {
        parse_str(strtr($cookie, array('&' => '%26', '+' => '%2B', ';' => '&')), $cookies);
        return $cookies;
    }

    protected function parseResponse()
    {
        $this->open();
        fwrite($this->handle, $this->request['options']['message']);
        $this->request['options']['headers'] = array(); // destroy the previous request options
        $next                                = count($this->response);
        $this->response[$next]               = array('headers' => array(), 'header' => '', 'body' => '');
        $cookies                             = array();
        while ($line = fgets($this->handle)) {
            if (rtrim($line) == '') {
                break;
            }
            if (($colon_pos = strpos($line, ':')) === false) {
                $this->response[$next]['headers'][0] = rtrim($line);
            } else {
                $name  = str_replace(' ', '-', ucwords(str_replace('-', ' ', substr($line, 0, $colon_pos))));
                $value = trim(substr($line, $colon_pos), ": \r\n");
                if ($name == 'Set-Cookie') {
                    $cookies[] = $value;
                }
                $this->response[$next]['headers'][$name] = $value;
            }
            $this->response[$next]['header'] .= $line;
        }
        $this->response[$next]['body'] = stream_get_contents($this->handle);
        if ($cookies) {
            $cookie = $this->parseCookie(implode('; ', $cookies));
            $domain = $this->host;
            foreach ($cookie as $name => $value) {
                if (strtolower($name) == 'domain') {
                    $domain = $value;
                }
            }
            if (isset($this->request['cookie'][$domain])) {
                $cookie += $this->request['cookie'][$domain];
            }
            $this->request['cookie'][$domain] = $cookie;
        }
        $this->close();
        return $this->response[$next];
    }

    public function getResponse($var = null)
    {
        return is_null($var) ? $this->response
                             : (isset($this->response[$var]) ? $this->response[$var] : array());
    }

    public function parseStatus($status)
    {
        $this->response['status'] = (array) sscanf($status, "%[^/]/%s %d %[^\r\n]") + array('', '', '', '');
    }

    public function getProtocol()
    {
        return $this->response['status'][0];
    }

    public function getProtocolVersion()
    {
        return $this->response['status'][1];
    }

    public function getStatusCode()
    {
        return $this->response['status'][2];
    }

    public function getReasonPhrase()
    {
        return $this->response['status'][3];
    }

    public function getHeaders()
    {
        $response = end($this->response);
        return $response['headers'];
    }

    public function getHeader($header = null)
    {
        $response = end($this->response);
        return is_null($header) ? $response['header']
                                : (isset($response['headers'][$header]) ? $response['headers'][$header] : '');
    }

    public function getBody()
    {
        $response = end($this->response);
        return $response['body'];
    }

    protected function realUrl($url)
    {
        if (strpos($url, '://') === false) { // relative url
            $path_pos = strpos($this->url, $this->netloc) + strlen($this->netloc);
            if ($url[0] == '/') {
                $url = substr($this->url, 0, $path_pos) . $url;
            } else {
                $path = strtok($this->path, '?');
                $base = substr($this->url, 0, $path_pos) . '/';
                if (strpos(basename($path), '.') === false) {
                    $base .= ltrim($path, '/');
                } else {
                    $base .= ltrim(substr($path, 0, strrpos($path, '/')), '/');
                }
                $url = rtrim($base, '/') . '/' . $url;
            }
        }
        return $url;
    }

    public function request($method = 'GET', $data = '') // prepare
    {
        switch (strtoupper($method)) {
            case 'POST':
                if (is_array($data)) {
                    $data                                                = http_build_query($data, '', '&');
                    $this->request['options']['headers']['Content-Type'] = 'Content-Type: application/x-www-form-urlencoded';
                } else {
                    $this->request['headers'] += array('Content-Type' => 'Content-Type: application/x-www-form-urlencoded');
                }
                break;
            case 'HEAD':
                $this->setMaxRedirs(0);
                break;
        }
        if ($data == '') {
            if (isset($this->request['headers']['Content-Type'])) {
                unset($this->request['headers']['Content-Type']);
            }
        } else {
            $this->request['options']['headers']['Content-Length'] = 'Content-Length: ' . strlen($data);
        }
        foreach ($this->request['cookie'] as $domain => $cookie) {
            if (substr($this->host, -strlen($domain)) == $domain) {
                $this->request['options']['headers']['Cookie'] = 'Cookie: ' . str_replace('+', '%20', http_build_query($cookie, '', '; '));
                break;
            }
        }
        $this->request['headers']['Host']     = 'Host: ' . $this->host;
        $this->request['options']['headers'] += $this->request['headers'];
        $this->request['options']['message']  = sprintf("%s %s HTTP/1.0\r\n%s\r\n\r\n%s",
                                                        $method, $this->path, implode("\r\n", $this->request['options']['headers']), $data);
        if ($this->debug) {
            printf("%s\r\n----------------\r\n", rtrim($this->request['options']['message']));
        }
        return $this;
    }

    public function send()
    {
        static $redirscount = 0;
        $this->request['options']['headers'] or $this->request();
        $response = $this->parseResponse();
        if (
            isset($response['headers']['Location']) && $response['headers']['Location'] != $this->url
            && ($this->maxredirs < 0 || $redirscount < $this->maxredirs)
        ) {
            $redirscount++;
            return $this->setHeaders(array('Referer: ' . $this->url))
                        ->setUrl($this->realUrl($response['headers']['Location']))
                        ->send();
        } else {
            $this->parseStatus($this->getHeader(0)); // last status
            $redirscount = 0;
            return $this->setHeaders(array('Referer: ' . $this->url));
        }
    }
}
