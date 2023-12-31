<?php
/**
 * Horde_Core_ActiveSync_Mail::
 *
 * @copyright 2010-2017 Horde LLC (http://www.horde.org/)
 * @license http://www.horde.org/licenses/lgpl21 LGPL
 * @author  Michael J Rubinsky <mrubinsk@horde.org>
 * @package Core
 */
/**
 * Horde_Core_ActiveSync_Mail::
 *
 * Wraps functionality related to sending/replying/forwarding email from
 * EAS clients.
 *
 * @copyright 2010-2017 Horde LLC (http://www.horde.org/)
 * @license http://www.horde.org/licenses/lgpl21 LGPL
 * @author  Michael J Rubinsky <mrubinsk@horde.org>
 * @package Core
 *
 * @property-read Horde_ActiveSync_Imap_Adapter $imapAdapter  The imap adapter.
 * @property      boolean $replacemime  Flag to indicate we are to replace the MIME contents of a SMART request.
 * @property-read integer $id  The UID of the source email for any SMARTREPLY or SMARTFORWARD requests.
 * @property-read boolean $reply  Flag indicating a SMARTREPLY request.
 * @property-read boolean $forward  Flag indicating a SMARTFORWARD request.
 * @property-read Horde_Mime_Header $header  The headers used when sending the email.
 * @property-read string $parentFolder  he email folder that contains the source email for any SMARTREPLY or SMARTFORWARD requests.
 */
class Horde_Core_ActiveSync_Mail
{
    const HTML_BLOCKQUOTE = '<blockquote type="cite" style="border-left:2px solid blue;margin-left:2px;padding-left:12px;">';

    /**
     * The headers used when sending the email.
     *
     * @var Horde_Mime_Header
     */
    protected $_headers;

    /**
     * The raw message body sent from the EAS client.
     *
     * @var Horde_ActiveSync_Rfc822
     */
    protected $_raw;

    /**
     * The email folder that contains the source email for any SMARTREPLY or
     * SMARTFORWARD requests.
     *
     * @var string
     */
    protected $_parentFolder = false;

    /**
     * The UID of the source email for any SMARTREPLY or SMARTFORWARD requests.
     *
     * @var integer
     */
    protected $_id;

    /**
     * Flag indicating a SMARTFORWARD request.
     *
     * @var boolean
     */
    protected $_forward = false;

    /**
     * Flag indicating a SMARTREPLY request.
     *
     * @var boolean
     */
    protected $_reply = false;

    /**
     * Flag indicating the client requested to replace the MIME part
     * a SMARTREPLY or SMARTFORWARD request.
     *
     * @var boolean
     */
    protected $_replacemime = false;

    /**
     * The current EAS user.
     *
     * @var string
     */
    protected $_user;

    /**
     * Flag to indicate reply position for SMARTREPLY requests.
     *
     * @var boolean
     */
    protected $_replyTop = false;

    /**
     * Internal cache of the mailer used when sending SMART[REPLY|FORWARD].
     * Used to fetch the raw message used to save to sent mail folder.
     *
     * @var Horde_Mime_Mail
     */
    protected $_mailer;

    /**
     * The message object representing the source email for a
     * SMART[REPLY|FORWARD] request.
     *
     * @var Horde_ActiveSync_Imap_Message
     */
    protected $_imapMessage;

    /**
     * The imap adapter needed to fetch the source IMAP message if needed.
     *
     * @var Horde_ActiveSync_Imap_Adapter
     */
    protected $_imap;

    /**
     * EAS version in use.
     *
     * @var string
     */
    protected $_version;

    /**
     * Array of email addresses to forward message to, if using SMART_FORWARD.
     *
     * @var array
     * @since  2.31.0
     */
    protected $_forwardees = array();

    /**
     * Const'r
     *
     * @param Horde_ActiveSync_Imap_Adapter $imap  The IMAP adapter.
     * @param string $user                         EAS user.
     * @param integer $eas_version                 EAS version in use.
     */
    public function __construct(
        Horde_ActiveSync_Imap_Adapter $imap, $user, $eas_version)
    {
        $this->_imap = $imap;
        $this->_user = $user;
        $this->_version = $eas_version;
    }

    public function &__get($property)
    {
        switch ($property) {
        case 'imapMessage':
            if (!isset($this->_imapMessage)) {
                $this->_getImapMessage();
            }
            return $this->_imapMessage;
        case 'replacemime':
        case 'id':
        case 'reply':
        case 'forward':
        case 'headers':
        case 'parentFolder':
            $property = '_' . $property;
            return $this->$property;
        }
    }

