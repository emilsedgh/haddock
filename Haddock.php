<?php

namespace Haddock;

define('POST', 'POST');
define('GET', 'GET');
define('DELETE', 'DELETE');
define('PUT', 'PUT');


class Route {
    protected $_paths = Array();
    protected $_callback = Null;
    protected $_methods = Array(POST, GET, DELETE, PUT);
    protected $_hosts = Array();

    public function __construct() {

    }

    public function setCallback($callback) {
        $this->_callback = $callback;
        return $this;
    }

    public function method() {
        $methods = func_get_args();
        foreach($methods as $method)
            $this->_methods[] = $method;

        return $this;
    }

    public function path() {
        $paths = func_get_args();
        foreach($paths as $path)
            $this->_paths[] = $path;

        return $this;
    }

    public function host() {
        $hosts = func_get_args();
        foreach($hosts as $host)
            $this->_hosts[] = $host;

        return $this;
    }

    protected function matchHost($request) {
        if(empty($this->_hosts))
            return Array();

        foreach($this->_hosts as $host) {
            $params = $this->parse($host, $request['HTTP_HOST']);
            if($params === False)
                continue;

            return $params;
        }

        return False;
    }

    protected function matchPath($request) {
        if(empty($this->_paths))
            return Array();

        $uri = str_replace($request['SCRIPT_NAME'], '', $request['REQUEST_URI']);
        $uri = str_replace('?'.$request['QUERY_STRING'], '', $uri);

        foreach($this->_paths as $path) {
            $params = $this->parse($path, $uri);

            if($params === False)
                continue;

            return $params;
        }

        return False;
    }

    public function route($request) {
        //Filter HTTP methods.
        if(!in_array($request['REQUEST_METHOD'], $this->_methods))
            return false;

        $hostParams = $this->matchHost($request);
        if($hostParams === False)
            return False;

        $pathParams = $this->matchPath($request);
        if($pathParams === False)
            return False;


        $callback = $this->_callback;
        $callback(array_merge($hostParams, $pathParams));
        return True;
    }

    protected function parse($expr, $val) {
        //Find all {foo} and {foo[:bar]}'s inside expression.
        preg_match_all('/{[^}]+}/', $expr, $params);

        $parameter_names = Array();
        foreach($params[0] as $param) {
            $analyzed = substr($param, 1, strlen($param)-2);

            if(strpos($analyzed, ':')) {
                list($name, $regex) = explode(':', $analyzed);
                $regex = '('.$regex.')';
            } else {
                $name = $analyzed;
                $regex = '(.+)';
            }

            $expr = str_replace('{'.$analyzed.'}', $regex, $expr);
            $parameter_names[] = $name;
        }

        $expr = '/^'.str_replace('/', '\/', $expr).'$/i';

        $matched = preg_match_all($expr, $val, $matches);
        if(!$matched)
            return False;

        $map = Array();
        foreach($parameter_names as $index => $param) {
            $map[$param] = current($matches[$index+1]);
        }

        return $map;
    }
}

class Router {
    protected $_routes = Array();

    public function handle($path, $callback) {
        $route = new Route;
        $route->path($path);

        $route->setCallback($callback);

        $this->addRoute($route);

        return $route;
    }

    public function post($path, $callback) {
        return $this->handle($path, $callback)->method(POST);
    }

    public function put($path, $callback) {
        return $this->handle($path, $callback)->method(PUT);
    }

    public function get($path, $callback) {
        return $this->handle($path, $callback)->method(GET);
    }

    public function delete($path, $callback) {
        return $this->handle($path, $callback)->method(DELETE);
    }

    public function addRoute(Route $route) {
        $this->_routes[] = $route;
    }

    public function route() {
        foreach($this->_routes as $route) {
            if(!$route->route($_SERVER))
                continue;
        }
    }

    public static function getUrl($clean = False) {
        $host = $_SERVER['HTTP_HOST'];

        $url = 'http://'.$host;

        if($_SERVER['SERVER_PORT'] != 80)
            $url .= ':'.$_SERVER['SERVER_PORT'];

        if(!$clean)
            $url .= $_SERVER['REQUEST_URI'];

        return $url;
    }
}