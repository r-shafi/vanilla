<?php
/**
 * Gdn_Email.
 *
 * @author Mark O'Sullivan <markm@vanillaforums.com>
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package Core
 * @since 2.0
 */

/**
 * Object Representation of an email.
 *
 * All public methods return $this for
 * chaining purposes. ie. $Email->Subject('Hi')->Message('Just saying hi!')-
 * To('joe@vanillaforums.com')->Send();
 */
class Gdn_Email extends Gdn_Pluggable {

    /** @var PHPMailer */
    public $PhpMailer;

    /** @var boolean */
    private $_IsToSet;

    /** @var array Recipients that were skipped because they lack permission. */
    public $Skipped = array();

    /** @var EmailTemplate The email body renderer. Use this to edit the email body. */
    protected $emailTemplate;

    /** @var string The format of the email. */
    protected $format;

    /** @var string The supported email formats. */
    protected $allowedFormats = array('html', 'plaintext');

    /**
     * Constructor.
     */
    function __construct($format = 'html') {
        $this->PhpMailer = new PHPMailer();
        $this->PhpMailer->CharSet = c('Garden.Charset', 'utf-8');
        $this->PhpMailer->SingleTo = c('Garden.Email.SingleTo', false);
        $this->PhpMailer->PluginDir = combinePaths(array(PATH_LIBRARY, 'vendors/phpmailer/'));
        $this->PhpMailer->Hostname = c('Garden.Email.Hostname', '');
        $this->PhpMailer->Encoding = 'quoted-printable';
        $this->clear();
        $this->addHeader('Precedence', 'list');
        $this->addHeader('X-Auto-Response-Suppress', 'All');
        $this->format = in_array(strtolower($format), $this->allowedFormats) ? strtolower($format) : 'html';
        $this->emailTemplate = new EmailTemplate();
        if ($this->format === 'html') {
            $this->mimeType('text/html');
        } else {
            $this->emailTemplate->setPlaintext(true);
        }
        parent::__construct();
    }

    protected function setDefaultEmailColors() {
        if ($bg = c('Garden.Email.Styles.BackgroundColor')) {
            $this->emailTemplate->setBackgroundColor($bg);
        }
        if ($brandPrimary = c('Garden.Email.Styles.BrandPrimary')) {
            $this->emailTemplate->setBrandPrimary($brandPrimary);
        }
        if ($linkColor = c('Garden.Email.Styles.LinkColor')) {
            $this->emailTemplate->setLinkColor($linkColor);
        }
        if ($buttonBackgroundColor = c('Garden.Email.Styles.ButtonBackgroundColor')) {
            $this->emailTemplate->setButtonBackgroundColor($buttonBackgroundColor);
        }
    }

    /**
     * Sets the default logo for the email template.
     */
    protected function setDefaultEmailLogo() {
        if (!$this->emailTemplate->getImage()) {
            $logo = $this->getDefaultEmailLogo();
            $this->emailTemplate->setImageArray($logo);
        }
    }

    /**
     * Retrieves default values for the email logo.
     *
     * @return array An array representing an image.
     */
    public function getDefaultEmailLogo() {
        $logo = array();
        if ($logo['source'] = c('Garden.Logo', '')) {
            $logo['source'] = Gdn_Upload::url(c('Garden.Logo', ''));
        }
        $logo['link'] = url('/', true);
        $logo['alt'] = c('Garden.LogoTitle', c('Garden.Title', ''));
        return $logo;
    }

    /**
     * Sets the default title for the email template.
     */
    protected function setDefaultEmailTitle() {
        if ((!$this->emailTemplate->getTitle()) && $this->PhpMailer->Subject) {
            $title = $this->getDefaultEmailTitle();
            $this->emailTemplate->setTitle($title);
        }
    }

