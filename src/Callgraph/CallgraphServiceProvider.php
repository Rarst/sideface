<?php
declare(strict_types=1);

namespace Rarst\Sideface\Callgraph;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class CallgraphServiceProvider implements ServiceProviderInterface
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
        $container['action.callgraph'] = static function (Container $container) {
            return new CallgraphAction($container['handler.runs'], $container['responder']);
        };
    }
}
