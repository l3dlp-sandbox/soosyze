<?php

use Soosyze\Components\Http\Uri;
use Soosyze\Components\Router\Router;
use Soosyze\Components\Validator\Validator;
use SoosyzeCore\System\Services\Alias;

class RouteValue extends \Soosyze\Components\Validator\Rule
{
    protected function messages(): array
    {
        return [
            'must' => 'The value of :label must be a route.',
            'not'  => 'The value of :label should not be a route.'
        ];
    }

    protected function test(string $keyRule, $value, $args, bool $not): void
    {
        $app    = \Core::getInstance();
        /** @var Router $router */
        $router = $app->get(Router::class);
        /** @var Alias $alias */
        $alias  = $app->get(Alias::class);

        $uri        = Uri::create(is_string($value) ? $value : '');
        $linkSource = $router->getPathFromRequest(
            $app->getRequest()->withUri($uri)
        );

        $linkSource = $alias->getSource($linkSource, $linkSource);

        $uriSource = Uri::create($router->getBasePath() . '/' . $linkSource);

        $isRoute = $router->parse(
            $app->getRequest()
                ->withUri($uriSource)
                ->withMethod('get')
                ->withoutHeader('x-http-method-override')
        );

        if (!$isRoute && $not) {
            $this->addReturn($keyRule, 'must');
        }
    }
}

class RouteOrUrlValue extends \RouteValue
{
    protected function messages(): array
    {
        return [
            'must' => 'The value of :label must be a link or route.',
            'not'  => 'The value of :label should not be a link or route.'
        ];
    }

    protected function test(string $keyRule, $value, $args, bool $not): void
    {
        $isRoute = !(new \RouteValue())
            ->hydrate('route', $this->getKey(), $args, $not)
            ->execute($value)
            ->hasErrors();
        $isLink = !(new \Soosyze\Components\Validator\Rules\Url())
            ->hydrate('url', $this->getKey(), $args, $not)
            ->execute($value)
            ->hasErrors();

        if (!($isRoute || $isLink) && $not) {
            $this->addReturn($keyRule, 'must');
        }
    }
}

Validator::addTestGlobal('route', RouteValue::class);
Validator::addTestGlobal('route_or_url', RouteOrUrlValue::class);
