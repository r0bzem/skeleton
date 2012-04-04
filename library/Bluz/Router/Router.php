<?php
/**
 * Copyright (c) 2012 by Bluz PHP Team
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * @namespace
 */
namespace Bluz\Router;

class Router implements RouterInterface
{
    protected $_name = '';
    protected $_route = '';
    protected $_defaults = array();
    protected $_reqs = array();
    protected $_static = null;
    protected $_isStatic = false;


    /**
     * @param $route
     * @param array $options
     */
    public function __construct($route, array $options)
    {
        if (!is_array($options)) throw new \Exception('Options can be array, giving "' . gettype($options) . '"');

        $module = $this->getConfigParam($options['module']);
        $controller = $this->getConfigParam($options['controller']);

        $name = (!empty($options['name'])) ? $options['name'] : $module['default'] . '-' . $controller['default'];
        unset($options['name']);

        $this->setName($name)
             ->setRoute((string) $route);

        $defaults = $reqs = array();

        foreach ($options as $key => $value) {
            $value = $this->getConfigParam($value);

            $defaults[$key] = $value['default'];
            if (!empty($value['reqs'])) $reqs[$key] = $value['reqs'];
        }

        $this->setDefaults($defaults)
             ->setReqs($reqs);
    }

    /**
     * Get param default value & request
     *
     * @param mixed $param
     * @return array
     */
    private function getConfigParam($param)
    {
        if (is_array($param) && sizeof($param) >= 2) {
            return array(
                'default'   => array_shift($param),
                'reqs'      => array_shift($param)
            );
        } else {
            return array('default' => $param);
        }
    }

    /**
     * Проверка на соответствие
     *
     * @param string $path
     * @return false|array
     */
    public function match($path)
    {
        // Удаление по 1 слешу с концов строки
        $trim = function($url) {
            $url = trim($url);
            if ('/' == substr($url, 0, 1)) $url = substr($url, 1);
            if ('/' == substr($url, -1)) $url = substr($url, 0, -1);
            return $url;
        };

        $route = $this->_route;
        $path = $trim($path);

        // Если роутер полностью статичен
        if ($this->_isStatic) {
            if ($this->_static != $path) {
                return false;
            }

            return $this->_defaults;
        }

        // Проверка статичной части
        if (null !== $this->_static) {
            if (0 !== strpos($path . '/', $this->_static . '/')) {
                return false;
            }
            $route = $trim(substr($route, strlen($this->_static)));
            $path = $trim(substr($path, strlen($this->_static)));
        }

        $routeArray = explode('/', $route);
        $pathArray = explode('/', $path);
        $params = (is_array($this->_defaults)) ? $this->_defaults : array();

        $match = $matchPath = '';

        // Есть ли '*' в конце
        $last = false;

        while ($var = array_shift($routeArray)) {
            $part = null;

            // Не извлекает часть из пути, есть последний символ роутера *
            if (!('*' == $var && !sizeof($routeArray)))
                $part = array_shift($pathArray);

            if (':' == substr($var, 0, 1)) {
                // $var is variable

                $varName = substr($var, 1);

                // Если для переменной нету значиня по умолчанию и кусок пути пустой
                if (!isset($this->_defaults[$varName]) && empty($part)) {
                    return false;
                }

                if (isset($this->_reqs[$varName])) {
                    $var = $this->_reqs[$varName];
                }
                else {
                    $var = '.*';
                }

                // Если для переменной есть значение по умолчанию и кусок пути пустой
                if (isset($this->_defaults[$varName]) && empty($part)) {
                    $part = $this->_defaults[$varName];
                }

                $params[$varName] = $part;
            } else if ('*' == substr($var, 0, 1)) {
                $var = '.*';
                if (!sizeof($routeArray)) {
                    $last = true;
                    if (null !== $part) {
                        $match .= (empty($match)) ? "(?:($var))" : "(?:\/($var))?";
                    }
                    continue;
                }
            } else {
                if ($var != $part) {
                    return false;
                }
            }

            $match .= (empty($match)) ? "($var)" : "\/($var)";
            $matchPath .= (empty($matchPath)) ? "$part" : "/$part";
        }

        // Если есть дополнительные переменные, а их вводить нельзя
        if (sizeof($pathArray) && !$last) {
            return false;
        }

        $match = "#^$match$#i";

        if (preg_match($match, $matchPath)) {

            // Если остались переменные в пути, определяем их
            if (sizeof($pathArray)) {
                while (sizeof($pathArray)) {
                    $key = array_shift($pathArray);
                    $value = array_shift($pathArray);

                    // Дополнительными переменными нельзя сбить основные
                    if (!isset($params[$key])) {
                        $params[$key] = $value;
                    }
                }
            }

            // Возвращаем параметры
            return $params;

        } else {
            return false;
        }
    }

    /**
     * Set route name
     *
     * @param string $name
     * @return Route
     */
    public function setName($name)
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * Get route name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Set default variable
     *
     * @param array $defaults
     * @return Route
     */
    public function setDefaults(array $defaults)
    {
        $this->_defaults = $defaults;
        return $this;
    }

    /**
     * Get default variable
     *
     * @return array
     */
    public function getDefaults()
    {
        return $this->_defaults;
    }

    /**
     * Is static route
     * @return bool
     */
    public function isStatic()
    {
        return $this->_isStatic;
    }

    /**
     * Set route string
     *
     * @param string $route
     * @return Route
     */
    public function setRoute($route)
    {
        $this->_route = $route = trim($route, '/');

        if (false !== ($pos = strpos($route, ':'))) {
            $this->_static = trim(substr($route, 0, $pos), '/');
            $route = substr($route, strlen($this->_static));
        } else {
            if (false === strpos($route, '*')) {
                $this->_static = trim($route, '/');
                $this->_isStatic = true;
            }
        }

        return $this;
    }

    /**
     * Get route string
     *
     * @return string
     */
    public function getRoute()
    {
        return $this->_route;
    }

    /**
     * Get static path of route
     *
     * @return string|null
     */
    public function getStatic()
    {
        return $this->_static;
    }

    /**
     * Set params request
     *
     * @param array $reqs
     * @return Route
     */
    public function setReqs(array $reqs)
    {
        $this->_reqs = $reqs;
        return $this;
    }

    /**
     * Get params request
     *
     * @return array
     */
    public function getReqs()
    {
        return $this->_reqs;
    }

    /**
     * Using object as function
     *
     * @param $path
     * @return array|false
     */
    public function __invoke($path)
    {
        return $this->match($path);
    }

    public function url(array $params)
    {
        $url = $this->getRoute();
        $last = (0 === strrpos($url, '*')) ? true : false;
        $defaults = $this->getDefaults();

        foreach ($params as $param => $value) {
            if (isset($defaults[$param])) {
                $url = str_replace(':' . $param, $value, $url);
            } else {
                if ('module' == $param || 'controller' == $param) continue;

                if (!$last) throw new \Exception("Param '{$param}' not set on router", 404);

                $url .= "/{$param}/{$value}";
            }
        }

        return '/' . $url;
    }
}