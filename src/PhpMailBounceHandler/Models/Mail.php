<?php

/**
 * Mail.
 *
 * @author Cr@zy
 * @copyright 2013-2015, Cr@zy
 * @license GNU LESSER GENERAL PUBLIC LICENSE
 *
 * @link https://github.com/crazy-max/CwsMailBounceHandler
 */
namespace PhpMailBounceHandler\Models;

class Mail
{
    /**
     * Message number or filename.
     *
     * @var int|string
     */
    private $token;

    /**
     * Message subject.
     *
     * @var string
     */
    private $subject;

    /**
     * Message headers.
     *
     * @var object
     */
    private $header;

    /**
     * Message body.
     *
     * @var object
     */
    private $body;

    /**
     * List of recipients,.
     *
     * @var array
     */
    private $recipients;

    public function __construct()
    {
        $this->token = null;
        $this->subject = null;
        $this->recipients = array();
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function setHeader($header)
    {
        $this->header = $header;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getRecipients()
    {
        return $this->recipients;
    }

    public function addRecipient(Recipient $recipient)
    {
        $this->recipients[] = $recipient;
    }
}
