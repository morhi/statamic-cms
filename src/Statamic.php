<?php

namespace Statamic;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\File;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Support\Str;
use Stringy\StaticStringy;

class Statamic
{
    const CORE_SLUG = 'statamic';
    const PACKAGE = 'statamic/cms';

    protected static $scripts = [];
    protected static $externalScripts = [];
    protected static $styles = [];
    protected static $cpRoutes = [];
    protected static $webRoutes = [];
    protected static $actionRoutes = [];
    protected static $jsonVariables = [];
    protected static $bootedCallbacks = [];
    protected static $afterInstalledCallbacks = [];

    public static function version()
    {
        return \Facades\Statamic\Version::get();
    }

    public static function pro(): bool
    {
        return config('statamic.editions.pro');
    }

    public static function enablePro(): void
    {
        $path = config_path('statamic/editions.php');

        $contents = File::get($path);

        if (! Str::contains($contents, "'pro' => false,")) {
            throw new \Exception('Could not reliably update the config file.');
        }

        $contents = str_replace("'pro' => false,", "'pro' => true,", $contents);

        File::put($path, $contents);
    }

    public static function availableScripts(Request $request): array
    {
        return static::$scripts;
    }

    public static function availableExternalScripts(Request $request): array
    {
        return static::$externalScripts;
    }

    public static function script($name, $path): Statamic
    {
        static::$scripts[$name][] = str_finish($path, '.js');

        return new static;
    }

    public static function externalScript($url): Statamic
    {
        static::$externalScripts[] = $url;

        return new static;
    }

    public static function availableStyles(Request $request): array
    {
        return static::$styles;
    }

    public static function style($name, $path): Statamic
    {
        static::$styles[$name][] = str_finish($path, '.css');

        return new static;
    }

    public static function pushWebRoutes(Closure $routes): Statamic
    {
        static::$webRoutes[] = $routes;

        return new static;
    }

    public static function pushCpRoutes(Closure $routes): Statamic
    {
        static::$cpRoutes[] = $routes;

        return new static;
    }

    public static function pushActionRoutes(Closure $routes): Statamic
    {
        static::$actionRoutes[] = $routes;

        return new static;
    }

    public static function additionalCpRoutes(): void
    {
        foreach (static::$cpRoutes as $routes) {
            $routes();
        }
    }

    public static function additionalWebRoutes(): void
    {
        foreach (static::$webRoutes as $routes) {
            $routes();
        }
    }

    public static function additionalActionRoutes(): void
    {
        foreach (static::$actionRoutes as $routes) {
            $routes();
        }
    }

    public static function isCpRoute(): bool
    {
        if (! config('statamic.cp.enabled')) {
            return false;
        }

        return starts_with(request()->path(), config('statamic.cp.route'));
    }

    public static function cpRoute($route, $params = []): ?string
    {
        if (! config('statamic.cp.enabled')) {
            return null;
        }

        $route = route('statamic.cp.'.$route, $params);

        // TODO: This is a temporary workaround to routes like
        // `route('assets.browse.edit', 'some/image.jpg')` outputting two slashes.
        // Can it be fixed with route regex, or is it a laravel bug?
        $route = preg_replace('/(?<!:)\/\//', '/', $route);

        return $route;
    }

    public static function isApiRoute(): bool
    {
        if (! config('statamic.api.enabled') || ! static::pro()) {
            return false;
        }

        return starts_with(request()->path(), config('statamic.api.route'));
    }

    public static function apiRoute($route, $params = []): ?string
    {
        if (! config('statamic.api.enabled') || ! static::pro()) {
            return null;
        }

        $route = route('statamic.api.'.$route, $params);

        // TODO: This is a temporary workaround to routes like
        // `route('assets.browse.edit', 'some/image.jpg')` outputting two slashes.
        // Can it be fixed with route regex, or is it a laravel bug?
        $route = preg_replace('/(?<!:)\/\//', '/', $route);

        return $route;
    }

    public static function isAmpRequest(): bool
    {
        if (! config('statamic.amp.enabled')) {
            return false;
        }

        $url = Site::current()->relativePath(
            str_finish(request()->getUri(), '/')
        );

        return starts_with($url, '/'.config('statamic.amp.route'));
    }

    public static function jsonVariables(Request $request): array
    {
        return collect(static::$jsonVariables)->map(function ($variable) use ($request) {
            return is_callable($variable) ? $variable($request) : $variable;
        })->all();
    }

    public static function provideToScript(array $variables): Statamic
    {
        static::$jsonVariables = array_merge(static::$jsonVariables, $variables);

        return new static;
    }

    public static function svg($name, $attrs = null): string
    {
        if ($attrs) {
            $attrs = " class=\"{$attrs}\"";
        }

        $svg = StaticStringy::collapseWhitespace(
            File::get(public_path("vendor/statamic/cp/svg/{$name}.svg"))
        );

        return str_replace('<svg', sprintf('<svg%s', $attrs), $svg);
    }

    public static function vendorAssetUrl($url = '/'): string
    {
        return asset(URL::tidy('vendor/'.$url));
    }

    public static function cpAssetUrl($url = '/'): string
    {
        return static::vendorAssetUrl('statamic/cp/'.$url);
    }

    public static function flash(): array
    {
        if ($success = session('success')) {
            $messages[] = ['type' => 'success', 'message' => $success];
        }

        if ($error = session('error')) {
            $messages[] = ['type' => 'error', 'message' => $error];
        }

        return $messages ?? [];
    }

    public static function crumb(...$values): string
    {
        return implode(' ‹ ', array_map('__', $values));
    }

    public static function docsUrl($url): string
    {
        return URL::tidy('https://statamic.dev/'.$url);
    }

    public static function booted(Closure $callback)
    {
        static::$bootedCallbacks[] = $callback;
    }

    public static function runBootedCallbacks()
    {
        foreach (static::$bootedCallbacks as $callback) {
            $callback();
        }
    }

    public static function afterInstalled(Closure $callback)
    {
        static::$afterInstalledCallbacks[] = $callback;
    }

    public static function runAfterInstalledCallbacks($command)
    {
        foreach (static::$afterInstalledCallbacks as $callback) {
            $callback($command);
        }
    }

    public static function repository($abstract, $concrete)
    {
        app()->singleton($abstract, $concrete);

        foreach ($concrete::bindings() as $abstract => $concrete) {
            app()->bind($abstract, $concrete);
        }
    }
}
