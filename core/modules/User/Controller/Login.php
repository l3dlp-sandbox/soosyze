<?php

declare(strict_types=1);

namespace SoosyzeCore\User\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soosyze\Components\Http\Redirect;
use Soosyze\Components\Util\Util;
use Soosyze\Components\Validator\Validator;
use SoosyzeCore\User\Form\FormUser;
use SoosyzeCore\User\Hook\Config;

/**
 * @method \SoosyzeCore\User\Services\Auth           auth()
 * @method \SoosyzeCore\Mailer\Services\Mailer       mailer()
 * @method \SoosyzeCore\QueryBuilder\Services\Query  query()
 * @method \SoosyzeCore\Template\Services\Templating template()
 * @method \SoosyzeCore\User\Services\User           user()
 */
class Login extends \Soosyze\Controller
{
    public function __construct()
    {
        $this->pathViews = dirname(__DIR__) . '/Views/';
    }

    public function login(string $url, ServerRequestInterface $req): ResponseInterface
    {
        if (self::user()->isConnectUrl($url)) {
            return $this->get404($req);
        }

        if (self::user()->isConnected()) {
            return new Redirect(self::router()->generateUrl('user.account'), 302);
        }

        $values = [];
        $this->container->callHook('user.login.form.data', [ &$values ]);

        $form = (new FormUser([
            'action' => self::router()->generateUrl('user.login.check', [ 'url' => $url ]),
            'method' => 'post'
            ], null, self::config()))
            ->setValues($values);

        $form->group('login-fieldset', 'fieldset', function ($formbuilder) use ($form) {
            $formbuilder->legend('login-legend', t('User login'));
            $form->emailGroup($formbuilder)
                ->passwordCurrentGroup($formbuilder);
        })->submitForm(t('Sign in'));

        $this->container->callHook('user.login.form', [ &$form, $values ]);

        return self::template()
                ->view('page', [
                    'icon'       => '<i class="fa fa-user" aria-hidden="true"></i>',
                    'title_main' => t('Sign in')
                ])
                ->make('page.content', 'user/content-login-login.php', $this->pathViews, [
                    'form'             => $form,
                    'url_relogin'      => self::router()->generateUrl('user.relogin', [
                        'url' => $url
                    ]),
                    'url_register'     => self::router()->generateUrl('user.register.create'),
                    'granted_relogin'  => self::config()->get('settings.user_relogin', Config::USER_RELOGIN),
                    'granted_register' => self::config()->get('settings.user_register', Config::USER_REGISTER)
        ]);
    }

    public function loginCheck(string $url, ServerRequestInterface $req): ResponseInterface
    {
        if (self::user()->isConnectUrl($url)) {
            return $this->json(404, [
                    'messages' => [ 'errors' => [ t('The requested resource does not exist.') ] ]
            ]);
        }

        $validator = (new Validator())
            ->setRules([
                'email'           => 'required|email|max:254',
                'password'        => 'required|string',
                'token_user_form' => 'token'
            ])
            ->setInputs((array) $req->getParsedBody());

        $this->container->callHook('user.login.check.validator', [ &$validator, $url ]);

        if (!$validator->isValid()) {
            return $this->json(400, [
                    'messages'    => [ 'errors' => $validator->getKeyErrors() ],
                    'errors_keys' => $validator->getKeyInputErrors()
            ]);
        }

        $user = self::auth()->attempt(
            $validator->getInputString('email'),
            $validator->getInputString('password')
        );
        if ($user === null) {
            return $this->json(400, [
                    'messages' => [ 'errors' => [ t('E-mail or password not recognized.') ] ]
            ]);
        }

        if (self::config()->get('settings.maintenance') && !self::user()->getGranted($user, 'system.config.maintenance')) {
            return $this->json(400, [
                    'messages' => [ 'errors' => [ t('You are not allowed to log in while the site is under maintenance.') ] ]
            ]);
        }

        self::auth()->login(
            $validator->getInputString('email'),
            $validator->getInputString('password')
        );
        $route = $this->getRedirectLogin($user);

        return $this->json(200, [ 'redirect' => $route ]);
    }

    public function logout(): ResponseInterface
    {
        session_destroy();
        session_unset();

        return new Redirect(self::router()->getBasePath(), 302);
    }

