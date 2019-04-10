<?php
declare(strict_types=1);

namespace Rarst\Sideface\Run;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Rarst\Sideface\RunsHandler;

class RunServiceProvider implements ServiceProviderInterface
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
        $container['handler.runs'] = static function () {
            return new RunsHandler();
        };

        $container['domain.run'] = static function (Container $container) {
            return new RunDomainLogic($container['handler.runs']);
        };

        $container['action.run'] = static function (Container $container) {
            return new RunAction($container['domain.run'], $container['responder']);
        };
    }
}
