<?php
declare(strict_types=1);

use Cms\Admin\Domain\PostTypes\PostTypeRegistry;

if (!function_exists('register_post_type')) {
    /**
     * @param array<string,mixed> $args
     *        $args['public']  bool  Whether the type is publicly queryable (default true)
     *        $args['sitemap'] bool  Include in sitemap generation (default follows public)
     *        $args['feed']    bool  Include in RSS feed (default true for "post")
     */
    function register_post_type(string $type, array $args): void
    {
        PostTypeRegistry::register($type, $args);
    }
}

if (!function_exists('registered_post_types')) {
    /**
     * @return array<string,array{nav:string,list:string,create:string,edit:string,label:string,icon:string,supports:array<int,string>}>
     */
    function registered_post_types(): array
    {
        return PostTypeRegistry::all();
    }
}

register_post_type('post', [
    'nav'      => 'Příspěvky',
    'list'     => 'Příspěvky',
    'create'   => 'Nový příspěvek',
    'edit'     => 'Upravit příspěvek',
    'label'    => 'Příspěvek',
    'icon'     => 'bi-file-earmark-text',
    'supports' => ['thumbnail', 'comments', 'terms:category', 'terms:tag'],
]);

register_post_type('page', [
    'nav'      => 'Stránky',
    'list'     => 'Stránky',
    'create'   => 'Nová stránka',
    'edit'     => 'Upravit stránku',
    'label'    => 'Stránka',
    'icon'     => 'bi-file-earmark-richtext',
    'supports' => ['thumbnail', 'comments'],
    'feed'     => false,
]);
