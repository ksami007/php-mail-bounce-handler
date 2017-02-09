<?php

/**
 * Handler.
 *
 * @author Cr@zy
 * @copyright 2013-2016, Cr@zy
 * @license GNU LESSER GENERAL PUBLIC LICENSE
 *
 * @link https://github.com/crazy-max/CwsMailBounceHandler
 */
namespace PhpMailBounceHandler;

use PhpMailBounceHandler\Models\Mail;
use PhpMailBounceHandler\Models\Recipient;
use PhpMailBounceHandler\Models\Result;

class Handler
{
    const OPEN_MODE_MAILBOX = 'mailbox'; // if you open a mailbox via imap.
    const OPEN_MODE_FILE = 'file'; // if you open a eml file or a folder containing eml files.

    const PROCESS_MODE_NEUTRAL = 'neutral'; // messages will not be processed.
    const PROCESS_MODE_MOVE = 'move'; // if you want to move bounces.
    const PROCESS_MODE_DELETE = 'delete'; // if you want to delete bounces.

    const MAILBOX_SERVICE_IMAP = 'imap';
    const MAILBOX_SERVICE_POP3 = 'pop3';

    const MAILBOX_PORT_POP3 = 110;
    const MAILBOX_PORT_POP3_TLS_SSL = 995;
    const MAILBOX_PORT_IMAP = 143;
    const MAILBOX_PORT_IMAP_TLS_SSL = 993;

    const MAILBOX_SECURITY_NONE = 'none';
    const MAILBOX_SECURITY_NOTLS = 'notls';
    const MAILBOX_SECURITY_TLS = 'tls';
    const MAILBOX_SECURITY_SSL = 'ssl';

    const MAILBOX_CERT_NOVALIDATE = 'novalidate-cert'; // do not validate certificates from TLS/SSL server.
    const MAILBOX_CERT_VALIDATE = 'validate-cert'; // validate certificates from TLS/SSL server.

    const SUFFIX_FBL_MOVE = 'fbl'; // suffix of mailbox or folder to move bounces to.

    const TYPE_FBL = 'fbl'; // message is a feedback loop

    const SEARCH_ALL = 'ALL';
    const SEARCH_UNSEEN = 'UNSEEN';

    /**
     * @var bool
     */
    private $showProgress = false;

    /**
     * Control the method to open e-mail(s).
     *
     * @var string
     */
    private $openMode;

    /**
     * Control the method to process bounces.
     * default PROCESS_MODE_NEUTRAL.
     *
     * @var string
     */
    private $processMode;

    /**
     * Defines mailbox service, MAILBOX_SERVICE_POP3 or MAILBOX_SERVICE_IMAP
     * default MAILBOX_SERVICE_IMAP.
     *
     * @var string
     */
    private $mailboxService;

    /**
     * Mailbox host server.<br />
     * default 'localhost'.
     *
     * @var string
     */
    private $mailboxHost;

    /**
     * The username of mailbox.
     *
     * @var string
     */
    private $mailboxUsername;

    /**
     * The password needed to access mailbox.
     *
     * @var string
     */
    private $mailboxPassword;

    /**
     * Defines port number, other common choices are MAILBOX_PORT_IMAP, MAILBOX_PORT_IMAP_TLS_SSL
     * default MAILBOX_PORT_IMAP.
     *
     * @var int
     */
    private $mailboxPort;

    /**
     * Defines service option, choices are MAILBOX_SECURITY_NONE, MAILBOX_SECURITY_NOTLS, MAILBOX_SECURITY_TLS, MAILBOX_SECURITY_SSL.
     * default MAILBOX_SECURITY_NOTLS.
     *
     * @var string
     */
    private $mailboxSecurity;

    /**
     * Control certificates validation if mailSecurity is MAILBOX_SECURITY_TLS or MAILBOX_SECURITY_SSL.
     * default MAILBOX_CERT_NOVALIDATE.
     *
     * @var string
     */
    private $mailboxCert;

    /**
     * Mailbox type, other choices are (Tasks, Spam, Replies, etc.)
     * default 'INBOX'.
     *
     * @var string
     */
    private $mailboxName = 'INBOX';

    /**
     * The resource handler for the opened mailbox (POP3/IMAP/NNTP/etc.).
     *
     * @var resource
     */
    private $mailboxHandler = false;