    public function __set($property, $value)
    {
        if ($property == 'replacemime') {
            $this->_replacemime = $value;
        }
    }


    /**
     * Set the raw message content received from the EAS client to send.
     *
     * @param Horde_ActiveSync_Rfc822 $raw  The data from the EAS client.
     */
    public function setRawMessage(Horde_ActiveSync_Rfc822 $raw)
    {
        $this->_headers = $raw->getHeaders();

        // Attempt to always use the identity's From address, but fall back
        // to the device's sent value if it's not present.
        if ($from = $this->_getIdentityFromAddress()) {
            $this->_headers->removeHeader('From');
            $this->_headers->addHeader('From', $from);
        }

        // Reply-To?
        if ($replyto = $this->_getReplyToAddress()) {
            $this->_headers->addHeader('Reply-To', $replyto);
        }

        $this->_raw = $raw;
    }

    /**
     * Set this as a SMARTFORWARD requests.
     *
     * @param string $parent  The folder containing the source message.
     * @param integer $id     The source message UID.
     * @param  array $params  Additional parameters: @since 2.31.0
     *   - forwardees: An array of email addresses that this message will be
     *                 forwarded to. DEFAULT: Recipients are taken from raw
     *                 message.
     * @throws Horde_ActiveSync_Exception
     */
    public function setForward($parent, $id, $params = array())
    {
        if (!empty($this->_reply)) {
            throw new Horde_ActiveSync_Exception('Cannot set both Forward and Reply.');
        }
        $this->_id = $id;
        $this->_parentFolder = $parent;
        $this->_forward = true;

        if (!empty($params['forwardees'])) {
            $this->_forwardees = $params['forwardees'];
        }
    }

    /**
     * Set this as a SMARTREPLY requests.
     *
     * @param string $parent  The folder containing the source message.
     * @param integer $id     The source message UID.
     * @throws Horde_ActiveSync_Exception
     */
    public function setReply($parent, $id)
    {
        if (!empty($this->_forward)) {
            throw new Horde_ActiveSync_Exception('Cannot set both Forward and Reply.');
        }
        $this->_id = $id;
        $this->_parentFolder = $parent;
        $this->_reply = true;
    }

    /**
     * Send the email.
     *
     * @throws Horde_ActiveSync_Exception
     */
    public function send()
    {
        if (empty($this->_raw)) {
            throw new Horde_ActiveSync_Exception('No data set or received from EAS client.');
        }
        $this->_callPreSendHook();
        if (!$this->_parentFolder || ($this->_parentFolder && $this->_replacemime)) {
            $this->_sendRaw();
        } else {
            $this->_sendSmart();
        }
    }

    protected function _callPreSendHook()
    {
        $hooks = $GLOBALS['injector']->getInstance('Horde_Core_Hooks');
        $params = array(
            'raw' => $this->_raw,
            'imap_msg' => $this->imapMessage,
            'parent' => $this->_parentFolder,
            'reply' => $this->_reply,
            'forward' => $this->_forward);
        try {
            if (!$result = $hooks->callHook('activesync_email_presend', 'horde', array($params))) {
                throw new Horde_ActiveSync_Exception('There was an issue running the activesync_email_presend hook.');
            }
            if ($result instanceof Horde_ActiveSync_Mime) {
                $this->_raw->replaceMime($result->base);
            }
        } catch (Horde_Exception_HookNotSet $e) {
        }
    }

    /**
     * Get the raw message suitable for saving to the sent email folder.
     *
     * @return stream  A stream contianing the raw message.
     */
    public function getSentMail()
    {
        if (!empty($this->_mailer)) {
            return $this->_mailer->getRaw();
        }
        $stream = new Horde_Stream_Temp(array('max_memory' => 262144));
        $stream->add($this->_headers->toString(array('charset' => 'UTF-8')) . $this->_raw->getMessage(), true);
        return $stream;
    }

