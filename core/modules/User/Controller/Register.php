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
 *
 * @phpstan-import-type UserEntity from \SoosyzeCore\User\Extend
 */
class Register extends \Soosyze\Controller
{
    public function __construct()
    {
        $this->pathViews = dirname(__DIR__) . '/Views/';
    }

    public function create(): ResponseInterface
    {
        $values = [];
        $this->container->callHook('user.register.create.form.data', [ &$values ]);

        $form = (new FormUser([
            'action' => self::router()->generateUrl('user.register.store'),
            'method' => 'post'
            ], null, self::config()))
            ->setValues($values);

        $form->group('login-fieldset', 'fieldset', function ($formbuilder) use ($form) {
            $formbuilder->legend('register-legend', t('User registration'));
            $form->usernameGroup($formbuilder)
                ->emailGroup($formbuilder)
                ->passwordNewGroup($formbuilder)
                ->passwordConfirmGroup($formbuilder)
                ->passwordPolicy($formbuilder)
                ->eulaGroup($formbuilder, self::router());
        })->submitForm(t('Registration'));

        $this->container->callHook('user.register.create.form', [ &$form, $values ]);

        if (($connectUrl = self::config()->get('settings.connect_url', Config::CONNECT_URL))) {
            $connectUrl = '/' . $connectUrl;
        }

        return self::template()
                ->view('page', [
                    'icon'       => '<i class="fa fa-user" aria-hidden="true"></i>',
                    'title_main' => t('Registration')
                ])
                ->make('page.content', 'user/content-register-create.php', $this->pathViews, [
                    'form'        => $form,
                    'url_relogin' => self::router()->generateUrl('user.login', [
                        'url' => $connectUrl
                    ])
        ]);
    }

    public function store(ServerRequestInterface $req): ResponseInterface
    {
        $validator = (new Validator())->setInputs((array) $req->getParsedBody());

        $isEmail = ($user = self::user()->getUser($validator->getInputString('email')))
            ? $user[ 'email' ]
            : '';

        $isUsername = ($user = self::user()->getUserByUsername($validator->getInputString('username')))
            ? $user[ 'username' ]
            : '';

        $isRgpd = self::config()->get('settings.rgpd_show', Config::RGPD_SHOW) === false
            ? ''
            : true;

        $isTermsOfService = self::config()->get('settings.terms_of_service_show', Config::TERMS_OF_SERVICE_SHOW) === false
            ? ''
            : true;

        $validator
            ->addInput('is_email', $isEmail)
            ->addInput('is_username', $isUsername)
            ->addInput('is_rgpd', $isRgpd)
            ->addInput('is_terms_of_service', $isTermsOfService)
            ->setRules([
                'username'         => 'required|string|max:255|!equal:@is_username',
                'email'            => 'required|string|email|!equal:@is_email',
                'password_new'     => 'required|string|regex:' . self::user()->passwordPolicy(),
                'password_confirm' => 'required|string|equal:@password_new',
                'rgpd'             => 'required_with:is_rgpd',
                'terms_of_service' => 'required_with:is_terms_of_service',
                'token_user_form'  => 'required|token'
            ])
            ->setLabels([
                'username'         => t('User name'),
                'email'            => t('E-mail'),
                'password_new'     => t('New Password'),
                'password_confirm' => t('Confirmation of the new password'),
                'rgpd'             => t('Accepter la politique de confidentialité'),
                'terms_of_service' => t('Accepter les conditions générale d\'utilisation')
            ])
            ->setMessages([
                'password_confirm' => [
                    'equal' => [
                        'must' => ':label is incorrect'
                    ]
                ]
        ]);

        $this->container->callHook('user.register.store.validator', [ &$validator ]);

        if ($validator->isValid()) {
            $data = [
                'username'         => $validator->getInputString('username'),
                'email'            => $validator->getInputString('email'),
                'password'         => self::auth()->hash($validator->getInputString('password_new')),
                'token_actived'    => Util::strRandom(30),
                'time_installed'   => (string) time(),
                'timezone'         => 'Europe/Paris',
                'terms_of_service' => (bool) $validator->hasInput('terms_of_service'),
                'rgpd'             => (bool) $validator->hasInput('rgpd'),
            ];

            $this->container->callHook('user.register.store.before', [ $validator, &$data ]);
            self::query()
                ->insertInto('user', array_keys($data))
                ->values($data)
                ->execute();

            /** @phpstan-var array $user */
            $user = self::user()->getUserActived($data[ 'email' ], false);

            self::query()
                ->insertInto('user_role', [ 'user_id', 'role_id' ])
                ->values([ $user[ 'user_id' ], 2 ])
                ->execute();

            $this->container->callHook('user.register.store.after', [ $validator ]);

            if ($this->sendMailRegister($data[ 'email' ])) {
                $_SESSION[ 'messages' ][ 'success' ][] = t(
                    'An email with instructions to access your account has just been sent to you. Warning ! This can be in your junk mail.'
                );

                return $this->json(201, [
                        'redirect' => self::router()->generateUrl('user.register.create')
                ]);
            } else {
                return $this->json(400, [
                        'messages' => [ t('An error prevented your email from being sent.') ]
                ]);
            }
        }

        return $this->json(400, [
                'messages'    => [ 'errors' => $validator->getKeyErrors() ],
                'errors_keys' => $validator->getKeyInputErrors()
        ]);
    }

    public function activate(int $id, string $token, ServerRequestInterface $req): ResponseInterface
    {
        $user = self::user()->find($id);
        if (!isset($user) || $user[ 'token_actived' ] !== $token) {
            return $this->get404($req);
        }

        $data = [ 'token_actived' => null, 'actived' => true ];

        $this->container->callHook('user.register.activate.before', [ &$data, $id ]);
        self::query()
            ->update('user', $data)
            ->where('user_id', '=', $id)
            ->execute();
        $this->container->callHook('user.register.activate.after', [ $data, $id ]);

        $_SESSION[ 'messages' ][ 'success' ][] = t('Your user account has just been activated, you can now login.');

        return new Redirect(self::router()->generateUrl('user.login', [ 'url' => '' ]), 302);
    }

    private function sendMailRegister(string $from): bool
    {
        /** @phpstan-var UserEntity $user */
        $user     = self::user()->getUser($from);
        $urlReset = self::router()->generateUrl('user.activate', [
            'id'    => $user[ 'user_id' ],
            'token' => $user[ 'token_actived' ]
        ]);

        $message = t('A user registration request has been made.') . "<br><br>\n";
        $message .= t('You can now validate the creation of your user account by clicking on this link or by copying it to your browser: ') . "\n";
        $message .= '<a target="_blank" href="' . $urlReset . '" rel="noopener noreferrer" data-auth="NotApplicable">' . $urlReset . "</a><br>\n";
        $message .= t('This link can only be used once.');

        /** @phpstan-var string $from */
        $from = self::config()->get('mailer.email');
        $mail = self::mailer()
            ->from($from)
            ->to($from)
            ->subject(t('User registration'))
            ->message($message)
            ->isHtml(true);

        return $mail->send();
    }
}
