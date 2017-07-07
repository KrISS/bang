<?php
// KrISS bang: a simple and smart (or stupid) bang manager
// Copyleft (ɔ) - Tontof - http://tontof.net
// use KrISS bang at your own risk
trait ArrayModelTrait {
 public function count($criteria = []){return empty($criteria)?count($this->data):count($this->findBy($criteria));}
 public function getData(){return $this->data;}
 public function getResultClass(){return $this->resultClass;}
 public function getSlug(){return $this->slug;}
 public function findOneBy($criteria = []){
  $result = $this->findBy($criteria);
  if (count($result) === 1){
   return reset($result);
  }
  return null;
 }
 public function findBy($criteria = [], $limit = null, $offset = null, $orderBy = null){
  if (empty($criteria) && is_null($limit) && is_null($offset) && is_null($orderBy)) return $this->data;
  $readProperty = function($property){ return $this->$property; };
  $filter = function($data) use ($criteria, $readProperty){
   $keep = true;
   if (is_array($criteria)){
    if (is_object($data)){
     $readProperty = $readProperty->bind($readProperty, $data, get_class($data));
     foreach($criteria as $key => $value){
      if ($readProperty($key) != $value) $keep = false;
     }
    } else {
     foreach($criteria as $key => $value){
      if ($data[$key] != $value) $keep = false;
     }
    }
   } else {
    $keep = false;
    if (is_object($data)){
     foreach(((array)$data) as $key => $value){
      if (strpos($data->$key, $criteria) !== false) $keep = true;
     }
    } else {
     foreach($data as $key => $value){
      if (strpos($data[$key], $criteria) !== false) $keep = true;
     }
    }
   }
   return $keep;
  };
  return array_slice(array_filter($this->data, $filter), $offset, $limit, true);
 }
 public function persist($data){
  $index = array_search($data, $this->data, true);
  if ($index !== false){
   $this->data[$index] = $data;
  } else {
   if (empty($this->resultClass) || $data instanceof $this->resultClass){
    if (empty($this->data)){
     $this->data[1] = $data;
    } else {
     $this->data[] = $data;
    }
    $id = array_search($data, $this->data, true);
    if (is_array($data)) $data['id'] = $id;
    else $data->id = $id;
   }
  }
  return $data;
 }
 public function remove($data = null){
  if (is_null($data)){
   $this->data = [];
  } else {
   $index = array_search($data, $this->data);
   if ($index !== false){
    unset($this->data[$index]);
   }
  }
 }
 /** @codeCoverageIgnore */
 private function createDir(){
  $dir = dirname($this->file);
  if (!is_dir($dir)){
   if (!mkdir($dir, 0755)){die('Unable to create data directory');}
   chmod($dir, 0755);
   if (!is_file($dir.'/.htaccess')){
    if (!file_put_contents($dir.'/.htaccess', "Allow from none\nDeny from all\n")){
     die('Unable to create .htaccess in data directory');
    }
   }
  }
 }
}
class ArrayModel{
 use ArrayModelTrait;
 const PHPPREFIX = '<'.'?php /* ';
 const PHPSUFFIX = ' */ ?'.'>';
 protected $resultClass;
 protected $slug;
 protected $file;
 protected $data = [];
 public function __construct($slug = "data", $resultClass = null, $prefix = 'data'){
  $this->resultClass = $resultClass;
  $this->slug = $slug;
  $this->file = getcwd()
     . DIRECTORY_SEPARATOR . trim($prefix, DIRECTORY_SEPARATOR)
     . DIRECTORY_SEPARATOR . $slug . '.php';
  if (file_exists($this->file)){
   $this->data = unserialize(
    gzinflate(
     base64_decode(
      substr(
       file_get_contents($this->file),
       strlen(self::PHPPREFIX),
       -strlen(self::PHPSUFFIX)
      )
     )
    )
   );
  }
 }
 public function flush(){
  if (!empty($this->data)){
   if (!file_exists($this->file)) $this->createDir();
   file_put_contents(
    $this->file,
    self::PHPPREFIX
    . base64_encode(gzdeflate(serialize($this->data)))
    . self::PHPSUFFIX,
    LOCK_EX
   );
  } else if (file_exists($this->file)){
   unlink($this->file);
  }
 }
}
class ViewModel{
 use ViewModelTrait;
 public function __construct($model){$this->model = $model;}
 public function getData(){
  $data = [];
  $pagination = ['current' => ((int)$this->offset)/$this->limit+1, 'total' => (int)(count($this->model->findBy($this->criteria))/$this->limit)];
  if ($pagination['total'] > 0) $data['pagination'] = $pagination;
  $data['slug'] = $this->model->getSlug();
  $data['data'] = $this->model->findBy($this->criteria, $this->limit, $this->offset, $this->orderBy);
  return $data;
 }
}
trait ViewModelTrait {
 protected $model;
 protected $criteria = [];
 protected $orderBy = null;
 protected $offset = null;
 protected $limit = null;
 public function setCriteria($criteria){$this->criteria = $criteria;}
 public function setOrderBy($orderBy){$this->orderBy = $orderBy;}
 public function setOffset($offset){$this->offset = $offset;}
 public function setLimit($limit){$this->limit = $limit;}
}
class FormViewModel{
 use ViewModelTrait;
 protected $validator;
 protected $form;
 private $errors = [];
 public function __construct($model, $form, $validator = null){
  $this->model = $model;
  $this->form = $form;
  $this->validator = $validator;
 }
 public function getData(){return ['slug' => $this->model->getSlug(), 'form' => $this->form->getForm()];}
 public function setCriteria($criteria){
  $this->criteria = $criteria;
  $data = $this->model->findBy($this->criteria, $this->limit, $this->offset, $this->orderBy);
  if (count($data) === 1) $data = reset($data);
  $this->form->setData($data);
 }
 public function getErrors(){return $this->errors;}
 private function dataErrors($data){
  if (!is_null($this->validator)) $this->validator->isValid($data);
  return is_null($this->validator)?[]:$this->validator->getErrors();
 }
 public function isValid($data){
  if (array_key_exists('_', $data)) $data = $data['_'];
  if (empty(array_filter(array_keys($data), 'is_int'))){
   $this->errors = $this->dataErrors($data);
  } else {
   $errors = [];
   foreach($data as $key => $item){
    $errors = $this->dataErrors($item);
    if (!empty($errors)) $this->errors[$key] = $errors;
   }
  }
  return empty($this->errors);
 }
}
class RequestRouter{
 use RouterTrait;
 private $request = null;
 public function __construct($request){$this->request = $request;}
 public function generate($name, $params = []){
  return $this->request->getSchemeAndHttpHost()
   . $this->request->getBaseUrl()
   . $this->generateRelativeUrl($name, $params);
 }
}
trait RouterTrait {
 private $matches = [];
 private $methods = [];
 private $responses = [];
 private $patterns = [];
 private $routeParameters = null;
 private function getMatchedRoute($match){
  if (is_callable($match[0])){
   return call_user_func_array($match[0], $match[1]);
  }
  return $match[0];
 }
 public function getRouteParameters(){
  if (is_null($this->routeParameters)) throw new \Exception('Route not dispatched');
  return $this->routeParameters;
 }
 public function dispatch($method, $pathInfo){
  $allowedMethods = [];
  foreach($this->matches as $name => $routes){
   foreach($routes as $route){
    if ($match = $route($pathInfo)){
     if (in_array($method, $this->methods[$name])){
      $this->routeParameters = $match[1];
      return $this->getMatchedRoute($match);
     } else {
      $allowedMethods = array_merge($allowedMethods, $this->methods[$name]);
     }
    }
   }
  }
  if ($allowedMethods){
   throw new \Exception($method.': not allowed: '.implode(',', $allowedMethods));
  }
  throw new \Exception($method.': '.$pathInfo.' not found');
 }
 private function extractRoutePattern($pattern){
  return preg_replace(
   [
    '@<([^:]+)>@U',   
    '@<([^:]+)(:(.+))?>@U', 
   ],
   [
    '<$1:[^/]+>',
    '(?<$1>$3)',
   ],
   ltrim($pattern, '/')
  );
 }
 private function addOneRoute($name, $pattern, $response){
  $pattern = $this->extractRoutePattern($pattern);
  $this->matches[$name][] = function ($routePath) use ($pattern, $response){
   $routePath = ltrim($routePath, '/');
   if (!preg_match('@^'.$pattern.'$@', $routePath, $vals)){
    return null;
   }
   $vals = array_map('urldecode', array_intersect_key(
    array_slice($vals, 1),
    array_flip(array_filter(array_keys($vals), 'is_string'))
   ));
   return [$response, $vals];
  };
 }
 public function getRoutes($name = null){
  if (!is_null($name)) return [$this->methods[$name], $this->patterns[$name], $this->responses[$name]];
  $results = [];
  foreach(array_keys($this->matches) as $name){
   $results[$name] = $this->getRoutes($name);
  }
  return $results;
 }
 public function setRoute($name, $methods, $pattern, $response){
  if (is_string($methods)) $methods = [$methods];
  $this->methods[$name] = array_map('strtoupper', $methods);
  $this->patterns[$name] = $pattern;
  $this->responses[$name] = $response;
  $this->matches[$name] = [];
  $optionalRoutes = explode('!', str_replace('<!', '!<', $pattern));
  $pattern = [];
  foreach($optionalRoutes as $oneRoute){
   $pattern[] = $oneRoute;
   $this->addOneRoute($name, implode('', $pattern), $response);
  } 
 }
 private function generateRelativeUrl($name, $params = []){
  if (!array_key_exists($name, $this->patterns)){
   throw new \Exception($name.' not found');
  }
  $pattern = $this->patterns[$name];
  $pattern = preg_replace('@<([^:]+)(:(.+))?>@U', '<$1>', $pattern);
  $query = [];
  foreach($params as $key => $value){
   $regex = '@\<!?'.$key.'\>@U';
   if (preg_match($regex, $pattern)){
    preg_match('@\<!?'.$key.':(.+)\>@U', $this->patterns[$name], $format);
    if (empty($format) || preg_match('@^'.$format[1].'$@', $value)){
     $pattern = preg_replace($regex, $value, $pattern);
    } else {
     throw new \Exception('Invalid parameter '.$key.': "'.$value.'" does not match format '.$format[1]);
    }
   } else {
    $query[$key] = $value;
   }
  }
  $pattern = preg_replace('@<!([^:]+)(:(.+))?>@U', '', $pattern);
  if (strpos($pattern, '<') !== false) throw new \Exception('Mandatory parameter '.$pattern);
  $query = http_build_query($query);
  $pattern = $pattern . (empty($query)?'':'?'.$query);
  return $pattern;
 }
}
class Router{
 use RouterTrait;
 public function generate($name, $params = []){
  return $this->generateRelativeUrl($name, $params);
 }
}
class RedirectResponse{
 use ResponseTrait;
 private $uri;
 public function __construct($uri = ''){
  if (is_callable($uri)){ $uri = $uri(); }
  $this->uri = $uri;
 }
 public function send(){
  $this->sendHeadersBody([['Location', $this->uri]]);
 }
}
class Response{
 use ResponseTrait;
 protected $body = '';
 protected $headers = [];
 public function __construct($body = '', $headers = [])
 {
  $this->body = $body;
  $this->headers = $headers;
 }
 public function send(){$this->sendHeadersBody($this->headers, $this->body);}
}
class UnauthorizedResponse{
 use ResponseTrait;
 public function send(){$this->sendHeadersBody([[$_SERVER['SERVER_PROTOCOL'].' 401 Unauthorized']], 'Not authorized');}
}
class ExceptionResponse{
 use ResponseTrait;
 private $exception;
 private $headers;
 public function __construct(\Exception $e, $headers = [])
 {
  $this->exception = $e;
  $this->headers = $headers;
 }
 public function send(){$this->sendHeadersBody($this->headers, $this->exception->getMessage());}
}
class ViewControllerResponse{
 use ResponseTrait;
 private $view;
 private $controller;
 public function __construct($view, $controller = null){
  $this->view = $view;
  $this->controller = $controller;
 }
 public function send(){
  if (!is_null($this->controller)){
   call_user_func(array($this->controller, 'action'));
  }
  list($headers, $body) = $this->view->render();
  $this->sendHeadersBody($headers, $body);
 }
}
class BasicUnauthorizedResponse{
 private $request;
 private $session;
 use ResponseTrait;
 public function __construct($session, $request){
  $this->request = $request;
  $this->session = $session;
 }
 public function send(){
  $realm = "KrISS".(empty($this->session->get('secret', ''))?'':':'.$this->session->get('secret', ''));
  $this->sendHeadersBody([
   [$this->request->getServer('SERVER_PROTOCOL', 'HTTP/1.0').' 401 Unauthorized'],
   ['WWW-Authenticate', 'Basic realm="'.$realm.'"'],
  ], 'Not authorized');
 }
}
trait ResponseTrait {
 private function sendHeadersBody($headers = [], $body = ''){
  foreach ($headers as $header){
   header($header[0].(isset($header[1])?': ' . $header[1]:''));
  }
  echo $body;
 }
}
class View{
 use ViewTrait;
 private function classToAttr($class){return strtolower($class);}
 private function stringify($data, $first = false){
  $string = ['<ul>'];
  foreach($data as $key => $item){
   $attr = is_object($item)?$this->classToAttr(get_class($item)):(!is_numeric($key)?$key:'');
   $attr = empty($attr)?'':($first?' id="'.$attr.'"':' class="'.$attr.'"');
   if (is_object($item) || is_array($item)){
    $string[] = '<li'.$attr.'>'.$key.': '.$this->stringify($item).'</li>';
   } else {
    $string[] = '<li'.$attr.'>'.$key.': '.$item.'</li>';
   }
  }
  $string[] = '</ul>';
  return join('', $string);
 }
 public function render(){return [[], $this->stringify($this->viewModel->getData(), true)];}
}
class FormView{
 protected $viewModel;
 public function __construct($viewModel){$this->viewModel = $viewModel;}
 private function stringify($data){
  $string = ['<ul>'];
  foreach($data as $key => $item){
   $attr = '';
   if (is_object($item) || is_array($item)){
    $string[] = '<li'.$attr.'>'.$key.': '.$this->stringify($item).'</li>';
   } else {
    $string[] = '<li'.$attr.'>'.$key.': '.$item.'</li>';
   }
  }
  $string[] = '</ul>';
  return join('', $string);
 }
 public function render(){
  $result = '';
  $data = $this->viewModel->getData();
  $errors = $this->viewModel->getErrors();
  $result .= $this->stringify($errors);
  if (!is_null($data)){
   foreach($data as $slug => $object){
    if (!is_null($object)){
     $method = $object['*']['method'];
     $url = $object['*']['action'];
     $result .= '<form action="'.$url.'" id="'.$slug.'" method="'.$method.'">';
     if (isset($object['_method']['value'])) $method = $object['_method']['value'];
     if ($method != 'DELETE'){
      foreach($object as $name => $value){
       if ($name != 'id' && $name[0] != '*'){
        $result .= '<div><label>'.$name.': <input name="'.$name.'" value="'.$value['value'].'" type="'.$value['type'].'"/></label></div>';
       }
      }
     } else {
      foreach($object as $name => $value){
       if ($name != 'id' && $name[0] != '*'){
        if ($name === '_method'){
         $result .= '<input name="'.$name.'" value="'.$value['value'].'" type="'.$value['type'].'"/>';
        } else {
         $result .= '<div>'.$name.': '.$value['value'].'</div>';
        }
       }
      }
     }
     $result .= '<input type="submit" value="'.$method.'"/>';
     $result .= '</form>';
    }
   }
  }
  return [[], $result];
 }
}
trait ViewTrait {
 protected $viewModel;
 public function __construct($viewModel){
   $this->viewModel = $viewModel;
 }
}
class Session{
 public function __construct($sessionName = ''){
  if (!isset($_SESSION)) $_SESSION = []; 
  if (!empty($sessionName)) session_name($sessionName);
 }
 public function start(){
  if (!$this->isActive()){
   ini_set('session.use_trans_sid', false);
   session_start();
  }
 }
 public function get($key, $default = null){
  $this->autostart();
  if (array_key_exists($key, $_SESSION)){
   $default = $_SESSION[$key];
  }
  return $default;
 }
 public function set($key, $value){
  $this->autostart();
  $_SESSION[$key] = $value;
 }
 public function remove($keys){
  if (is_string($keys)){
   $keys = [$keys];
  }
  foreach ($keys as $key){
   unset($_SESSION[$key]);
  }
 }
 public function isActive(){
  return \PHP_SESSION_ACTIVE === session_status();
 }
 private function autostart(){
  if (!$this->isActive()){
   $this->start();
  }
 }
}
class Container{
 protected $container = [];
 protected $rules = [];
 public function has($id){return class_exists($id)||isset($this->container[$this->getId($id)]);}
 public function get($id, $args = []){
  if ($this->has($this->getId($id).'_instance')){
   return $this->get($this->getId($id).'_instance');
  } else if (isset($this->container[$this->getId($id)])){
   $get = $this->container[$this->getId($id)];
   $instance = is_callable($get)?$get($this, $args):$get;
   $this->setInstance($id, $instance);
   return $instance;
  } else if (class_exists($id)){
   $instance = new $id(args);
   $this->setInstance($id, $instance);
   return $instance;
  } else {
   throw new \Exception($id.' not found');
  }
 }
 public function getRule($id){return $this->rules[$id];}
 public function set($id, $rules = array()){
  $this->rules[$id] = $rules;
  if (!$this->has($this->getId($id).'_class')) $this->container[$this->getId($id).'_class'] = $id;
  $id = $this->getId($id);
  foreach ($rules as $key => $rule){
   switch($key){
   case 'instanceOf':
    $this->container[$id.'_class'] = $rule;
    break;
   case 'constructParams':
    $this->container[$id.'_params'] = $rule;
    break;
   case 'call':
    $this->container[$id.'_call'] = $rule;
    break;
   case 'shared':
    $this->container[$id.'_shared'] = $rule;
    break;
   }
  }
  $this->container[$id] = function ($container, $params = []) use ($id){
   $class = new \ReflectionClass($container->has($id.'_class') ? $container->get($id.'_class') : $id);
   if ($container->has($id.'_params')){
    $params = [];
    foreach($container->get($id.'_params') as $param){
     if (is_array($param) && isset($param['instance'])){
      $params[] = $container->get($param['instance']);
     } else {
      $params[] = $param;
     }
    }
   }
   $instance = $class->newInstanceArgs($params);
   if ($container->has($id.'_call')){
    foreach($container->get($id.'_call') as $call){
     call_user_func_array(array($instance, $call[0]), $call[1]);
    }
   }
   return $instance;
  };
 }
 private function getId($id){return ltrim(strtolower($id), '\\');}
 private function setInstance($id, $instance){
  if (isset($this->container[$this->getId($id).'_shared'])
  && $this->container[$this->getId($id).'_shared']){
   $this->container[$this->getId($id).'_instance'] = $instance;
  }
 }
}
class RemoveFormAction{
 use FormActionTrait;
 private function saveEntity($entity){
  $this->model->remove($entity);
  $this->flush = true;
 }
 public function success($data){$this->traitSuccess($data);}
 public function failure($data){$this->traitFailure($data);}
}
trait FormActionTrait  {
 private $model;
 private $form;
 private $request;
 private $resetData;
 private $flush = false;
 public function __construct($model, $form, $request, $resetData = false){
  $this->model = $model;
  $this->form = $form;
  $this->request = $request;
  $this->resetData = $resetData;
 }
 public function traitSuccess($data){
  if ($this->resetData) $this->model->remove();
  $this->form->setFormData($data);
  $data = $this->form->getData();
  if (is_array($data)){
   if (empty(array_filter(array_keys($data), 'is_int'))) $this->saveEntity($data);
   else foreach($data as $entity) $this->saveEntity($entity);
  } else {
   $this->saveEntity($data);
  }
  if ($this->flush) $this->model->flush();
  header('Location: '.$this->request->getSchemeAndHttpHost().$this->request->getBaseUrl());
 }
 public function traitFailure($data){
  if (array_key_exists('_', $data)) $data = $data['_'];
  $this->form->setData($data);
 }
}
class PersistFormAction{
 use FormActionTrait;
 private function saveEntity($entity){
  $this->model->persist($entity);
  $this->flush = true;
 }
 public function success($data){$this->traitSuccess($data);}
 public function failure($data){$this->traitFailure($data);}
}
class Request{
 private $basePath = '';
 private $baseUrl = '';
 private $host = 'localhost';
 private $method = 'GET';
 private $pathInfo = '';
 private $port = 80;
 private $queryString = '';
 private $requestUri = ''; 
 private $scheme = 'http';
 public function __construct($uri = null, $method = 'GET'){
  if (is_null($uri)){
   $this->initFromServer();
  } else {
   $this->initFromUri($uri, $method);
  }
 }
 public function getBaseUrl(){return $this->baseUrl;}
 public function getHost(){return $this->host;}
 public function getPathInfo(){return $this->pathInfo;}
 public function getPort(){return $this->port;}
 public function getQueryString(){return $this->queryString;}
 public function getScheme(){return $this->scheme;}
 public function getMethod(){return $this->method;}
 public function getRequest($request = null, $default = null){
  if (is_null($request)) return $_POST;
  return isset($_POST[$request])?$_POST[$request]:$default;
 }
 public function getQuery($query = null, $default = null){
  if (is_null($query)) return $_GET;
  return isset($_GET[$query])?$_GET[$query]:$default;
 }
 public function getRequestUri(){return $this->requestUri;}
 public function setBaseUrl($baseUrl){$this->baseUrl = rtrim($baseUrl, '/');}
 public function setPathInfo($pathInfo){$this->pathInfo = $pathInfo;}
 public function setMethod($method){$this->method = strtoupper($method);}
 public function setHost($host){$this->host = preg_replace('/:(.*)$/i', "", strtolower($host));}
 public function setRequestUri($requestUri){$this->requestUri = $requestUri;}
 public function setScheme($scheme){$this->scheme = strtolower($scheme);}
 public function setPort($port){$this->port = (int) $port;}
 public function setQueryString($queryString){$this->queryString = (string) $queryString;}
 public function getUri(){
  $queryString = $this->getQueryString();
  $queryString = empty($queryString)?'':'?'.$queryString;
  return $this->getSchemeAndHttpHost().$this->getBaseUrl().$this->getPathInfo().$queryString;
 }
 public function getSchemeAndHttpHost(){
  $scheme = $this->getScheme();
  $port = $this->getPort();
  $defaultPort = $scheme.$port === 'http80' || $scheme.$port === 'https443';
  return $scheme.'://'.$this->getHost().(($defaultPort)?'':':'.$port);
 }
 private function prepareRequestUri(){
  $requestUri = '';
  if (!empty($this->getServer('REQUEST_URI'))){
   $requestUri = $this->getServer('REQUEST_URI');
   $schemeAndHttpHost = $this->getSchemeAndHttpHost();
   if (strpos($requestUri, $schemeAndHttpHost) === 0){
    $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
   }
  } elseif (!empty($this->getServer('ORIG_PATH_INFO'))){
   $requestUri = $this->getServer('ORIG_PATH_INFO');
   if ('' != $this->getQueryString()){
    $requestUri .= '?'.$this->getQueryString();
   }
  }
  return $requestUri;
 }
 private function prepareBaseUrl(){
  $filename    = basename($this->getServer('SCRIPT_FILENAME', ''));
  $scriptName  = $this->getServer('SCRIPT_NAME');
  $phpSelf  = $this->getServer('PHP_SELF');
  $origScriptName = $this->getServer('ORIG_SCRIPT_NAME');
  if (basename($scriptName) === $filename){
   $baseUrl = $scriptName;
  } elseif (basename($phpSelf) === $filename){
   $baseUrl = $phpSelf;
  } elseif (basename($origScriptName) === $filename){
   $baseUrl = $origScriptName; 
  } else {
   $baseUrl  = '/';
   $basename = $filename;
   if ($basename){
    $path  = ($phpSelf ? trim($phpSelf, '/') : '');
    $basePos  = strpos($path, $basename) ?: 0;
    $baseUrl .= substr($path, 0, $basePos) . $basename;
   }
  }
  $requestUri = $this->getRequestUri();
  if (0 === strpos($requestUri, $baseUrl)){
   return $baseUrl;
  }
  $baseDir = str_replace('\\', '/', dirname($baseUrl));
  if (0 === strpos($requestUri, $baseDir)){
   return $baseDir;
  }
  $truncatedRequestUri = $requestUri;
  if (false !== ($pos = strpos($requestUri, '?'))){
   $truncatedRequestUri = substr($requestUri, 0, $pos);
  }
  $basename = basename($baseUrl);
  if (empty($basename) || false === strpos($truncatedRequestUri, $basename)){
   $baseUrl = '';
  }
  return $baseUrl;
 }
 private function preparePathInfo(){
  $baseUrl = $this->getBaseUrl();
  $requestUri = $this->getRequestUri();
  if ($pos = strpos($requestUri, '?')){
   $requestUri = substr($requestUri, 0, $pos);
  }
  $pathInfo = substr($requestUri, strlen($baseUrl));
  return (string) $pathInfo;
 }
 private function initFromServer(){
  $method = $this->getServer('REQUEST_METHOD', 'GET');
  if (isset($_POST['_method']) && $method === 'POST') $method = $_POST['_method'];
  $this->setMethod($method);
  $this->setHost($this->getServer(
   'HTTP_HOST',
   $this->getServer(
    'SERVER_NAME',
    $this->getServer('SERVER_ADDR', $this->host))));
  $isSecure = !empty($this->getServer('HTTPS')) && $this->getServer('HTTPS') == 'on';
  $this->setScheme('http'.($isSecure?'s':''));
  $this->setPort($this->getServer('SERVER_PORT', ($isSecure?'443':'80')));
  $this->setQueryString($this->getServer('QUERY_STRING'));
  $this->setRequestUri($this->prepareRequestUri());
  $this->setBaseUrl($this->prepareBaseUrl());
  $this->setPathInfo($this->preparePathInfo());
 }
 private function initFromUri($uri, $method){
  $this->setMethod($method);
  $components = parse_url($uri);
  if (isset($components['host'])){
   $this->setHost($components['host']);
  }
  if (isset($components['scheme'])){
   $this->setScheme($components['scheme']);
   if ('https' === $components['scheme']){
    $this->setPort(443);
   }
  }
  if (isset($components['port'])){
   $this->setPort($components['port']);
  }
  if (isset($components['query'])){
   $this->setQueryString($components['query']);
  }
  if (isset($components['path'])){
   $queryString = $this->getQueryString();
   $this->setRequestUri($components['path'].('' !== $queryString ? '?'.$queryString : ''));
  }
  $this->setBaseUrl($this->prepareBaseUrl());
  $this->setPathInfo($this->preparePathInfo());
 }
 public function getServer($server = null, $default = null){
  if (is_null($server)) return $_SERVER;
  return isset($_SERVER[$server])?$_SERVER[$server]:$default;
 }
}
trait ListControllerTrait {
 private function listAction(){
  $params = $this->router->getRouteParameters();
  $criteria = [];
  if (isset($params['id'])) $criteria = ['id' => (int)$params['id']];
  $data = $this->request->getQuery();
  if (isset($data['search'])){
   $search = json_decode($data['search'], true);
   if (is_null($search)) $search = json_decode('"'.$data['search'].'"');
   $criteria = $search;
  }
  $orderBy = isset($data['order_by'])?$data['order_by']:null;
  $offset = isset($data['offset'])?$data['offset']:null;
  $limit = (int)(isset($data['limit'])?$data['limit']:24);
  $page = isset($data['page'])?$data['page']:1;
  if (is_null($offset)){ $offset = ($page-1)*$limit; }
  $this->viewModel->setOrderBy($orderBy);
  $this->viewModel->setOffset($offset);
  $this->viewModel->setLimit($limit);
  $this->viewModel->setCriteria($criteria);
 }
}
trait FormControllerTrait {
 private function formAction()
 {
  $data = $this->request->getRequest();
  if ($this->viewModel->isValid($data)){$this->formAction->success($data);}
  else {$this->formAction->failure($data);}
 }
}
class FormController{
 use FormControllerTrait;
 protected $viewModel;
 protected $request;
 private $formAction;
 public function __construct($viewModel, $request, $formAction){
  $this->viewModel = $viewModel;
  $this->request = $request;
  $this->formAction = $formAction;
 }
 public function action(){$this->formAction();}
}
class FormListController{
 use ListControllerTrait;
 use FormControllerTrait;
 private $formAction;
 protected $viewModel;
 protected $request;
 protected $router;
 public function __construct($viewModel, $request, $formAction, $router){
  $this->formAction = $formAction;
  $this->viewModel = $viewModel;
  $this->request = $request;
  $this->router = $router;
 }
 public function action(){
  $this->listAction();
  $this->formAction();
 }
}
class ListController{
 use ListControllerTrait;
 protected $viewModel;
 protected $request;
 protected $router;
 public function __construct($viewModel, $request, $router){
  $this->viewModel = $viewModel;
  $this->request = $request;
  $this->router = $router;
 }
 public function action(){$this->listAction();}
}
class Form{
 protected $rules;
 protected $formatters;
 protected $data;
 protected $formData;
 public function __construct($data = [], $method = 'GET', $action = ''){
  $this->rules = [];
  $this->setAction($action);
  $this->setMethod($method);
  $this->data = $data;
  $this->formData = [];
  $this->formatters = [];
 }
 private function updateCurrentData(&$data, $key, $value){
  if (is_array($data)){$data[$key] = $value;}
  else {$data->$key = $value;}
 }
 private function updateData(&$data, $formData){
  foreach ($data as $key => $value){
   if (array_key_exists($key, $formData) && $key !== 'id'){
    if (is_object($value) || is_array($value)){
     if (is_array($data)){
      $this->updateData($data[$key], $formData[$key]);
     } else {
      $this->updateData($data->$key, $formData[$key]);
     }
    } else {
     $value = $this->formatValue($key, $formData[$key]);
     $this->updateCurrentData($data, $key, $value);
    }
   }
  }
 }
 public function getData($name = ''){
  $this->updateData($this->data, (empty($name)?$this->formData:$this->formData[$name]));
  return $this->data;
 }
 public function setData($data){$this->data = $data;}
 public function setFormatter($name, $fun){$this->formatters[$name] = $fun;}
 private function setCurrentFormData(&$formData, $key, $value){
  $pos = strpos($key, '[');
  if ($pos === false){
   if (empty($key) || $key === '_'){$formData = $value;}
   else $formData[$key] = $value;
  } else {
   $currentKey = substr($key,0,$pos);
   if (!array_key_exists($currentKey, $formData)){
    $formData[$currentKey] = [];
   }
   $key = substr($key,$pos+1);
   $pos = strpos($key, ']');
   if ($pos !== false){
    $key = substr_replace($key, '', $pos, 1);
   }
   if ($currentKey !== '_'){
    $formData[$currentKey] = $this->setCurrentFormData($formData[$currentKey], $key, $value);
   } else {
    $formData = $this->setCurrentFormData($formData[$currentKey], $key, $value);
   }
  }
  return $formData;
 }
 public function setFormData($formData){
  $this->formData = [];
  foreach ($formData as $key => $value){
   $this->setCurrentFormData($this->formData, $key, $value);
  }
 }
 public function getAction(){return $this->rules['*']['action'];}
 public function setAction($action){$this->rules['*']['action'] = $action;}
 public function getMethod(){
  if (isset($this->rules['_method']))
   return $this->rules['_method']['value'];
  return $this->rules['*']['method'];
 }
 public function setMethod($method){
  switch($method){
  case 'GET':
   $this->rules['*']['method'] = $method;
   break;
  case 'POST':
   $this->rules['*']['method'] = $method;
   break;
  default:
   $this->rules['*']['method'] = 'POST';
   $this->rules['_method'] = [
    'type' => 'hidden',
    'value' => $method,
   ];
  }
 }
 public function setRule($name, $rule){$this->rules[$name] = $rule;}
 public function getRule($name){return array_key_exists($name, $this->rules)?$this->rules[$name]:[];}
 public function generateRules($data, $name){
  foreach($data as $key => $value){
   if (is_numeric($key)){
    $this->generateRules($value, (empty($name)?'_':$name).'['.$key.']');
   } else {
    $currentName = (empty($name)?'':$name.'[').$key.(empty($name)?'':']');
    if (!array_key_exists($key, $this->rules)){
     $this->rules[$currentName] = [];
    }
    if ($this->rules[$currentName] instanceOf Form){
     $form = $this->rules[$currentName]->getForm();
     foreach ($form as $sub => $rule){
      $openingBracket = '[';
      $closingBracket = ']';
      if ($sub[0] === '_'){
       $sub = substr($sub, 1);
       $openingBracket = '';
       $closingBracket = '';
      }
      if ($sub !== '*') $this->rules[$currentName.$openingBracket.$sub.$closingBracket] = $rule;
     };
     unset($this->rules[$currentName]);
    } else {
     if (!is_null($this->rules[$currentName])){
      if (!array_key_exists('type', $this->rules[$currentName])){
       $this->rules[$currentName]['type'] = 'text';
      } 
      if (!array_key_exists('value', $this->rules[$currentName])){
       $this->rules[$currentName]['value'] = $value;
      }
     }
    }
   }
  }
 }
 public function getForm($name = ''){
  $this->generateRules($this->data, $name);
  return array_filter($this->rules);
 }
 private function formatValue($name, $value){
  if (array_key_exists($name, $this->formatters)){
   $value = $this->formatters[$name]($value, $this->formData);
  }
  return $value;
 }
}
class BasicAuthentication{
 private $passwordHash;
 private $request;
 private $session;
 private $user;
 private $userProvider;
 public function __construct($userProvider, $request, $session, $passwordHash){
  $this->passwordHash = $passwordHash;
  $this->request = $request;
  $this->session = $session;
  $this->userProvider = $userProvider;
  $this->user = null;
 }
 public function authenticate(){
  if ($this->isAuthenticated()){
   $this->user = $this->userProvider->loadUser(['username' => $this->session->get('username')]);
   return 2;
  }
  $secretCaptcha = $this->session->get('secret_captcha', (new \DateTime())->format('is'));
  $secret = $this->session->get('secret', $secretCaptcha);
  $username = $this->request->getServer('PHP_AUTH_USER');
  $password = $this->request->getServer('PHP_AUTH_PW');
  if (is_null($username) || is_null($password)){
   return 5;
  }
  $user = $this->userProvider->loadUser(['username' => $username]);
  if (is_null($user)){
   return 3;
  }
  $userPassword = null;
  if (is_array($user)){ $userPassword = $user['password']; }
  else { $userPassword = $user->password; }
  if ($this->passwordHash->hash($password, $username) !== $userPassword){
   return 4;
  }
  if ($secret !== $secretCaptcha){
   $this->session->remove('secret');
   return 5;
  }
  $this->user = $user;
  $this->session->set('uid', sha1(uniqid('', true).'_'.mt_rand()));
  $this->session->set('username', $username);
  $this->session->set('secret', $secret);
  return 1;
 }
 public function deauthenticate(){
  $this->user = null;
  $this->session->remove(['secret_captcha', 'uid']);
 }
 public function getUser(){
  return $this->user;
 }
 public function isAuthenticated(){
  return ((bool)$this->session->get('uid', false)) && ((bool)$this->session->get('username', false));
 }
}
class UserProvider{
 private $userModel;
 public function __construct($userModel){$this->userModel = $userModel;}
 public function loadUser($criteria){return $this->userModel->findOneBy($criteria);}
}
class Authorization{
 private $authentication;
 public function __construct($authentication){$this->authentication = $authentication;}
 public function isGranted(){return $this->authentication->isAuthenticated();}
}
class SessionAuthentication{
 private $passwordHash;
 private $request;
 private $session;
 private $user = null;
 private $userProvider;
 private $inactivityTimeout = 3600;
 private $sessionName = '';
 private $disableSessionProtection = false;
 public function __construct($userProvider, $request, $session, $passwordHash, $options = []){
  $this->passwordHash = $passwordHash;
  $this->request = $request;
  $this->session = $session;
  $this->userProvider = $userProvider;
  $this->loadOptions($options);
  $cookie = session_get_cookie_params();
  $cookiedir = '';
  if (dirname($this->request->getServer('SCRIPT_NAME', ''))!='/'){
   $cookiedir = dirname($this->request->getServer('SCRIPT_NAME', '')).'/';
  }
  $ssl = true;
  if ($this->request->getServer('HTTPS','') !== 'on'){
   $ssl = false;
  }
  session_set_cookie_params($cookie['lifetime'], $cookiedir, $this->request->getServer('HTTP_HOST'), $ssl);
  ini_set('session.use_cookies', 1);
  ini_set('session.use_only_cookies', 1);
  if (!session_id()){
   ini_set('session.use_trans_sid', false);
   if (!empty($this->sessionName)){
    session_name($this->sessionName);
   }
   session_start();
  }
 }
 public function authenticate(){
  if ($this->isAuthenticated()){
   $this->user = $this->userProvider->loadUser(['username' => $this->session->get('username')]);
   return 2;
  }
  $request = $this->request->getRequest();
  if (!array_key_exists('username', $request) || !array_key_exists('password', $request)){
   return 5;
  }
  $username = $request['username'];
  $password = $request['password'];
  $user = $this->userProvider->loadUser(['username' => $username]);
  if (is_null($user)){
   return 3;
  }
  $userPassword = null;
  if (is_array($user)){ $userPassword = $user['password']; }
  else { $userPassword = $user->password; }
  if ($userPassword !== $this->passwordHash->hash($password, $username)){
   return 4;
  }
  $this->user = $user;
  $this->session->set('uid', sha1(uniqid('', true).'_'.mt_rand()));
  $this->session->set('ip', $this->allIPs());
  $this->session->set('username', $username);
  $this->session->set('expires_on', time() + $this->inactivityTimeout);
  return 1;
 }
 public function deauthenticate(){
  $this->user = null;
  $this->session->remove(['uid', 'ip', 'expires_on']);
 }
 public function getUser(){return $this->user;}
 public function isAuthenticated(){
  if (!(bool)$this->session->get('uid', false)
   || ($this->disableSessionProtection === false
    && $this->session->get('ip') !== $this->allIPs())
   || time() >= $this->session->get('expires_on')){
   $this->deauthenticate();
   return false;
  }
  $this->session->set('expires_on', time() + $this->inactivityTimeout);
  if (!empty($this->session->get('longlastingsession'))){
   $this->session->set('expires_on', $this->session->get('expires_on') + $this->session->get('longlastingsession'));
  }
  return true;
 }
 private function allIPs(){
  return $this->request->getServer('REMOTE_ADDR','')
   .'_'.$this->request->getServer('HTTP_X_FORWARDED_FOR', '')
   .'_'.$this->request->getServer('HTTP_CLIENT_IP', '');
 }
 private function loadOptions($options){
  foreach($options as $key => $option){
   if (in_array($key, ['disableSessionProtection', 'inactivityTimeout', 'sessionName'])){
    $this->$key = $option;
   }
  }
 }
}
class PrivateRequestAuthorization{
 private $authentication;
 private $request;
 public function __construct($authentication, $request){
  $this->authentication = $authentication;
  $this->request = $request;
 }
 public function isGranted(){
  if ($this->request->getPathInfo() == '/login/'){return true;}
  else {return $this->authentication->isAuthenticated();}
 }
}
class ProtectedRequestAuthorization{
 private $authentication;
 private $request;
 public function __construct($authentication, $request){
  $this->authentication = $authentication;
  $this->request = $request;
 }
 public function isGranted(){
  if ($this->request->getMethod() === 'GET'){return true;}
  else {return $this->authentication->isAuthenticated();}
 }
}
class HashPassword{
 private $salt;
 public function __construct($salt = 'a18c1239f19135e3072d931c1050603f4f405194'){
  $this->$salt = $salt;
 }
 public function hash($password, $username = ''){
  return sha1($password.$username.$this->salt);
 }
}
class App{
 private $container;
 private $plugins = [];
 private $configs = [];
 public function __construct($container){
  $this->container = $container;
  $this->container->set('Request', [
   'instanceOf' => 'Request',
   'shared' => true,
  ]);
  $this->container->set('Router', [
   'instanceOf' => 'RequestRouter',
   'shared' => true,
   'constructParams' => [
    ['instance' => 'Request'],
   ],
  ]);
 }
 public function getContainer(){return $this->container;}
 public function addPlugin($name){
  call_user_func_array($name, [$this]);
  $this->plugins[] = $name;
 }
 public function configPlugin($name, $config){$this->configs[$name] = $config;}
 public function run(){
  $container = $this->container;
  $next = function() use ($container){
   $request = $container->get('Request');
   return $container->get('Router')->dispatch($request->getMethod(), $request->getPathInfo());
  };
  foreach($this->plugins as $plugin){
   $params = [$this, $next];
   if (array_key_exists($plugin, $this->configs)){
    $params = array_merge($params, $this->configs[$plugin]);
   }
   $next = call_user_func_array($plugin, $params);
  }
  $response = $next();
  $response->send();
 }
}
class Validator{
 private $errors = [];
 private $constraints = [];
 private function getProperty($data, $name){
  $value = null;
  if (is_array($data) && array_key_exists($name, $data)){
   $value = $data[$name];
  }
  if (is_object($data) && isset($data->$name)){
   $value = $data->$name;
  }
  return $value;
 }
 private function setConstraint($name, $rule, $params = [], $error = ''){
  if (empty($error)) $error = $name.' '.$rule.' Error';
  $this->constraints[$name][] = [$rule, $params, $error];
 }
 public function setConstraints($name, $constraints = []){
  $this->constraints[$name] = [];
  foreach ($constraints as $constraint){
   call_user_func_array(array($this, 'setConstraint'), array_merge([$name], $constraint));
  }
 }
 public function getConstraints($name){
  return array_key_exists($name, $this->constraints)?$this->constraints[$name]:[];
 }
 public function isValid($data){
  $this->errors = [];
  foreach($this->constraints as $name => $constraints){
   $value = $this->getProperty($data, $name);
   foreach($constraints as $constraint){
    list($rule, $params, $error) = $constraint;
    switch($rule){
    case 'email':
     if (filter_var($value, FILTER_VALIDATE_EMAIL) === false && !is_null($value)){
      $this->errors[$name][] = $error;
     }
     break;
    case 'inArray':
     if (!in_array($value, $params[0])){
      $this->errors[$name][] = $error;
     }
     break;
    case 'minLength':
     if (strlen($value) < $params[0]){
      $this->errors[$name][] = $error;
     }
     break;
    case 'required':
     if (is_null($value)){
      $this->errors[$name][] = $error;
     }
     break;
    case 'closure':
     if (!$params[0]($value, $data)){
      $this->errors[$name][] = $error;
     }
     break;
    default:
     throw new \Exception($rule.' validation is not defined');
    }
   }
  }
  return empty($this->errors);
 }
 public function getErrors(){
  return $this->errors;
 }
}
class Bang {
 public $bang = '!?';
 public $url = 'https://duckduckgo.com';
 public $pattern = 'https://duckduckgo.com/?q=kriss_bang';
}
$app = new App(new Container());
$container = $app->getContainer();
$isUnique = function($value, $data) use ($container){
 $model = $container->get('#bang_model');
 $bang = $model->findOneBy(['bang' => $value]);
 $validId = true;
 if (!is_null($bang)){
  $validId = false;
  $refId = ((array)$bang);
  $refId = $refId['id'];
  $checkId = '';
  if (isset($data['id'])) $checkId = (int)$data['id'];
  else {
   $params = $container->get('Router')->getRouteParameters();
   $checkId = isset($params['id'])?((int)$params['id']):'';
  };
  $validId = ($refId === $checkId);
 }
 return $validId;
};
$container->set('$bang_validator', [
 'instanceOf' => 'Validator',
 'call' => [
  ['setConstraints', [
   'bang', [['closure', [$isUnique], 'bang already exists']],
  ]],
 ],
]);
$routerRule = $container->getRule('Router');
$container->set('Router', [
 'call' => array_merge([
  ['setRoute', [
   'kriss_bang_index', 'GET', '/',
   function () use ($container){
    $request = $container->get('Request');
    $query = $request->getQuery();
    if (isset($query['bang'])){
     $router = $container->get('Router');
     if (empty($query['bang'])) return new RedirectResponse($router->generate('kriss_bang_index'));
     $model = $container->get('#bang_model');
     $str = null;
     if (preg_match('/![^ ]*/', $query['bang'], $matches)){
      $str = $matches[0];
     } else {
      $str = '!?';
     }
     $bang = $model->findOneBy(['bang' => $str]);
     if (is_null($bang)){
      $bang = $container->get('Bang');
      $bang->pattern .= ' '.$str;
     }
     $search = trim(preg_replace('/![^ ]*/', '', $query['bang']));
     if (empty($search)){
      return new RedirectResponse($bang->url);
     } else {
      return new RedirectResponse(trim(preg_replace('/kriss_bang/', urlencode($search), $bang->pattern)));
     }
    }
    $router = $container->get('Router');
    $authLink = '';
    if ($container->has('Authentication')){
     $authentication = $container->get('Authentication');
     $authUrl = $router->generate('login');
     $auth = 'Login';
     if ($authentication->isAuthenticated()){
      $authUrl = $router->generate('logout');
      $auth = 'Logout';
     }
     $authLink = '<a href="'.$authUrl.'">'.$auth.'</a>';
    }
    $faviconUrl = $router->generate('kriss_bang_favicon');
    $xmlUrl = $router->generate('kriss_bang_xml');
    $updateCsvUrl = $router->generate('kriss_bang_update_csv');
    $exportCsvUrl = $router->generate('kriss_bang_export_csv');
    $indexUrl = $router->generate('kriss_bang_index');
    $bangListUrl = $router->generate('autoroute_index', ['slug' => 'bang']);
    $configUrl = $router->generate('autoroute_index', ['slug' => 'config']);
    $adminUrl = $router->generate('autoroute_index', ['slug' => 'admin']);
    $response = <<<html
<!DOCTYPE html>
<html>
  <head>
 <meta charset="utf-8">
 <title>KrISS bang</title>
 <link href="$faviconUrl" rel="shortcut icon" type="image/x-icon" sizes="16x16 64x64">
 <link href="$xmlUrl" rel="search" type="application/opensearchdescription+xml" title="KrISS bang">
 <style>body{text-align:center}</style>
  </head>
  <body onload="document.bang.bang.focus();">
 <header><a href="$indexUrl"><img src="$faviconUrl"/></a></header>
 <section>
   <form action="$indexUrl" method="GET" name="bang"><input name="bang" type="text" tabindex="1"><input type="submit" value="bang"></form> 
   <a href="$bangListUrl">Bang list</a><br>
   <form action="$bangListUrl" method="GET" name="search"><input name="search" type="text" tabindex="2"><input type="submit" value="search for bang"></form> 
   <a href="$exportCsvUrl">Export CSV</a><br>
   <a href="$updateCsvUrl">Upload CSV</a><br>
   <a href="$configUrl">Config</a><br>
   <a href="$adminUrl">Admin</a><br>
   $authLink
 </section>
 <footer><a href="//tontof.net/kriss/bang">KrISS bang</a> - A simple and smart (or stupid) <a href="//duckduckgo.com/bang">bang</a> manager. By <a href="//tontof.net">Tontof</a></footer>
  </body>
</html>
html;
    return new Response($response);
   }
  ]],
  ['setRoute', [
   'kriss_bang_favicon', 'GET', '/favicon.ico',
   function () use ($container){
    $favicon = <<<base64
AAABAAIAQEAQAAEABABoCgAAJgAAABAQAAABACAAaAQAAI4KAAAoAAAAQAAAAIAAAAABAAQAAAAA AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABAABRceMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABEREREREREREREREREREQAAERERERERERERERER EREREiIiIiIiIiIiIiIiIiIhAAASIiIiIiIiIiIiIiIiIiESIiIiIiIiIiIiIiIiIRAAAAESIiIi IiIiIiIiIiIiIRIiIiIiIiIiIiIiIiIQAAAAAAEiIiIiIiIiIiIiIiIhEiIiIiIiIiIiIiIiIQAA AAAAABIiIiIiIiIiIiIiIiESIiIiAAAAAiIiIiIQAAAAAAAAASIiIiIiIiIiIiIiIRIiIiAAAAAA IiIiIQAAAAAAAAAAEiIiIiIiIiIiIiIhEiIiAAAAAAACIiIhAAAAAAAAAAASIiIiIiIiIiIiIiES IiIAAAAAAAIiIhAAAAAAAAAAAAEiIiIiIiIiIiIiIRIiIgAAAAAAAiIiEAAAAAAAAAAAASIiIiIi IiIiIiIhEiIiAAAAAAACIiIQAAAAAAAAAAABIiIiIiIiIiIiIiESIiIAAAAAAAIiIhAAAAAAAAAA AAEiIiIiIiIiIiIiIRIiIgAAAAAAAiIiEAAAAAAAAAAAASIiIiIiIiIiIiIhEiIiAAAAAAACIiIQ AAAAAAAAAAABIiIiIiIiIiIiIiESIiIgAAAAACIiIiEAAAAAAAAAABIiIiIiIiIiIiIiIRIiIiIA AAACIiIiIQAAAAAAAAAAEiIiIiIiIiIiIiIhEiIiIiIiIiIiIiIiEAAAAAAAAAEiIiIiIiIiIiIi IiESIiIiIiIiIiIiIiIhAAAAAAAAEiIiIiIiIiIiIiIiIRIiIiIiIiIiIiIiIiIQAAAAAAEiIiIi IiIiIiIiIiIhEiIiIiIiIiIiIiIiIiEQAAABEiIiIiIiIiIiIiIiIiESIiIiIiIiIiIiIiIiIiER ERIiIiIiIiIiIiIiIiIiIRIiIiIAAAACIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIhEiIiIAAAAAAi IiIiIiIiIiIiIiIiIiIiIhERESIiIiESIiIAAAAAAAIiIiIiIiIiIiIiIiIiIiIRAAAAESIiIRIi IgAAAAAAAiIiIiIiIiIiIiIiIiIiIQAAAAAAEiIhEiIiAAAAAAACIiIiIiIiIiIiIiIiIiIQAAAA AAABIiESIiIAAAAAAAIiIiIiIiIiIiIiIiIiIQAAAAAAAAASIRIiIgAAAAAAAiIiIiIiIiIiIiIi IiIQAAAAAAAAAAEhEiIiAAAAAAACIiIiIiIiIiIiIiIiIhAAAAAAAAAAASESIiIAAAAAAAIiIiIi IiIiIiIiIiIhAAAAAAAAAAAAERIiIgAAAAAAAiIiIiIiIiIiIiIiIiEAAAAAAAAAAAAAEiIiAAAA AAACIiIiIiIiIiIiIiIiIQAAAAAAAAAAAAASIiIAAAAAAAIiIiIiIiIiIiIiIiIhAAAAAAAAAAAA ABIiIgAAAAAAAiIiIiIiIiIiIiIiIiEAAAAAAAAAAAAAEiIiAAAAAAACIiIiIiIiIiIiIiIiIQAA AAAAAAAAABESIiIAAAAAAAIiIiIiIiIiIiIiIiIiEAAAAAAAAAABIRIiIgAAAAAAAiIiIiIiIiIi IiIiIiIQAAAAAAAAAAEhEiIiAAAAAAACIiIiIiIiIiIiIiIiIiEAAAAAAAAAEiESIiIAAAAAAAIi IiIiIiIiIiIiIiIiIhAAAAAAAAEiIRIiIgAAAAAAAiIiIiIiIiIiIiIiIiIiIQAAAAAAEiIhEiIi AAAAAAACIiIiIiIiIiIiIiIiIiIiEQAAABEiIiESIiIAAAAAAAIiIiIiIiIiIiIiIiIiIiIiERER IiIiIRIiIgAAAAAAAiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIhEiIiAAAAAAACIiIiIiIhERESIiIi IiIiIiIiIiIiIiESIiIAAAAAAAIiIiIiIRAAAAESIiIiIiIiIiIiIiIiIRIiIgAAAAAAAiIiIiIQ AAAAAAEiIiIiIiIiIiIiIiIhEiIiAAAAAAACIiIiIQAAAAAAABIiIiIiIiIiIiIiIiESIiIAAAAA AAIiIiIQAAAAAAAAASIiIiIiIiIiIiIiIRIiIgAAAAAAAiIiIQAAAAAAAAAAEiIiIiIiIiIiIiIh EiIiAAAAAAACIiIhAAAAAAAAAAASIiIiIiIiIiIiIiESIiIAAAAAAAIiIhAAAAAAAAAAAAEiIiIi IiIiIiIiIRIiIgAAAAAAAiIiEAAAAAAAAAAAASIiIiIiIiIiIiIhEiIiAAAAAAACIiIQAAAAAAAA AAABIiIiIiIiIiIiIiESIiIAAAAAAAIiIhAAAAAAAAAAAAEiIiIiIiIiIiIiIRIiIgAAAAAAAiIi EAAAAAAAAAAAASIiIiIiIiIiIiIhEiIiAAAAAAACIiIQAAAAAAAAAAABIiIiIiIiIiIiIiESIiIg AAAAACIiIiEAAAAAAAAAABIiIiIiIiIiIiIiIRIiIiIAAAACIiIiIQAAAAAAAAAAEiIiIiIiIiIi IiIhEiIiIiIiIiIiIiIiEAAAAAAAAAEiIiIiIiIiIiIiIiESIiIiIiIiIiIiIiIhAAAAAAAAEiIi IiIiIiIiIiIiIRIiIiIiIiIiIiIiIiIQAAAAAAEiIiIiIiIiIiIiIiIhEiIiIiIiIiIiIiIiIiEQ AAABEiIiIiIiIiIiIiIiIiESIiIiIiIiIiIiIiIiIiEAABIiIiIiIiIiIiIiIiIiIRERERERERER EREREREREQAAERERERERERERERERERERAAAAA8AAAAAAAAADwAAAAAAAAAfgAAAAAAAAH/gAAAAA AAA//AAAAAAAAH/+AAAAAAAA//8AAAAAAAD//wAAAAAAAf//gAAAAAAB//+AAAAAAAH//4AAAAAA Af//gAAAAAAB//+AAAAAAAH//4AAAAAAAP//AAAAAAAA//8AAAAAAAB//gAAAAAAAD/8AAAAAAAA H/gAAAAAAAAH4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD8AAAAAAAAA/8AAAAAA AAH/4AAAAAAAA//wAAAAAAAH//gAAAAAAAf/+AAAAAAAD//8AAAAAAAP//8AAAAAAA///wAAAAAA D///AAAAAAAP//8AAAAAAA///AAAAAAAB//4AAAAAAAH//gAAAAAAAP/8AAAAAAAAf/gAAAAAAAA /8AAAAAAAAA/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAfgAAAAAAAAH/gAAAAAAAA//AAA AAAAAH/+AAAAAAAA//8AAAAAAAD//wAAAAAAAf//gAAAAAAB//+AAAAAAAH//4AAAAAAAf//gAAA AAAB//+AAAAAAAH//4AAAAAAAP//AAAAAAAA//8AAAAAAAB//gAAAAAAAD/8AAAAAAAAH/gAAAAA AAAH4AAAAAAAAAPAAAAAAAAAA8AAAAAoAAAAEAAAACAAAAABACAAAAAAAAAAAAAAAAAAAAAAAAAA AAAAAAAAAAAA/wAAAP8AAAD/AAAA/wAAAP8AAAD/AAAA/wAAAAAAAAAAAAAA/wAAAP8AAAD/AAAA /wAAAP8AAAD/AAAA/wAAAP9RceP/UXHj/1Fx4/9RceP/UXHj/wAAAP8AAAAAAAAAAAAAAP9RceP/ UXHj/1Fx4/9RceP/UXHj/wAAAP8AAAD/UXHj/wAAAP8AAAD/UXHj/wAAAP8AAAAAAAAAAAAAAAAA AAAAAAAA/1Fx4/9RceP/UXHj/1Fx4/8AAAD/AAAA/1Fx4/8AAAD/AAAA/1Fx4/8AAAD/AAAAAAAA AAAAAAAAAAAAAAAAAP9RceP/UXHj/1Fx4/9RceP/AAAA/wAAAP9RceP/UXHj/1Fx4/9RceP/UXHj /wAAAP8AAAAAAAAAAAAAAP9RceP/UXHj/1Fx4/9RceP/UXHj/wAAAP8AAAD/UXHj/wAAAP8AAAD/ UXHj/1Fx4/9RceP/AAAA/wAAAP9RceP/UXHj/1Fx4/8AAAD/AAAA/1Fx4/8AAAD/AAAA/1Fx4/8A AAD/AAAA/1Fx4/9RceP/UXHj/1Fx4/9RceP/UXHj/1Fx4/8AAAD/AAAAAAAAAAAAAAD/AAAA/wAA AP9RceP/AAAA/wAAAP9RceP/UXHj/1Fx4/9RceP/UXHj/1Fx4/8AAAD/AAAAAAAAAAAAAAAAAAAA AAAAAAAAAAD/UXHj/wAAAP8AAAD/UXHj/1Fx4/9RceP/UXHj/1Fx4/9RceP/AAAA/wAAAAAAAAAA AAAAAAAAAAAAAAAAAAAA/1Fx4/8AAAD/AAAA/1Fx4/9RceP/UXHj/1Fx4/9RceP/UXHj/1Fx4/8A AAD/AAAAAAAAAAAAAAD/AAAA/wAAAP9RceP/AAAA/wAAAP9RceP/UXHj/1Fx4/8AAAD/AAAA/1Fx 4/9RceP/UXHj/wAAANkAAAD/UXHj/wAAAP8AAAD/UXHj/wAAAP8AAAD/UXHj/1Fx4/8AAAD/AAAA AAAAAAAAAAD/UXHj/1Fx4/9RceP/UXHj/1Fx4/8AAAD/AAAA/1Fx4/8AAAD/AAAA/1Fx4/8AAAD/ AAAAAAAAAAAAAAAAAAAAAAAAAP9RceP/UXHj/1Fx4/9RceP/AAAA/wAAAP9RceP/AAAA/wAAAP9R ceP/AAAA/wAAAAAAAAAAAAAAAAAAAAAAAAD/UXHj/1Fx4/9RceP/UXHj/wAAAP8AAAD/UXHj/1Fx 4/9RceP/UXHj/1Fx4/8AAAD/AAAAAAAAAAAAAAD/UXHj/1Fx4/9RceP/UXHj/1Fx4/8AAAD/AAAA /wAAAP8AAAD/AAAA/wAAAP8AAAD/AAAA/wAAAAAAAAAAAAAA/wAAAP8AAAD/AAAA/wAAAP8AAAD/ AAAA/wGAAAABgAAAA8AAAAPAAAABgAAAAAAAAAAMAAAAHwAAAB8AAAAMAAAAAAAAAYAAAAPAAAAD wAAAAYAAAAGAAAA=
base64;
    return new Response(base64_decode($favicon), [['Content-Type', 'image/x-icon']]);
   }
  ]],
  ['setRoute', [
   'kriss_bang_xml', 'GET', '/opensearch.xml',
   function () use ($container){
    $router = $container->get('Router');
    $faviconUrl = $router->generate('kriss_bang_favicon', []);
    $searchUrl = urldecode($router->generate('kriss_bang_index', ['bang' => '{searchTerms}']));
    $response = <<<xml
<?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
  <ShortName>KrISS bang</ShortName>
  <Description>KrISS bang</Description>
  <Tags>bang</Tags>
  <Image height="16" width="16" type="image/vnd.microsoft.icon">$faviconUrl</Image>
  <Url type="text/html" template="$searchUrl" />
</OpenSearchDescription>
xml;
    return new Response($response, [['Content-Type', 'application/opensearchdescription+xml']]);
   }
  ]],
  ['setRoute', [
   'kriss_bang_update_csv', ['GET', 'POST'] ,'/update-csv/',
   function () use ($container){
    $request = $container->get('Request');
    if ($request->getMethod() === 'GET'){
     $router = $container->get('Router');
     $faviconUrl = $router->generate('kriss_bang_favicon', []);
     $indexUrl = $router->generate('kriss_bang_index');
     $updateCsvUrl = $router->generate('kriss_bang_update_csv', []);
     $response = <<<html
<!DOCTYPE html>
<html>
  <head>
 <meta charset="utf-8">
 <title>KrISS bang</title>
 <link href="$faviconUrl" rel="shortcut icon" type="image/x-icon" sizes="16x16 64x64">
 <style>body{text-align:center}</style>
  </head>
  <body>
 <header><a href="$indexUrl"><img src="$faviconUrl"/></a></header>
 <section>
   <form action="$updateCsvUrl" method="POST" enctype="multipart/form-data">
  <label>Upload a CSV file:<br> <input type="file" name="file-csv"></label><br> 
  <label><input type="checkbox" name="override"> Check to override existing bang</label><br> 
  <button type="submit">Valider</button>
   </form>
 <footer><a href="//tontof.net/kriss/bang">KrISS bang</a> - A simple and smart (or stupid) <a href="//duckduckgo.com/bang">bang</a> manager. By <a href="//tontof.net">Tontof</a></footer>
  </body>
</html>
html;
     return new Response($response);
    } else {
     if (($handle = fopen($_FILES['file-csv']['tmp_name'], "r")) !== FALSE){
      $model = $container->get('#bang_model');
      set_time_limit(0);
      while (($data = fgetcsv($handle, 0, ';')) !== FALSE){
       $toPersist = false;  
       $bang = $model->findOneBy(['bang' => $data[0]]);
       if (is_null($bang)){
        $bang = $container->get('Bang');
        $bang->bang = $data[0];
        $toPersist = true;
       } else if (isset($_POST['override'])){
        $toPersist = true;
       }
       if ($toPersist && isset($data[1]) && isset($data[2])){
        $bang->url = $data[1];
        $bang->pattern = $data[2];
        $model->persist($bang);
       }
      }
      $model->flush();
     }
     $indexUrl = $container->get('Router')->generate('kriss_bang_index', [], true);
     return new RedirectResponse($indexUrl);
    }
   }
  ]],
  ['setRoute', [
   'kriss_bang_export_csv', 'GET' ,'/export-csv/',
   function () use ($container){
    $request = $container->get('Request');
    $model = $container->get('#bang_model');
    ob_start();
    $csv = fopen('php://output', 'w');
    foreach($model->findBy() as $bang){
      fputcsv($csv, [$bang->bang, $bang->url, $bang->pattern], ';');
    }
    fclose($csv);
    $content = ob_get_contents();
    ob_end_clean();
    return new Response($content, [['Content-Type', 'text/csv'], ['Content-Disposition', 'attachment; filename=bang.csv']]);
   }
  ]]
 ], isset($routerRule['call'])?$routerRule['call']:[])
 ]);