    /**
     * Maximum limit messages processed in one batch (0 for unlimited).
     * default 0.
     *
     * @var int
     */
    private $maxMessages = 0;

    /**
     * Purge unknown messages. Be careful with this option.
     * default false.
     *
     * @var bool
     */
    private $purge = false;

    /**
     * The folder path opened.
     *
     * @var string
     */
    private $emlFolder;

    /**
     * The eml files opened.
     *
     * @var array
     */
    private $emlFiles;

    /**
     * Control the move mode.
     *
     * @var bool
     */
    private $enableMove;

    /**
     * The last error message.
     *
     * @var string
     */
    private $error;

    /**
     * Search criteria.
     * default SEARCH_ALL.
     *
     * @var string
     */
    private $searchCriteria;

    public function __construct()
    {
        $this->processMode     = self::PROCESS_MODE_NEUTRAL;
        $this->mailboxService  = self::MAILBOX_SERVICE_IMAP;
        $this->mailboxHost     = 'localhost';
        $this->mailboxPort     = self::MAILBOX_PORT_IMAP;
        $this->mailboxSecurity = self::MAILBOX_SECURITY_NOTLS;
        $this->mailboxCert     = self::MAILBOX_CERT_NOVALIDATE;
        $this->purge           = false;
        $this->searchCriteria  = self::SEARCH_ALL;
    }

    private function reset()
    {
        $this->mailboxHandler = false;
        $this->emlFolder      = '';
        $this->emlFiles       = array();
        $this->enableMove     = true;
    }

    /**
     * Open a IMAP mail box in local file system.
     *
     * @param string $filePath : the local mailbox file path
     *
     * @return bool
     */
    public function openImapLocal($filePath)
    {
        $this->reset();

        $this->openMode = self::OPEN_MODE_MAILBOX;

        $this->mailboxHandler = imap_open(
            $filePath,
            '',
            '',
            !$this->isNeutralProcessMode() ? CL_EXPUNGE : null
        );

        if (!$this->mailboxHandler) {
            $this->error = 'Cannot open the mailbox file to '.$filePath.': '.imap_last_error();

            return false;
        }

        return true;
    }

    /**
     * Open a remote IMAP mail box.
     *
     * @return bool
     */
    public function openImapRemote()
    {
        $this->reset();

        $this->openMode = self::OPEN_MODE_MAILBOX;

        // disable move operations if server is Gmail... Gmail does not support mailbox creation
        if (stristr($this->mailboxHost, 'gmail') && $this->isMoveProcessMode()) {
            $this->enableMove = false;
        }

        // required options for imap_open connection.
        $opts = '/'.$this->mailboxService.'/'.$this->mailboxSecurity;
        if ($this->mailboxSecurity == self::MAILBOX_SECURITY_TLS || $this->mailboxSecurity == self::MAILBOX_SECURITY_SSL) {
            $opts .= '/'.$this->mailboxCert;
        }

        $this->mailboxHandler = imap_open(
            '{'.$this->mailboxHost.':'.$this->mailboxPort.$opts.'}'.$this->mailboxName,
            $this->mailboxUsername,
            $this->mailboxPassword,
            !$this->isNeutralProcessMode() ? CL_EXPUNGE : null
        );

        if (!$this->mailboxHandler) {
            $this->error = 'Cannot create '.$this->mailboxService.' connection to '.$this->mailboxHost.': '.imap_last_error();

            return false;
        } else {
            return true;
        }
    }

    /**
     * Open a folder containing eml files on your system.
     *
     * @param string $emlFolder : the eml folder
     *
     * @return bool
     */
    public function openEmlFolder($emlFolder)
    {
        $this->reset();

        $this->openMode = self::OPEN_MODE_FILE;

        $this->emlFolder = self::formatUnixPath(rtrim(realpath($emlFolder), '/'));

        $handle = @opendir($this->emlFolder);
        if (!$handle) {
            $this->error = 'Cannot open the eml folder '.$this->emlFolder;

            return false;
        }

        $nbFiles = 0;
        while (false !== ($file = readdir($handle))) {
            if ($file == '.' || $file == '..' || !self::endWith($file, '.eml')) {
                continue;
            }
            $emlFilePath = $this->emlFolder.'/'.$file;
            $emlFile = self::getEmlFile($emlFilePath);
            if (!empty($emlFile)) {
                $this->emlFiles[] = $emlFile;
            }
            $nbFiles++;
        }
        closedir($handle);

        if (empty($this->emlFiles)) {
            $this->error = 'No eml file found in '.$this->emlFolder;

            return false;
        } else {
            return true;
        }
    }

