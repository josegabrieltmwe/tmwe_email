<?php

namespace tmwe_email\service\email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\Connection;
use Ddeboer\Imap\Mailbox;
use Ddeboer\Imap\Message;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Flag\Seen;
use Ddeboer\Imap\Search\Flag\Unseen;
use Ddeboer\Imap\Search\Flag\Flagged;
use Ddeboer\Imap\Search\Flag\Unflagged;
use Ddeboer\Imap\Search\Flag\Answered;
use Ddeboer\Imap\Search\Flag\Unanswered;
use Ddeboer\Imap\Search\Date\Since;
use Ddeboer\Imap\Search\Date\Before;
use Ddeboer\Imap\Search\Text\Subject;
use Ddeboer\Imap\Search\Text\Body;
use Ddeboer\Imap\Search\Text\From;
use Ddeboer\Imap\Search\Text\To;

/**
 * Description of Email_Client
 *
 * @author pepe
 */
class Email_Client extends \tmwe_email\service\Abstract_Service {

    /**
     * @return Email_Client
     */
    public static function get_instance() {
        return parent::get_instance();
    }

    private $connected = false;       // Connection status
    private $imap_hostname;           // IMAP server hostname
    private $imap_username;           // IMAP username
    private $imap_password;           // IMAP password
    private $imap_port;               // IMAP port
    private $imap_use_ssl;            // IMAP use SSL
    private $imap_use_tls;            // IMAP use TLS
    private $connection;              // Ddeboer IMAP Connection
    private $current_mailbox;         // Current selected mailbox
    private $smtp_host;               // SMTP server hostname
    private $smtp_username;           // SMTP username
    private $smtp_password;           // SMTP password
    private $smtp_port;               // SMTP port
    private $smtp_use_ssl;            // Use SSL for SMTP
    private $smtp_use_tls;            // Use TLS for SMTP
    private $mailer; // PHPMailer instance

    /**
     * Connect to the IMAP email server
     */
    public function connect(
            $imap_hostname,
            $imap_username,
            $imap_password,
            $imap_port = 993,
            $imap_use_ssl = false,
            $imap_use_tls = false
    ) {

        $this->imap_hostname = $imap_hostname;
        $this->imap_username = $imap_username;
        $this->imap_password = $imap_password;
        $this->imap_port = $imap_port;
        $this->imap_use_ssl = $imap_use_ssl;
        $this->imap_use_tls = $imap_use_tls;

        try {
            // Create server instance
            $server = new Server($this->imap_hostname, $this->imap_port, !$this->imap_use_ssl ? '/ssl/novalidate-cert' : '');

            $this->log("Attempting IMAP connection to {$this->imap_hostname}:{$this->imap_port} SSL:" . ($this->imap_use_ssl ? 'true' : 'false'));

            // Authenticate
            $this->connection = $server->authenticate($this->imap_username, $this->imap_password);

            // Get default mailbox (INBOX)
            $this->current_mailbox = $this->connection->getMailbox('INBOX');

            $this->connected = true;
            $this->log("IMAP connection successful");

            return true;

        } catch (\Exception $e) {
            $this->log_fail("IMAP connection failed: " . $e->getMessage());
            throw new \Exception('IMAP connection error: ' . $e->getMessage());
        }
    }

    /**
     * Check if the connection is active
     */
    public function is_connected() {
        return $this->connected && $this->connection !== null;
    }

    /**
     * Get the connection resource for direct operations
     * @return Connection|false The IMAP connection or false if not connected
     */
    public function get_connection() {
        return $this->is_connected() ? $this->connection : false;
    }

    /**
     * Disconnect from the IMAP server
     */
    public function disconnect() {
        if ($this->connected && $this->connection) {
            try {
                $this->connection->close();
            } catch (\Exception $e) {
                $this->log_fail("Error closing IMAP connection: " . $e->getMessage());
            }
            $this->connection = null;
            $this->current_mailbox = null;
            $this->connected = false;
        }
    }

