<?php

namespace SoosyzeCore\System\Controller;

use Soosyze\Components\Http\Redirect;
use Soosyze\Components\Template\Template;
use Soosyze\Components\Util\Util;

class Install extends \Soosyze\Controller
{
    protected $pathViews;

    /**
     * Liste des modules à installer.
     *
     * @var array
     */
    private $modules = [
        'Config'      => 'SoosyzeCore\\Config\\',
        'Contact'     => 'SoosyzeCore\\Contact\\',
        'Dashboard'   => 'SoosyzeCore\\Dashboard\\',
        'Node'        => 'SoosyzeCore\\Node\\',
        'Menu'        => 'SoosyzeCore\\Menu\\',
        'System'      => 'SoosyzeCore\\System\\',
        'User'        => 'SoosyzeCore\\User\\',
        'Block'       => 'SoosyzeCore\\Block\\',
        'FileManager' => 'SoosyzeCore\\FileManager\\',
        'Trumbowyg'   => 'SoosyzeCore\\Trumbowyg\\'
    ];

    public function __construct()
    {
        $this->pathServices = dirname(__DIR__) . '/Config/service-install.json';
        $this->pathRoutes   = dirname(__DIR__) . '/Config/routes-install.php';
        $this->pathViews    = dirname(__DIR__) . '/Views/system/';
    }

    public function index($req)
    {
        if (!($steps = $this->getSteps())) {
            return $this->get404($req);
        }
        $keys = array_keys($steps);
        if (!empty($steps[ $keys[ 0 ] ][ 'key' ])) {
            return $this->step($steps[ $keys[ 0 ] ][ 'key' ], $req);
        }

        return $this->get404($req);
    }

    public function step($id, \Soosyze\Components\Http\ServerRequest $req)
    {
        if (!($steps = $this->getSteps()) || !isset($steps[ $id ])) {
            return $this->get404($req);
        }

        $messages = [
            'errors'   => [], 'warnings' => [],
            'infos'    => [], 'success'  => []
        ];
        if (isset($_SESSION[ 'messages' ][ $id ])) {
            $messages = array_merge($messages, $_SESSION[ 'messages' ][ $id ]);
            unset($_SESSION[ 'messages' ][ $id ]);
        }

        $blockPage     = $this->container->callHook("step.$id", [ $id ]);
        $blockMessages = (new Template('messages-install.php', $this->pathViews))
            ->addVars($messages);

        return (new Template('html-install.php', $this->pathViews))
                ->addBlock('page', $blockPage)
                ->addBlock('messages', $blockMessages)
                ->addVars([
                    'steps'       => $steps,
                    'step_active' => $id
                ])
                ->render();
    }

    public function stepCheck($id, $req)
    {
        if (!($steps = $this->getSteps()) || !isset($steps[ $id ])) {
            return $this->get404($req);
        }

        /* Validation de l'étape. */
        $this->container->callHook("step.$id.check", [ $id, $req ]);

        $route = self::router()->getRoute('install.step', [ ':id' => $id ]);
        if (!empty($_SESSION[ 'inputs' ][ $id ]) && empty($_SESSION[ 'messages' ][ $id ])) {
            $this->position($steps, $id);
            if (($next = next($steps)) === false && key($steps) === null) {
                $this->installModule();

                return $this->installFinish();
            }

            $route = self::router()->getRoute('install.step', [ ':id' => $next[ 'key' ] ]);
        }

        return new Redirect($route);
    }

    protected function getSteps()
    {
        $step = [];
        $this->container->callHook('step', [ &$step ]);
        uasort($step, function ($a, $b) {
            if ($a[ 'weight' ] === $b[ 'weight' ]) {
                return 0;
            }

            return ($a[ 'weight' ] < $b[ 'weight' ])
                ? -1
                : 1;
        });

        return $step;
    }

