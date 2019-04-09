<?php
declare(strict_types=1);

namespace Rarst\Sideface\Responder;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Slim\Http\Uri;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

class ResponderServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container A container instance
     */
    public function register(Container $container): void
    {
        $container['view'] = static function (Container $container) {
            $view   = new Twig(__DIR__ . '/../../src/twig');
            $router = $container['router'];
            $uri    = Uri::createFromEnvironment($container['environment']);
            $view->addExtension(new TwigExtension($router, $uri));

            return $view;
        };

        $container['responder'] = static function (Container $container) {
            return new Responder($container['view']);
        };
    }
}
