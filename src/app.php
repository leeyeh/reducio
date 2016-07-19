<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/cloud.php';

/*
 * A simple Slim based sample application
 *
 * See Slim documentation:
 * http://www.slimframework.com/docs/
 */

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Slim\Views\PhpRenderer;
use \LeanCloud\LeanClient;
use \LeanCloud\Storage\CookieStorage;
use \LeanCloud\Engine\SlimEngine;
use \LeanCloud\LeanQuery;
use \LeanCloud\LeanObject;

$app = new \Slim\App();
// 禁用 Slim 默认的 handler，使得错误栈被日志捕捉
unset($app->getContainer()['errorHandler']);

LeanClient::initialize(
    getenv("LC_APP_ID"),
    getenv("LC_APP_KEY"),
    getenv("LC_APP_MASTER_KEY")
);
// 将 sessionToken 持久化到 cookie 中，以支持多实例共享会话
LeanClient::setStorage(new CookieStorage());

SlimEngine::enableHttpsRedirect();
$app->add(new SlimEngine());
$app->add(new \Tuupola\Middleware\Cors([
    "origin" => ["*"],
    "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
    "headers.allow" => ['Content-Type'],
    "headers.expose" => [],
    "credentials" => true,
    "cache" => 86400,
]));

// 使用 Slim/PHP-View 作为模版引擎
$container = $app->getContainer();
$container["view"] = function($container) {
    return new \Slim\Views\PhpRenderer(__DIR__ . "/views/");
};

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, "index.phtml", array(
    ));
});

function getRandomId() {
    return substr(preg_replace('/[^0-9a-zA-Z]/', '', base64_encode(random_bytes(12))), 0, 7);
};

$createURL = function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $originalUrl = $data["original"];
    error_log("[POST] $originalUrl");

    $query = new LeanQuery("Url");
    $query->equalTo("original", $originalUrl);
    $urls = $query->limit(1)->find();

    if (empty($urls)) {
        $url = new LeanObject("Url");
        $url->set("original", $originalUrl);
        $url->set("short", getRandomId());
        $url->save();
    } else {
        $url = $urls[0];
    }

    $path = $url->get("short");
    error_log("[POST] $originalUrl -> $path done");
    return $response->withStatus(200)->withHeader("Content-Type", "application/json")->write(json_encode([
        "short" => $path,
    ]));
};

$app->post('/', $createURL);
$app->post('/urls', $createURL); // alias

$app->put('/{path}', function(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $originalUrl = $data["original"];
    $path = $request->getAttribute('path');
    error_log("[POST] $originalUrl -> $path");

    $query = new LeanQuery("Url");
    $query->equalTo("short", $path);
    $urls = $query->find();

    if (empty($urls)) {
        $url = new LeanObject("Url");
        $url->set("original", $originalUrl);
        $url->set("short", $path);
        $url->save();
    } else {
        $url = $urls[0];
        if ($url->get('original') !== $originalUrl) {
            return $response->withStatus(409)->withHeader("Content-Type", "application/json")->write(json_encode([
                "message" => "url $path already exists"
            ]));
        }
    }

    $path = $url->get("short");
    error_log("[PUT] $originalUrl -> $path done");
    return $response->withStatus(200)->withHeader("Content-Type", "application/json")->write(json_encode([
        "short" => $path,
    ]));
});

$app->get('/{path}', function (Request $request, Response $response) {
    $path = $request->getAttribute('path');
    error_log("[GET] $path");
    $query = new LeanQuery("Url");
    $query->equalTo("short", $path);
    $urls = $query->find();
    if (empty($urls)) {
        error_log("[GET] $path not found");
        return $response->withStatus(404)->write("Not Found");
    } else {
        $originalUrl = $urls[0]->get('original');
        error_log("[GET] $path -> $originalUrl");
        return $response->withStatus(302)->withHeader("Location", $originalUrl);
    }
});

$app->run();
