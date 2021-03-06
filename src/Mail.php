<?php

namespace Simples\Message;

use ForceUTF8\Encoding;
use Simples\Helper\File;
use Simples\Helper\JSON;
use Simples\Helper\Text;
use PHPMailer;

/**
 * Class Mail
 * @package Simples\Message
 */
class Mail
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var string
     */
    protected $toAddress;

    /**
     * @var string
     */
    protected $toName;

    /**
     * @var string
     */
    protected $alt;

    /**
     * @var string
     */
    protected $fromAddress;

    /**
     * @var string
     */
    protected $fromName;

    /**
     * @var string
     */
    protected $replyToAddress;

    /**
     * @var string
     */
    protected $replyToName;

    /**
     * @var array
     */
    protected $attachments = [];

    /**
     * @var array
     */
    protected $ccs = [];

    /**
     * @var string
     */
    private $error;

    /**
     * @var string
     */
    const STATUS_WAITING = 'waiting', STATUS_SENT = 'sent', STATUS_ERROR = 'error';

    /**
     * EMail constructor.
     * @param string $subject
     * @param string $message
     * @param string $toAddress
     * @param string $toName
     * @param string $alt
     * @param string $fromAddress
     * @param string $fromName
     */
    public function __construct(
        $subject = '',
        $message = '',
        $toAddress = '',
        $toName = '',
        $alt = '',
        $fromAddress = '',
        $fromName = ''
    ) {
        $this->subject = $subject;
        $this->message = $message;
        $this->toAddress = $toAddress;
        $this->toName = $toName;
        $this->alt = $alt;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
        $this->id = uniqid();
    }

    /**
     * @param string $driver
     * @return bool
     */
    public function send($driver = 'default'): bool
    {
        if (!$this->toAddress) {
            return false;
        }
        $file = $this->id . '.' . 'mail';

        $root = storage('files/mail');

        $waiting = path($root, self::STATUS_WAITING, $file);
        if (File::exists($waiting)) {
            File::destroy($waiting);
        }

        $settings = off(config('mail'), $driver);

        $mailer = $this->create($settings);

        $this->configureAddresses($mailer, $settings);

        $this->configureMessage($mailer);

        foreach ($this->attachments as $attachment) {
            $mailer->addAttachment($attachment->filename, $attachment->description);
        }

        $filename = path($root, self::STATUS_SENT, $file);
        $sent = $mailer->send();

        if (!$sent) {
            $filename = path($root, self::STATUS_ERROR, $file);
            $this->error = $mailer->ErrorInfo;
        }
        File::write($filename, $this->json());

        return $sent;
    }

    /**
     * @param array $settings
     * @return PHPMailer
     */
    private function create(array $settings)
    {
        $mailer = new PHPMailer();

        $mailer->isSMTP();
        $mailer->SMTPAuth = true;

        $mailer->Host = off($settings, 'host');
        $mailer->Port = off($settings, 'port');
        $mailer->SMTPSecure = off($settings, 'secure');
        $mailer->Username = off($settings, 'user');
        $mailer->Password = off($settings, 'password');

        return $mailer;
    }

    /**
     * @param PHPMailer $mailer
     * @param array $settings
     */
    private function configureAddresses(PHPMailer $mailer, array $settings)
    {
        $mailer->addAddress($this->toAddress, $this->toName ? $this->toName : '');

        if (!$this->fromAddress && !($this->fromAddress = off($settings, 'address'))) {
            $this->fromAddress = $mailer->Username;
        }

        if (!$this->fromName) {
            $this->fromName = off($settings, 'name', off(config('app'), 'name'));
        }

        $mailer->setFrom($this->fromAddress, $this->fromName);

        if ($this->replyToAddress) {
            $mailer->addReplyTo($this->replyToAddress, coalesce($this->replyToName, ''));
        }

        foreach ($this->ccs as $cc) {
            $mailer->addCC($cc->address, $cc->name);
        }
    }

    /**
     * @param PHPMailer $mailer
     */
    private function configureMessage(PHPMailer $mailer)
    {
        $mailer->isHTML(true);

        $mailer->Subject = Encoding::fixUTF8($this->subject);
        $mailer->Body = Encoding::fixUTF8(Text::replace($this->message, '{id}', $this->id));
        $mailer->AltBody = $this->alt;
    }

    /**
     * @return bool
     */
    public function schedule(): bool
    {
        $filename = storage('files/mail/' . self::STATUS_WAITING . '/' . $this->id . '.' . 'mail');
        if (!File::exists($filename)) {
            if (File::write($filename, $this->json())) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $filename
     * @return Mail
     */
    public static function load($filename): Mail
    {
        $instance = new static();
        if (File::exists($filename)) {
            $properties = JSON::decode(File::read($filename));
            foreach ($properties as $key => $value) {
                /** @noinspection PhpVariableVariableInspection */
                $instance->$key = $value;
            }
        }

        return $instance;
    }

    /**
     * @return string
     */
    public function json()
    {
        $properties = [];
        foreach ($this as $key => $value) {
            $properties[$key] = $value;
        }

        return JSON::encode($properties);
    }

    /**
     * @param $address
     * @param string $name
     */
    public function addCC($address, $name = '')
    {
        $this->ccs[] = (object)['address' => $address, 'name' => $name];
    }

    /**
     * @param $filename
     * @param string $description
     * @return bool
     */
    public function addAttachment($filename, $description = '')
    {
        if (File::exists($filename)) {
            $this->attachments[] = (object)['filename' => $filename, 'description' => $description];
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getToAddress()
    {
        return $this->toAddress;
    }

    /**
     * @param string $toAddress
     */
    public function setToAddress($toAddress)
    {
        $this->toAddress = $toAddress;
    }

    /**
     * @return string
     */
    public function getToName()
    {
        return $this->toName;
    }

    /**
     * @param string $toName
     */
    public function setToName($toName)
    {
        $this->toName = $toName;
    }

    /**
     * @return string
     */
    public function getAlt()
    {
        return $this->alt;
    }

    /**
     * @param string $alt
     */
    public function setAlt($alt)
    {
        $this->alt = $alt;
    }

    /**
     * @return string
     */
    public function getFromAddress()
    {
        return $this->fromAddress;
    }

    /**
     * @param string $fromAddress
     */
    public function setFromAddress($fromAddress)
    {
        $this->fromAddress = $fromAddress;
    }

    /**
     * @return string
     */
    public function getFromName()
    {
        return $this->fromName;
    }

    /**
     * @param string $fromName
     */
    public function setFromName($fromName)
    {
        $this->fromName = $fromName;
    }

    /**
     * @return string
     */
    public function getReplyToAddress()
    {
        return $this->replyToAddress;
    }

    /**
     * @param string $replyToAddress
     */
    public function setReplyToAddress($replyToAddress)
    {
        $this->replyToAddress = $replyToAddress;
    }

    /**
     * @return string
     */
    public function getReplyToName()
    {
        return $this->replyToName;
    }

    /**
     * @param string $replyToName
     */
    public function setReplyToName($replyToName)
    {
        $this->replyToName = $replyToName;
    }

    /**
     * @return array
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @param array $attachments
     */
    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
    }

    /**
     * @return array
     */
    public function getCcs()
    {
        return $this->ccs;
    }

    /**
     * @param array $ccs
     */
    public function setCcs($ccs)
    {
        $this->ccs = $ccs;
    }
}
