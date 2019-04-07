<?php
namespace Rarst\Sideface;

use Silex\Application\TwigTrait;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;

class Application extends \Silex\Application
{
    use TwigTrait;

    public function __construct(array $values = [ ])
    {
        parent::__construct();

        $defaults = [ ];

        $defaults['handler.runs'] = $this->share(function () {
            return new RunsHandler();
        });

        $this->register(new TwigServiceProvider());
        $this->register(new UrlGeneratorServiceProvider());

        foreach (array_merge($defaults, $values) as $key => $value) {
            $this[$key] = $value;
        }
    }
}
