<?php
namespace Lucinda\Mail;

/**
 * Encapsulates mail sending on top of PHP mail function
 */
class Message
{
    private $subject;
    private $to = array();
    private $from;
    private $sender;
    private $replyTo;
    private $cc = array();
    private $bcc = array();
    private $customHeaders = array();
    
    private $contentType;
    private $charset;
    private $message;
    private $attachments = array();

    /**
     * MailMessage constructor.
     * @param string $subject Subject of email.
     * @param string $body Email body.
     */
    public function __construct(string $subject, string $body)
    {
        $this->subject = $subject;
        $this->message = $body;
    }

    /**
     * Adds address to send mail to
     *
     * @param Address $address Value of email and optional name of person email belongs to
     */
    public function addTo(Address $address): void
    {
        $this->to[] = $address;
    }

    /**
     * Sets sender's address.
     *
     * @param Address $address Value of email and optional name of person email belongs to
     */
    public function setFrom(Address $address): void
    {
        $this->from = $address;
    }

    /**
     * Sets message submitter, in case mail agent is sending on behalf of someone else.
     *
     * @param Address $address Value of email and optional name of person email belongs to
     */
    public function setSender(Address $address): void
    {
        $this->sender = $address;
    }

    /**
     * Sets address recipients must use on replies to message.
     *
     * @param Address $address Value of email and optional name of person email belongs to
     */
    public function setReplyTo(Address $address): void
    {
        $this->replyTo = $address;
    }

    /**
     * Adds address to publicly send a copy of message to
     *
     * @param Address $address Value of email and optional name of person email belongs to
     */
    public function addCC(Address $address): void
    {
        $this->cc[] = $address;
    }

    /**
     * Adds address to discreetly send a copy of message to (invisible to others)
     *
     * @param Address $address Value of email and optional name of person email belongs to
     */
    public function addBCC(Address $address): void
    {
        $this->bcc[] = $address;
    }

    /**
     * Sets email content type (useful when it's different from text/plain) and charset (useful when it's different from iso-8859-1).
     *
     * @param string $contentType
     * @param string $charset
     */
    public function setContentType(string $contentType, string $charset): void
    {
        $this->contentType = $contentType;
        $this->charset = $charset;
    }

    /**
     * Adds custom mail header
     *
     * @param string $name Value of header name.
     * @param string $value Value of header content.
     */
    public function addCustomHeader(string $name, string $value): void
    {
        $this->customHeaders[] = $name.": ".$value;
    }

    /**
     * Adds attachment
     *
     * @param string $filePath Location of attached file
     */
    public function addAttachment(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("Attached file doesn't exist!");
        }
        $this->attachments[] = $filePath;
    }

    /**
     * Sends mail to recipients
     */
    public function send(): void
    {
        if (empty($this->to)) {
            throw new Exception("You must add at least one recipient to mail message!");
        }
        
        $separator = md5(uniqid(time()));
        $result = mail(implode(",", $this->to), $this->subject, $this->getBody($separator), implode("\r\n", $this->getHeaders($separator)));
        if (!$result) {
            throw new Exception("Send failed!");
        }
    }
    
    /**
     * Compiles email headers to send
     *
     * @param string $separator Separator to use in case attachments are sent
     * @return array Headers to send
     */
    private function getHeaders(string $separator): array
    {
        $headers = array();
        if (!empty($this->attachments)) {
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/mixed; boundary=\"".$separator."\"";
            $headers[] = "Content-Transfer-Encoding: 7bit";
            $headers[] = "This is a MIME encoded message";
        } else {
            if ($this->contentType) {
                $headers[] = "MIME-Version: 1.0";
                $headers[] = "Content-type:".$this->contentType."; charset=\"".$this->charset."\"";
            }
        }
        if (!empty($this->from)) {
            $headers[] = "From: ".$this->from;
        }
        if (!empty($this->sender)) {
            $headers[] = "Sender: ". $this->sender;
        }
        if (!empty($this->replyTo)) {
            $headers[] = "Reply-To: ".$this->replyTo;
        }
        if (!empty($this->cc)) {
            $headers[] = "Cc: ".implode(",", $this->cc);
        }
        if (!empty($this->bcc)) {
            $headers[] = "Bcc: ".implode(",", $this->bcc);
        }
        if (!empty($this->customHeaders)) {
            $headers = array_merge($headers, $this->customHeaders);
        }
        return $headers;
    }
    
    /**
     * Compiles message body to send
     *
     * @param string $separator Separator to use in case attachments are sent
     * @return string Message body to send.
     */
    private function getBody(string $separator): string
    {
        $body = "";
        if (!empty($this->attachments)) {
            $bodyParts = array();
            
            // add message body
            $bodyParts[] = "--".$separator;
            $bodyParts[] = "Content-Type: ".($this->contentType?$this->contentType:"text/plain")."; charset=\"".($this->charset?$this->charset:"iso-8859-1")."\"";
            $bodyParts[] = "Content-Transfer-Encoding: 8bit";
            $bodyParts[] = $this->message;
            
            // add attachments
            foreach ($this->attachments as $filePath) {
                $bodyParts[] = "--".$separator;
                $bodyParts[] = "Content-Type: ".mime_content_type($filePath)."; name=\"".basename($filePath) ."\"";
                $bodyParts[] = "Content-Transfer-Encoding: base64";
                $bodyParts[] = "Content-Disposition: attachment";
                $bodyParts[] = chunk_split(base64_encode(file_get_contents($filePath)));
            }
            $bodyParts[] = "--".$separator."--";
            $body = implode("\r\n", $bodyParts);
        } else {
            $body = $this->message;
        }
        return $body;
    }
}