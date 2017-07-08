<?php
  
include('../../mvvm/autoload.php');

use Kriss\Core\App\App;
use Kriss\Core\Container\Container;
use Kriss\Core\Response\Response;
use Kriss\Core\Response\RedirectResponse;

// define what a Bang is
class Bang {
    public $bang = '!?';
    public $url = 'https://duckduckgo.com';
    public $pattern = 'https://duckduckgo.com/?q=kriss_bang';
}

$app = new App(new Container());

$container = $app->getContainer();
  
// a bang is unique if it's not present in model or
// the modification occurs on the same bang
$isUnique = function($value, $data) use ($container) {
    $model = $container->get('#bang_model');
    $bang = $model->findOneBy(['bang' => $value]);
    $validId = true;
    if (!is_null($bang)) {
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
// For object of class Bang, check if bang is unique
$container->set('$bang_validator', [
    'instanceOf' => 'Kriss\\Core\\Validator\\Validator',
    'call' => [
        ['setConstraints', [
            'bang', [['closure', [$isUnique], 'bang already exists']],
        ]],
    ],
]);

// define custom routes of KrISS bang app
$routerRule = $container->getRule('Router');
$container->set('Router', [
    'call' => array_merge([
        ['setRoute', [
            'kriss_bang_index', 'GET', '/',
            function () use ($container) {
                $request = $container->get('Request');
                $query = $request->getQuery();
                if (isset($query['bang'])) {
                    $router = $container->get('Router');
                    if (empty($query['bang'])) return new RedirectResponse($router->generate('kriss_bang_index'));
                    $model = $container->get('#bang_model');
                    $str = null;
                    if (preg_match('/![^ ]*/', $query['bang'], $matches)) {
                        $str = $matches[0];
                    } else {
                        $str = '!?';
                    }
                    $bang = $model->findOneBy(['bang' => $str]);
                    if (is_null($bang)) {
                        $bang = $container->get('Bang');
                        $bang->pattern .= ' '.$str;
                    }
                    $search = trim(preg_replace('/![^ ]*/', '', $query['bang']));
                    if (empty($search)) {
                        return new RedirectResponse($bang->url);
                    } else {
                        return new RedirectResponse(trim(preg_replace('/kriss_bang/', urlencode($search), $bang->pattern)));
                    }
                }
                $router = $container->get('Router');

                $authLink = '';
                if ($container->has('Authentication')) {
                    $authentication = $container->get('Authentication');
                    $authUrl = $router->generate('login');
                    $auth = 'Login';
                    if ($authentication->isAuthenticated()) {
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
    <header><a href="$indexUrl"><img src="$faviconUrl" alt="favicon"/></a></header>
    <section>
      <h1>KrISS bang</h1>
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
            function () use ($container) {
                $favicon = base64_encode(file_get_contents('inc/favicon.ico'));
                return new Response(base64_decode($favicon), [['Content-Type', 'image/x-icon']]);
            }
        ]],
        ['setRoute', [
            'kriss_bang_xml', 'GET', '/opensearch.xml',
            function () use ($container) {
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
            function () use ($container) {
                $request = $container->get('Request');
                if ($request->getMethod() === 'GET') {
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
    <header><a href="$indexUrl"><img src="$faviconUrl" alt="favicon"/></a></header>
    <section>
      <h1>Upload a CSV file</h1>
      <form action="$updateCsvUrl" method="POST" enctype="multipart/form-data">
        <input type="file" name="file-csv"><br>
        <label><input type="checkbox" name="override"> Check to override existing bang</label><br> 
        <button type="submit">Valider</button>
      </form>
      <footer><a href="//tontof.net/kriss/bang">KrISS bang</a> - A simple and smart (or stupid) <a href="//duckduckgo.com/bang">bang</a> manager. By <a href="//tontof.net">Tontof</a></footer>
    </section>
  </body>
</html>
html;
                    return new Response($response);
                } else {
                    if (($handle = fopen($_FILES['file-csv']['tmp_name'], "r")) !== FALSE) {
                        $model = $container->get('#bang_model');
                        set_time_limit(0);
                        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                            $toPersist = false;        
                            $bang = $model->findOneBy(['bang' => $data[0]]);
                            if (is_null($bang)) {
                                $bang = $container->get('Bang');
                                $bang->bang = $data[0];
                                $toPersist = true;
                            } else if (isset($_POST['override'])) {
                                $toPersist = true;
                            }
                            if ($toPersist && isset($data[1]) && isset($data[2])) {
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
            function () use ($container) {
                $request = $container->get('Request');
                $model = $container->get('#bang_model');
                ob_start();
                $csv = fopen('php://output', 'w');
                foreach($model->findBy() as $bang) {
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
  
    include_once('../../mvvm/plugins/config.php');
    
config($app);
$app->configPlugin('routerAuto', [['admin' => 'Admin', 'config' => 'Config'], ['bang' => 'Bang']]);
$app->run();