    /**
     * Returns the default title for an email based on its subject.
     * If the subject is prepended by the forum title, it removes this.
     *
     * @return string The email title.
     */
    public function getDefaultEmailTitle() {
        $title = $this->PhpMailer->Subject;
        $prefix = '['.c('Garden.Title').'] ';
        if (strpos($title, $prefix) == 0) {
            $title = substr($title, strlen($prefix));
        }
        return $title;
    }

    /**
     * Add a custom header to the outgoing email.
     *
     * @param string $Name
     * @param string $Value
     * @since 2.1
     */
    public function addHeader($Name, $Value) {
        $this->PhpMailer->addCustomHeader("$Name:$Value");
    }

    /**
     * Adds to the "Bcc" recipient collection.
     *
     * @param mixed $RecipientEmail An email (or array of emails) to add to the "Bcc" recipient collection.
     * @param string $RecipientName The recipient name associated with $RecipientEmail. If $RecipientEmail is
     * an array of email addresses, this value will be ignored.
     * @return Email
     */
    public function bcc($RecipientEmail, $RecipientName = '') {
        ob_start();
        $this->PhpMailer->addBCC($RecipientEmail, $RecipientName);
        ob_end_clean();
        return $this;
    }

    /**
     * Adds to the "Cc" recipient collection.
     *
     * @param mixed $RecipientEmail An email (or array of emails) to add to the "Cc" recipient collection.
     * @param string $RecipientName The recipient name associated with $RecipientEmail. If $RecipientEmail is
     * an array of email addresses, this value will be ignored.
     * @return Email
     */
    public function cc($RecipientEmail, $RecipientName = '') {
        ob_start();
        $this->PhpMailer->addCC($RecipientEmail, $RecipientName);
        ob_end_clean();
        return $this;
    }

    /**
     * Clears out all previously specified values for this object and restores
     * it to the state it was in when it was instantiated.
     *
     * @return Email
     */
    public function clear() {
        $this->PhpMailer->clearAllRecipients();
        $this->PhpMailer->Body = '';
        $this->PhpMailer->AltBody = '';
        $this->from();
        $this->_IsToSet = false;
        $this->mimeType(c('Garden.Email.MimeType', 'text/plain'));
        $this->_MasterView = 'email.master';
        $this->Skipped = array();
        return $this;
    }

    /**
     * Allows the explicit definition of the email's sender address & name.
     * Defaults to the applications Configuration 'SupportEmail' & 'SupportName' settings respectively.
     *
     * @param string $SenderEmail
     * @param string $SenderName
     * @return Email
     */
    public function from($SenderEmail = '', $SenderName = '', $bOverrideSender = false) {
        if ($SenderEmail == '') {
            $SenderEmail = c('Garden.Email.SupportAddress', '');
            if (!$SenderEmail) {
                $SenderEmail = 'noreply@'.Gdn::request()->host();
            }
        }

        if ($SenderName == '') {
            $SenderName = c('Garden.Email.SupportName', c('Garden.Title', ''));
        }

        if ($this->PhpMailer->Sender == '' || $bOverrideSender) {
            $this->PhpMailer->Sender = $SenderEmail;
        }

        ob_start();
        $this->PhpMailer->setFrom($SenderEmail, $SenderName, false);
        ob_end_clean();
        return $this;
    }

    /**
     * Allows the definition of a masterview other than the default: "email.master".
     *
     * @param string $MasterView
     * @return Email
     */
    public function masterView($MasterView) {
        return $this;
    }

    /**
     * The message to be sent.
     *
     * @param string $Message The body of the message to be sent.
     * @return Email
     */
    public function message($Message) {
        $this->emailTemplate->setMessage($Message);
    }

