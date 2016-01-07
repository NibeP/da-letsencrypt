<?php

namespace DirectAdmin\LetsEncrypt\Lib;

use Crypt_RSA;
use DirectAdmin\LetsEncrypt\Lib\Utility\ConfigurableTrait;
use DirectAdmin\LetsEncrypt\Lib\Utility\StorageTrait;
use Kelunik\Acme\AcmeClient;
use Kelunik\Acme\AcmeService;
use Kelunik\Acme\KeyPair;

/**
 * Class Account
 *
 * @package DirectAdmin\LetsEncrypt\Lib
 */
class Account {

    use ConfigurableTrait;

    private $username;
    private $email;

    private $keyPair;

    private $acmeServer;

    private $publicKeyPath;
    private $privateKeyPath;

    /** @var  AcmeService */
    public $acme;

    /**
     * Initialize Account
     *
     * @param string $username
     * @param string|null $email
     * @param string|null $acmeServer
     * @throws \Exception
     */
    function __construct($username, $email = null, $acmeServer = null) {
        $this->username = $username;
        $this->email = $email;

        $this->acmeServer = $acmeServer;
    }

    /**
     * Check if keys exists, and when they does load keys into local variables.
     *
     * @return bool
     */
    public function loadKeys() {
        if (!$this->existsInStorage('public.key') || !$this->existsInStorage('private.key')) {
            return false;
        } else {
            $publicKey = $this->getFromStorage('public.key');
            $privateKey = $this->getFromStorage('private.key');

            $this->keyPair = new KeyPair($privateKey, $publicKey);

            $this->acme = new AcmeService(new AcmeClient($this->acmeServer, $this->keyPair), $this->keyPair);

            return true;
        }
    }

    /**
     * Get path to user root
     *
     * @return string
     */
    public function getPath() {
        return DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . $this->username;
    }

    /**
     * Get path to users Let's Encrypt dir
     *
     * @return string
     */
    public function getStoragePath() {
        return  $this->getPath() . DIRECTORY_SEPARATOR . '.letsencrypt';
    }

    /**
     * Create and save a key pair for user
     *
     * @return KeyPair
     * @throws \Exception
     */
    public function createKeys() {
        $rsa = new Crypt_RSA();

        $keys = $rsa->createKey(4096);

        if ($keys['partialkey'] === false) {
            $this->keyPair = new KeyPair($keys['privatekey'], $keys['publickey']);

            $this->writeToStorage('public.key', $this->keyPair->getPublic());
            $this->writeToStorage('private.key', $this->keyPair->getPrivate());

            $this->config('status', 'keys generated');
        } else {
            throw new \Exception('CPU was to slow, we\'ve not yet coded this part.');
        }

        $this->acme = new AcmeService(new AcmeClient($this->acmeServer, $this->keyPair), $this->keyPair);

        return $this->keyPair;
    }

    /**
     * Register user at ACME
     *
     * @throws \Kelunik\Acme\AcmeException
     */
    public function register() {
        try {
            \amp\wait($this->acme->register($this->email));
        } catch (\Exception $e) {
            throw new \Exception('Error registering ' . $this->email . ': '. $e->getMessage(), 0, $e);
        }

        $this->config('status', 'registered at Let\'s Encrypt');
        $this->config('email', $this->email);
    }

    /**
     * Get username of account
     *
     * @return string
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * Set e-mail of account
     *
     * @param $email
     */
    public function setEmail($email) {
        $this->email = $email;
    }

    function __debugInfo() {
        return [
            'acme' => 'hidden',
            'username' => $this->username,
            'email' => $this->email,
            'keyPair' => 'hidden',
            'acmeServer' => $this->acmeServer,
            'publicKeyPath' => $this->publicKeyPath,
            'privateKeyPath' => $this->privateKeyPath
        ];
    }
}