function auth($app, $next = null){
 if (is_null($next)){
  class Admin {
   public $username = 'admin';
   public $password = 'pass';
  }
  $container = $app->getContainer();
  $container->set('Session', [
   'instanceOf' => 'Session',
   'shared' => true,
   'constructParams' => [
    'KrISS',
   ]
  ]);
  modelArray($container, ['admin' => 'Admin']);
  $container->set('#admin_form', [
   'instanceOf' => 'Form',
   'call' => [
    ['setRule', [
     'username', []
    ]],
    ['setRule', [
     'password', ['value' => '']
    ]],
    ['setFormatter', [
     'password',
     function ($value, $formData) use ($container){
      $hashPassword = $container->get('HashPassword');
      return $hashPassword->hash($value, $formData['username']);
     }
    ]]
   ]
  ]);
  $adminValidator = [
   'instanceOf' => 'Validator',
   'call' => [
    ['setConstraints', [
     'password', [['minLength', [4], 'password requires at least 4 characters']],
    ]],
   ],
  ];
  $container->set('#admin_create_validator', $adminValidator);
  $container->set('#admin_update_validator', $adminValidator);
  $container->set('HashPassword', [
   'instanceOf' => 'HashPassword',
  ]);
  $container->set('UserProvider', [
   'instanceOf' => 'UserProvider',
   'shared' => true,
   'constructParams' => [
    ['instance' => '#admin_model'],
   ]
  ]);
  if (!$container->has('Authorization')){
   $container->set('Authorization', [
    'instanceOf' => 'ProtectedRequestAuthorization',
    'shared' => true,
    'constructParams' => [
     ['instance' => 'Authentication'],
     ['instance' => 'Request'],
    ]
   ]);
  }
 } else {
  return function() use ($app, $next){
   $container = $app->getContainer();
   $container->get('Session')->start();
   $authentication = $container->get('Authentication');
   $authorization = $container->get('Authorization');
   $authentication->authenticate();
   $request = $container->get('Request');
   $adminUrl = '/admin/new/';
   $install = empty($container->get('#admin_model')->getData());
   if ($install && $request->getPathInfo() !== '/admin/' && $request->getPathInfo() !== $adminUrl){
    return new RedirectResponse($request->getSchemeAndHttpHost().$request->getBaseUrl().$adminUrl);
   } else if ($authorization->isGranted() || $install){
    return $next();
   } else {
    return $container->get('UnauthorizedResponse');
   }
  };
 }
}
function authSession($app, $next = null){
 if (is_null($next)){
  $container = $app->getContainer();
  $routerRule = $container->getRule('Router');
  $container->set('Router', [
   'call' => array_merge([
    ['setRoute', [
     'logout', 'GET', '/logout/',
     function () use ($container){
      $request = $container->get('Request');
      $authentication = $container->get('Authentication');
      $authentication->deauthenticate();
      return new RedirectResponse($request->getSchemeAndHttpHost().$request->getBaseUrl());
     }
    ]],['setRoute', [
     'login', ['GET', 'POST'], '/login/',
     function () use ($container){
      $authentication = $container->get('Authentication');
      $request = $container->get('Request');
      if ($authentication->isAuthenticated()){
       $redirect = $request->getQuery('redirect', '');
       $query = $request->getQuery();
       unset($query['redirect']);
       $query = http_build_query($query);
       return new RedirectResponse($request->getSchemeAndHttpHost().$request->getBaseUrl().$redirect.(empty($query)?'':'?'.$query));
      }
      return new Response((($request->getMethod() === 'POST')?'Wrong login':'').'<form method="POST">username:<input name="username" type="text"><br>password:<input name="password" type="password"><br><input type="submit"></form>');
     }
    ]]
   ], isset($routerRule['call'])?$routerRule['call']:[])
  ]);
  $container->set('Authentication', [
   'instanceOf' => 'SessionAuthentication',
   'shared' => true,
   'constructParams' => [
    ['instance' => 'UserProvider'],
    ['instance' => 'Request'],
    ['instance' => 'Session'],
    ['instance' => 'HashPassword'],
   ]
  ]);
  $container->set('UnauthorizedResponse', [
   'instanceOf' => 'RedirectResponse',
   'constructParams' => [function() use ($container){
     $request = $container->get('Request');
     $router = $container->get('Router');
     $params = empty($request->getPathInfo())?$request->getQuery():array_merge(['redirect' => $request->getPathInfo()], $request->getQuery());
     return $router->generate('login', $params);
    }]
  ]);
 }
 return auth($app, $next);
}
function authBasic($app, $next = null){
 if (is_null($next)){
  $container = $app->getContainer();
  $routerRule = $container->getRule('Router');
  $container->set('Router', [
   'call' => array_merge([
    ['setRoute', [
     'logout', 'GET', '/logout/',
     function () use ($container){
      $request = $container->get('Request');
      $authentication = $container->get('Authentication');
      $authentication->deauthenticate();
      return new RedirectResponse($request->getSchemeAndHttpHost().$request->getBaseUrl());
     }
    ]],['setRoute', [
     'login', 'GET', '/login/',
     function () use ($container){
      $authentication = $container->get('Authentication');
      $authentication->isAuthenticated();
      if ($authentication->isAuthenticated()){
       $request = $container->get('Request');
       return new RedirectResponse($request->getSchemeAndHttpHost().$request->getBaseUrl());
      }
      return $container->get('UnauthorizedResponse');
     }
    ]]
   ], isset($routerRule['call'])?$routerRule['call']:[])
  ]);
  $container->set('Authentication', [
   'instanceOf' => 'BasicAuthentication',
   'shared' => true,
   'constructParams' => [
    ['instance' => 'UserProvider'],
    ['instance' => 'Request'],
    ['instance' => 'Session'],
    ['instance' => 'HashPassword'],
   ]
  ]);
  $container->set('UnauthorizedResponse', [
   'instanceOf' => 'BasicUnauthorizedResponse',
   'constructParams' => [
    ['instance' => 'Session'],
    ['instance' => 'Request']
   ]
  ]);
 }
 return auth($app, $next);
}
function modelArray($container, $models = []){  
 foreach($models as $slug => $class){
  $id = '#'.$slug.'_model';
  if (!$container->has($id)){
   $container->set($id, [
    'instanceOf' => 'ArrayModel',
    'shared' => true,
    'constructParams' => [
     $slug,
     $class
    ]
   ]);
  }
 }    
}
class RouterAutoFormView{
 private $viewModel;
 private $router;
 public function __construct($viewModel, $router){
  $this->viewModel = $viewModel;
  $this->router = $router;
 }
 private function stringify($data){
  $string = ['<ul>'];
  foreach($data as $key => $item){
   $attr = '';
   if (is_object($item) || is_array($item)){
    $string[] = '<li'.$attr.'>'.$key.': '.$this->stringify($item).'</li>';
   } else {
    $string[] = '<li'.$attr.'>'.$key.': '.$item.'</li>';
   }
  }
  $string[] = '</ul>';
  return join('', $string);
 }
 public function render(){
  $result = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>KrISS MVVM</title></head><body>';
  $data = $this->viewModel->getData();
  $data = [$data['slug'] => $data['form']];
  $errors = $this->viewModel->getErrors();
  $result .= $this->stringify($errors);
  if (!is_null($data)){
   foreach($data as $slug => $object){
    $result .= '<ul><li><a href="'.$this->router->generate('autoroute_index', ['slug' => $slug]).'">'.$slug.'</a></li></ul>';
    if (!is_null($object)){
     $method = $object['*']['method'];
     $url = $object['*']['action'];
     $result .= '<form action="'.$url.'" id="'.$slug.'" method="'.$method.'">';
     if (isset($object['_method']['value'])) $method = $object['_method']['value'];
     if ($method != 'DELETE'){
      foreach($object as $name => $value){
       if ($name != 'id' && $name[0] != '*'){
        $result .= '<div><label>'.$name.': <input name="'.$name.'" value="'.$value['value'].'" type="'.$value['type'].'"/></label></div>';
       }
      }
     } else {
      foreach($object as $name => $value){
       if ($name != 'id' && $name[0] != '*'){
        if ($name === '_method'){
         $result .= '<input name="'.$name.'" value="'.$value['value'].'" type="'.$value['type'].'"/>';
        } else {
         $result .= '<div>'.$name.': '.$value['value'].'</div>';
        }
       }
      }
     }
     $result .= '<input type="submit" value="'.$method.'"/>';
     $result .= '</form>';
    }
   }
  }
  $result .= '</html>';
  return [[], $result];
 }
}
class RouterAutoView{
 private $viewModel;
 private $request;
 private $router;
 public function __construct($viewModel, $router, $request){
   $this->viewModel = $viewModel;
   $this->request = $request;
   $this->router = $router;
 }
 private function classToAttr($class){
  return strtolower($class);
 }
 private function pagination($slug, $pagination){
  $current = $pagination['current'];
  $total = $pagination['total'];
  $string = [];
  if ($total > 1){
   $route = 'autoroute_index';
   $string[] = '<ul id="pagination-'.$slug.'" class="pagination">';
   if ($current > 1){
    $page = $current - 1;
    $url = $this->router->generate($route, array_merge($this->request->getQuery(), ['slug' => $slug, 'page' => $page]));
    $string[] = '<li class="previous"><a href="'.$url.'">previous</a></li>';
   }
   $page = $current;
   $string[] = '<li class="current">'.$page.'/'.$total.'</li>';
   if ($current < $total){
    $page = $current + 1;
    $url = $this->router->generate($route, array_merge($this->request->getQuery(), ['slug' => $slug, 'page' => $page]));
    $string[] = '<li class="next"><a href="'.$url.'">next</a></li>';
   }
   $string[] = '</ul>';
  }
  return join('', $string);
 }
 private function data($slug, $data){
  $string[] = '<ul>';
  foreach($data as $key => $item){
   if ($key == 'pagination'){ $string[] = 'pagination';}
   if ($key == 'slug'){ $string[] = 'slug';}
   $attr = is_object($item)?$this->classToAttr(get_class($item)):(!is_numeric($key)?$key:'');
   $attr = empty($attr)?'':($slug === true?' id="'.$attr.'"':' class="'.$attr.'"');
   if (is_object($item) || is_array($item)){
    $routeIndex = 'autoroute_index_id';
    $routeEdit = 'autoroute_edit_id';
    $routeDelete = 'autoroute_delete_id';
    try {
     $urlIndex = $this->router->generate($routeIndex, ['slug' => $slug, 'id' => $key]);
     $urlEdit = $this->router->generate($routeEdit, ['slug' => $slug, 'id' => $key]);
     $urlDelete = $this->router->generate($routeDelete, ['slug' => $slug, 'id' => $key]);
    } catch (\Exception $e){
     $routeIndex = 'autoroute_index';
     $routeEdit = 'autoroute_edit';
     $routeDelete = 'autoroute_delete';
     $urlIndex = $this->router->generate($routeIndex, ['slug' => $slug]);
     $urlEdit = $this->router->generate($routeEdit, ['slug' => $slug]);
     $urlDelete = $this->router->generate($routeDelete, ['slug' => $slug]);
    }
    $string[] = '<li'.$attr.'><a href="'.$urlIndex.'">'.$key.'</a>: <a href="'.$urlEdit.'">edit</a> <a href="'.$urlDelete.'">delete</a> '.$this->data($slug, $item).'</li>';
   } else {
    if ($key != 'password'){
     $string[] = '<li'.$attr.'>'.$key.': '.$item.'</li>';
    }
   }   
  }
  $string[] = '</ul>';
  return join('', $string);
 }
 private function stringify($data){
  return
   '<!DOCTYPE html><html><head><meta charset="utf-8"><title>KrISS MVVM</title></head><body>'.
   '<a href="'.$this->request->getSchemeAndHttpHost().$this->request->getBaseUrl().'">index</a>'.
   (isset($data['pagination'])?$this->pagination($data['slug'], $data['pagination']):'').
   '<ul id="'.$data['slug'].'">'.
   '<li><a href="'.$this->router->generate('autoroute_index', ['slug' => $data['slug']]).'">'.$data['slug'].'</a>: <a href="'.$this->router->generate('autoroute_edit', array_merge($this->request->getQuery(), ['slug' => $data['slug']])).'">edit</a> <a href="'.$this->router->generate('autoroute_new', ['slug' => $data['slug']]).'">new</a>'.
   $this->data($data['slug'], $data['data']).'</li>'.
   '</ul></html>';
 }
 public function render(){return [[], $this->stringify($this->viewModel->getData())];}
}
class AutoRoute {
 protected $classes;
 protected $singleClasses;
 protected $listClasses;
 protected $prefix;
 protected $prefixId;
 protected $resetModel = false;
 protected $rules = [];
 public function __construct($container, $autoSingleClasses = [], $autoListClasses = []){
  $this->container = $container;
  $this->request = $container->get('Request');
  $this->router = $container->get('Router');
  $this->singleClasses = $autoSingleClasses;
  $this->listClasses = $autoListClasses;
  $this->classes = array_merge($this->singleClasses, $this->listClasses);
  modelArray($this->container, $this->classes);
  $slugs = join(array_merge(array_filter(array_keys($this->singleClasses), 'is_string'),array_filter(array_keys($this->listClasses), 'is_string')), '|');
  $slugsId = join(array_filter(array_keys($this->listClasses), 'is_string'), '|');
  $this->prefix = '<slug'.(empty($slugs)?'':':'.$slugs).'>';
  $this->prefixId = '<slug'.(empty($slugsId)?'':':'.$slugsId).'>';
  $this->addResponses();
 }
 private function addResponses(){
  $this->addResponse($this->router, 'GET', '/'.$this->prefix.'/', 'index');
  $this->addResponse($this->router, 'GET', '/'.$this->prefix.'/new/', 'new');
  $this->addResponse($this->router, 'GET', '/'.$this->prefix.'/edit/', 'edit');
  $this->addResponse($this->router, 'POST', '/'.$this->prefix.'/', 'create');
  $this->addResponse($this->router, 'PUT', '/'.$this->prefix.'/', 'update');
  $this->addResponse($this->router, 'GET', '/'.$this->prefix.'/delete/', 'delete');
  $this->addResponse($this->router, 'DELETE', '/'.$this->prefix.'/', 'remove');
  $this->addResponse($this->router, 'GET', '/'.$this->prefixId.'/<id:\d+>/', 'index_id');
  $this->addResponse($this->router, 'GET', '/'.$this->prefixId.'/<id:\d+>/edit/', 'edit_id');
  $this->addResponse($this->router, 'PUT', '/'.$this->prefixId.'/<id:\d+>/', 'update_id');
  $this->addResponse($this->router, 'GET', '/'.$this->prefixId.'/<id:\d+>/delete/', 'delete_id');
  $this->addResponse($this->router, 'DELETE', '/'.$this->prefixId.'/<id:\d+>/', 'remove_id');
 }
 private function addResponse($router, $method, $pattern, $action){
  $autoroute = $this;
  $router->setRoute(
   'autoroute_'.$action, $method, $pattern,
   function ($slug, $id = null) use ($autoroute, $action){
    $fun = $autoroute->getFunction($action);
    $autoroute->resetModel = false;
    call_user_func_array(array($autoroute, 'auto'.$fun), [$slug, $action, $id]);
    return $autoroute->getRouteResponse($slug, $action);
   }
  );
 }
 private function getRouteResponse($slug, $action){
  $autoRoute = '#auto_route_' . $slug . '_' . $action;
  $class = $this->getClass($slug);
  if (!is_null($class) && !$this->container->has($class)) return new Response('Invalid autoroute: '.$autoRoute);
  if (!$this->container->has($autoRoute)){
   $this->generate($slug, $action);
  }
  if (!$this->container->has($autoRoute)){
   $this->generate($slug, $action);
   $this->container->set($autoRoute, [
    'instanceOf' => 'ViewControllerResponse'
   ]);
  }
  $model = $this->container->get('#'.$slug.'_model');
  $form = null;
  if ($this->container->has('#'.$slug.'_'.$action.'_form')){
   $params = array_merge($this->router->getRouteParameters(),$this->request->getQuery());
   $method = 'POST';
   switch($action){
   case 'edit':
   case 'update':
   case 'edit_id':
   case 'update_id':
    $method = 'PUT';
    break;
   case 'delete':
   case 'remove':
   case 'delete_id':
   case 'remove_id':
    $method = 'DELETE';
    break;
   }
   $form = $this->container->get('#'.$slug.'_'.$action.'_form', [$this->container->get($this->getClass($slug)), $method, $this->router->generate('autoroute_'.(isset($params['id'])?'index_id':'index'), $params)]);
  }
  $validator = null;
  if ($this->container->has('#'.$slug.'_'.$action.'_validator'))
   $validator = $this->container->get('#'.$slug.'_'.$action.'_validator');
  $formAction = null;
  if ($this->container->has('#'.$slug.'_'.$action.'_form_action'))
   $formAction = $this->container->get('#'.$slug.'_'.$action.'_form_action', [$model, $form, $this->request, $this->resetModel]);
  $viewModel = $this->container->get('#'.$slug.'_'.$action.'_view_model', [$model, $form, $validator]);
  $controller = null;
  if ($this->container->has('#'.$slug.'_'.$action.'_controller')){
   $rule = $this->container->getRule('#'.$slug.'_'.$action.'_controller');
   switch($rule['instanceOf']){
   case 'ListController':
    $controller = $this->container->get('#'.$slug.'_'.$action.'_controller', [$viewModel, $this->request, $this->router]);
    break;
   case 'FormController':
    $controller = $this->container->get('#'.$slug.'_'.$action.'_controller', [$viewModel, $this->request, $formAction]);
    break;
   case 'FormListController':
    $controller = $this->container->get('#'.$slug.'_'.$action.'_controller', [$viewModel, $this->request, $formAction, $this->router]);
    break;
   }
  }
  $view = $this->container->get('#'.$slug.'_'.$action.'_view', [$viewModel, $this->router, $this->request]);
  return $this->container->get($autoRoute, [$view, $controller]);
 }
 private function getFunction($name){
  return preg_replace_callback(
   '/(^|_)([a-z])/'
   , function ($matches){
    return strtoupper($matches[2]);
   }
   , $name
  );
 }
 private function _autoEdit($slug, $action, $id = null){
  $this->generateModel($slug, $action);
  $this->generateForm($slug, $action, 'PUT');
  $this->generateFormViewModel($slug, $action, null);
  $this->generateFormView($slug, $action);
  $this->generateListController($slug, $action);
 }
 private function _autoUpdate($slug, $action, $id = null){
  $this->_autoEdit($slug, $action, $id);
  $this->generateValidator($slug, $action);
  $this->generatePersistFormAction($slug, $action);
  $this->generateFormListController($slug, $action);
 }
 private function _autoDelete($slug, $action, $id = null){
  $this->_autoEdit($slug, $action, $id);
  $this->generateForm($slug, $action, 'DELETE');
 }
 private function _autoRemove($slug, $action, $id = null){
  $this->_autoDelete($slug, $action, $id);
  $this->generateValidator($slug, $action);
  $this->generateFormListController($slug, $action);
 }
 private function autoIndex($slug, $action, $id = null){
  $this->generateModel($slug, $action);
  $this->generateViewModel($slug, $action, $id);
  $this->generateView($slug, $action);
  $this->generateListController($slug, $action);
 }
 private function autoIndexId($slug, $action, $id){$this->autoIndex($slug, $action, $id);}
 private function autoNew($slug, $action){
  $this->generateModel($slug, $action);
  $this->generateForm($slug, $action, 'POST');
  $this->generateFormViewModel($slug, $action, null);
  $this->generateFormView($slug, $action);
 }
 private function autoCreate($slug, $action){
  $this->autoNew($slug, $action);
  if (in_array($slug, array_keys($this->singleClasses))){
   $this->resetModel = true;
  }
  $this->generateValidator($slug, $action);
  $this->generatePersistFormAction($slug, $action);
  $this->generateFormController($slug, $action);
 }
 private function autoEdit($slug, $action){$this->_autoEdit($slug, $action);}
 private function autoEditId($slug, $action, $id){$this->_autoEdit($slug, $action, $id);}
 private function autoUpdate($slug, $action){$this->_autoUpdate($slug, $action);}
 private function autoUpdateId($slug, $action, $id){$this->_autoUpdate($slug, $action, $id);}
 private function autoDelete($slug, $action){$this->_autoDelete($slug, $action);}
 private function autoRemove($slug, $action){
  $this->_autoRemove($slug, $action);
  $this->generateRemoveFormAction($slug, $action);
 }
 private function autoDeleteId($slug, $action, $id){$this->_autoDelete($slug, $action, $id);}
 private function autoRemoveId($slug, $action, $id){
  $this->_autoRemove($slug, $action, $id);
  $this->generateRemoveFormAction($slug, $action);
 }
 private function generateModel($slug, $action){
  $this->rules[$slug][$action]['model'] = [
   'instanceOf' => 'ArrayModel',
   'shared' => true,
   'constructParams' => [
    $slug,
    $this->getClass($slug)
   ]
  ];
 }
 private function generateViewModel($slug, $action, $id = null){
  $this->rules[$slug][$action]['view_model'] = [
   'instanceOf' => 'ViewModel',
  ];
 }
 private function generateView($slug, $action){
  $this->rules[$slug][$action]['view'] = [
   'instanceOf' => 'RouterAutoView',
  ];
 }
 private function generateListController($slug, $action){
  $this->rules[$slug][$action]['controller'] = [
   'instanceOf' => 'ListController',
  ];
 }
 private function generateForm($slug, $action, $method){
  $this->rules[$slug][$action]['form'] = [
   'instanceOf' => 'Form',
  ];
 }
 private function generateFormViewModel($slug, $action, $validator = null){
  $this->rules[$slug][$action]['view_model'] = [
   'instanceOf' => 'FormViewModel',
  ];
 }
 private function generateFormView($slug, $action){
  $this->rules[$slug][$action]['view'] = [
   'instanceOf' => 'RouterAutoFormView',
  ];
 }
 private function generatePersistFormAction($slug, $action){
  $this->rules[$slug][$action]['form_action'] = [
   'instanceOf' => 'PersistFormAction',
  ];
 }
 private function generateRemoveFormAction($slug, $action){
  $this->rules[$slug][$action]['form_action'] = [
   'instanceOf' => 'RemoveFormAction',
  ];
 }
 private function generateFormListController($slug, $action){
  $this->rules[$slug][$action]['controller'] = [
   'instanceOf' => 'FormListController',
  ];
 }
 private function generateFormController($slug, $action){
  $this->rules[$slug][$action]['controller'] = [
   'instanceOf' => 'FormController',
  ];
 }
 private function generateValidator($slug, $action){
  $this->rules[$slug][$action]['validator'] = [
   'instanceOf' => 'Validator',
  ];
 }
 private function generate($slug, $action){
  foreach($this->rules[$slug][$action] as $key => $rule){
   $classKey = '$'.strtolower($this->getClass($slug)).'_'.$key;
   $classActionKey = '$'.strtolower($this->getClass($slug)).'_'.$action.'_'.$key;
   $identifierKey = '#'.$slug.'_'.$key;
   $identifierActionKey = '#'.$slug.'_'.$action.'_'.$key;
   if ($this->container->has($classKey)){
    $rule = array_replace_recursive($rule, $this->container->getRule($classKey));
   }
   if ($this->container->has($classActionKey)){
    $rule = array_replace_recursive($rule, $this->container->getRule($classActionKey));
   }
   if ($this->container->has($identifierKey)){
    $rule = array_replace_recursive($rule, $this->container->getRule($identifierKey));
   }
   if ($this->container->has($identifierActionKey)){
    $rule = $this->container->getRule($identifierActionKey);
   }
   if ($key == 'model'){
    $this->container->set($identifierKey, $rule);
   } else {
    $this->container->set($identifierActionKey, $rule);
   }
  }
 }
 private function getClass($slug){
  if (array_key_exists($slug, $this->classes)) return $this->classes[$slug];
  else return strtolower($slug);
 }
}
function routerAuto($app, $next = null, $singleClasses = [], $listClasses = []){
 if (!is_null($next)){
  return function() use ($app, $next, $singleClasses, $listClasses){
   $container = $app->getContainer();
   $routerRule = $container->getRule('Router');
   $routerRule = isset($routerRule['call'])?$routerRule['call']:[];
   $newRule = [
    'setRoute', [
     'index', 'GET', '/',
     function () use ($container, $singleClasses, $listClasses){
      $router = $container->get('Router');
      $request = $container->get('Request');
      $body = '';
      if (is_array($singleClasses)){
       foreach($singleClasses as $slug => $class){
        $body .= '<a href="'.$router->generate('autoroute_index', ['slug' => $slug]).'">'.$class.' ('.$slug.')</a><br>';
       }
      }
      if (is_array($listClasses)){
       foreach($listClasses as $slug => $class){
        $body .= '<a href="'.$router->generate('autoroute_index', ['slug' => $slug], $request).'">'.$class.' ('.$slug.')</a><br>';
       }
      }
      return new Response($body);
     }
    ]];
   $addNewRule = true;
   foreach ($routerRule as $rule){
    if ($rule[0] === 'setRoute' && $rule[1][2] == '/' ){
     $addNewRule = false;
    }
   }
   if ($addNewRule){
    $routerRule = array_merge($routerRule, [$newRule]);
   }
   $container->set('Router', [
    'instanceOf' => 'RequestRouter',
    'shared' => true,
    'constructParams' => [
     ['instance' => 'Request'],
    ],
    'call' => $routerRule
   ]);
   new AutoRoute($container, $singleClasses, $listClasses);
   return $next();
  };
 }
}
if (isset($app)) $app->addPlugin('routerAuto');
function responseException($app, $next = null){
 if (!is_null($next)){
  return function() use ($app, $next){
   $container = $app->getContainer();
   try {
    return $next();
   } catch(\Exception $e){
    if (!$container->has('ExceptionResponse')){
     $container->set('ExceptionResponse', [
      'instanceOf' => 'ExceptionResponse',
     ]);
    }    
    $container->set('ExceptionResponse', [
     'constructParams' => [$e]
    ]);   
    return $container->get('ExceptionResponse');
   }
  };
 }
}
if (isset($app)) $app->addPlugin('responseException');
function config($app){
 $container = $app->getContainer();
 class Config {
  public $auth = 'authSession';
  public $visibility = 'protected';
 }
 modelArray($container, ['config' => 'Config']);
 $model = $container->get('#config_model');
 $config = $model->findOneBy();
 if (is_null($config)) $config = $container->get('Config');
 if (!empty($config->auth)){
  $app->addPlugin($config->auth);
 }
 $configValidator = [
  'instanceOf' => 'Validator',
  'call' => [
   ['setConstraints', [
    'visibility', [['inArray', [['protected', 'private']], '"protected" or "private"']],
   ]],
   ['setConstraints', [
    'auth', [['inArray', [['', 'authSession', 'authBasic']], '"authSession", "authBasic" or empty: ""']],
   ]],
  ],
 ];
 $container->set('#config_create_validator', $configValidator);
 $container->set('#config_update_validator', $configValidator);
 $container->set('Authorization', [
  'instanceOf' => ''.ucfirst($config->visibility).'RequestAuthorization',
  'shared' => true,
  'constructParams' => [
   ['instance' => 'Authentication'],
   ['instance' => 'Request'],
  ]
 ]);
}
config($app);
$app->configPlugin('routerAuto', [['admin' => 'Admin', 'config' => 'Config'], ['bang' => 'Bang']]);
$app->run();