    public function formatMessage($message) {
        // htmlspecialchars_decode is being used here to revert any specialchar escaping done by Gdn_Format::Text()
        // which, untreated, would result in &#039; in the message in place of single quotes.

        if ($this->PhpMailer->ContentType == 'text/html') {
            $TextVersion = false;
            if (stristr($message, '<!-- //TEXT VERSION FOLLOWS//')) {
                $EmailParts = explode('<!-- //TEXT VERSION FOLLOWS//', $message);
                $TextVersion = array_pop($EmailParts);
                $message = array_shift($EmailParts);
                $TextVersion = trim(strip_tags(preg_replace('/<(head|title|style|script)[^>]*>.*?<\/\\1>/s', '', $TextVersion)));
                $message = trim($message);
            }

            $this->PhpMailer->msgHTML(htmlspecialchars_decode($message, ENT_QUOTES));
            if ($TextVersion !== false && !empty($TextVersion)) {
                $TextVersion = html_entity_decode($TextVersion);
                $this->PhpMailer->AltBody = $TextVersion;
            }
        } else {
            $this->PhpMailer->Body = htmlspecialchars_decode($message, ENT_QUOTES);
        }
        return $this;
    }

    /**
     * @return EmailTemplate The email body renderer.
     */
    public function getEmailTemplate() {
        return $this->emailTemplate;
    }

    /**
     * @param EmailTemplate $emailTemplate The email body renderer.
     * @return Email
     */
    public function setEmailTemplate($emailTemplate) {
        $this->emailTemplate = $emailTemplate;
        return $this;
    }

    /**
     *
     *
     * @param $Template
     * @return bool|mixed|string
     */
    public static function getTextVersion($Template) {
        if (stristr($Template, '<!-- //TEXT VERSION FOLLOWS//')) {
            $EmailParts = explode('<!-- //TEXT VERSION FOLLOWS//', $Template);
            $TextVersion = array_pop($EmailParts);
            $TextVersion = trim(strip_tags(preg_replace('/<(head|title|style|script)[^>]*>.*?<\/\\1>/s', '', $TextVersion)));
            return $TextVersion;
        }
        return false;
    }

    /**
     *
     *
     * @param $Template
     * @return mixed|string
     */
    public static function getHTMLVersion($Template) {
        if (stristr($Template, '<!-- //TEXT VERSION FOLLOWS//')) {
            $EmailParts = explode('<!-- //TEXT VERSION FOLLOWS//', $Template);
            $TextVersion = array_pop($EmailParts);
            $Message = array_shift($EmailParts);
            $Message = trim($Message);
            return $Message;
        }
        return $Template;
    }

    /**
     * Sets the mime-type of the email.
     *
     * Only accept text/plain or text/html.
     *
     * @param string $MimeType The mime-type of the email.
     * @return Email
     */
    public function mimeType($MimeType) {
        $this->PhpMailer->isHTML($MimeType === 'text/html');
        return $this;
    }

    /**
     * @todo add port settings
     */
    public function send($EventName = '') {
        if ($this->format == 'html') {
            $this->setDefaultEmailTitle();
            $this->setDefaultEmailLogo();
            $this->setDefaultEmailColors();
        }
        $this->formatMessage($this->emailTemplate->toString());
        $this->fireEvent('BeforeSendMail');

        if (c('Garden.Email.Disabled')) {
            return;
        }

        if (C('Garden.Email.UseSmtp')) {
            $this->PhpMailer->isSMTP();
            $SmtpHost = c('Garden.Email.SmtpHost', '');
            $SmtpPort = c('Garden.Email.SmtpPort', 25);
            if (strpos($SmtpHost, ':') !== false) {
                list($SmtpHost, $SmtpPort) = explode(':', $SmtpHost);
            }

            $this->PhpMailer->Host = $SmtpHost;
            $this->PhpMailer->Port = $SmtpPort;
            $this->PhpMailer->SMTPSecure = C('Garden.Email.SmtpSecurity', '');
            $this->PhpMailer->Username = $Username = C('Garden.Email.SmtpUser', '');
            $this->PhpMailer->Password = $Password = C('Garden.Email.SmtpPassword', '');
            if (!empty($Username)) {
                $this->PhpMailer->SMTPAuth = true;
            }


        } else {
            $this->PhpMailer->isMail();
        }

        if ($EventName != '') {
            $this->EventArguments['EventName'] = $EventName;
            $this->fireEvent('SendMail');
        }

        if (!empty($this->Skipped) && $this->PhpMailer->countRecipients() == 0) {
            // We've skipped all recipients.
            return true;
        }

        $this->PhpMailer->throwExceptions(true);
        if (!$this->PhpMailer->send()) {
            throw new Exception($this->PhpMailer->ErrorInfo);
        }

        return true;
    }

