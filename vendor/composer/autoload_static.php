<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6188c44f206e5aee3751b8c341687db9
{
    public static $prefixLengthsPsr4 = array (
        'f' => 
        array (
            'fivefilters\\Readability\\' => 24,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'Psr\\Http\\Message\\' => 17,
            'Predis\\' => 7,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
            'Masterminds\\' => 12,
        ),
        'L' => 
        array (
            'League\\Uri\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'fivefilters\\Readability\\' => 
        array (
            0 => __DIR__ . '/..' . '/fivefilters/readability.php/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Predis\\' => 
        array (
            0 => __DIR__ . '/..' . '/predis/predis/src',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
        'Masterminds\\' => 
        array (
            0 => __DIR__ . '/..' . '/masterminds/html5/src',
        ),
        'League\\Uri\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/uri/src',
            1 => __DIR__ . '/..' . '/league/uri-interfaces/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'Parsedown' => 
            array (
                0 => __DIR__ . '/..' . '/erusev/parsedown',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit6188c44f206e5aee3751b8c341687db9::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit6188c44f206e5aee3751b8c341687db9::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit6188c44f206e5aee3751b8c341687db9::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit6188c44f206e5aee3751b8c341687db9::$classMap;

        }, null, ClassLoader::class);
    }
}