    /**
     * Process the messages in a mailbox or a folder.
     *
     * @return bool|Result
     */
    public function processMails()
    {
        if ($this->isMailboxOpenMode()) {
            if (!$this->mailboxHandler) {
                $this->error = 'Mailbox not opened';

                return false;
            }
        } else {
            if (empty($this->emlFiles)) {
                $this->error = 'File(s) not opened';

                return false;
            }
        }

        $searchCriteria = $this->getSearchCriteria();
        $cwsMbhResult = $this->isMailboxOpenMode() && ($searchCriteria != self::SEARCH_ALL)
            ? $this->getEmailsByCriteria($searchCriteria)
            : $this->getAllEmails();

        // process mails
        foreach ($cwsMbhResult->getMails() as $cwsMbhMail) {
            /* @var $cwsMbhMail Mail */
            $cwsMbhResult->getCounter()->incrProcessed();
            if ($this->enableMove && $this->isMoveProcessMode()) {
                $this->processMailMove($cwsMbhMail);
                $cwsMbhResult->getCounter()->incrMoved();
            } elseif ($this->isDeleteProcessMode()) {
                $this->processMailDelete($cwsMbhMail);
                $cwsMbhResult->getCounter()->incrDeleted();
            }
        }

        if ($this->isMailboxOpenMode()) {
            @imap_close($this->mailboxHandler);
        }

        return $cwsMbhResult;
    }

    /**
     * Get all emails
     *
     * @return \PhpMailBounceHandler\Models\Result
     */
    private function getAllEmails()
    {
        $cwsMbhResult = new Result();

        $this->enableMove = $this->enableMove && $this->isMoveProcessMode();

        // count mails
        $totalMails = $this->isMailboxOpenMode() ? imap_num_msg($this->mailboxHandler) : count($this->emlFiles);

        // init counter
        $cwsMbhResult->getCounter()->setTotal($totalMails);
        $cwsMbhResult->getCounter()->setFetched($totalMails);

        // process maximum number of messages
        if ($this->maxMessages > 0 && $cwsMbhResult->getCounter()->getFetched() > $this->maxMessages) {
            $cwsMbhResult->getCounter()->setFetched($this->maxMessages);
        }

        // parsing mails
        if ($this->isMailboxOpenMode()) {

            $fetched = $cwsMbhResult->getCounter()->getFetched();

            for ($mailNo = 1; $mailNo <= $fetched; $mailNo++) {
                $header = @imap_fetchheader($this->mailboxHandler, $mailNo);
                $body = @imap_body($this->mailboxHandler, $mailNo);
                $processedMail = $this->processMailParsing($mailNo, $header.'\r\n\r\n'.$body);
                if (!is_null($processedMail)) {
                    $cwsMbhResult->addMail($processedMail);
                }

                if ($this->getShowProgress()) {
                    $this->showProcessMailProgress($fetched, $mailNo);
                }
            }
        } else {
            foreach ($this->emlFiles as $file) {
                $processedMail = $this->processMailParsing($file['name'], $file['content']);
                if (!is_null($processedMail)) {
                    $cwsMbhResult->addMail($processedMail);
                }

            }
        }

        return $cwsMbhResult;
    }

    /**
     * Get emails by criteria
     *
     * @param string $criteria
     * @return \PhpMailBounceHandler\Models\Result
     */
    private function getEmailsByCriteria($criteria)
    {
        $cwsMbhResult = new Result();

        $this->enableMove = $this->enableMove && $this->isMoveProcessMode();

        $filteredEmails = imap_search($this->mailboxHandler, $criteria);

        // count mails
        $totalMails = count($filteredEmails);

        // init counter
        $cwsMbhResult->getCounter()->setTotal($totalMails);
        $cwsMbhResult->getCounter()->setFetched($totalMails);

        // process maximum number of messages
        if ($this->maxMessages > 0 && $cwsMbhResult->getCounter()->getFetched() > $this->maxMessages) {
            $cwsMbhResult->getCounter()->setFetched($this->maxMessages);
            $filteredEmails = array_slice($filteredEmails, 0, $this->maxMessages);
        }

        $fetched = $cwsMbhResult->getCounter()->getFetched();

        // parsing mails
        foreach ($filteredEmails as $key => $val) {
            $header = @imap_fetchheader($this->mailboxHandler, $val);
            $body = @imap_body($this->mailboxHandler, $val);
            $processedMail = $this->processMailParsing($val, $header.'\r\n\r\n'.$body);
            if (!is_null($processedMail)) {
                $cwsMbhResult->addMail($processedMail);
            }

            if ($this->getShowProgress()) {
                $curr = $key + 1;
                $this->showProcessMailProgress($fetched, $curr);
            }
        }

        return $cwsMbhResult;
    }