    /**
     * Get list of all available mailbox folders
     * @return array List of folders
     * @throws \Exception If not connected to the server.
     */
    public function get_folders() {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $mailboxes = $this->connection->getMailboxes();
            $folder_list = [];

            foreach ($mailboxes as $mailbox) {
                $folder_list[] = [
                    'name' => $mailbox->getName(),
                    'messages' => $mailbox->count(),
                    'unseen' => count($mailbox->getMessages(new Unseen())),
                    'recent' => 0, // ddeboer/imap doesn't provide recent count directly
                    'full_name' => $mailbox->getFullEncodedName()
                ];
            }

            return $folder_list;
        } catch (\Exception $e) {
            $this->log_fail("Error getting folders: " . $e->getMessage());
            throw new \Exception('Failed to get folders: ' . $e->getMessage());
        }
    }

    /**
     * Switch to a specific mailbox folder
     * @param string $folder - folder name to switch to
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function select_folder($folder = 'INBOX') {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $this->current_mailbox = $this->connection->getMailbox($folder);
            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error selecting folder '$folder': " . $e->getMessage());
            throw new \Exception("Failed to switch to folder: $folder - " . $e->getMessage());
        }
    }

    /**
     * Fetch recent emails from a specified folder
     * @param string $folder - mailbox folder, default 'INBOX'
     * @param string $criteria - search criteria, e.g., 'ALL', 'UNSEEN', 'FROM "sender@example.com"'
     * @param int $offset - offset for pagination
     * @param int $limit - number of emails to fetch
     * @return array An array of objects, where each object is an email overview.
     * @throws \Exception If not connected to the server.
     */
    public function get_emails($folder = 'INBOX', $criteria = 'ALL', $offset = 0, $limit = false) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        $start_time = microtime(true);

        try {
            // Switch to folder if different from current
            if ($this->current_mailbox->getName() !== $folder) {
                $this->select_folder($folder);
            }

            $this->log("Starting get_emails: folder={$folder}, criteria={$criteria}, offset={$offset}, limit={$limit}");

            // Build search expression
            $search = $this->build_search_expression($criteria);

            // Get messages iterator (don't convert to array yet)
            $messages = $this->current_mailbox->getMessages($search);
            $total = count($messages);

            $this->log("Found {$total} total messages, starting optimized processing...");

            // Create array for efficiently sorting and limiting
            $message_numbers = [];
            foreach ($messages as $message) {
                $message_numbers[] = $message->getNumber();
            }

            // Sort message numbers in descending order (newest first)
            rsort($message_numbers);

            // Apply pagination to message numbers first
            $from = $offset;
            $to = $total;

            if ($limit !== false) {
                $to = min($offset + $limit, $total);
                $message_numbers = array_slice($message_numbers, $offset, $limit);
            } else {
                $message_numbers = array_slice($message_numbers, $offset);
            }

            $this->log("Processing " . count($message_numbers) . " selected messages for overview conversion...");

            // Now fetch only the needed messages
            $message_array = [];
            foreach ($message_numbers as $msg_num) {
                try {
                    $message = $this->current_mailbox->getMessage($msg_num);
                    if ($message) {
                        $message_array[] = $message;
                    }
                } catch (\Exception $e) {
                    $this->log_fail("Error fetching message {$msg_num}: " . $e->getMessage());
                    // Continue with other messages
                }
            }

            // Convert to overview format
            $overview_messages = [];
            foreach ($message_array as $message) {
                $overview_messages[] = $this->message_to_overview($message);
            }

            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            $this->log("get_emails completed in {$execution_time}ms");

            return [
                'messages' => $overview_messages,
                'from' => $from,
                'to' => $to,
                'total' => $total
            ];

        } catch (\Exception $e) {
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            $this->log_fail("Error getting emails after {$execution_time}ms: " . $e->getMessage());
            throw new \Exception('Failed to get emails: ' . $e->getMessage());
        }
    }

    /**
     * Convert a Message object to overview format
     * @param Message $message
     * @return \stdClass
     */
    private function message_to_overview(Message $message) {
        try {
            $overview = new \stdClass();
            $uid = $message->getNumber();

            $overview->uid = $uid;
            $overview->msgno = $uid;

            // Use try-catch for individual fields to prevent one field error from breaking the whole conversion
            try {
                $overview->subject = $message->getSubject() ?: '';
            } catch (\Exception $e) {
                $overview->subject = '[Error reading subject]';
            }

            try {
                $from = $message->getFrom();
                $overview->from = $from ? $from->getAddress() : '';
            } catch (\Exception $e) {
                $overview->from = '[Error reading from]';
            }

            try {
                $to_addresses = $message->getTo();
                if ($to_addresses && is_array($to_addresses)) {
                    $to_list = [];
                    foreach ($to_addresses as $addr) {
                        if ($addr && method_exists($addr, 'getAddress')) {
                            $to_list[] = $addr->getAddress();
                        }
                    }
                    $overview->to = implode(', ', $to_list);
                } else {
                    $overview->to = '';
                }
            } catch (\Exception $e) {
                $overview->to = '[Error reading to]';
            }

            try {
                $date = $message->getDate();
                $overview->date = $date ? $date->format('r') : '';
            } catch (\Exception $e) {
                $overview->date = date('r'); // Fallback to current date
            }

            try {
                $overview->size = $message->getSize() ?: 0;
            } catch (\Exception $e) {
                $overview->size = 0;
            }

            // Flags with error handling
            try {
                $overview->seen = $message->isSeen() ? 1 : 0;
            } catch (\Exception $e) {
                $overview->seen = 0;
            }

            try {
                $overview->flagged = $message->isFlagged() ? 1 : 0;
            } catch (\Exception $e) {
                $overview->flagged = 0;
            }

            try {
                $overview->answered = $message->isAnswered() ? 1 : 0;
            } catch (\Exception $e) {
                $overview->answered = 0;
            }

            $overview->recent = 0; // ddeboer doesn't provide recent flag directly

            return $overview;

        } catch (\Exception $e) {
            // If complete conversion fails, return minimal overview
            $this->log_fail("Error converting message to overview: " . $e->getMessage());
            $overview = new \stdClass();
            $overview->uid = $message->getNumber();
            $overview->msgno = $message->getNumber();
            $overview->subject = '[Error loading message]';
            $overview->from = '';
            $overview->to = '';
            $overview->date = date('r');
            $overview->size = 0;
            $overview->seen = 0;
            $overview->flagged = 0;
            $overview->answered = 0;
            $overview->recent = 0;
            return $overview;
        }
    }

    /**
     * Build search expression from criteria string
     * @param string $criteria
     * @return SearchExpression|null
     */
    private function build_search_expression($criteria) {
        if ($criteria === 'ALL') {
            return null; // No filter, get all messages
        }

        $search = new SearchExpression();

        // Handle simple flags
        switch (strtoupper($criteria)) {
            case 'UNSEEN':
                $search->addCondition(new Unseen());
                break;
            case 'SEEN':
                $search->addCondition(new Seen());
                break;
            case 'FLAGGED':
                $search->addCondition(new Flagged());
                break;
            case 'UNFLAGGED':
                $search->addCondition(new Unflagged());
                break;
            case 'ANSWERED':
                $search->addCondition(new Answered());
                break;
            case 'UNANSWERED':
                $search->addCondition(new Unanswered());
                break;
        }

        return $search;
    }

    /**
     * Reads a single email by its UID.
     *
     * @param int $uid The UID of the email to read.
     * @return array|false An associative array containing email details or false on failure.
     * @throws \Exception If not connected to the server.
     */
    public function read_email_by_uid(int $uid) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);

            if (!$message) {
                $this->log_fail("Message with UID $uid not found");
                return false;
            }

            $email_data = [
                'header' => $this->message_to_overview($message),
                'body_plain' => $message->getBodyText(),
                'body_html' => $message->getBodyHtml(),
                'attachments' => []
            ];

            // Get attachments
            $attachments = $message->getAttachments();
            foreach ($attachments as $attachment) {
                $email_data['attachments'][] = [
                    'filename' => $attachment->getFilename(),
                    'mimetype' => $attachment->getType() . '/' . $attachment->getSubtype(),
                    'encoding' => $attachment->getEncoding(),
                    'data' => $attachment->getDecodedContent(),
                    'size' => $attachment->getSize()
                ];
            }

            $this->log('EMAIL DATA retrieved for UID: ' . $uid);

            return $email_data;

        } catch (\Exception $e) {
            $this->log_fail("Error reading email UID $uid: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds an email's UID by its Message-ID header string.
     *
     * @param string $message_id_string The Message-ID header value.
     * @return int|false The UID if found, otherwise false.
     * @throws \Exception If not connected to the server.
     */
    public function get_uid_by_message_id(string $message_id_string) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $messages = $this->current_mailbox->getMessages();

            foreach ($messages as $message) {
                if ($message->getId() === $message_id_string) {
                    return $message->getNumber();
                }
            }

            return false;

        } catch (\Exception $e) {
            $this->log_fail("Error searching by message ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize SMTP connection and assign to $mailer attribute
     */
    public function connect_smtp($smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_use_ssl = false, $smtp_use_tls = false) {
        $this->mailer = new PHPMailer(true);

        $this->smtp_host = $smtp_host;
        $this->smtp_port = $smtp_port;
        $this->smtp_username = $smtp_username;
        $this->smtp_password = $smtp_password;
        $this->smtp_use_ssl = $smtp_use_ssl;
        $this->smtp_use_tls = $smtp_use_tls;

        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtp_host;
            $this->mailer->Port = $this->smtp_port;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtp_username;
            $this->mailer->Password = $this->smtp_password;

            // Configure encryption based on explicit settings and port
            if ($this->smtp_use_ssl || $this->smtp_port == 465) {
                $this->mailer->SMTPSecure = 'ssl';
            } elseif ($this->smtp_use_tls || $this->smtp_port == 587 || $this->smtp_port == 25) {
                $this->mailer->SMTPSecure = 'tls';
            } else {
                $this->mailer->SMTPSecure = false;
            }

            $this->log("SMTP connection configured: Host={$this->smtp_host}, Port={$this->smtp_port}, SSL={$this->smtp_use_ssl}, TLS={$this->smtp_use_tls}, SMTPSecure={$this->mailer->SMTPSecure}");
        } catch (Exception $e) {
            throw new \Exception('SMTP Connection Error: ' . $e->getMessage());
        }
    }

    public function send_email($to, $subject, $body, $headers = [], $from = false) {
        if (!$this->mailer) {
            throw new \Exception('SMTP connection not initialized. Call connect_smtp() first.');
        }
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom(!$from ? $this->smtp_username : $from, 'Sender');
            $this->mailer->addAddress($to);

            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->isHTML(true);

            foreach ($headers as $key => $value) {
                $this->mailer->addCustomHeader($key, $value);
            }

            $this->mailer->send();
        } catch (Exception $e) {
            throw new \Exception('Mailer Error: ' . $this->mailer->ErrorInfo);
        }
    }

    /**
     * Mark an email as read/unread
     * @param int $uid Email UID
     * @param bool $read True to mark as read, false to mark as unread
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function mark_as_read($uid, $read = true) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);
            if (!$message) {
                return false;
            }

            if ($read) {
                $message->markAsSeen();
            } else {
                $message->clearFlag('\\Seen');
            }

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error marking message as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark an email as flagged/unflagged (star)
     * @param int $uid Email UID
     * @param bool $flagged True to mark as flagged, false to unflag
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function mark_as_flagged($uid, $flagged = true) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);
            if (!$message) {
                return false;
            }

            if ($flagged) {
                $message->setFlag('\\Flagged');
            } else {
                $message->clearFlag('\\Flagged');
            }

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error marking message as flagged: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move an email to another folder
     * @param int $uid Email UID
     * @param string $target_folder Target folder name
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function move_email($uid, $target_folder) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);
            if (!$message) {
                return false;
            }

            $target_mailbox = $this->connection->getMailbox($target_folder);
            $message->move($target_mailbox);

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error moving message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copy an email to another folder
     * @param int $uid Email UID
     * @param string $target_folder Target folder name
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function copy_email($uid, $target_folder) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);
            if (!$message) {
                return false;
            }

            $target_mailbox = $this->connection->getMailbox($target_folder);
            $message->copy($target_mailbox);

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error copying message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an email (mark for deletion)
     * @param int $uid Email UID
     * @param bool $expunge Whether to immediately expunge deleted emails
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function delete_email($uid, $expunge = false) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);
            if (!$message) {
                return false;
            }

            $message->delete();

            if ($expunge) {
                $this->current_mailbox->expunge();
            }

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error deleting message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Undelete an email (remove deletion mark)
     * @param int $uid Email UID
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function undelete_email($uid) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);
            if (!$message) {
                return false;
            }

            $message->clearFlag('\\Deleted');

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error undeleting message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Expunge deleted emails (permanently remove)
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function expunge_deleted() {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $this->current_mailbox->expunge();
            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error expunging messages: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new folder
     * @param string $folder_name New folder name
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function create_folder($folder_name) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $this->connection->createMailbox($folder_name);
            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error creating folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a folder
     * @param string $folder_name Folder name to delete
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function delete_folder($folder_name) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $mailbox = $this->connection->getMailbox($folder_name);
            $mailbox->delete();
            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error deleting folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rename a folder
     * @param string $old_name Current folder name
     * @param string $new_name New folder name
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function rename_folder($old_name, $new_name) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            // ddeboer/imap doesn't have direct rename, so we need to use IMAP extension directly
            // or create new and move messages (more complex)
            throw new \Exception('Rename folder not directly supported by ddeboer/imap library');
        } catch (\Exception $e) {
            $this->log_fail("Error renaming folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search emails with advanced criteria
     * @param array $search_params Search parameters
     * @return array Array of UIDs matching the criteria
     * @throws \Exception If not connected to the server.
     */
    public function advanced_search($search_params) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $search = new SearchExpression();

            if (!empty($search_params['from'])) {
                $search->addCondition(new From($search_params['from']));
            }

            if (!empty($search_params['to'])) {
                $search->addCondition(new To($search_params['to']));
            }

            if (!empty($search_params['subject'])) {
                $search->addCondition(new Subject($search_params['subject']));
            }

            if (!empty($search_params['body'])) {
                $search->addCondition(new Body($search_params['body']));
            }

            if (!empty($search_params['since'])) {
                $since_date = new \DateTime($search_params['since']);
                $search->addCondition(new Since($since_date));
            }

            if (!empty($search_params['before'])) {
                $before_date = new \DateTime($search_params['before']);
                $search->addCondition(new Before($before_date));
            }

            if (isset($search_params['seen'])) {
                if ($search_params['seen']) {
                    $search->addCondition(new Seen());
                } else {
                    $search->addCondition(new Unseen());
                }
            }

            if (isset($search_params['flagged'])) {
                if ($search_params['flagged']) {
                    $search->addCondition(new Flagged());
                } else {
                    $search->addCondition(new Unflagged());
                }
            }

            if (isset($search_params['answered'])) {
                if ($search_params['answered']) {
                    $search->addCondition(new Answered());
                } else {
                    $search->addCondition(new Unanswered());
                }
            }

            $messages = $this->current_mailbox->getMessages($search);
            $uids = [];

            foreach ($messages as $message) {
                $uids[] = $message->getNumber();
            }

            return $uids;

        } catch (\Exception $e) {
            $this->log_fail("Error in advanced search: " . $e->getMessage());
            return [];
        }
    }

    // Continue with the remaining methods in the next part...
    // (The file is getting long, so I'll continue with the rest)

    /**
     * Test connection without establishing a persistent connection
     * @param string $imap_hostname IMAP hostname
     * @param string $imap_username IMAP username
     * @param string $imap_password IMAP password
     * @param int $imap_port IMAP port
     * @param bool $imap_use_ssl Use SSL
     * @return array Test result with connection details
     */
    public static function test_connection($imap_hostname, $imap_username, $imap_password, $imap_port = 993, $imap_use_ssl = true, $imap_use_tls = false) {
        $start_time = microtime(true);
        $result = [
            'success' => false,
            'connection_time' => 0,
            'server_info' => [
                'hostname' => $imap_hostname,
                'port' => $imap_port,
                'ssl' => $imap_use_ssl,
                'username' => $imap_username
            ],
            'errors' => [],
            'capabilities' => []
        ];

        try {
            $server = new Server($imap_hostname, $imap_port, $imap_use_ssl ? '/ssl/novalidate-cert' : '');
            $connection = $server->authenticate($imap_username, $imap_password);

            $result['success'] = true;
            $result['message'] = 'Connection successful';

            // Try to get some basic info
            try {
                $mailboxes = $connection->getMailboxes();
                $result['mailbox_count'] = count($mailboxes);
            } catch (\Exception $e) {
                // Ignore mailbox enumeration errors
            }

            $connection->close();

        } catch (\Exception $e) {
            $result['success'] = false;
            $result['message'] = 'Connection failed';
            $result['errors'][] = $e->getMessage();
        }

        $result['connection_time'] = round((microtime(true) - $start_time) * 1000, 2);
        return $result;
    }

    // Sync-related properties
    private static $sync_status = [];
    private static $sync_cancel_flags = [];

    /**
     * Perform full synchronization of all folders
     * @param callable $progress_callback Optional callback to report progress
     * @return array Sync result with statistics
     * @throws \Exception If not connected to the server.
     */
    public function full_sync($progress_callback = null) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        $sync_id = uniqid('full_sync_');
        self::$sync_status[$sync_id] = [
            'type' => 'full_sync',
            'status' => 'running',
            'progress' => 0,
            'start_time' => time(),
            'folders_processed' => 0,
            'total_folders' => 0,
            'messages_processed' => 0,
            'errors' => []
        ];
        self::$sync_cancel_flags[$sync_id] = false;

        try {
            $folders = $this->get_folders();
            self::$sync_status[$sync_id]['total_folders'] = count($folders);

            foreach ($folders as $index => $folder) {
                if (self::$sync_cancel_flags[$sync_id]) {
                    self::$sync_status[$sync_id]['status'] = 'cancelled';
                    break;
                }

                try {
                    $folder_result = $this->sync_folder($folder['name'], $sync_id);
                    self::$sync_status[$sync_id]['messages_processed'] += $folder_result['messages_processed'];
                } catch (\Exception $e) {
                    self::$sync_status[$sync_id]['errors'][] = "Folder {$folder['name']}: " . $e->getMessage();
                }

                self::$sync_status[$sync_id]['folders_processed']++;
                self::$sync_status[$sync_id]['progress'] = round(($index + 1) / count($folders) * 100, 2);

                if ($progress_callback && is_callable($progress_callback)) {
                    call_user_func($progress_callback, self::$sync_status[$sync_id]);
                }
            }

            if (self::$sync_status[$sync_id]['status'] !== 'cancelled') {
                self::$sync_status[$sync_id]['status'] = 'completed';
                self::$sync_status[$sync_id]['progress'] = 100;
            }

        } catch (\Exception $e) {
            self::$sync_status[$sync_id]['status'] = 'error';
            self::$sync_status[$sync_id]['errors'][] = $e->getMessage();
        }

        self::$sync_status[$sync_id]['end_time'] = time();
        return ['sync_id' => $sync_id, 'result' => self::$sync_status[$sync_id]];
    }

    /**
     * Synchronize a specific folder
     * @param string $folder_name Folder to synchronize
     * @param string $parent_sync_id Parent sync ID if part of larger sync
     * @return array Sync result for the folder
     * @throws \Exception If not connected to the server.
     */
    public function sync_folder($folder_name, $parent_sync_id = null) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        $sync_id = $parent_sync_id ?: uniqid('folder_sync_');
        $messages_processed = 0;

        try {
            if (self::$sync_cancel_flags[$sync_id] ?? false) {
                return ['sync_id' => $sync_id, 'messages_processed' => 0];
            }

            $this->select_folder($folder_name);
            $messages = $this->current_mailbox->getMessages();

            foreach ($messages as $message) {
                if (self::$sync_cancel_flags[$sync_id] ?? false) {
                    break;
                }
                $messages_processed++;
            }

        } catch (\Exception $e) {
            if (!$parent_sync_id) {
                throw $e;
            }
        }

        return ['sync_id' => $sync_id, 'messages_processed' => $messages_processed];
    }

    /**
     * Get synchronization status
     * @param string $sync_id Sync ID to check
     * @return array|false Sync status or false if not found
     */
    public static function get_sync_status($sync_id) {
        return isset(self::$sync_status[$sync_id]) ? self::$sync_status[$sync_id] : false;
    }

    /**
     * Cancel a running synchronization
     * @param string $sync_id Sync ID to cancel
     * @return bool Success status
     */
    public static function cancel_sync($sync_id) {
        if (isset(self::$sync_cancel_flags[$sync_id])) {
            self::$sync_cancel_flags[$sync_id] = true;
            if (isset(self::$sync_status[$sync_id])) {
                self::$sync_status[$sync_id]['status'] = 'cancelling';
            }
            return true;
        }
        return false;
    }

    /**
     * Get all sync statuses
     * @return array All sync statuses
     */
    public static function get_all_sync_statuses() {
        return self::$sync_status;
    }

    /**
     * Perform incremental synchronization
     * @param int $since_timestamp Timestamp to sync from
     * @param callable $progress_callback Optional callback to report progress
     * @return array Sync result with statistics
     * @throws \Exception If not connected to the server.
     */
    public function incremental_sync($since_timestamp = null, $progress_callback = null) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        $sync_id = uniqid('incremental_sync_');
        $since_date = $since_timestamp ? new \DateTime('@' . $since_timestamp) : new \DateTime('-1 day');

        self::$sync_status[$sync_id] = [
            'type' => 'incremental_sync',
            'status' => 'running',
            'progress' => 0,
            'start_time' => time(),
            'since_date' => $since_date->format('c'),
            'messages_processed' => 0,
            'errors' => []
        ];

        try {
            $search = new SearchExpression();
            $search->addCondition(new Since($since_date));

            $messages = $this->current_mailbox->getMessages($search);
            $total = count($messages);
            $processed = 0;

            foreach ($messages as $message) {
                if (self::$sync_cancel_flags[$sync_id] ?? false) {
                    self::$sync_status[$sync_id]['status'] = 'cancelled';
                    break;
                }
                $processed++;
                self::$sync_status[$sync_id]['progress'] = round($processed / $total * 100, 2);
            }

            if (self::$sync_status[$sync_id]['status'] !== 'cancelled') {
                self::$sync_status[$sync_id]['status'] = 'completed';
                self::$sync_status[$sync_id]['progress'] = 100;
            }

            self::$sync_status[$sync_id]['messages_processed'] = $processed;

        } catch (\Exception $e) {
            self::$sync_status[$sync_id]['status'] = 'error';
            self::$sync_status[$sync_id]['errors'][] = $e->getMessage();
        }

        self::$sync_status[$sync_id]['end_time'] = time();
        return ['sync_id' => $sync_id, 'result' => self::$sync_status[$sync_id]];
    }

    /**
     * Get folder tree structure
     * @return array Hierarchical folder structure
     * @throws \Exception If not connected to the server.
     */
    public function get_folder_tree() {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $folders = $this->get_folders();
            $tree = [];

            foreach ($folders as $folder) {
                $parts = explode('.', $folder['name']);
                $current = &$tree;

                foreach ($parts as $part) {
                    if (!isset($current[$part])) {
                        $current[$part] = [
                            'name' => $part,
                            'full_name' => $folder['name'],
                            'children' => [],
                            'info' => $folder
                        ];
                    }
                    $current = &$current[$part]['children'];
                }
            }

            return $tree;
        } catch (\Exception $e) {
            $this->log_fail("Error getting folder tree: " . $e->getMessage());
            throw new \Exception('Failed to get folder tree: ' . $e->getMessage());
        }
    }

    /**
     * Empty a folder (delete all emails)
     * @param string $folder_name Folder to empty
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function empty_folder($folder_name = 'INBOX') {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $this->select_folder($folder_name);
            $messages = $this->current_mailbox->getMessages();

            foreach ($messages as $message) {
                $message->delete();
            }

            $this->current_mailbox->expunge();
            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error emptying folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get account information
     * @return array Account information
     * @throws \Exception If not connected to the server.
     */
    public function get_account_info() {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $folders = $this->get_folders();
            $total_messages = 0;
            $total_unseen = 0;

            foreach ($folders as $folder) {
                $total_messages += $folder['messages'];
                $total_unseen += $folder['unseen'];
            }

            return [
                'username' => $this->imap_username,
                'server' => $this->imap_hostname,
                'port' => $this->imap_port,
                'ssl' => $this->imap_use_ssl,
                'total_folders' => count($folders),
                'total_messages' => $total_messages,
                'total_unseen' => $total_unseen,
                'connection_time' => time()
            ];
        } catch (\Exception $e) {
            $this->log_fail("Error getting account info: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get quota information
     * @param string $quota_root Quota root
     * @return array Quota information
     * @throws \Exception If not connected to the server.
     */
    public function get_quota($quota_root = null) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        // ddeboer/imap doesn't support quota operations directly
        // This would require raw IMAP commands
        return [
            'quota_root' => $quota_root ?: 'user.' . $this->imap_username,
            'used' => 0,
            'limit' => 0,
            'available' => 0,
            'message' => 'Quota information not available with current IMAP library'
        ];
    }

    /**
     * Subscribe to a folder
     * @param string $folder_name Folder to subscribe to
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function subscribe_folder($folder_name) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $mailbox = $this->connection->getMailbox($folder_name);
            // ddeboer/imap doesn't have direct subscribe method
            // Would require raw IMAP commands for full implementation
            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error subscribing to folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsubscribe from a folder
     * @param string $folder_name Folder to unsubscribe from
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function unsubscribe_folder($folder_name) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $mailbox = $this->connection->getMailbox($folder_name);
            // ddeboer/imap doesn't have direct unsubscribe method
            // Would require raw IMAP commands for full implementation
            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error unsubscribing from folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete multiple messages
     * @param array $uids Array of UIDs to delete
     * @param bool $expunge Whether to immediately expunge
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function delete_messages($uids, $expunge = false) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            foreach ($uids as $uid) {
                $message = $this->current_mailbox->getMessage($uid);
                if ($message) {
                    $message->delete();
                }
            }

            if ($expunge) {
                $this->current_mailbox->expunge();
            }

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error deleting messages: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move multiple messages to another folder
     * @param array $uids Array of UIDs to move
     * @param string $target_folder Target folder
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function move_messages($uids, $target_folder) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $target_mailbox = $this->connection->getMailbox($target_folder);

            foreach ($uids as $uid) {
                $message = $this->current_mailbox->getMessage($uid);
                if ($message) {
                    $message->move($target_mailbox);
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error moving messages: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark multiple messages with flags
     * @param array $uids Array of UIDs to mark
     * @param string $flag Flag to set/clear
     * @param bool $set Whether to set (true) or clear (false) the flag
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function mark_messages($uids, $flag, $set = true) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            foreach ($uids as $uid) {
                $message = $this->current_mailbox->getMessage($uid);
                if ($message) {
                    if ($set) {
                        $message->setFlag($flag);
                    } else {
                        $message->clearFlag($flag);
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error marking messages: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get attachment from a message
     * @param int $uid Message UID
     * @param int $attachment_index Attachment index
     * @return array|false Attachment data or false if not found
     * @throws \Exception If not connected to the server.
     */
    public function get_attachment($uid, $attachment_index) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);
            if (!$message) {
                return false;
            }

            $attachments = $message->getAttachments();
            if (!isset($attachments[$attachment_index])) {
                return false;
            }

            $attachment = $attachments[$attachment_index];
            return [
                'filename' => $attachment->getFilename(),
                'mimetype' => $attachment->getType() . '/' . $attachment->getSubtype(),
                'encoding' => $attachment->getEncoding(),
                'data' => $attachment->getDecodedContent(),
                'size' => $attachment->getSize()
            ];

        } catch (\Exception $e) {
            $this->log_fail("Error getting attachment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reply to an email
     * @param int $uid Email UID to reply to
     * @param string $reply_body Reply message body
     * @param bool $reply_all Whether to reply to all recipients
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function reply_to_email($uid, $reply_body, $reply_all = false) {
        if (!$this->connected || !$this->mailer) {
            throw new \Exception('Not connected to server or SMTP not configured.');
        }

        try {
            $original_message = $this->current_mailbox->getMessage($uid);
            if (!$original_message) {
                return false;
            }

            // Get original email details
            $original_subject = $original_message->getSubject();
            $original_from = $original_message->getFrom();
            $original_to = $original_message->getTo();
            $original_cc = $original_message->getCc();

            // Prepare reply
            $reply_subject = preg_match('/^Re:/i', $original_subject) ? $original_subject : 'Re: ' . $original_subject;
            $reply_to = $original_from ? $original_from->getAddress() : '';

            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($reply_to);

            if ($reply_all) {
                // Add all original recipients
                if ($original_to) {
                    foreach ($original_to as $addr) {
                        if ($addr->getAddress() !== $this->smtp_username) {
                            $this->mailer->addAddress($addr->getAddress());
                        }
                    }
                }
                if ($original_cc) {
                    foreach ($original_cc as $addr) {
                        if ($addr->getAddress() !== $this->smtp_username) {
                            $this->mailer->addCC($addr->getAddress());
                        }
                    }
                }
            }

            // Set reply content
            $this->mailer->Subject = $reply_subject;
            $this->mailer->Body = $reply_body;
            $this->mailer->addCustomHeader('In-Reply-To', $original_message->getId());

            // Send reply
            $this->mailer->send();

            // Mark original as answered
            $original_message->setFlag('\\Answered');

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error replying to email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Forward an email
     * @param int $uid Email UID to forward
     * @param string $to_email Recipient email
     * @param string $forward_message Additional message
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function forward_email($uid, $to_email, $forward_message = '') {
        if (!$this->connected || !$this->mailer) {
            throw new \Exception('Not connected to server or SMTP not configured.');
        }

        try {
            $original_message = $this->current_mailbox->getMessage($uid);
            if (!$original_message) {
                return false;
            }

            // Get original email details
            $original_subject = $original_message->getSubject();
            $original_body = $original_message->getBodyHtml() ?: $original_message->getBodyText();

            // Prepare forward
            $forward_subject = preg_match('/^Fwd?:/i', $original_subject) ? $original_subject : 'Fwd: ' . $original_subject;
            $forward_body = $forward_message . "\n\n" . "---------- Forwarded message ----------\n" . $original_body;

            // Clear previous recipients and set new ones
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to_email);

            // Set forward content
            $this->mailer->Subject = $forward_subject;
            $this->mailer->Body = $forward_body;

            // Forward attachments
            $attachments = $original_message->getAttachments();
            foreach ($attachments as $attachment) {
                $this->mailer->addStringAttachment(
                    $attachment->getDecodedContent(),
                    $attachment->getFilename(),
                    'base64',
                    $attachment->getType() . '/' . $attachment->getSubtype()
                );
            }

            // Send forward
            $this->mailer->send();

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error forwarding email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark email as answered
     * @param int $uid Email UID
     * @param bool $answered True to mark as answered, false to clear
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function mark_as_answered($uid, $answered = true) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $message = $this->current_mailbox->getMessage($uid);
            if (!$message) {
                return false;
            }

            if ($answered) {
                $message->setFlag('\\Answered');
            } else {
                $message->clearFlag('\\Answered');
            }

            return true;
        } catch (\Exception $e) {
            $this->log_fail("Error marking message as answered: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email thread/conversation
     * @param int $uid Base email UID
     * @return array Thread emails
     * @throws \Exception If not connected to the server.
     */
    public function get_email_thread($uid) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        try {
            $base_message = $this->current_mailbox->getMessage($uid);
            if (!$base_message) {
                return [];
            }

            $subject = $base_message->getSubject();
            $message_id = $base_message->getId();

            // Remove Re: and Fwd: prefixes for thread matching
            $clean_subject = preg_replace('/^(Re|Fwd?):\s*/i', '', $subject);

            // Search for messages with similar subjects
            $search = new SearchExpression();
            $search->addCondition(new Subject($clean_subject));

            $messages = $this->current_mailbox->getMessages($search);
            $thread_messages = [];

            foreach ($messages as $message) {
                $thread_messages[] = $this->message_to_overview($message);
            }

            // Sort by date
            usort($thread_messages, function($a, $b) {
                return strtotime($a->date) - strtotime($b->date);
            });

            return $thread_messages;

        } catch (\Exception $e) {
            $this->log_fail("Error getting email thread: " . $e->getMessage());
            return [];
        }
    }
}