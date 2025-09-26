<?php

namespace tmwe_email\service\email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure IMAP constants are defined if not already globally available
if (!defined('TYPETEXT'))
    define('TYPETEXT', 0);
if (!defined('TYPEMULTIPART'))
    define('TYPEMULTIPART', 1);
if (!defined('ENCBASE64'))
    define('ENCBASE64', 3);
if (!defined('ENCQUOTEDPRINTABLE'))
    define('ENCQUOTEDPRINTABLE', 4);
if (!defined('FT_UID'))
    define('FT_UID', 1); // For imap_fetchbody, imap_fetchstructure etc.
if (!defined('SE_UID'))
    define('SE_UID', 1); // For imap_search
if (!defined('ST_UID'))
    define('ST_UID', 1); // For imap_setflag_full, imap_clearflag_full
if (!defined('CP_UID'))
    define('CP_UID', 1); // For imap_mail_copy, imap_mail_move
if (!defined('SA_ALL'))
    define('SA_ALL', 15); // For imap_status - get all status information

// Resto de tu código

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
    private $mailbox;                 // IMAP stream resource
    private $smtp_host;               // SMTP server hostname
    private $smtp_username;           // SMTP username
    private $smtp_password;           // SMTP password
    private $smtp_port;               // SMTP port
    private $use_ssl;                 // Use SSL for SMTP
    private $mailer; // PHPMailer instance

    /**
     * Connect to the IMAP email server
     */
    public function connect(
            $imap_hostname,
            $imap_username,
            $imap_password,
            $imap_port = 993,
            $imap_use_ssl = false
    ) {

        
        
        $this->imap_hostname = $imap_hostname;
        $this->imap_username = $imap_username;
        $this->imap_password = $imap_password;
        $this->imap_port = $imap_port;
        $this->imap_use_ssl = $imap_use_ssl;

        // Try different hostname configurations
        if (!$this->imap_use_ssl) {
            // More comprehensive SSL options for troubleshooting
            $hostname = '{' . $this->imap_hostname . ':' . $this->imap_port . '/imap/ssl/novalidate-cert/notls}INBOX';
            $this->log("Using SSL hostname (with notls): " . $hostname);
        } else {
            $hostname = '{' . $this->imap_hostname . ':' . $this->imap_port . '/imap}INBOX';
            $this->log("Using non-SSL hostname: " . $hostname);
        }

        // Log connection attempt details to both error log and file
        $debug_msg = "IMAP Connection Debug:\n";
        $debug_msg .= "Hostname string: " . $hostname . "\n";
        $debug_msg .= "Username: " . $this->imap_username . "\n";
        $debug_msg .= "Password length: " . strlen($this->imap_password) . "\n";
        $debug_msg .= "Port: " . $this->imap_port . "\n";
        $debug_msg .= "SSL: " . ($this->imap_use_ssl ? 'true' : 'false') . "\n";

        // Log connection debug information
        $this->log($debug_msg);

        // Clear any previous IMAP errors
        imap_errors();
        imap_alerts();

        // Additional diagnostics before attempting connection
        $log_msg = "About to attempt imap_open...";
        $this->log($log_msg);

        // Check if IMAP extension is loaded before attempting connection
        if (!extension_loaded('imap')) {
            $error_msg = "FATAL: IMAP extension is NOT loaded!";
            $this->log_fail($error_msg);
            throw new \Exception('IMAP extension is not loaded. Please install php-imap extension.');
        }

        // Check if functions exist
        if (!function_exists('imap_open')) {
            $error_msg = "FATAL: imap_open function does not exist!";
            $this->log_fail($error_msg);
            throw new \Exception('imap_open function does not exist.');
        }

        // Test with shorter timeout and different options
        $options = 0;
        $retries = 1;

        $log_msg = "Calling imap_open with options: $options, retries: $retries";
        $this->log($log_msg);

        try {
            // Use error suppression and check if function hangs
            set_time_limit(30); // 30 second timeout

            $log_msg = "Starting imap_open call...";
            $this->log($log_msg);

            $mailbox = @imap_open($hostname, $this->imap_username, $this->imap_password, $options, $retries);

            $log_msg = "imap_open call completed";
            $this->log($log_msg);

        } catch(\Exception $e) {
            $error_msg = "Exception caught: " . $e->getMessage();
            $this->log_fail($error_msg);
            $mailbox = false;
        } catch(\Error $e) {
            $error_msg = "Fatal error caught: " . $e->getMessage();
            $this->log_fail($error_msg);
            $mailbox = false;
        } catch(\Throwable $e) {
            $error_msg = "Throwable caught: " . $e->getMessage();
            $this->log_fail($error_msg);
            $mailbox = false;
        }

        if ($mailbox === false) {
            $last_error = imap_last_error();
            $all_errors = imap_errors();
            $alerts = imap_alerts();

            $this->log_fail("IMAP connection failed!");
            $this->log_fail("Last error: " . ($last_error ? $last_error : 'No error message'));

            if ($all_errors) {
                $this->log_fail("All errors: " . implode(', ', $all_errors));
            }

            if ($alerts) {
                $this->log("Alerts: " . implode(', ', $alerts));
            }

            // Check if IMAP extension is loaded
            if (!extension_loaded('imap')) {
                $this->log_fail("IMAP extension is NOT loaded!");
            }

            throw new \Exception('IMAP connection error: ' . ($last_error ? $last_error : 'Unknown error') .
                                ($all_errors ? ' | All errors: ' . implode(', ', $all_errors) : '') .
                                ($alerts ? ' | Alerts: ' . implode(', ', $alerts) : ''));
        }
        $this->mailbox = $mailbox;
        $this->connected = true;
        return true;
    }

    /**
     * Check if the connection is active
     */
    public function is_connected() {
        return $this->connected;
    }

    /**
     * Disconnect from the IMAP server
     */
    public function disconnect() {
        if ($this->connected && $this->mailbox) {
            imap_close($this->mailbox);
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

        $hostname = '{' . $this->imap_hostname . ':' . $this->imap_port . ($this->imap_use_ssl ? '/imap/ssl/novalidate-cert' : '') . '}';
        $folders = imap_list($this->mailbox, $hostname, '*');

        if ($folders === false) {
            return [];
        }

        $folder_list = [];
        foreach ($folders as $folder) {
            $folder_name = str_replace($hostname, '', $folder);
            $folder_info = imap_status($this->mailbox, $folder, SA_ALL);
            $folder_list[] = [
                'name' => $folder_name,
                'messages' => $folder_info ? $folder_info->messages : 0,
                'unseen' => $folder_info ? $folder_info->unseen : 0,
                'recent' => $folder_info ? $folder_info->recent : 0
            ];
        }

        return $folder_list;
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

        $hostname = '{' . $this->imap_hostname . ':' . $this->imap_port . ($this->imap_use_ssl ? '/imap/ssl/novalidate-cert' : '') . '}' . $folder;

        imap_close($this->mailbox);
        $this->mailbox = imap_open($hostname, $this->imap_username, $this->imap_password);

        if ($this->mailbox === false) {
            throw new \Exception('Failed to switch to folder: ' . $folder . ' - ' . imap_last_error());
        }

        return true;
    }

    /**
     * Fetch recent emails from a specified folder
     * @param string $folder - mailbox folder, default 'INBOX'
     * @param string $criteria - search criteria, e.g., 'ALL', 'UNSEEN', 'FROM "sender@example.com"'
     * @param int $limit - number of emails to fetch
     * @return array An array of objects, where each object is an email overview.
     * @throws \Exception If not connected to the server.
     */
    public function get_emails($folder = 'INBOX', $criteria = 'ALL', $offset = 0, $limit = false) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        // Search for emails, returning UIDs for more stable referencing
        $emails_uids = imap_search($this->mailbox, $criteria, SE_UID);

        if (!$emails_uids) {
            return []; // No emails found
        }

        rsort($emails_uids); // Sort UIDs from newest to oldest

        $total = count($emails_uids);
        $from = $offset;
        $to = $total;

        // Limit the number of UIDs
        if ($limit) {
            $emails_uids = array_slice($emails_uids, $offset, $limit);
            $to = ($offset + $limit);
        }


        $messages = [];
        foreach ($emails_uids as $uid) {
            $overview = imap_fetch_overview($this->mailbox, $uid, FT_UID);
            if (!empty($overview[0])) {
                $messages[] = $overview[0]; // Add email overview to list
            }
        }

        return [
            'messages' => $messages,
            'from' => $from,
            'to' => $to,
            'total' => $total
        ];
    }

    /**
     * Reads a single email by its IMAP UID.
     *
     * @param int $uid The IMAP UID of the email to read.
     * @return array|false An associative array containing email details (header, body_plain, body_html, attachments) or false on failure.
     * @throws \Exception If not connected to the server.
     */
    public function read_email_by_uid(int $uid) { // Renamed method for clarity, strictly accepts UID
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        // imap_headerinfo does NOT accept FT_UID, so we must convert UID to msgno first.
        $message_num_for_headerinfo = imap_msgno($this->mailbox, $uid);
        
        if (!$message_num_for_headerinfo) {
            // UID not found in current mailbox, or an error occurred during conversion
            $this->log_fail("UID not found in current mailbox $uid");
            return false;
        }

        $header = imap_headerinfo($this->mailbox, $message_num_for_headerinfo);
        if (!$header) {
            return false;
        }
        
        // Decode subject
        $subject = '';
        if (isset($header->subject)) {
            foreach (imap_mime_header_decode($header->subject) as $part) {
                $subject .= $part->text;
            }
        }
        
        $header->subject = $subject; // Overwrite with decoded subject

        $structure = imap_fetchstructure($this->mailbox, $uid, FT_UID);
        
        if (!$structure) {
            $this->log_fail("STRUCTURE FAILED FOR $uid");
            return false;
        }

        $email_data = [
            'header' => $header,
            'body_plain' => '',
            'body_html' => '',
            'attachments' => [],
        ];

        // If the email has parts (i.e., it's multipart), iterate through them.
        if (isset($structure->parts)) {
            foreach ($structure->parts as $part_number => $part) {
                $this->decode_part($this->mailbox, $uid, $part, $part_number + 1, $email_data, FT_UID);
            }
        } else {
            // If the email does NOT have parts, it means the content is directly in the main structure.
            // In this case, treat the main structure as a single part.
            // The part_id for the main body when there are no sub-parts is '1'.
            $this->decode_part($this->mailbox, $uid, $structure, 1, $email_data, FT_UID);
        }

        $this->log('EMAIL DATA');
        $this->log($email_data);
        
        return $email_data;
    }

    /**
     * Finds an email's UID by its Message-ID header string.
     *
     * @param string $message_id_string The Message-ID header value (e.g., "<abc@example.com>").
     * @return int|false The IMAP UID if found, otherwise false.
     * @throws \Exception If not connected to the server.
     */
    public function get_uid_by_message_id(string $message_id_string) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        // Escape the message ID string for IMAP search criteria
        // The criterion should be 'HEADER Message-ID <the_message_id>'
        // Note: The Message-ID might contain characters that need escaping.
        // imap_search usually handles basic strings, but quotes around the full ID are crucial.
        $search_criteria = 'HEADER Message-ID "' . addslashes($message_id_string) . '"';

        $uids = imap_search($this->mailbox, $search_criteria, SE_UID);

        if (!empty($uids)) {
            // Return the first UID found. Message-IDs should be unique, but handle potential duplicates.
            return (int) $uids[0];
        }

        return false; // Message-ID not found
    }

    /**
     * Helper method to decode email parts (recursive for multipart messages).
     *
     * @param resource $imap_stream The IMAP stream.
     * @param int $message_identifier The message sequence number or UID.
     * @param object $part The part object.
     * @param string $part_id The ID of the part (e.g., '1', '1.1').
     * @param array $email_data The array to populate with email data.
     * @param int $fetch_flags Flags for imap_fetchbody (e.g., FT_UID).
     */
    private function decode_part($imap_stream, $message_identifier, $part, $part_id, &$email_data, $fetch_flags = 0) {
        $data = imap_fetchbody($imap_stream, $message_identifier, $part_id, $fetch_flags);

        // Decode if necessary
        switch ($part->encoding) {
            case ENCBASE64: // Base64
                $data = base64_decode($data);
                break;
            case ENCQUOTEDPRINTABLE: // Quoted-Printable
                $data = quoted_printable_decode($data);
                break;
        }

        // Determine charset and convert if necessary
        $charset = 'UTF-8'; // Default to UTF-8
        if (isset($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) == 'charset') {
                    $charset = strtoupper($param->value);
                    break;
                }
            }
        }

        if (isset($part->type) && $part->type == TYPETEXT) { // Text part
            $decoded_data = ($charset && $charset !== 'UTF-8') ? iconv($charset, 'UTF-8//IGNORE', $data) : $data;

            if (isset($part->subtype) && strtolower($part->subtype) == 'plain') {
                $email_data['body_plain'] .= $decoded_data;
            } elseif (isset($part->subtype) && strtolower($part->subtype) == 'html') {
                $email_data['body_html'] .= $decoded_data;
            }
        } elseif (isset($part->type) && $part->type == TYPEMULTIPART) { // Multipart part
            // Recursively decode sub-parts
            foreach ($part->parts as $sub_part_number => $sub_part) {
                $this->decode_part($imap_stream, $message_identifier, $sub_part, $part_id . '.' . ($sub_part_number + 1), $email_data, $fetch_flags);
            }
        } elseif (isset($part->dparameters) || isset($part->parameters)) { // Attachment or inline
            $filename = '';
            // Check for filename in disposition parameters
            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $dparam) {
                    if (strtolower($dparam->attribute) == 'filename') {
                        $filename = $dparam->value;
                        break;
                    }
                }
            }
            // Check for filename in parameters if not found in disposition
            if (!$filename && isset($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) == 'name') {
                        $filename = $param->value;
                        break;
                    }
                }
            }

            if ($filename) {
                // Decode filename if it's MIME encoded (e.g., =?UTF-8?B?...)
                $decoded_filename = '';
                foreach (imap_mime_header_decode($filename) as $fpart) {
                    $decoded_filename .= $fpart->text;
                }
                $filename = $decoded_filename;

                $email_data['attachments'][] = [
                    'filename' => $filename,
                    'mimetype' => (isset($part->type) ? $part->type : 'application') . '/' . (isset($part->subtype) ? $part->subtype : 'octet-stream'),
                    'encoding' => isset($part->encoding) ? $part->encoding : '',
                    'data' => $data, // Raw binary data
                ];
            }
        }
    }

    /**
     * Initialize SMTP connection and assign to $mailer attribute
     */
    public function connect_smtp($smtp_host, $smtp_port, $smtp_username, $smtp_password, $use_ssl) {
        $this->mailer = new PHPMailer(true);

        $this->smtp_host = $smtp_host;
        $this->smtp_port = $smtp_port;
        $this->smtp_username = $smtp_username;
        $this->smtp_password = $smtp_password;
        $this->use_ssl = $use_ssl;

        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtp_host;
            $this->mailer->Port = $this->smtp_port;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtp_username;
            $this->mailer->Password = $this->smtp_password;
            $this->mailer->SMTPSecure = $this->use_ssl ? 'ssl' : 'tls';
            // Set timeout or other options as needed
        } catch (Exception $e) {
            throw new \Exception('SMTP Connection Error: ' . $e->getMessage());
        }
    }

    public function send_email($to, $subject, $body, $headers = [], $from = false) {
        if (!$this->mailer) {
            throw new \Exception('SMTP connection not initialized. Call connect_smtp() first.');
        }
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            // Set sender
            $this->mailer->setFrom(!$from?$this->smtp_username:$from, 'Sender'); // Assuming IMAP username is also the sender email
            // Add recipient
            $this->mailer->addAddress($to);

            // Set subject and body
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->isHTML(true); // assuming HTML email, adjust if needed
            // Add additional headers if provided
            foreach ($headers as $key => $value) {
                $this->mailer->addCustomHeader($key, $value);
            }

            // Send email
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

        $flag = $read ? "\\Seen" : "";
        if ($read) {
            return imap_setflag_full($this->mailbox, $uid, "\\Seen", ST_UID);
        } else {
            return imap_clearflag_full($this->mailbox, $uid, "\\Seen", ST_UID);
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

        if ($flagged) {
            return imap_setflag_full($this->mailbox, $uid, "\\Flagged", ST_UID);
        } else {
            return imap_clearflag_full($this->mailbox, $uid, "\\Flagged", ST_UID);
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

        $result = imap_mail_move($this->mailbox, $uid, $target_folder, CP_UID);
        if ($result) {
            imap_expunge($this->mailbox);
        }
        return $result;
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

        return imap_mail_copy($this->mailbox, $uid, $target_folder, CP_UID);
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

        $result = imap_delete($this->mailbox, $uid, FT_UID);
        if ($result && $expunge) {
            imap_expunge($this->mailbox);
        }
        return $result;
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

        return imap_undelete($this->mailbox, $uid, FT_UID);
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

        return imap_expunge($this->mailbox);
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

        $hostname = '{' . $this->imap_hostname . ':' . $this->imap_port . ($this->imap_use_ssl ? '/imap/ssl/novalidate-cert' : '') . '}' . $folder_name;
        return imap_createmailbox($this->mailbox, $hostname);
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

        $hostname = '{' . $this->imap_hostname . ':' . $this->imap_port . ($this->imap_use_ssl ? '/imap/ssl/novalidate-cert' : '') . '}' . $folder_name;
        return imap_deletemailbox($this->mailbox, $hostname);
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

        $hostname = '{' . $this->imap_hostname . ':' . $this->imap_port . ($this->imap_use_ssl ? '/imap/ssl/novalidate-cert' : '') . '}';
        return imap_renamemailbox($this->mailbox, $hostname . $old_name, $hostname . $new_name);
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

        $criteria_parts = [];

        if (!empty($search_params['from'])) {
            $criteria_parts[] = 'FROM "' . $search_params['from'] . '"';
        }

        if (!empty($search_params['to'])) {
            $criteria_parts[] = 'TO "' . $search_params['to'] . '"';
        }

        if (!empty($search_params['subject'])) {
            $criteria_parts[] = 'SUBJECT "' . $search_params['subject'] . '"';
        }

        if (!empty($search_params['body'])) {
            $criteria_parts[] = 'BODY "' . $search_params['body'] . '"';
        }

        if (!empty($search_params['since'])) {
            $criteria_parts[] = 'SINCE "' . date('d-M-Y', strtotime($search_params['since'])) . '"';
        }

        if (!empty($search_params['before'])) {
            $criteria_parts[] = 'BEFORE "' . date('d-M-Y', strtotime($search_params['before'])) . '"';
        }

        if (isset($search_params['seen'])) {
            $criteria_parts[] = $search_params['seen'] ? 'SEEN' : 'UNSEEN';
        }

        if (isset($search_params['flagged'])) {
            $criteria_parts[] = $search_params['flagged'] ? 'FLAGGED' : 'UNFLAGGED';
        }

        if (isset($search_params['answered'])) {
            $criteria_parts[] = $search_params['answered'] ? 'ANSWERED' : 'UNANSWERED';
        }

        $criteria = empty($criteria_parts) ? 'ALL' : implode(' ', $criteria_parts);

        $uids = imap_search($this->mailbox, $criteria, SE_UID);
        return $uids ?: [];
    }

    /**
     * Reply to an email
     * @param int $uid Original email UID
     * @param string $reply_body Reply message body
     * @param bool $reply_all Whether to reply to all recipients
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function reply_to_email($uid, $reply_body, $reply_all = false) {
        if (!$this->mailer) {
            throw new \Exception('SMTP connection not initialized. Call connect_smtp() first.');
        }

        // Get original email data
        $original_email = $this->read_email_by_uid($uid);
        if (!$original_email) {
            throw new \Exception('Original email not found');
        }

        $original_header = $original_email['header'];

        // Prepare reply
        $reply_to = $original_header->from[0]->mailbox . '@' . $original_header->from[0]->host;
        $subject = 'Re: ' . (isset($original_header->subject) ? $original_header->subject : '');

        // Build quoted original message
        $quoted_body = "\n\n--- Original Message ---\n";
        $quoted_body .= "From: " . $reply_to . "\n";
        $quoted_body .= "Date: " . $original_header->date . "\n";
        $quoted_body .= "Subject: " . (isset($original_header->subject) ? $original_header->subject : '') . "\n\n";
        $quoted_body .= "> " . str_replace("\n", "\n> ", $original_email['body_plain']);

        $full_reply = $reply_body . $quoted_body;

        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->smtp_username, 'Reply');
            $this->mailer->addAddress($reply_to);

            if ($reply_all && isset($original_header->cc)) {
                foreach ($original_header->cc as $cc) {
                    $cc_email = $cc->mailbox . '@' . $cc->host;
                    if ($cc_email != $this->smtp_username) {
                        $this->mailer->addCC($cc_email);
                    }
                }
            }

            // Set References and In-Reply-To headers for proper threading
            if (isset($original_header->message_id)) {
                $this->mailer->addCustomHeader('In-Reply-To', $original_header->message_id);
                $this->mailer->addCustomHeader('References', $original_header->message_id);
            }

            $this->mailer->Subject = $subject;
            $this->mailer->Body = $full_reply;
            $this->mailer->isHTML(false);

            $this->mailer->send();

            // Mark original as answered
            $this->mark_as_answered($uid, true);

            return true;
        } catch (Exception $e) {
            throw new \Exception('Reply Error: ' . $e->getMessage());
        }
    }

    /**
     * Mark an email as answered/unanswered
     * @param int $uid Email UID
     * @param bool $answered True to mark as answered, false otherwise
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function mark_as_answered($uid, $answered = true) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        if ($answered) {
            return imap_setflag_full($this->mailbox, $uid, "\\Answered", ST_UID);
        } else {
            return imap_clearflag_full($this->mailbox, $uid, "\\Answered", ST_UID);
        }
    }

    /**
     * Forward an email
     * @param int $uid Original email UID
     * @param string $to_email Recipient email
     * @param string $forward_message Additional message to include
     * @return bool Success status
     * @throws \Exception If not connected to the server.
     */
    public function forward_email($uid, $to_email, $forward_message = '') {
        if (!$this->mailer) {
            throw new \Exception('SMTP connection not initialized. Call connect_smtp() first.');
        }

        // Get original email data
        $original_email = $this->read_email_by_uid($uid);
        if (!$original_email) {
            throw new \Exception('Original email not found');
        }

        $original_header = $original_email['header'];

        $subject = 'Fwd: ' . (isset($original_header->subject) ? $original_header->subject : '');

        // Build forwarded message
        $forwarded_body = $forward_message . "\n\n";
        $forwarded_body .= "--- Forwarded Message ---\n";
        $forwarded_body .= "From: " . $original_header->from[0]->mailbox . '@' . $original_header->from[0]->host . "\n";
        $forwarded_body .= "Date: " . $original_header->date . "\n";
        $forwarded_body .= "Subject: " . (isset($original_header->subject) ? $original_header->subject : '') . "\n\n";
        $forwarded_body .= $original_email['body_plain'];

        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->smtp_username, 'Forward');
            $this->mailer->addAddress($to_email);

            $this->mailer->Subject = $subject;
            $this->mailer->Body = $forwarded_body;
            $this->mailer->isHTML(false);

            // Add attachments from original email
            foreach ($original_email['attachments'] as $attachment) {
                $this->mailer->addStringAttachment(
                    $attachment['data'],
                    $attachment['filename'],
                    'base64',
                    $attachment['mimetype']
                );
            }

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            throw new \Exception('Forward Error: ' . $e->getMessage());
        }
    }

    /**
     * Get email thread/conversation
     * @param int $uid Email UID
     * @return array Array of related emails
     * @throws \Exception If not connected to the server.
     */
    public function get_email_thread($uid) {
        if (!$this->connected) {
            throw new \Exception('Not connected to the server.');
        }

        $email = $this->read_email_by_uid($uid);
        if (!$email) {
            return [];
        }

        $thread_emails = [];
        $header = $email['header'];

        // Search by Message-ID, References, and In-Reply-To
        $search_criteria = [];

        if (isset($header->message_id)) {
            $search_criteria[] = 'HEADER Message-ID "' . $header->message_id . '"';
            $search_criteria[] = 'HEADER References "' . $header->message_id . '"';
            $search_criteria[] = 'HEADER In-Reply-To "' . $header->message_id . '"';
        }

        if (isset($header->references)) {
            $references = explode(' ', $header->references);
            foreach ($references as $ref) {
                $ref = trim($ref);
                if (!empty($ref)) {
                    $search_criteria[] = 'HEADER Message-ID "' . $ref . '"';
                    $search_criteria[] = 'HEADER References "' . $ref . '"';
                }
            }
        }

        foreach ($search_criteria as $criteria) {
            $uids = imap_search($this->mailbox, $criteria, SE_UID);
            if ($uids) {
                foreach ($uids as $thread_uid) {
                    if (!in_array($thread_uid, array_column($thread_emails, 'uid'))) {
                        $thread_email = $this->read_email_by_uid($thread_uid);
                        if ($thread_email) {
                            $thread_email['uid'] = $thread_uid;
                            $thread_emails[] = $thread_email;
                        }
                    }
                }
            }
        }

        // Sort by date
        usort($thread_emails, function($a, $b) {
            return strtotime($a['header']->date) - strtotime($b['header']->date);
        });

        return $thread_emails;
    }
}