    /**
     * Function to process parsing of each individual message.
     *
     * @param string $token   : message number or filename.
     * @param string $content : message content.
     *
     * @return null|Mail
     */
    private function processMailParsing($token, $content)
    {
        $cwsMbhMail = new Mail();
        $cwsMbhMail->setToken($token);

        // format content
        $content = self::formatEmailContent($content);

        // split head and body
        if (preg_match('#\r\n\r\n#is', $content)) {
            list($header, $body) = preg_split('#\r\n\r\n#', $content, 2);
        } else {
            unset($cwsMbhMail);
            return null;
        }

        $cwsMbhMail->setHeader($header);
        $cwsMbhMail->setBody($body);

        // parse header
        $header = self::parseHeader($header);

        // parse body sections
        $bodySections = self::parseBodySections($header, $body);

        $isFbl = self::isFbl($header);

        // begin process
        $tmpRecipients = array();
        if ($isFbl) {
            $cwsMbhMail->setSubject(trim(str_ireplace('Fw:', '', $header['Subject'])));

            if (!self::isEmpty($bodySections, 'machine')) {
                $bodySections['arMachine'] = self::parseLines($bodySections['machine']);
            } else {
                $bodySections['arMachine'] = [];
            }

            if (!self::isEmpty($bodySections, 'returned')) {
                $bodySections['arReturned'] = self::parseLines($bodySections['returned']);
            } else {
                $bodySections['arReturned'] = [];
            }

            if (self::isEmpty($bodySections['arMachine'], 'Original-mail-from') && !self::isEmpty($bodySections['arReturned'], 'From')) {
                $bodySections['arMachine']['Original-mail-from'] = $bodySections['arReturned']['From'];
            }

            if (self::isEmpty($bodySections['arMachine'], 'Original-rcpt-to') && !self::isEmpty($bodySections['arMachine'], 'Removal-recipient')) {
                $bodySections['arMachine']['Original-rcpt-to'] = $bodySections['arMachine']['Removal-recipient'];
            } elseif (!self::isEmpty($bodySections['arReturned'], 'To')) {
                $bodySections['arMachine']['Original-rcpt-to'] = $bodySections['arReturned']['To'];
            }

            // try to get the actual intended recipient if possible
            if (preg_match('#Undisclosed|redacted#i', $bodySections['arMachine']['Original-mail-from']) && !self::isEmpty($bodySections['arMachine'], 'Removal-recipient')) {
                $bodySections['arMachine']['Original-rcpt-to'] = $bodySections['arMachine']['Removal-recipient'];
            }

            if (self::isEmpty($bodySections['arMachine'], 'Received-date') && !self::isEmpty($bodySections['arMachine'], 'Arrival-date')) {
                $bodySections['arMachine']['Received-date'] = $bodySections['arMachine']['Arrival-date'];
            }

            if (!self::isEmpty($bodySections['arMachine'], 'Original-mail-from')) {
                $bodySections['arMachine']['Original-mail-from'] = self::extractEmail($bodySections['arMachine']['Original-mail-from']);
            }

            if (!self::isEmpty($bodySections['arMachine'], 'Original-rcpt-to')) {
                $bodySections['arMachine']['Original-rcpt-to'] = self::extractEmail($bodySections['arMachine']['Original-rcpt-to']);
            }

            $cwsMbhRecipient = new Recipient();
            if (!empty($bodySections['arMachine']['Original-rcpt-to'])) {
                $cwsMbhRecipient->setEmail($bodySections['arMachine']['Original-rcpt-to']);
            }
            $tmpRecipients[] = $cwsMbhRecipient;

            if (count($tmpRecipients) > 0) {
                foreach ($tmpRecipients as $cwsMbhRecipient) {
                    /* @var $cwsMbhRecipient Recipient */
                    $cwsMbhMail->addRecipient($cwsMbhRecipient);
                }
            }
            return $cwsMbhMail;
        }

        unset($cwsMbhMail);
        return null;
    }

