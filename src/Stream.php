<?php

# Stream class
# PHP Simple Client (PHP Stream)
# 20190206 nggit

namespace Nggit\PHPSimpleClient;

class Stream
{
    protected $handle;
    protected $options   = array(
                               'ssl' => array(
                                            'verify_peer'      => false,
                                            'verify_peer_name' => false
                                        )
                           );
    protected $url;
    protected $host;
    protected $path;
    protected $socket;
    protected $maxredirs = -1;
    protected $timeout   = 10;
    protected $request   = array(
                               'cookie'  => array(),
                               'headers' => array(
                                                'Connection' => 'Connection: close'
                                            ),
                               'options' => array()
                           );
    protected $response  = array(
                               'status' => array()
                           );

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
            $this->request['headers'][substr($header, 0, strpos($header, ':'))] = $header;
        }
        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        if (strpos($url, '://') === false || !($url = parse_url($url))) {
            throw new \Exception('Invalid url or not an absolute url');
        }
        $this->host = $url['host'];
        $this->path = isset($url['path']) ? substr($this->url, strpos($this->url, $this->host) + strlen($this->host)) : '/';
        if (stripos($this->url, 'https://') === 0) {
            $transport = 'ssl';
            isset($url['port']) or $url['port'] = 443;
        } else {
            $transport = 'tcp';
            isset($url['port']) or $url['port'] = 80;
        }
        $this->socket = $transport . '://' . $url['host'] . ':' . $url['port'];
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
        parse_str(str_replace(';', '&', $cookie), $cookies);
        return $cookies;
    }

    protected function parseResponse()
    {
        $this->open();
        fwrite($this->handle, $this->request['options']['message']);
        $this->request['options'] = array(); // destroy the previous request options
        $next                     = count($this->response);
        $this->response[$next] = array('headers' => array(), 'header' => null, 'body' => null);
        while (!feof($this->handle)) {
            $line = fgets($this->handle);
            if (rtrim($line)) {
                $colon_pos = strpos($line, ':');
                $name      = substr($line, 0, $colon_pos);
                $value     = trim(substr($line, $colon_pos), ": \r\n");
                if ($name == 'Set-Cookie') {
                    $cookie = $this->parseCookie($value);
                    $domain = isset($cookie['domain']) ? $cookie['domain'] : $this->host;
                    $this->request['cookie'][$domain][] = $value;
                }
                $this->response[$next]['headers'][$name] = $value;
                $this->response[$next]['header']        .= $line;
            } else {
                $this->response[$next]['body'] = stream_get_contents($this->handle, -1, strlen($this->response[$next]['header']) + 2);
                break;
            }
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
        $this->response['status'] = explode(' ', str_replace('/', ' ', $status), 4);
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
                                : (isset($response['headers'][$header]) ? $response['headers'][$header] : null);
    }

    public function getBody()
    {
        $response = end($this->response);
        return $response['body'];
    }

    protected function realUrl($url)
    {
        if (strpos($url, '://') === false) { // relative url
            $path_pos = strpos($this->url, $this->host) + strlen($this->host);
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

    public function request($method = 'GET', $data = null)
    {
        switch ($method) {
            case 'POST':
                if (is_array($data)) {
                    $data = http_build_query($data, '', '&');
                }
                $this->request['options']['headers']['Content-Length'] = 'Content-Length: ' . strlen($data);
                $this->request['options']['headers']['Content-Type']   = 'Content-Type: application/x-www-form-urlencoded';
                break;
        }
        foreach ($this->request['cookie'] as $domain => $cookie) {
            if (substr($this->host, -strlen($domain)) == $domain) {
                $this->request['options']['headers']['Cookie'] = 'Cookie: ' . implode('; ', $this->request['cookie'][$domain]);
                break;
            }
        }
        $this->request['headers']['Host']     = 'Host: ' . $this->host;
        $this->request['options']['headers'] += $this->request['headers'];
        $this->request['options']['message']  = sprintf("%s %s HTTP/1.0\r\n%s\r\n\r\n%s",
                                                        $method, $this->path, implode("\r\n", $this->request['options']['headers']), $data);
        return $this;
    }

    public function send()
    {
        static $redirscount = 0;
        $this->request['options'] or $this->request();
        $response = $this->parseResponse();
        if (isset($response['headers']['Location']) &&
           $response['headers']['Location'] != $this->url &&
           ($this->maxredirs < 0 || $redirscount < $this->maxredirs)) {
            $redirscount++;
            $url = $this->url;
            return $this->setUrl($this->realUrl($response['headers']['Location']))
                        ->setHeaders(array('Referer: ' . $url))
                        ->request()
                        ->send();
        } else {
            $this->parseStatus($this->getHeader('')); // last status
            $redirscount = 0;
            return $this;
        }
    }
}
