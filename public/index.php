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

$app->get('[/{source}]', 'action.run:list')
    ->setName('runs_list');

$app->get('/{source}/{run1}-{run2}/callgraph[{callgraphType}]', 'action.callgraph:diff')
    ->setName('diff_callgraph');

$app->get('/{source}/{run1}-{run2}[/{symbol}]', 'action.run:diff')
    ->setName('diff_runs');

$app->get('/{source}/{run}/callgraph[{callgraphType}]', 'action.callgraph:show')
    ->setName('single_callgraph');

$app->get('/{source}/{run}[/{symbol}]', 'action.run:show')
    ->setName('single_run');

$app->run();