    /**
     * Send the raw message received from the client. E.g., NOT a SMART request.
     *
     * @throws Horde_ActiveSync_Exception
     */
    protected function _sendRaw()
    {
        $h_array = $this->_headers->toArray(array('charset' => 'UTF-8'));
        $recipients = $h_array['To'];
        if (!empty($h_array['Cc'])) {
            $recipients .= ',' . $h_array['Cc'];
        }
        if (!empty($h_array['Bcc'])) {
            $recipients .= ',' . $h_array['Bcc'];
            unset($h_array['Bcc']);
        }

        try {
            $GLOBALS['injector']->getInstance('Horde_Mail')
                ->send($recipients, $h_array, $this->_raw->getMessage()->stream);
        } catch (Horde_Mail_Exception $e) {
            throw new Horde_ActiveSync_Exception($e->getMessage());
        } catch (InvalidArgumentException $e) {
            // Some clients (HTC One devices, for one) generate HTML signatures
            // that contain line lengths too long for servers without BINARYMIME
            // to send. If we are here, see if that's the reason why by trying
            // to wrap any text/html parts.
            if (!$this->_tryWithoutBinary($recipients, $h_array)) {
                throw new Horde_ActiveSync_Exception($e->getMessage());
            }
        }

        // Replace MIME? Don't have original body, but still need headers.
        // @TODO: Get JUST the headers?
        if ($this->_replacemime) {
            try {
                $this->_getImapMessage();
            } catch (Horde_Exception_NotFound $e) {
                throw new Horde_ActiveSync_Exception($e->getMessage());
            }
        }
    }