    /**
     * Adds subject of the message to the email.
     *
     * @param string $Subject The subject of the message.
     */
    public function subject($Subject) {
        $this->PhpMailer->Subject = $Subject;
        return $this;
    }

    public function addTo($RecipientEmail, $RecipientName = '') {
        ob_start();
        $this->PhpMailer->addAddress($RecipientEmail, $RecipientName);
        ob_end_clean();
        return $this;
    }

    /**
     * Adds to the "To" recipient collection.
     *
     * @param mixed $RecipientEmail An email (or array of emails) to add to the "To" recipient collection.
     * @param string $RecipientName The recipient name associated with $RecipientEmail. If $RecipientEmail is
     * an array of email addresses, this value will be ignored.
     */
    public function to($RecipientEmail, $RecipientName = '') {

        if (is_string($RecipientEmail)) {
            if (strpos($RecipientEmail, ',') > 0) {
                $RecipientEmail = explode(',', $RecipientEmail);
                // trim no need, PhpMailer::AddAnAddress() will do it
                return $this->to($RecipientEmail, $RecipientName);
            }
            if ($this->PhpMailer->SingleTo) {
                return $this->addTo($RecipientEmail, $RecipientName);
            }
            if (!$this->_IsToSet) {
                $this->_IsToSet = true;
                $this->addTo($RecipientEmail, $RecipientName);
            } else {
                $this->cc($RecipientEmail, $RecipientName);
            }
            return $this;

        } elseif ((is_object($RecipientEmail) && property_exists($RecipientEmail, 'Email'))
            || (is_array($RecipientEmail) && isset($RecipientEmail['Email']))
        ) {
            $User = $RecipientEmail;
            $RecipientName = val('Name', $User);
            $RecipientEmail = val('Email', $User);
            $UserID = val('UserID', $User, false);

            if ($UserID !== false) {
                // Check to make sure the user can receive email.
                if (!Gdn::userModel()->checkPermission($UserID, 'Garden.Email.View')) {
                    $this->Skipped[] = $User;

                    return $this;
                }
            }

            return $this->to($RecipientEmail, $RecipientName);

        } elseif ($RecipientEmail instanceof Gdn_DataSet) {
            foreach ($RecipientEmail->resultObject() as $Object) {
                $this->to($Object);
            }
            return $this;

        } elseif (is_array($RecipientEmail)) {
            $Count = count($RecipientEmail);
            if (!is_array($RecipientName)) {
                $RecipientName = array_fill(0, $Count, '');
            }
            if ($Count == count($RecipientName)) {
                $RecipientEmail = array_combine($RecipientEmail, $RecipientName);
                foreach ($RecipientEmail as $Email => $Name) {
                    $this->to($Email, $Name);
                }
            } else {
                trigger_error(errorMessage('Size of arrays do not match', 'Email', 'To'), E_USER_ERROR);
            }

            return $this;
        }

        trigger_error(errorMessage('Incorrect first parameter ('.getType($RecipientEmail).') passed to function.', 'Email', 'To'), E_USER_ERROR);
    }

    public function charset($Use = '') {
        if ($Use != '') {
            $this->PhpMailer->CharSet = $Use;
            return $this;
        }
        return $this->PhpMailer->CharSet;
    }
}
