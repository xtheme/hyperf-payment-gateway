<?php
/**
 * Initialize a dependency injection container that implemented PSR-11 and return the container.
 */

declare(strict_types=1);

use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;

$container = new Container((new DefinitionSourceFactory())());

return Hyperf\Context\ApplicationContext::setContainer($container);
