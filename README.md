[![Latest Stable Version](https://img.shields.io/packagist/v/ksami007/php-mail-bounce-handler.svg?style=flat-square)](https://packagist.org/packages/ksami007/php-mail-bounce-handler)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.3.2-8892BF.svg?style=flat-square)](https://php.net/)
[![Tested on PHP 5.4 to 7.4](https://img.shields.io/badge/tested%20on-PHP%205.4%20|%205.5%20|%205.6%20|%207.0%20|%207.1%20|%207.2%20|%207.3%20|%207.4%20-brightgreen.svg?maxAge=2419200)](https://php.net/)
# php-mail-bounce-handler

PHP class to help webmasters handle bounce-back, feedback loop and ARF mails in standard DSN (Delivery Status Notification, RFC-1894).
It checks your IMAP inbox or eml files and delete or move all bounced emails.
If a bounce is malformed, it tries to extract some useful information to parse status.

## Requirements

* PHP >= 5.3.2
* Enable the [php_imap](http://php.net/manual/en/book.imap.php) extension if you want to use the IMAP open mode.

## Installation with Composer

```bash
composer require ksami007/php-mail-bounce-handler
```

And download the code:

```bash
composer install # or update
```

## Getting started

See `tests/test.php` file sample to help you.<br />
You can use the eml files in the `tests/emls` folder for testing.

## Methods

**openImapLocal** - Open a IMAP mail box in local file system.<br />
**openImapRemote** - Open a remote IMAP mail box.<br />
**openEmlFolder** - Open a folder containing eml files on your system.<br />

**processMails** - Process the messages in a mailbox or a folder.<br />

**getStatusCodeExplanations** -Get explanations from DSN status code via the RFC 1893.<br />

**isMailboxOpenMode** - Check if open mode is mailbox.<br />
**isFileOpenMode** - Check if open mode is file.<br />
**isNeutralProcessMode** - Check if process mode is neutral mode.<br />
**isMoveProcessMode** - Check if process mode is move mode.<br />
**isDeleteProcessMode** - Check if process mode is delete mode.<br />
**getProcessMode** - The method to process bounces.<br />
**setNeutralProcessMode** - Set the method to process bounces to neutral. (default)<br />
**setMoveProcessMode** - Set the method to process bounces to move.<br />
**setDeleteProcessMode** - Set the method to process bounces to delete.<br />
**setProcessMode** - Set the method to process bounces.<br />
**getMailboxService** - Mailbox service.<br />
**setImapMailboxService** - Set the mailbox service to IMAP. (default)<br />
**setMailboxService** - Set the mailbox service.<br />
**getMailboxHost** - Mailbox host server.<br />
**setMailboxHost** - Set the mailbox host server. (default localhost)<br />
**getMailboxUsername** - The username of mailbox.<br />
**setMailboxUsername** - Set the username of mailbox.<br />
**setMailboxPassword** - Set the password needed to access mailbox.<br />
**getMailboxPort** - The mailbox server port number.<br />
**setMailboxPortPop3** - Set the mailbox server port number to POP3 (110).<br />
**setMailboxPortPop3TlsSsl** - Set the mailbox server port number to POP3 TLS/SSL (995).<br />
**setMailboxPortImap** - Set the mailbox server port number to IMAP (143). (default)<br />
**setMailboxPortImapTlsSsl** - Set the mailbox server port number to IMAP TLS/SSL (995).<br />
**setMailboxPort** - Set the mailbox server port number.<br />
**getMailboxSecurity** - The mailbox security option.<br />
**setMailboxSecurity** - Set the mailbox security option. (default const MAILBOX_SECURITY_NOTLS)<br />
**getMailboxCert** - Certificate validation.<br />
**setMailboxCertValidate** - Set the certificate validation to VALIDATE.<br />
**setMailboxCertNoValidate** - Set the certificate validation to NOVALIDATE. (default)<br />
**setMailboxCert** - Set the certificate validation.<br />
**getMailboxName** - Mailbox name.<br />
**setMailboxName** - Set the mailbox name, other choices are (Tasks, Spam, Replies, etc...). (default INBOX)<br />
**getMailboxHandler** - The resource handler for the opened mailbox (POP3/IMAP/NNTP/etc...).<br />
**getMaxMessages** - Maximum limit messages processed in one batch.<br />
**setMaxMessages** - Set the maximum limit messages processed in one batch (0 for unlimited).<br />
**isPurge** - Check if purge unknown messages.<br />
**setPurge** - Set the mailbox server port number.<br />
**getError** - The last error message.<br />

## License

LGPL. See `LICENSE` for more details.

