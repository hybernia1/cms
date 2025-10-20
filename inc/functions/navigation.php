<?php
declare(strict_types=1);

use Core\Navigation\MenuLocations;

if (!function_exists('register_nav_menu')) {
    function register_nav_menu(string $location, string|array $args, ?string $description = null): void
    {
        MenuLocations::register($location, $args, $description);
    }
}

if (!function_exists('register_nav_menus')) {
    /**
     * @param array<string,string|array{label?:string,description?:string|null}> $menus
     */
    function register_nav_menus(array $menus): void
    {
        MenuLocations::registerMany($menus);
    }
}

if (!function_exists('registered_nav_menus')) {
    /**
     * @return array<string,array{label:string,description:?string}>
     */
    function registered_nav_menus(): array
    {
        return MenuLocations::all();
    }
}