    private function installModule()
    {
        /* Installation */
        $composer = [];
        $profil    = htmlspecialchars($_SESSION[ 'inputs' ][ 'profil' ][ 'profil' ]);

        $this->container->callHook("step.install.modules.$profil", [ &$this->modules ]);

        foreach ($this->modules as $title => $namespace) {
            $migration = $namespace . 'Installer';
            $installer = new $migration();

            $installer->boot();
            /* Lance les scripts d'installation (database, configuration...) */
            $installer->install($this->container);
            /* Lance les scripts de remplissages de la base de données. */
            $installer->seeders($this->container);

            $composer[ $title ] = Util::getJson($installer->getDir() . '/composer.json');

            $composer[ $title ] += [
                'dir'          => $installer->getDir(),
                'translations' => $installer->getTranslations()
            ];

            /* Charge le container des nouveaux services. */
            $this->loadContainer($composer[ $title ]);
        }

        self::module()->loadTranslations(
            array_keys($this->modules),
            $composer,
            true
        );

        foreach ($this->modules as $title => $namespace) {
            /* Charge la version du coeur à ses modules. */
            $composer[$title]['version'] = $this->container->get('module')->getVersionCore();

            /* Enregistre le module en base de données. */
            self::module()->create($composer[ $title ]);
            /* Install les scripts de migrations. */
            self::migration()->installMigration(
                $composer[ $title ][ 'dir' ] . DS . 'Migrations',
                $title
            );

            /* Hook d'installation pour les autres modules utilise le module actuel. */
            $this->container->callHook('install.' . $title, [ $this->container ]);
        }

        self::query()
            ->insertInto('module_require', [
                'title_module', 'title_required', 'version'
            ])
            ->values([ 'Core', 'System', '1.0' ])
            ->values([ 'Core', 'User', '1.0' ])
            ->execute();
    }

    private function installMigration($dir, $title)
    {
        if (!\is_dir($dir)) {
            return;
        }
        self::query()->insertInto('migration', [ 'migration', 'extension' ]);
        foreach (new \DirectoryIterator($dir) as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            self::query()->values([
                $fileInfo->getBasename('.php'), $title
            ]);
        }
        self::query()->execute();
    }

    private function installFinish()
    {
        $saveLanguage = $_SESSION[ 'inputs' ][ 'language' ];
        $save         = $_SESSION[ 'inputs' ][ 'user' ];

        $data     = [
            'username'         => $save[ 'username' ],
            'email'            => $save[ 'email' ],
            'password'         => password_hash($save[ 'password' ], PASSWORD_DEFAULT),
            'firstname'        => $save[ 'firstname' ],
            'name'             => $save[ 'name' ],
            'actived'          => true,
            'time_installed'   => (string) time(),
            'timezone'         => $saveLanguage['timezone'],
            'rgpd'             => true,
            'terms_of_service' => true
        ];

        self::query()
            ->insertInto('user', array_keys($data))
            ->values($data)
            ->execute();

        self::query()
            ->insertInto('user_role', [ 'user_id', 'role_id' ])
            ->values([ 1, 2 ])
            ->values([ 1, 3 ])
            ->execute();

        self::config()
            ->set('mailer.email', $data[ 'email' ])
            ->set('mailer.driver', 'mail')
            ->set('settings.time_installed', time())
            ->set('settings.lang', $saveLanguage['lang'])
            ->set('settings.timezone', $saveLanguage['timezone'])
            ->set('settings.theme', 'Fez')
            ->set('settings.theme_admin', 'Admin')
            ->set('settings.logo', '')
            ->set('settings.key_cron', Util::strRandom(50))
            ->set('settings.rewrite_engine', false);

        $profil = htmlspecialchars($_SESSION[ 'inputs' ][ 'profil' ][ 'profil' ]);
        $this->container->callHook("step.install.finish.$profil", [ $this->container ]);

        $path = self::config()->getPath();
        chmod($path . 'database.json', 0444);

        session_destroy();
        $route = self::router()->getBasePath();

        return new Redirect($route);
    }

    private function loadContainer($composer)
    {
        $obj  = new $composer[ 'extra' ][ 'soosyze' ][ 'controller' ]();
        if (!($path = $obj->getPathServices())) {
            return;
        }

        $this->container->addServices(Util::getJson($path));
    }

    private function position(array &$array, $position)
    {
        reset($array);
        do {
            if (key($array) === $position) {
                break;
            }
        } while (next($array) !== false);
    }
}
