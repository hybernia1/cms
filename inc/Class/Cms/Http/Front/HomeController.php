<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Front\View\HomeViewModel;
use Core\Database\Init as DB;

final class HomeController extends BaseFrontController
{
    public function __invoke(): void
    {
        $latest = DB::query()
            ->table('posts', 'p')
            ->select(['p.id','p.title','p.slug','p.created_at'])
            ->where('p.status', '=', 'publish')
            ->orderBy('p.created_at', 'DESC')
            ->limit(10)
            ->get();

        $this->render('home', new HomeViewModel($this->viewContext, $latest));
    }
}