    /**
     * Function to delete a message.
     *
     * @param Mail $cwsMbhMail : mail bounce object.
     *
     * @return bool
     */
    private function processMailDelete(Mail $cwsMbhMail)
    {
        if ($this->isMailboxOpenMode()) {
            return @imap_delete($this->mailboxHandler, $cwsMbhMail->getToken());
        } elseif ($this->isFileOpenMode()) {
            return @unlink($this->emlFolder.'/'.$cwsMbhMail->getToken());
        }

        return false;
    }

    /**
     * Method to move a mail bounce.
     *
     * @param Mail $cwsMbhMail : mail bounce object.
     *
     * @return bool
     */
    private function processMailMove(Mail $cwsMbhMail)
    {
        if ($this->isMailboxOpenMode()) {
            $moveFolder = $this->getMailboxName().'.'.self::SUFFIX_FBL_MOVE;
            if ($this->isImapMailboxExists($moveFolder)) {
                return imap_mail_move($this->mailboxHandler, $cwsMbhMail->getToken(), $moveFolder);
            }
        } elseif ($this->isFileOpenMode()) {
            $moveFolder = $this->emlFolder.'/'.self::SUFFIX_FBL_MOVE;
            if (!is_dir($moveFolder)) {
                mkdir($moveFolder);
            }

            return rename($this->emlFolder.'/'.$cwsMbhMail->getToken(), $moveFolder.'/'.$cwsMbhMail->getToken());
        }

        return false;
    }

    /**
     * Function to check if a mailbox exists. If not found, it will create it.
     *
     * @param string $mailboxName : the mailbox name, must be in mailboxName.bounces format
     * @param bool   $create      : whether or not to create the mailbox if not found, defaults to true
     *
     * @return bool
     */
    private function isImapMailboxExists($mailboxName, $create = true)
    {
        // required security option for imap_open connection.
        $opts = '/'.$this->mailboxService.'/'.$this->mailboxSecurity;
        if ($this->mailboxSecurity == self::MAILBOX_SECURITY_TLS || $this->mailboxSecurity == self::MAILBOX_SECURITY_SSL) {
            $opts .= '/'.$this->mailboxCert;
        }

        $handler = imap_open(
            '{'.$this->mailboxHost.':'.$this->mailboxPort.$opts.'}',
            $this->mailboxUsername,
            $this->mailboxPassword,
            !$this->isNeutralProcessMode() ? CL_EXPUNGE : null
        );

        $list = imap_getmailboxes($handler,
            '{'.$this->mailboxHost.':'.$this->mailboxPort.$opts.'}',
            '*'
        );

        $mailboxFound = false;
        if (is_array($list)) {
            foreach ($list as $key => $val) {
                // get the mailbox name only
                $nameArr = explode('}', imap_utf7_decode($val->name));
                $nameRaw = $nameArr[count($nameArr) - 1];
                if ($mailboxName == $nameRaw) {
                    $mailboxFound = true;
                    break;
                }
            }
            if ($mailboxFound === false && $create) {
                $mailboxFound = @imap_createmailbox(
                    $handler,
                    imap_utf7_encode('{'.$this->mailboxHost.':'.$this->mailboxPort.$opts.'}'.$mailboxName)
                );
            }
        }

        return $mailboxFound;
    }

