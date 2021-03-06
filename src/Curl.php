<?php

# PHP Simple Client (PHP cURL).
# https://github.com/nggit/php-simple-client
# Copyright (c) 2019 nggit.

namespace Nggit\PHPSimpleClient;

class Curl
{
    protected $debug;
    protected $maxredirs;
    protected $url;
    protected $host;
    protected $netloc;
    protected $path;
    protected $handle;
    protected $options   = array(
                               CURLOPT_COOKIEFILE     => null,
                               CURLOPT_HEADER         => 1,
                               CURLOPT_ENCODING       => 'gzip, deflate',
                               CURLOPT_RETURNTRANSFER => true,
                               CURLOPT_SSL_VERIFYPEER => 0
                           );
    protected $request   = array(
                               'headers' => array(),
                               'options' => array()
                           );
    protected $response  = array(
                               'status' => array()
                           );

    public function __construct($debug = false, $maxredirs = -1, $timeout = 30)
    {
        $this->debug                    = $debug;
        $this->maxredirs                = $maxredirs;
        $this->options[CURLOPT_TIMEOUT] = $timeout;
    }

    protected function open()
    {
        if (!$this->handle) {
            $this->handle = curl_init();
            curl_setopt_array($this->handle, $this->options);
        }
    }

    protected function close()
    {
        $this->handle = curl_close($this->handle);
    }

    public function setCookieFile($file)
    {
        $this->options[CURLOPT_COOKIEFILE] = $this->options[CURLOPT_COOKIEJAR] = $file;
        return $this;
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
        $this->host   = $url['host'];
        $this->netloc = isset($url['port']) ? $this->host . ':' . $url['port'] : $this->host;
        $this->path   = isset($url['path']) ? $url['path'] : '/';
        return $this;
    }

    public function setMaxRedirs($maxredirs)
    {
        $this->maxredirs = $maxredirs;
        return $this;
    }

    public function setTimeOut($timeout)
    {
        $this->options[CURLOPT_TIMEOUT] = $timeout;
        return $this;
    }

    protected function parseResponse()
    {
        curl_setopt_array($this->handle, $this->request['options']);
        $this->request['options'] = array(); // destroy the previous request options
        if (!($response = curl_exec($this->handle))) {
            throw new \Exception(curl_error($this->handle) . ' (' . curl_errno($this->handle) . ')');
        }
        if ($this->debug) {
            printf("%s\r\n----------------\r\n", rtrim(curl_getinfo($this->handle, CURLINFO_HEADER_OUT)));
        }
        $tok                   = strtok($response, "\n");
        $next                  = count($this->response);
        $this->response[$next] = array('headers' => array(), 'header' => '', 'body' => '');
        while ($tok !== false) {
            if (rtrim($tok) == '') {
                break;
            }
            if (($colon_pos = strpos($tok, ':')) === false) {
                $this->response[$next]['headers'][0] = rtrim($tok);
            } else {
                $this->response[$next]['headers'][str_replace(' ', '-', ucwords(str_replace('-', ' ', substr($tok, 0, $colon_pos))))] = trim(substr($tok, $colon_pos), ": \r");
            }
            $this->response[$next]['header'] .= $tok . "\n";
            $tok = strtok("\n");
        }
        $this->response[$next]['body'] = substr($response, curl_getinfo($this->handle, CURLINFO_HEADER_SIZE));
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
                $base = substr($this->url, 0, $path_pos) . '/';
                if (strpos(basename($this->path), '.') === false) {
                    $base .= ltrim($this->path, '/');
                } else {
                    $base .= ltrim(substr($this->path, 0, strrpos($this->path, '/')), '/');
                }
                $url = rtrim($base, '/') . '/' . $url;
            }
        }
        return $url;
    }

    public function request($method = 'GET', $data = array()) // prepare
    {
        switch ($method) {
            case 'GET':
                $this->request['options'][CURLOPT_HTTPGET] = 1;
                break;
            case 'HEAD':
                $this->request['options'][CURLOPT_NOBODY] = 1;
                $this->setMaxRedirs(0);
                break;
            default:
                $this->request['options'][CURLOPT_CUSTOMREQUEST] = $method;
                if ($data) {
                    $this->request['options'][CURLOPT_POSTFIELDS] = $data;
                }
        }
        $this->request['options'][CURLOPT_URL]        = $this->url;
        $this->request['options'][CURLOPT_HTTPHEADER] = $this->request['headers'];
        if ($this->debug) {
            $this->request['options'][CURLINFO_HEADER_OUT] = true;
        }
        return $this;
    }

    public function send()
    {
        static $redirscount = 0;
        $this->open();
        $this->request['options'] or $this->request();
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
            $this->close();
            $redirscount = 0;
            return $this->setHeaders(array('Referer: ' . $this->url));
        }
    }
}
