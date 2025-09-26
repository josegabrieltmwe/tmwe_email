<?php

namespace tmwe_email\rabbitmq\email;

/**
 * Description of Email_Consumer
 *
 * @author pepe
 */
class Email_Consumer extends \tmwe_email\rabbitmq\Abstract_Consumer_Rpc {

    public function get_queue_name() {
        return \tmwe_email\Rabbitmq_Config::get_instance()->get_email_client_queue();
    }

    protected function get_email_list($json, $message_amqp) {
        extract($json);

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password, isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $folder = isset($folder) ? $folder : 'INBOX';
            $criteria = isset($criteria) ? $criteria : 'ALL';
            $limit = isset($limit) ? $limit : 10;
            $offset = isset($offset)? $offset: 0;

            $email_list = $email_client->get_emails($folder, $criteria, $offset, $limit);
            return $email_list;
        } catch (\Exception $e) {
            return ['errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Retrieves a single email by its message number.
     * Expected $json payload:
     * [
     * "function_to_call": "get_single_email",
     * "imap_hostname": "your.imap.server.com",
     * "imap_username": "your_username@example.com",
     * "imap_password": "your_password",
     * "uid": 123, // Required
     * "imap_port": 993,      // Optional, defaults to 993
     * "imap_use_ssl": true   // Optional, defaults to true
     * ]
     *
     * @param array $json The decoded JSON payload from the RPC request.
     * @param \AMQPMessage $message_amqp The AMQP message object.
     * @return array The response data to be sent back via RPC.
     */
    protected function get_single_email($json, $message_amqp) {
        extract($json);
        
        // Validate required parameters
        if (!isset($uid)) {
            return ['success' => false, 'errors' => ['"uid" is required to get a single email.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password, isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $email_data = $email_client->read_email_by_uid($uid);

            if ($email_data) {
                return ['success' => true, 'data' => $email_data];
            } else {
                return ['success' => false, 'errors' => ["Failed to retrieve email with message number: $uid"]];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    protected function send_email($json, $message_amqp) {
        extract($json);
        
        if(isset($smtp_config)){
            extract($smtp_config);
        }
        
        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        
        $smtp_host = isset($smtp_host)?$smtp_host:(isset($smtp_server)?$smtp_server:false);
        $smtp_username = isset($smtp_username)?$smtp_username:(isset($smtp_user)?$smtp_user:false);
        $use_ssl = isset($use_ssl)?$use_ssl:false;
        
        try {
            // Assuming SMTP connection details are also provided in the JSON payload
            // or are fetched from a configuration.
            // For simplicity, let's assume they are directly in $json for this example.
            if (!isset($smtp_host, $smtp_port, $smtp_username, $smtp_password, $use_ssl) && $smtp_host && $smtp_port && $smtp_username && $smtp_password) {
                return ['success' => false, 'errors' => ['Missing SMTP connection parameters.']];
            }

            $email_client->connect_smtp(
                    $smtp_host,
                    $smtp_port,
                    $smtp_username,
                    $smtp_password,
                    $use_ssl
            );

            if (!isset($to, $subject, $body,)) {
                return ['success' => false, 'errors' => ['Missing email parameters (to, subject, or body).']];
            }

            $headers = isset($headers) ? (array) $headers : [];
            $from = isset($from)?$from:false;
            
            $email_client->send_email($to, $subject, $body, $headers, $from);
            return ['success' => true, 'message' => 'Email sent successfully.'];
        } catch (\Exception $e) {
            echo $e->getMessage();
            return ['success' => false, 'errors' => ['Failed to send email: ' . $e->getMessage()]];
        }
    }

    /**
     * Get list of all mailbox folders
     * Expected $json payload: ["function_to_call": "get_folders", "imap_hostname": "...", "imap_username": "...", "imap_password": "...", ...]
     */
    protected function get_folders($json, $message_amqp) {
        extract($json);
        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $folders = $email_client->get_folders();
            return ['success' => true, 'data' => $folders];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Switch to a specific folder
     * Expected $json payload: ["function_to_call": "select_folder", "folder": "INBOX", ...]
     */
    protected function select_folder($json, $message_amqp) {
        extract($json);
        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $folder = isset($folder) ? $folder : 'INBOX';
            $result = $email_client->select_folder($folder);
            return ['success' => $result, 'message' => "Switched to folder: $folder"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Mark email as read/unread
     * Expected $json payload: ["function_to_call": "mark_as_read", "uid": 123, "read": true, ...]
     */
    protected function mark_as_read($json, $message_amqp) {
        extract($json);

        if (!isset($uid)) {
            return ['success' => false, 'errors' => ['"uid" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $read = isset($read) ? (bool)$read : true;
            $result = $email_client->mark_as_read($uid, $read);
            return ['success' => $result, 'message' => $read ? 'Marked as read' : 'Marked as unread'];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Mark email as flagged/unflagged
     * Expected $json payload: ["function_to_call": "mark_as_flagged", "uid": 123, "flagged": true, ...]
     */
    protected function mark_as_flagged($json, $message_amqp) {
        extract($json);

        if (!isset($uid)) {
            return ['success' => false, 'errors' => ['"uid" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $flagged = isset($flagged) ? (bool)$flagged : true;
            $result = $email_client->mark_as_flagged($uid, $flagged);
            return ['success' => $result, 'message' => $flagged ? 'Marked as flagged' : 'Marked as unflagged'];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Move email to another folder
     * Expected $json payload: ["function_to_call": "move_email", "uid": 123, "target_folder": "Archive", ...]
     */
    protected function move_email($json, $message_amqp) {
        extract($json);

        if (!isset($uid, $target_folder)) {
            return ['success' => false, 'errors' => ['"uid" and "target_folder" are required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $result = $email_client->move_email($uid, $target_folder);
            return ['success' => $result, 'message' => "Email moved to $target_folder"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Copy email to another folder
     * Expected $json payload: ["function_to_call": "copy_email", "uid": 123, "target_folder": "Archive", ...]
     */
    protected function copy_email($json, $message_amqp) {
        extract($json);

        if (!isset($uid, $target_folder)) {
            return ['success' => false, 'errors' => ['"uid" and "target_folder" are required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $result = $email_client->copy_email($uid, $target_folder);
            return ['success' => $result, 'message' => "Email copied to $target_folder"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Delete email
     * Expected $json payload: ["function_to_call": "delete_email", "uid": 123, "expunge": false, ...]
     */
    protected function delete_email($json, $message_amqp) {
        extract($json);

        if (!isset($uid)) {
            return ['success' => false, 'errors' => ['"uid" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $expunge = isset($expunge) ? (bool)$expunge : false;
            $result = $email_client->delete_email($uid, $expunge);
            return ['success' => $result, 'message' => 'Email deleted'];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Advanced search emails
     * Expected $json payload: ["function_to_call": "advanced_search", "search_params": {"from": "...", "subject": "..."}, ...]
     */
    protected function advanced_search($json, $message_amqp) {
        extract($json);

        if (!isset($search_params)) {
            return ['success' => false, 'errors' => ['"search_params" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $uids = $email_client->advanced_search($search_params);
            return ['success' => true, 'data' => $uids];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Reply to an email
     * Expected $json payload: ["function_to_call": "reply_to_email", "uid": 123, "reply_body": "...", "reply_all": false, ...]
     */
    protected function reply_to_email($json, $message_amqp) {
        extract($json);

        if (!isset($uid, $reply_body)) {
            return ['success' => false, 'errors' => ['"uid" and "reply_body" are required.']];
        }

        if (isset($smtp_config)) {
            extract($smtp_config);
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            // Connect to IMAP first
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            // Connect to SMTP
            $smtp_host = isset($smtp_host) ? $smtp_host : (isset($smtp_server) ? $smtp_server : false);
            $smtp_username = isset($smtp_username) ? $smtp_username : (isset($smtp_user) ? $smtp_user : false);
            $use_ssl = isset($use_ssl) ? $use_ssl : false;

            if (!isset($smtp_host, $smtp_port, $smtp_username, $smtp_password)) {
                return ['success' => false, 'errors' => ['Missing SMTP connection parameters.']];
            }

            $email_client->connect_smtp($smtp_host, $smtp_port, $smtp_username, $smtp_password, $use_ssl);

            $reply_all = isset($reply_all) ? (bool)$reply_all : false;
            $result = $email_client->reply_to_email($uid, $reply_body, $reply_all);
            return ['success' => $result, 'message' => 'Reply sent successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Forward an email
     * Expected $json payload: ["function_to_call": "forward_email", "uid": 123, "to_email": "...", "forward_message": "...", ...]
     */
    protected function forward_email($json, $message_amqp) {
        extract($json);

        if (!isset($uid, $to_email)) {
            return ['success' => false, 'errors' => ['"uid" and "to_email" are required.']];
        }

        if (isset($smtp_config)) {
            extract($smtp_config);
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            // Connect to IMAP first
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            // Connect to SMTP
            $smtp_host = isset($smtp_host) ? $smtp_host : (isset($smtp_server) ? $smtp_server : false);
            $smtp_username = isset($smtp_username) ? $smtp_username : (isset($smtp_user) ? $smtp_user : false);
            $use_ssl = isset($use_ssl) ? $use_ssl : false;

            if (!isset($smtp_host, $smtp_port, $smtp_username, $smtp_password)) {
                return ['success' => false, 'errors' => ['Missing SMTP connection parameters.']];
            }

            $email_client->connect_smtp($smtp_host, $smtp_port, $smtp_username, $smtp_password, $use_ssl);

            $forward_message = isset($forward_message) ? $forward_message : '';
            $result = $email_client->forward_email($uid, $to_email, $forward_message);
            return ['success' => $result, 'message' => 'Email forwarded successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Get email thread/conversation
     * Expected $json payload: ["function_to_call": "get_email_thread", "uid": 123, ...]
     */
    protected function get_email_thread($json, $message_amqp) {
        extract($json);

        if (!isset($uid)) {
            return ['success' => false, 'errors' => ['"uid" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $thread_emails = $email_client->get_email_thread($uid);
            return ['success' => true, 'data' => $thread_emails];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Create a new folder
     * Expected $json payload: ["function_to_call": "create_folder", "folder_name": "MyFolder", ...]
     */
    protected function create_folder($json, $message_amqp) {
        extract($json);

        if (!isset($folder_name)) {
            return ['success' => false, 'errors' => ['"folder_name" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $result = $email_client->create_folder($folder_name);
            return ['success' => $result, 'message' => "Folder '$folder_name' created"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Delete a folder
     * Expected $json payload: ["function_to_call": "delete_folder", "folder_name": "MyFolder", ...]
     */
    protected function delete_folder($json, $message_amqp) {
        extract($json);

        if (!isset($folder_name)) {
            return ['success' => false, 'errors' => ['"folder_name" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $result = $email_client->delete_folder($folder_name);
            return ['success' => $result, 'message' => "Folder '$folder_name' deleted"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Rename a folder
     * Expected $json payload: ["function_to_call": "rename_folder", "old_name": "OldFolder", "new_name": "NewFolder", ...]
     */
    protected function rename_folder($json, $message_amqp) {
        extract($json);

        if (!isset($old_name, $new_name)) {
            return ['success' => false, 'errors' => ['"old_name" and "new_name" are required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true);

            $result = $email_client->rename_folder($old_name, $new_name);
            return ['success' => $result, 'message' => "Folder renamed from '$old_name' to '$new_name'"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    public function handle_rpc_request($json, $message_amqp) {

        if (!isset($json['function_to_call'])) {
            return ['success' => false, 'errors' => ['no function to call found']];
        }

        $function_to_call = $json['function_to_call'];
        switch ($function_to_call) {
            // Original functions
            case 'get_email_list':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_single_email':
                return $this->$function_to_call($json, $message_amqp);
            case 'send_email':
                return $this->$function_to_call($json, $message_amqp);

            // New Outlook/Thunderbird-like functions
            case 'get_folders':
                return $this->$function_to_call($json, $message_amqp);
            case 'select_folder':
                return $this->$function_to_call($json, $message_amqp);
            case 'mark_as_read':
                return $this->$function_to_call($json, $message_amqp);
            case 'mark_as_flagged':
                return $this->$function_to_call($json, $message_amqp);
            case 'move_email':
                return $this->$function_to_call($json, $message_amqp);
            case 'copy_email':
                return $this->$function_to_call($json, $message_amqp);
            case 'delete_email':
                return $this->$function_to_call($json, $message_amqp);
            case 'advanced_search':
                return $this->$function_to_call($json, $message_amqp);
            case 'reply_to_email':
                return $this->$function_to_call($json, $message_amqp);
            case 'forward_email':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_email_thread':
                return $this->$function_to_call($json, $message_amqp);
            case 'create_folder':
                return $this->$function_to_call($json, $message_amqp);
            case 'delete_folder':
                return $this->$function_to_call($json, $message_amqp);
            case 'rename_folder':
                return $this->$function_to_call($json, $message_amqp);

            default:
                return ['success' => false, 'errors' => ['Unknown function_to_call: ' . $function_to_call]];
        }
    }
}