    /**
     * Function to parse the header with some custom fields.
     *
     * @param array $arHeader : the array or plain text headers
     *
     * @return array
     */
    private static function parseHeader($arHeader)
    {
        if (!is_array($arHeader)) {
            $arHeader = explode("\r\n", $arHeader);
        }
        $arHeader = self::parseLines($arHeader);

        if (isset($arHeader['Received'])) {
            $arrRec = explode('|', $arHeader['Received']);
            $arHeader['Received'] = $arrRec;
        }

        if (isset($arHeader['Content-type'])) {
            $ar_mr = explode(';', $arHeader['Content-type']);
            $arHeader['Content-type'] = '';
            $arHeader['Content-type']['type'] = strtolower($ar_mr[0]);
            foreach ($ar_mr as $mr) {
                if (preg_match('#([^=.]*?)=(.*)#i', $mr, $matches)) {
                    $arHeader['Content-type'][strtolower(trim($matches[1]))] = str_replace('"', '', $matches[2]);
                }
            }
        }

        foreach ($arHeader as $key => $value) {
            if (strtolower($key) == 'x-hmxmroriginalrecipient') {
                unset($arHeader[$key]);
                $arHeader['X-HmXmrOriginalRecipient'] = $value;
            }
        }

        return $arHeader;
    }

    /**
     * Function to parse body by sections.
     *
     * @param array  $arHeader : the array headers
     * @param string $body     : the body content
     *
     * @return array
     */
    private static function parseBodySections($arHeader, $body)
    {
        $sections = array();

        if (is_array($arHeader) && isset($arHeader['Content-type']) && isset($arHeader['Content-type']['boundary'])) {
            $arBoundary = explode($arHeader['Content-type']['boundary'], $body);
            $sections['first'] = isset($arBoundary[1]) ? $arBoundary[1] : null;
            $sections['arFirst'] = isset($arBoundary[1]) ? self::parseHeader($arBoundary[1]) : null;
            $sections['machine'] = isset($arBoundary[2]) ? $arBoundary[2] : null;
            $sections['returned'] = isset($arBoundary[3]) ? $arBoundary[3] : null;
        }

        return $sections;
    }

    /**
     * Function to parse standard fields.
     *
     * @param string $content : a generic content
     *
     * @return array
     */
    private static function parseLines($content)
    {
        $result = array();

        if (!is_array($content)) {
            $content = explode("\r\n", $content);
        }

        foreach ($content as $line) {
            if (preg_match('#^([^\s.]*):\s*(.*)\s*#', $line, $matches)) {
                $entity = ucfirst(strtolower($matches[1]));
                if (empty($result[$entity])) {
                    $result[$entity] = trim($matches[2]);
                } elseif (isset($result['Received']) && $result['Received']) {
                    if ($entity && $matches[2] && $matches[2] != $result[$entity]) {
                        $result[$entity] .= '|'.trim($matches[2]);
                    }
                }
            } elseif (preg_match('/^\s+(.+)\s*/', $line) && isset($entity)) {
                $result[$entity] .= ' '.$line;
            }
        }

        return $result;
    }

