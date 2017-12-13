<?php
/**
 *  Copyright (C) 2007 Free Software Foundation, Inc. <http://fsf.org/>
 *  Everyone is permitted to copy and distribute verbatim copies
 *  of this license document, but changing it is not allowed.
 */

namespace AlvaroCanepa\SmsMasivos;


use Closure;
use GuzzleHttp\Client;
use InvalidArgumentException;

class SmsMasivos
{
    /**
     * Base uri of service
     * @var string
     */
    protected static $base_uri = 'http://servicio.smsmasivos.com.uy';

    /**
     * Username for authenticate with smsmasivos service
     * @var null|string
     */
    protected static $user = null;

    /**
     * Password to authenticate with smsmasivos service
     * @var null|string
     */
    protected static $pass = null;

    /**
     * @var Client
     */
    private $client;

    /**
     * SMS text message
     * @var null|string
     */
    public $text = null;

    /**
     * Phone number
     * @var null|integer
     */
    public $phone = null;

    /**
     * Test message without send
     * @var integer
     */
    public $test = null;

    /**
     * Send internal id
     * @var null|mixed
     */
    public $internalId = null;

    public function __construct($user = null, $pass = null)
    {
        if (strlen($user)) {
            self::$user = $user;
        }

        if (strlen($pass)) {
            self::$pass = $pass;
        }

        $this->client = new Client(['base_uri' => self::$base_uri]);
    }

    /**
     * Set username and password for smsmasivos service
     *
     * @param string $user
     * @param string $pass
     */
    public static function auth($user, $pass)
    {
        self::$user = $user;
        self::$pass = $pass;
    }

    public static function setBaseUri($uri)
    {
        self::$base_uri = $uri;
    }

    /**
     * @return bool
     */
    public function isAuth()
    {
        return (strlen(self::$user) && strlen(self::$pass));
    }

    /**
     * @param string $text
     * @param array  $params
     *
     * @return $this
     */
    public function message($text, $params = [])
    {
        if (strlen($text) > 160) {
            throw new InvalidArgumentException('El texto del mensaje supera el máximo de 160 caracteres.');
        }

        if (!$this->validateMessage($text)) {
            throw new InvalidArgumentException('El texto del mensaje contiene caracteres inválidos');
        }

        $params = array_map_assoc(
            function ($key, $value) {
                return [':'.$key => $value];
            },
            $params
        );
        trace_log($params);
        $this->text = strtr($text, $params);

        return $this;
    }

    /**
     * @return null|string
     */
    public function getMessage()
    {
        return $this->text;
    }

    /**
     * @param integer $phone
     *
     * @return $this
     */
    public function phone($phone)
    {
        if (!$this->validatePhone($phone)) {
            throw new InvalidArgumentException('El número de teléfono tiene caracter inválidos');
        }

        $this->phone = $phone;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Validate SMS text message
     *
     * @param null|string $text
     *
     * @return bool
     */
    public function validateMessage($text = null)
    {
        $pattern = '/^[a-zA-Z0-9!?#$%()*+, -.\/:;=@]*$/';
        if (!strlen($text)) {
            $text = $this->text;
        }

        return (bool)preg_match($pattern, $text);
    }

    /**
     * Validate phone number
     *
     * @param null|integer $phone
     *
     * @return bool
     */
    public function validatePhone($phone = null)
    {
        $pattern = '/^[0-9]*$/';
        if (!strlen($phone)) {
            $phone = $this->phone;
        }

        return (bool)preg_match($pattern, $phone);
    }

    public function send($message, $phone = null, $callback = null)
    {
        if ($message instanceof Closure) {
            /*
             * $message is callback, don't use the $phone and $callback arguments
             */
            $callback = $message;
            $message = $this->text;
            $phone = $this->phone;
        } else {
            if (is_string($message) && $phone instanceof Closure) {
                /*
                 * $phone is callback, don't use the $callback argument
                 */
                $callback = $phone;
                $phone = $this->phone;
            }
        }

        /*
         * Validators
         */
        $this->message($message)->phone($phone);

        if (!strlen($this->text)) {
            throw new InvalidArgumentException('No se ha especificado un texto en el mensaje');
        }

        if ($callback instanceof Closure) {
            return call_user_func($callback, $this->post(), $this);
        }

        throw new InvalidArgumentException('Función de llamada inválida');
    }

    /**
     * @return string
     */
    public function balance()
    {
        return $this->get('/obtener_saldo.asp', ['usuario' => self::$user, 'clave' => self::$pass])->getBody(
        )->getContents();
    }

    /**
     * @return string
     */
    public function expiration()
    {
        return $this->get(
            '/obtener_vencimiento_paquete.asp',
            ['usuario' => self::$user, 'clave' => self::$pass]
        )->getBody()->getContents();
    }

    /**
     * @return string
     */
    public function messagesSent()
    {
        return $this->get('/obtener_envios.asp', ['usuario' => self::$user, 'clave' => self::$pass])->getBody(
        )->getContents();
    }

    /**
     * @param bool $iso
     *
     * @return string
     */
    public function serverDate($iso = true)
    {
        $query = ($iso) ? ['iso' => 1] : [];

        return $this->get('/get_fecha.asp', $query)->getBody()->getContents();
    }

    /**
     * @return null|string
     */
    public function username()
    {
        return self::$user;
    }

    /**
     * @param string $uri
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    protected function post($uri = '/enviar_sms.asp')
    {
        return $this->client->request(
            'POST',
            $uri,
            [
                'query' => [
                    'api'       => 1,
                    'usuario'   => self::$user,
                    'clave'     => self::$pass,
                    'tos'       => $this->phone,
                    'texto'     => $this->text,
                    'test'      => $this->test,
                    'idinterno' => $this->internalId
                ]
            ]
        );
    }

    /**
     * @param string $uri
     * @param array  $query
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    protected function get($uri, $query)
    {
        if (!is_array($query)) {
            $query = (strlen($query)) ? [$query] : [];
        }

        return $this->client->request('GET', $uri, ['query' => $query]);
    }
}