    public function relogin(string $url, ServerRequestInterface $req): ResponseInterface
    {
        if (self::user()->isConnectUrl($url)) {
            return $this->get404($req);
        }

        $values = [];
        $this->container->callHook('user.relogin.form.data', [ &$values, $url ]);

        $action = self::router()->generateUrl('user.relogin.check', [ 'url' => $url ]);

        $form = (new FormUser([ 'action' => $action, 'method' => 'post' ]))
            ->setValues($values);

        $form->group('login-fieldset', 'fieldset', function ($formBuilder) use ($form) {
            $form->emailGroup($formBuilder);
        })->submitForm();

        $this->container->callHook('user.relogin.form', [ &$form, $values, $url ]);

        return self::template()
                ->view('page', [
                    'icon'       => '<i class="fa fa-user" aria-hidden="true"></i>',
                    'title_main' => t('Request a new password')
                ])
                ->make('page.content', 'user/content-login-relogin.php', $this->pathViews, [
                    'form'      => $form,
                    'url_login' => self::router()->generateUrl('user.login', [ 'url' => $url ])
        ]);
    }

    public function reloginCheck(string $url, ServerRequestInterface $req): ResponseInterface
    {
        if (self::user()->isConnectUrl($url)) {
            return $this->json(404, [
                    'messages' => [ 'errors' => [ t('The requested resource does not exist.') ] ]
            ]);
        }

        $validator = (new Validator())
            ->setRules([
                'email'           => 'required|email|max:254',
                'token_user_form' => 'required|token'
            ])
            ->setInputs((array) $req->getParsedBody());

        $this->container->callHook('user.relogin.check.validator', [ &$validator, $url ]);

        if ($validator->isValid()) {
            $user = self::user()->getUserActived($validator->getInputString('email'));

            if ($user) {
                $token = Util::strRandom();
                /** @phpstan-var string $passwordResetTimeout */
                $passwordResetTimeout = self::config()->get('settings.password_reset_timeout', Config::PASSWORD_RESET_TIMEOUT);

                self::query()
                    ->update('user', [
                        'token_forget' => $token,
                        'time_reset'   =>  (new \DateTime)
                            ->add(new \DateInterval($passwordResetTimeout))
                            ->getTimestamp()
                    ])
                    ->where('email', '=', $validator->getInputString('email'))
                    ->execute();

                $urlReset = self::router()->generateUrl('user.reset', [
                    'id'    => $user[ 'user_id' ],
                    'token' => $token
                ]);
                $message  = t('A request for renewal of the password has been made. You can now login by clicking on this link or by copying it to your browser:') . "\n";
                $message  .= '<a target="_blank" href="' . $urlReset . '" rel="noopener noreferrer" data-auth="NotApplicable">' . $urlReset . '</a>';

                /** @phpstan-var string $from */
                $from = self::config()->get('mailer.email');
                $mail = self::mailer()
                    ->from($from)
                    ->to($user[ 'email' ])
                    ->subject(t('New Password'))
                    ->message($message)
                    ->isHtml(true);

                if ($mail->send()) {
                    $_SESSION[ 'messages' ][ 'success' ][] =
                        t('An email with instructions to access your account has just been sent to you. Warning ! This can be in your junk mail.')
                    ;

                    return $this->json(200, [
                            'redirect' => self::router()->generateUrl('user.login', [
                                'url' => $url
                            ])
                    ]);
                }

                $messagesErrors[] = t('An error prevented your email from being sent.');
            } else {
                $messagesErrors[] = t('Sorry, this email is not recognized.');
            }
        } else {
            $messagesErrors = $validator->getKeyErrors();
        }

        return $this->json(400, [
                'messages'    => [ 'errors' => $messagesErrors ],
                'errors_keys' => $validator->getKeyInputErrors()
        ]);
    }

    public function resetUser(int $id, string $token, ServerRequestInterface $req): ResponseInterface
    {
        if (!($user = self::user()->find($id))) {
            return $this->get404($req);
        }
        if ($user[ 'token_forget' ] !== $token) {
            return $this->get404($req);
        }
        if ($user['time_reset'] < time()) {
            $_SESSION[ 'messages' ][ 'errors' ][] = t('Password reset timeout');

            return $this->get404($req);
        }

        $pwd = (string) time();

        self::query()
            ->update('user', [
                'password'     => self::auth()->hash($pwd),
                'token_forget' => '',
                'time_reset'   => null
            ])
            ->where('user_id', '=', $id)
            ->execute();

        self::auth()->login($user[ 'email' ], $pwd);

        return new Redirect(self::router()->generateUrl('user.edit', [ 'id' => $id ]));
    }

    private function getRedirectLogin(array $user): string
    {
        /** @phpstan-var string $redirect */
        $redirect = self::config()->get('settings.connect_redirect', Config::CONNECT_REDIRECT);
        if ($redirect !== '') {
            $redirect = str_replace(':user_id', $user[ 'user_id' ], $redirect);

            return self::router()->makeUrl('/' . ltrim($redirect, '/'));
        }

        return self::router()->generateUrl('user.account');
    }
}