    /**
     * Function to check if a message is a feedback loop via headers and body informations.
     *
     * @param array $arHeader     : the array headers
     *
     * @return bool
     */
    private static function isFbl($arHeader)
    {
        if (!empty($arHeader)) {
            if (isset($arHeader['Content-type'])
                && isset($arHeader['Content-type']['report-type'])
                && preg_match('#feedback-report#', $arHeader['Content-type']['report-type'])) {
                return true;
            } elseif (isset($arHeader['X-loop'])
                && preg_match('#scomp#', $arHeader['X-loop'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get eml file content on your system.
     *
     * @param string $emlFilePath : the eml file path
     *
     * @return array
     */
    private static function getEmlFile($emlFilePath)
    {
        set_time_limit(6000);

        if (!file_exists($emlFilePath)) {
            return null;
        }

        $content = @file_get_contents($emlFilePath, false, stream_context_create(array('http' => array('method' => 'GET'))));
        if (empty($content)) {
            return null;
        }

        return array(
            'name'    => basename($emlFilePath),
            'content' => $content,
        );
    }

    /**
     * Format the content of a message.
     *
     * @param string $content : a generic content
     *
     * @return string
     */
    private static function formatEmailContent($content)
    {
        if (empty($content)) {
            return $content;
        }

        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\n", "\r\n", $content);
        $content = str_replace('=3D', '=', $content);
        $content = str_replace('=09', '  ', $content);

        return $content;
    }

    /**
     * Extract email from string.
     *
     * @param string
     *
     * @return string
     */
    private static function extractEmail($string)
    {
        $result = $string;
        $arResult = preg_split('#[ \"\'\<\>:\(\)\[\]]#', $string);
        foreach ($arResult as $result) {
            if (strpos($result, '@') !== false) {
                return $result;
            }
        }

        return $result;
    }

    private static function formatUnixPath($path)
    {
        return str_replace('\\', '/', $path);
    }

    private static function endWith($string, $search)
    {
        $length = strlen($search);
        $start = $length * -1;

        return substr($string, $start) === $search;
    }

    private static function isEmpty($value, $key = '')
    {
        if (!empty($key) && is_array($value)) {
            return !array_key_exists($key, $value) || empty($value[$key]);
        } else {
            return !isset($value) || empty($value);
        }
    }

    private function showProcessMailProgress($total, $current)
    {
        $percents = ($current * 100) / $total;
        $percents = number_format($percents, 1);
        echo "Processed: {$percents}% [{$current} of {$total}] \r";
    }

    /**
     * Check if open mode is mailbox.
     *
     * @return bool
     */
    public function isMailboxOpenMode()
    {
        return $this->openMode == self::OPEN_MODE_MAILBOX;
    }

    /**
     * Check if open mode is file.
     *
     * @return bool
     */
    public function isFileOpenMode()
    {
        return $this->openMode == self::OPEN_MODE_FILE;
    }

    /**
     * Check if process mode is neutral mode.
     *
     * @return bool
     */
    public function isNeutralProcessMode()
    {
        return $this->processMode == self::PROCESS_MODE_NEUTRAL;
    }

    /**
     * Check if process mode is move mode.
     *
     * @return bool
     */
    public function isMoveProcessMode()
    {
        return $this->processMode == self::PROCESS_MODE_MOVE;
    }

    /**
     * Check if process mode is delete mode.
     *
     * @return bool
     */
    public function isDeleteProcessMode()
    {
        return $this->processMode == self::PROCESS_MODE_DELETE;
    }

    /**
     * The method to process bounces.
     *
     * @return string $processMode
     */
    public function getProcessMode()
    {
        return $this->processMode;
    }

    /**
     * Set the method to process bounces to neutral.
     */
    public function setNeutralProcessMode()
    {
        $this->setProcessMode(self::PROCESS_MODE_NEUTRAL);
    }

    /**
     * Set the method to process bounces to move.
     */
    public function setMoveProcessMode()
    {
        $this->setProcessMode(self::PROCESS_MODE_MOVE);
    }

    /**
     * Set the method to process bounces to delete.
     */
    public function setDeleteProcessMode()
    {
        $this->setProcessMode(self::PROCESS_MODE_DELETE);
    }

    /**
     * Set the method to process bounces.
     *
     * @param string $processMode
     */
    private function setProcessMode($processMode)
    {
        $this->processMode = $processMode;
    }

    /**
     * Mailbox service.
     *
     * @return string $mailboxService
     */
    public function getMailboxService()
    {
        return $this->mailboxService;
    }

    /**
     * Set the mailbox service to IMAP.
     */
    public function setImapMailboxService()
    {
        $this->setMailboxService(self::MAILBOX_SERVICE_IMAP);
    }

    /**
     * Set the mailbox service.
     *
     * @param string $mailboxService
     */
    private function setMailboxService($mailboxService)
    {
        $this->mailboxService = $mailboxService;
    }

    /**
     * Mailbox host server.
     *
     * @return string $mailboxHost
     */
    public function getMailboxHost()
    {
        return $this->mailboxHost;
    }

    /**
     * Set the mailbox host server.
     *
     * @param string $mailboxHost
     */
    public function setMailboxHost($mailboxHost)
    {
        $this->mailboxHost = $mailboxHost;
    }

    /**
     * The username of mailbox.
     *
     * @return string $mailboxUsername
     */
    public function getMailboxUsername()
    {
        return $this->mailboxUsername;
    }

    /**
     * Set the username of mailbox.
     *
     * @param string $mailboxUsername
     */
    public function setMailboxUsername($mailboxUsername)
    {
        $this->mailboxUsername = $mailboxUsername;
    }

    /**
     * Set the password needed to access mailbox.
     *
     * @param string $mailboxPassword
     */
    public function setMailboxPassword($mailboxPassword)
    {
        $this->mailboxPassword = $mailboxPassword;
    }

    /**
     * The mailbox server port number.
     *
     * @return int $mailboxPort
     */
    public function getMailboxPort()
    {
        return $this->mailboxPort;
    }

    /**
     * Set the mailbox server port number to POP3 (110).
     */
    public function setMailboxPortPop3()
    {
        $this->setMailboxPort(self::MAILBOX_PORT_POP3);
    }

    /**
     * Set the mailbox server port number to POP3 TLS/SSL (995).
     */
    public function setMailboxPortPop3TlsSsl()
    {
        $this->setMailboxPort(self::MAILBOX_PORT_POP3_TLS_SSL);
    }

    /**
     * Set the mailbox server port number to IMAP (143).
     */
    public function setMailboxPortImap()
    {
        $this->setMailboxPort(self::MAILBOX_PORT_IMAP);
    }

    /**
     * Set the mailbox server port number to IMAP TLS/SSL (993).
     */
    public function setMailboxPortImapTlsSsl()
    {
        $this->setMailboxPort(self::MAILBOX_PORT_IMAP_TLS_SSL);
    }

    /**
     * Set the mailbox server port number.
     *
     * @param int $mailboxPort
     */
    public function setMailboxPort($mailboxPort)
    {
        $this->mailboxPort = $mailboxPort;
    }

    /**
     * The mailbox security option.
     *
     * @return string $mailboxSecurity
     */
    public function getMailboxSecurity()
    {
        return $this->mailboxSecurity;
    }

    /**
     * Set the mailbox security option.
     *
     * @param string $mailboxSecurity
     */
    public function setMailboxSecurity($mailboxSecurity)
    {
        $this->mailboxSecurity = $mailboxSecurity;
    }

    /**
     * Certificate validation.
     *
     * @return string $mailboxCert
     */
    public function getMailboxCert()
    {
        return $this->mailboxCert;
    }

    /**
     * Set the certificate validation to VALIDATE.
     */
    public function setMailboxCertValidate()
    {
        $this->setMailboxCert(self::MAILBOX_CERT_VALIDATE);
    }

    /**
     * Set the certificate validation to NOVALIDATE.
     */
    public function setMailboxCertNoValidate()
    {
        $this->setMailboxCert(self::MAILBOX_CERT_NOVALIDATE);
    }

    /**
     * Set the certificate validation.
     *
     * @param string $mailboxCert
     */
    private function setMailboxCert($mailboxCert)
    {
        $this->mailboxCert = $mailboxCert;
    }

    /**
     * Mailbox name.
     *
     * @return string $mailboxName
     */
    public function getMailboxName()
    {
        return $this->mailboxName;
    }

    /**
     * Set the mailbox name, other choices are (Tasks, Spam, Replies, etc.).
     *
     * @param string $mailboxName
     */
    public function setMailboxName($mailboxName)
    {
        $this->mailboxName = $mailboxName;
    }

    /**
     * The resource handler for the opened mailbox (POP3/IMAP/NNTP/etc.).
     *
     * @return resource $handler
     */
    public function getMailboxHandler()
    {
        return $this->mailboxHandler;
    }

    /**
     * Maximum limit messages processed in one batch.
     *
     * @return int $maxMessages
     */
    public function getMaxMessages()
    {
        return $this->maxMessages;
    }

    /**
     * Set the maximum limit messages processed in one batch (0 for unlimited).
     *
     * @param number $maxMessages
     */
    public function setMaxMessages($maxMessages)
    {
        $this->maxMessages = $maxMessages;
    }

    /**
     * Check if purge unknown messages.
     *
     * @return bool $purge
     */
    public function isPurge()
    {
        return $this->purge;
    }

    /**
     * Set the purge unknown messages. Be careful with this option.
     *
     * @param bool $purge
     */
    public function setPurge($purge)
    {
        $this->purge = $purge;
    }

    /**
     * The last error message.
     *
     * @return string $error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Get search criteria.
     *
     * @return string $searchCriteria
     */
    public function getSearchCriteria()
    {
        return $this->searchCriteria;
    }

    /**
     * Set search criteria.
     *
     * @param string $criteria
     */
    public function setSearchCriteria($criteria)
    {
        $this->searchCriteria = $criteria;
    }

    /**
     * Show process mail progress
     *
     * @param bool $show
     */
    public function setShowProgress($show) {
        $this->showProgress = $show;
    }

    /**
     * Can show process mail progress
     *
     * @return bool
     */
    public function getShowProgress()
    {
        return $this->showProgress;
    }
}
