<?php

namespace Simples\Message;

use Simples\Error\SimplesRunTimeError;
use Simples\Helper\File;
use Simples\Helper\Text;
use Simples\Kernel\App;

/**
 * @method static string validation(string $i18, array $parameters = [])
 * @method static string auth(string $i18, array $parameters = [])
 *
 * Class Lang
 * @package Simples\Kernel
 */
abstract class Lang
{
    /**
     * @param string $default
     * @param string $fallback
     */
    public static function locale(string $default, string $fallback = '')
    {
        App::options('lang', ['default' => $default, 'fallback' => $fallback]);
    }

    /**
     * @param string $name
     * @param string $arguments
     * @return string
     * @throws SimplesRunTimeError
     */
    public static function __callStatic($name, $arguments)
    {
        if (!isset($arguments[0])) {
            throw new SimplesRunTimeError(
                'Call static scope requires the same parameters what ' .
                '`static::replace(string $scope, string $path, array $parameters = [])`'
            );
        }
        $parameters = [];
        if (isset($arguments[1])) {
            $parameters = $arguments[1];
        }
        return static::replace($name, $arguments[0], $parameters);
    }

    /**
     * @param string $scope
     * @param string $path
     * @return mixed
     */
    public static function get(string $scope, string $path)
    {
        $i18n = "Lang '{$scope}.{$path}' not found";
        $languages = App::options('lang');
        $filename = static::filename($scope, $languages['default'], $languages['fallback']);

        if ($filename) {
            /** @noinspection PhpIncludeInspection */
            $phrases = include $filename;
            $i18n = search($phrases, $path);
        }
        return $i18n;
    }

    /**
     * @param string $scope
     * @param string $path
     * @param array $parameters
     * @return string
     * @throws SimplesRunTimeError
     */
    public static function replace(string $scope, string $path, array $parameters = []): string
    {
        $i18n = static::get($scope, $path);
        if (gettype($i18n) === TYPE_STRING) {
            return static::replacement($i18n, $parameters);
        }
        throw new SimplesRunTimeError("Can't use `{$scope}` and `{$path}` to make replacement", $parameters);
    }

    /**
     * @param string $i18n
     * @param array $parameters
     * @return string
     */
    public static function replacement(string $i18n, array $parameters): string
    {
        return Text::replacement($i18n, $parameters);
    }

    /**
     * @param string $scope
     * @param string $default
     * @param string $fallback
     * @return string
     */
    private static function filename(string $scope, string $default, string $fallback): string
    {
        $filename = resources("locales/{$default}/{$scope}.php");
        if (!File::exists($filename)) {
            $filename = resources("locales/{$fallback}/{$scope}.php");
        }
        if (File::exists($filename)) {
            return $filename;
        }
        return '';
    }
}
