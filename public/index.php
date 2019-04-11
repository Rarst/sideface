<?php

namespace Rarst\Sideface;

use Pimple\Container;
use Rarst\Sideface\Callgraph\CallgraphServiceProvider;
use Rarst\Sideface\Responder\ResponderServiceProvider;
use Rarst\Sideface\Run\RunServiceProvider;
use Slim\App;

require __DIR__ . '/../vendor/autoload.php';

$app = new App([
    'settings' => [
        'displayErrorDetails' => true,
    ]
]);

/** @var Container $container */
$container = $app->getContainer();
$container->register(new RunServiceProvider());
$container->register(new CallgraphServiceProvider());
$container->register(new ResponderServiceProvider());

$app->get('/[{source}]', 'action.run:list')
    ->setName('run:list');

// TODO symbol callgraphs?
$app->get('/{source}/{run1}-{run2}/callgraph[{callgraphType}]', 'action.callgraph:diff')
    ->setName('callgraph:diff');

$app->get('/{source}/{run1}-{run2}[/{symbol}]', 'action.run:diff')
    ->setName('run:diff');

// TODO symbol callgraphs?
$app->get('/{source}/{run}/callgraph[{callgraphType}]', 'action.callgraph:show')
    ->setName('callgraph:show');

$app->get('/{source}/{run}[/{symbol}]', 'action.run:show')
    ->setName('run:show');

$app->run();
