<?php

namespace PhpMailBounceHandler\Models;

/**
 * Recipient.
 *
 * @author Cr@zy
 * @copyright 2013-2016, Cr@zy
 * @license GNU LESSER GENERAL PUBLIC LICENSE
 *
 * @link https://github.com/crazy-max/CwsMailBounceHandler
 */
class Recipient
{
    /**
     * The recipient email.
     *
     * @var string
     */
    private $email;

    public function __construct()
    {
        $this->email = null;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }
}
