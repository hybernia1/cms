<?php
declare(strict_types=1);

use Cms\Admin\Http\AdminAjaxRouter;
use Cms\Admin\Http\Ajax\PostsAutosaveHandler;

AdminAjaxRouter::instance()->register('posts_autosave', new PostsAutosaveHandler());