    /**
     * Some clients (HTC One devices, for one) generate HTML signatures
     * that contain line lengths too long for servers without BINARYMIME to
     * send. If we are here, see if that's the reason by checking content
     * encoding and trying again.
     *
     * @return boolean
     */
    protected function _tryWithoutBinary($recipients, array $headers)
    {
        // All we need to do is re-assign the mime object. This will cause the
        // content transfer encoding to be re-evaulated and set to an approriate
        // value if needed.
        $mime = $this->_raw->getMimeObject();
        $this->_raw->replaceMime($mime);
        try {
            $GLOBALS['injector']->getInstance('Horde_Mail')
                ->send($recipients, $headers, $this->_raw->getMessage()->stream);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Sends a SMART response.
     *
     * @throws Horde_ActiveSync_Exception
     */
    protected function _sendSmart()
    {
        $mime_message = $this->_raw->getMimeObject();
        // Need to remove content-type header from the incoming raw message
        // since in a smart request, we actually construct the full MIME msg
        // ourselves and the content-type in _headers only applies to the reply
        // text sent from the client, not the fully generated MIME message.
        $this->_headers->removeHeader('Content-Type');
        $this->_headers->removeHeader('Content-Transfer-Encoding');

        // Check for EAS 16.0 Forwardees
        if (!empty($this->_forwardees)) {
            $list = new Horde_Mail_Rfc822_List();
            foreach ($this->_forwardees as $forwardee) {
                $to = new Horde_Mail_Rfc822_Address($forwardee->email);
                $to->personal = $forwardee->name;
                $list->add($to);
            }
            $this->_headers->add('To', $list->writeAddress());
        }

        $mail = new Horde_Mime_Mail($this->_headers->toArray(array('charset' => 'UTF-8')));
        $base_part = $this->imapMessage->getStructure();
        $plain_id = $base_part->findBody('plain');
        $html_id = $base_part->findBody('html');

        try {
            $body_data = $this->imapMessage->getMessageBodyData(array(
                'protocolversion' => $this->_version,
                'bodyprefs' => array(Horde_ActiveSync::BODYPREF_TYPE_MIME => true))
            );
        } catch (Horde_Exception_NotFound $e) {
            throw new Horde_ActiveSync_Exception($e->getMessage());
        }
        if (!empty($html_id)) {
            $mail->setHtmlBody($this->_getHtmlPart($html_id, $mime_message, $body_data, $base_part));
        } elseif (!empty($plain_id)) {
            $mail->setBody($this->_getPlainPart($plain_id, $mime_message, $body_data, $base_part));
        }
        if ($this->_forward) {
            foreach ($base_part->contentTypeMap() as $mid => $type) {
                if ($this->imapMessage->isAttachment($mid, $type)) {
                    $mail->addMimePart($this->imapMessage->getMimePart($mid));
                }
            }
        }
        foreach ($mime_message->contentTypeMap() as $mid => $type) {
            if ($mid != 0 && $mid != $mime_message->findBody('plain') && $mid != $mime_message->findBody('html')) {
                $mail->addMimePart($mime_message->getPart($mid));
            }
        }

        try {
            $mail->send($GLOBALS['injector']->getInstance('Horde_Mail'));
            $this->_mailer = $mail;
        } catch (Horde_Mime_Exception $e) {
            throw new Horde_ActiveSync_Exception($e);
        }
    }

    /**
     * Build the text part of a SMARTREPLY or SMARTFORWARD
     *
     * @param string $plain_id               The MIME part id of the plaintext
     *                                       part of $base_part.
     * @param Horde_Mime_Part $mime_message  The MIME part of the email to be
     *                                       sent.
     * @param array $body_data @see Horde_ActiveSync_Imap_Message::getMessageBodyData()
     * @param Horde_Mime_Part $base_part     The base MIME part of the source
     *                                       message for a SMART request.
     *
     * @return string  The plaintext part of the email message that is being sent.
     */
    protected function _getPlainPart(
        $plain_id, Horde_Mime_Part $mime_message, array $body_data, Horde_Mime_Part $base_part)
    {
        if (!$id = $mime_message->findBody('plain')) {
            $smart_text = Horde_ActiveSync_Utils::ensureUtf8(
                $mime_message->getPart($mime_message->findBody())->getContents(),
                $mime_message->getCharset()
            );
            $smart_text = $this->_tidyHtml($smart_text);
            $smart_text = self::html2text($smart_text);
        } else {
            $smart_text = Horde_ActiveSync_Utils::ensureUtf8(
                $mime_message->getPart($id)->getContents(),
                $mime_message->getCharset());
        }

        if ($this->_forward) {
            return $smart_text . $this->_forwardText($body_data, $base_part->getPart($plain_id));
        }

        return $smart_text . $this->_replyText($body_data, $base_part->getPart($plain_id));
    }

    /**
     * Build the HTML part of a SMARTREPLY or SMARTFORWARD
     *
     * @param string $html_id                The MIME part id of the html part of
     *                                       $base_part.
     * @param Horde_Mime_Part $mime_message  The MIME part of the email to be
     *                                       sent.
     * @param array $body_data @see Horde_ActiveSync_Imap_Message::getMessageBodyData()
     * @param Horde_Mime_Part $base_part     The base MIME part of the source
     *                                       message for a SMART request.
     *
     * @return string  The plaintext part of the email message that is being sent.
     */
    protected function _getHtmlPart($html_id, $mime_message, $body_data, $base_part)
    {
        if (!$id = $mime_message->findBody('html')) {
            $smart_text = self::text2html(
                Horde_ActiveSync_Utils::ensureUtf8(
                    $mime_message->getPart($mime_message->findBody('plain'))->getContents(),
                    $mime_message->getCharset()));
        } else {
            $smart_text = Horde_ActiveSync_Utils::ensureUtf8(
                $mime_message->getPart($id)->getContents(),
                $mime_message->getCharset());
        }

        if ($this->_forward) {
            return $smart_text . $this->_forwardText($body_data, $base_part->getPart($html_id), true);
        }
        return $smart_text . $this->_replyText($body_data, $base_part->getPart($html_id), true);
    }

    /**
     * Fetch the source message for a SMART request from the IMAP server.
     *
     * @throws Horde_Exception_NotFound
     */
    protected function _getImapMessage()
    {
        if (empty($this->_id) || empty($this->_parentFolder)) {
            return;
        }
        $this->_imapMessage = array_pop($this->_imap->getImapMessage($this->_parentFolder, $this->_id, array('headers' => true)));
        if (empty($this->_imapMessage)) {
            throw new Horde_Exception_NotFound('The forwarded/replied message was not found.');
        }
    }

    /**
     * Return the current user's From address.
     *
     * @return string  A RFC822 valid email string.
     */
    protected function _getIdentityFromAddress()
    {
        global $prefs;

        $ident = $GLOBALS['injector']
            ->getInstance('Horde_Core_Factory_Identity')
            ->create($this->_user);

        $as_ident = $prefs->getValue('activesync_identity');
        $name = $ident->getValue('fullname', $as_ident == 'horde' ? $prefs->getValue('default_identity') : $prefs->getValue('activesync_identity'));
        $from_addr = $ident->getValue('from_addr', $as_ident == 'horde' ? $prefs->getValue('default_identity') : $prefs->getValue('activesync_identity'));
        if (empty($from_addr)) {
            return;
        }
        $rfc822 = new Horde_Mail_Rfc822_Address($from_addr);
        $rfc822->personal = $name;

        return $rfc822->encoded;
    }

    /**
     * Return the current user's ReplyTo address, if available.
     *
     * @return string A RFC822 valid email string.
     */
    protected function _getReplyToAddress()
    {
        global $prefs;

        $ident = $GLOBALS['injector']
            ->getInstance('Horde_Core_Factory_Identity')
            ->create($this->_user);

        $as_ident = $prefs->getValue('activesync_identity');
        $replyto_addr = $ident->getValue('replyto_addr', $as_ident == 'horde' ? $prefs->getValue('default_identity') : $prefs->getValue('activesync_identity'));
        if (empty($replyto_addr)) {
            return;
        }
        $rfc822 = new Horde_Mail_Rfc822_Address($replyto_addr);

        return $rfc822->encoded;
    }

    /**
     * Return the body of the forwarded message in the appropriate type.
     *
     * @param array $body_data         The body data array of the source msg.
     * @param Horde_Mime_Part $part    The body part of the email to send.
     * @param boolean $html            Is this an html part?
     *
     * @return string  The propertly formatted forwarded body text.
     */
    protected function _forwardText(array $body_data, Horde_Mime_Part $part, $html = false)
    {
        return $this->_msgBody($body_data, $part, $html);
    }

    /**
     * Return the body of the replied message in the appropriate type.
     *
     * @param array $body_data         The body data array of the source msg.
     * @param Horde_Mime_Part $partId  The body part of the email to send.
     * @param boolean $html            Is this an html part?
     *
     * @return string  The propertly formatted replied body text.
     */
    protected function _replyText(array $body_data, Horde_Mime_Part $part, $html = false)
    {
        $msg = $this->_msgBody($body_data, $part, $html, true);
        if (!empty($msg) && $html) {
            return self::HTML_BLOCKQUOTE . $msg . '</blockquote><br /><br />';
        }
        return empty($msg)
            ? '[' . Horde_Core_Translation::t("No message body text") . ']'
            : $msg;
    }

    /**
     * Return the body text of the original email from a smart request.
     *
     * @param array $body_data       The body data array of the source msg.
     * @param Horde_Mime_Part $part  The body mime part of the email to send.
     * @param boolean $html          Do we want an html body?
     * @param boolean $flow          Should the body be flowed?
     *
     * @return string  The properly formatted/flowed message body.
     */
    protected function _msgBody(array $body_data, Horde_Mime_Part $part, $html, $flow = false)
    {
        $subtype = $html == true ? 'html' : 'plain';
        $msg = Horde_String::convertCharset(
            (string)$body_data[$subtype]['body'],
            $body_data[$subtype]['charset'],
            'UTF-8');
        if (!$html) {
            if ($part->getContentTypeParameter('format') == 'flowed') {
                $flowed = new Horde_Text_Flowed($msg, 'UTF-8');
                if (Horde_String::lower($part->getContentTypeParameter('delsp')) == 'yes') {
                    $flowed->setDelSp(true);
                }
                $flowed->setMaxLength(0);
                $msg = $flowed->toFixed(false);
            } else {
                // If not flowed, remove padding at eol
                $msg = preg_replace("/\s*\n/U", "\n", $msg);
            }
            if ($flow) {
                $flowed = new Horde_Text_Flowed($msg, 'UTF-8');
                $msg = $flowed->toFlowed(true);
            }
        } else {
            return $this->_tidyHtml($msg);
        }

        return $msg;
    }

    /**
     * Attempt to sanitize the provided $html string.
     * Uitilizes the Cleanhtml filter if able, otherwise
     * uses Horde_Dom
     *
     * @param  string $html  An HTML string to sanitize.
     *
     * @return string  The sanitized HTML.
     */
    protected function _tidyHtml($html)
    {
        // This filter requires the tidy extenstion.
        if (Horde_Util::extensionExists('tidy')) {
            return Horde_Text_Filter::filter(
                $html,
                'Cleanhtml',
                array('body_only' => true)
            );
        } else {
            // If no tidy, use Horde_Dom.
            $dom = new Horde_Domhtml($html, 'UTF-8');
            return $dom->returnBody();
        }
    }

    /**
     * Shortcut function to convert text -> HTML.
     *
     * @param string $msg  The message text.
     *
     * @return string  HTML text.
     */
    public static function text2html($msg)
    {
        return Horde_Text_Filter::filter(
            $msg,
            'Text2html',
            array(
                'flowed' => self::HTML_BLOCKQUOTE,
                'parselevel' => Horde_Text_Filter_Text2html::MICRO)
        );
    }

    public static function html2text($msg)
    {
        return Horde_Text_Filter::filter($msg, 'Html2text', array('nestingLimit' => 1000));
    }

}