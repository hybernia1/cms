<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Front\View\Auth\LoginViewModel;
use Cms\Front\View\Auth\LostPasswordDoneViewModel;
use Cms\Front\View\Auth\LostPasswordViewModel;
use Cms\Front\View\Auth\RegisterDisabledViewModel;
use Cms\Front\View\Auth\RegisterSuccessViewModel;
use Cms\Front\View\Auth\RegisterViewModel;
use Cms\Front\View\Auth\ResetDoneViewModel;
use Cms\Front\View\Auth\ResetInvalidViewModel;
use Cms\Front\View\Auth\ResetViewModel;
use Cms\Mail\MailService;
use Cms\Utils\DateTimeFactory;
use Core\Database\Init as DB;
use Throwable;

final class AuthController extends BaseFrontController
{
    public function login(): void
    {
        $auth = $this->services->auth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrfPublic();
            $email = trim((string)($_POST['email'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');
            if ($email === '' || $pass === '') {
                $this->render('login', new LoginViewModel($this->viewContext, $this->tokenPublic(), 'danger', 'Vyplňte e-mail i heslo.'));
                return;
            }
            if ($auth->attempt($email, $pass)) {
                $this->services->refreshFrontUser();
                $this->redirect('./');
            }
            $this->render('login', new LoginViewModel($this->viewContext, $this->tokenPublic(), 'danger', 'Nesprávný e-mail nebo heslo.'));
            return;
        }

        $this->render('login', new LoginViewModel($this->viewContext, $this->tokenPublic(), null, null));
    }

    public function logout(): void
    {
        $this->services->auth()->logout();
        $this->services->refreshFrontUser();
        $this->redirect('./');
    }

    public function register(): void
    {
        $settingsRow = DB::query()
            ->table('settings')
            ->select(['allow_registration', 'registration_auto_approve'])
            ->where('id', '=', 1)
            ->first();

        $allow = (int)($settingsRow['allow_registration'] ?? 1);
        $autoApproveSetting = (int)($settingsRow['registration_auto_approve'] ?? 1) === 1;
        $requiresApproval = $allow === 1 ? !$autoApproveSetting : false;

        if ($allow !== 1) {
            $this->render('register-disabled', new RegisterDisabledViewModel($this->viewContext));
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrfPublic();
            $name  = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');
            if ($name === '' || $email === '' || $pass === '') {
                $this->render('register', new RegisterViewModel($this->viewContext, $this->tokenPublic(), 'danger', 'Vyplňte všechna pole.', $requiresApproval));
                return;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->render('register', new RegisterViewModel($this->viewContext, $this->tokenPublic(), 'danger', 'Neplatný e-mail.', $requiresApproval));
                return;
            }

            $exists = DB::query()->table('users')->select(['id'])->where('email', '=', $email)->first();
            if ($exists) {
                $this->render('register', new RegisterViewModel($this->viewContext, $this->tokenPublic(), 'danger', 'Účet s tímto e-mailem už existuje.', $requiresApproval));
                return;
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $now = DateTimeFactory::nowString();
            $active = $autoApproveSetting ? 1 : 0;

            DB::query()->table('users')->insertRow([
                'name'          => $name,
                'email'         => $email,
                'password_hash' => $hash,
                'role'          => 'user',
                'active'        => $active,
                'token'         => null,
                'token_expire'  => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ])->execute();

            if ($autoApproveSetting) {
                if ($this->services->auth()->attempt($email, $pass)) {
                    $this->services->refreshFrontUser();
                }

                $this->sendRegistrationMail('registration_welcome', [
                    'siteTitle' => $this->services->settings()->siteTitle(),
                    'userName'  => $name,
                    'userEmail' => $email,
                    'loginUrl'  => $this->loginUrl(),
                ], $email, $name);

                $this->render('register-success', new RegisterSuccessViewModel($this->viewContext, $email, false));
                return;
            }

            $this->sendRegistrationMail('registration_pending', [
                'siteTitle' => $this->services->settings()->siteTitle(),
                'userName'  => $name,
                'userEmail' => $email,
            ], $email, $name);

            $this->render('register-success', new RegisterSuccessViewModel($this->viewContext, $email, true));
            return;
        }

        $this->render('register', new RegisterViewModel($this->viewContext, $this->tokenPublic(), null, null, $requiresApproval));
    }

    public function lost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrfPublic();
            $email = trim((string)($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->render('lost', new LostPasswordViewModel($this->viewContext, $this->tokenPublic(), 'danger', 'Zadejte platný e-mail.'));
                return;
            }
            $user = DB::query()->table('users')->select(['id','name','email','active'])->where('email', '=', $email)->first();

            if (!$user || (int)$user['active'] !== 1) {
                $this->render('lost-done', new LostPasswordDoneViewModel($this->viewContext));
                return;
            }

            $token = bin2hex(random_bytes(20));
            $exp   = DateTimeFactory::now()->modify('+1 hour')->format('Y-m-d H:i:s');
            DB::query()->table('users')->update([
                'token'        => $token,
                'token_expire' => $exp,
                'updated_at'   => DateTimeFactory::nowString(),
            ])->where('id', '=', (int)$user['id'])->execute();

            $cs = $this->services->settings();
            $base = (string)(DB::query()->table('settings')->select(['site_url'])->where('id', '=', 1)->value('site_url') ?? '');
            $resetUrl = rtrim($base, '/') . '/reset?token=' . urlencode($token);

            try {
                $template = $this->services->mailTemplates()->render('lost_password', [
                    'resetUrl'  => $resetUrl,
                    'siteTitle' => $cs->siteTitle(),
                    'userName'  => (string)($user['name'] ?? ''),
                ]);

                (new MailService($cs))->sendTemplate((string)$user['email'], $template, (string)($user['name'] ?? ''));
            } catch (Throwable) {
                // ignore mail errors
            }

            $this->render('lost-done', new LostPasswordDoneViewModel($this->viewContext));
            return;
        }

        $this->render('lost', new LostPasswordViewModel($this->viewContext, $this->tokenPublic(), null, null));
    }

    public function reset(): void
    {
        $token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
        if ($token === '') {
            $this->render('reset-invalid', new ResetInvalidViewModel($this->viewContext));
            return;
        }

        $user = DB::query()->table('users')
            ->select(['id','token','token_expire','email','name'])
            ->where('token', '=', $token)
            ->first();

        if (!$user || ($user['token_expire'] && strtotime((string)$user['token_expire']) < time())) {
            $this->render('reset-invalid', new ResetInvalidViewModel($this->viewContext));
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrfPublic();
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if ($p1 === '' || $p1 !== $p2) {
                $this->render('reset', new ResetViewModel($this->viewContext, $this->tokenPublic(), $token, 'danger', 'Hesla se neshodují.'));
                return;
            }
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            DB::query()->table('users')->update([
                'password_hash' => $hash,
                'token'         => null,
                'token_expire'  => null,
                'updated_at'    => DateTimeFactory::nowString(),
            ])->where('id', '=', (int)$user['id'])->execute();

            $this->render('reset-done', new ResetDoneViewModel($this->viewContext));
            return;
        }

        $this->render('reset', new ResetViewModel($this->viewContext, $this->tokenPublic(), $token, null, null));
    }

    /**
     * @param array<string,mixed> $data
     */
    private function sendRegistrationMail(string $templateKey, array $data, string $toEmail, ?string $toName): void
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $template = $this->services->mailTemplates()->render($templateKey, $data);
        } catch (Throwable) {
            return;
        }

        try {
            (new MailService($this->services->settings()))->sendTemplate($toEmail, $template, $toName !== '' ? $toName : null);
        } catch (Throwable) {
            // swallow mail errors to avoid breaking registration
        }
    }

    private function loginUrl(): string
    {
        $base = rtrim($this->services->settings()->siteUrl(), '/');
        $path = $this->services->settings()->seoUrlsEnabled() ? '/login' : '/index.php?r=login';
        return $base . $path;
    }
}
