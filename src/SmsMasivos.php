<?php
/**
 * Copyright (c) 2016 Alvaro Cánepa <info@planetadeleste.com>.
 * PaySlip by Alvaro Cánepa is licensed under a
 * Creative Commons Attribution-NoDerivatives 4.0 International License
 * (https://creativecommons.org/licenses/by-nd/4.0/). Based on a work at recibosya.uy.
 */

namespace PlanetaDelEste\SmsMasivos;


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
    public $test = 0;

    public function __construct($user = null, $pass = null)
    {
        if (strlen($user)) {
            self::$user = $user;
        }

        if (strlen($pass)) {
            self::$pass = $pass;
        }
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
     * @param $text
     *
     * @return $this
     */
    public function message($text)
    {
        if (strlen($text) > 160) {
            throw new InvalidArgumentException('El texto del mensaje supera el máximo de 160 caracteres.');
        }

        if (!$this->validateMessage($text)) {
            throw new InvalidArgumentException('El texto del mensaje contiene caracteres inválidos');
        }

        $this->text = $text;

        return $this;
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

        if($callback instanceof Closure){
            return call_user_func($callback, post());
        }

        throw new InvalidArgumentException('Función de llamada inválida');

    }

    /**
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    protected function post()
    {
        $client = new Client(['base_uri' => self::$base_uri]);
        return $client->request(
            'POST',
            '/enviar_sms.asp',
            [
                'api'     => 1,
                'usuario' => self::$user,
                'clave'   => self::$pass,
                'tos'     => $this->phone,
                'texto'   => $this->text,
                'test'    => (int)$this->test
            ]
        );
    }
}