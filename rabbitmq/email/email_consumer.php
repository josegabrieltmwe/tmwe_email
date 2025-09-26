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
            $email_client->connect($imap_hostname, $imap_username, $imap_password, isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
            $email_client->connect($imap_hostname, $imap_username, $imap_password, isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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

        $smtp_host = isset($smtp_host) ? $smtp_host : (isset($smtp_server) ? $smtp_server : (isset($smtp_hostname)?$smtp_hostname:false));
        $smtp_username = isset($smtp_username)?$smtp_username:(isset($smtp_user)?$smtp_user:false);
        $smtp_use_ssl = isset($smtp_use_ssl)?$smtp_use_ssl:false;
        $smtp_use_tls = isset($smtp_use_tls)?$smtp_use_tls:false;
        
        try {
            // Assuming SMTP connection details are also provided in the JSON payload
            // or are fetched from a configuration.
            // For simplicity, let's assume they are directly in $json for this example.
            if (!isset($smtp_host, $smtp_port, $smtp_username, $smtp_password) && $smtp_host && $smtp_port && $smtp_username && $smtp_password) {
                return ['success' => false, 'errors' => ['Missing SMTP connection parameters.']];
            }

            $smtp_password = isset($smtp_password)?$smtp_password:$imap_password;

            $email_client->connect_smtp(
                    $smtp_host,
                    $smtp_port,
                    $smtp_username,
                    $smtp_password,
                    $smtp_use_ssl,
                    $smtp_use_tls
            );

            if(isset($message_data)){
                extract($message_data);
            }

            if (!isset($to, $subject, $body,)) {
                return ['success' => false, 'errors' => ['Missing email parameters (to, subject, or body).']];
            }

            $headers = isset($headers) ? (array) $headers : [];
            $from = (isset($from) && $from)?$from:$smtp_username;


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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);
            
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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            // Connect to SMTP
            $smtp_host = isset($smtp_host) ? $smtp_host : (isset($smtp_server) ? $smtp_server : (isset($smtp_hostname)?$smtp_hostname:false));
            $smtp_username = isset($smtp_username) ? $smtp_username : (isset($smtp_user) ? $smtp_user : false);
            $smtp_use_ssl = isset($smtp_use_ssl) ? $smtp_use_ssl : false;
            $smtp_use_tls = isset($smtp_use_tls) ? $smtp_use_tls : false;

            if (!isset($smtp_host, $smtp_port, $smtp_username, $smtp_password)) {
                return ['success' => false, 'errors' => ['Missing SMTP connection parameters.']];
            }

            $email_client->connect_smtp($smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_use_ssl, $smtp_use_tls);

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

        $smtp_password = isset($smtp_password)?$smtp_password:$imap_password;

        try {
            // Connect to IMAP first
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            // Connect to SMTP
            $smtp_host = isset($smtp_host) ? $smtp_host : (isset($smtp_server) ? $smtp_server : (isset($smtp_hostname)?$smtp_hostname:false));
            $smtp_username = isset($smtp_username) ? $smtp_username : (isset($smtp_user) ? $smtp_user : false);
            $smtp_use_ssl = isset($smtp_use_ssl) ? $smtp_use_ssl : false;
            $smtp_use_tls = isset($smtp_use_tls) ? $smtp_use_tls : false;

            if (!isset($smtp_host, $smtp_port, $smtp_username, $smtp_password)) {
                return ['success' => false, 'errors' => ['Missing SMTP connection parameters.']];
            }

            $email_client->connect_smtp($smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_use_ssl, $smtp_use_tls);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

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

    /**
     * Get information about a specific folder
     * Expected $json payload: ["function_to_call": "get_folder_info", "folder_name": "INBOX", ...]
     */
    protected function get_folder_info($json, $message_amqp) {
        extract($json);

        if (!isset($folder_name)) {
            return ['success' => false, 'errors' => ['"folder_name" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            // Switch to the requested folder first
            $email_client->select_folder($folder_name);

            // Get folder information from Email_Client methods
            $folders = $email_client->get_folders();
            $folder_info = null;

            foreach ($folders as $folder) {
                if ($folder['name'] === $folder_name) {
                    $folder_info = $folder;
                    break;
                }
            }

            if ($folder_info) {
                $result = [
                    'folder_name' => $folder_name,
                    'messages' => $folder_info['messages'],
                    'recent' => $folder_info['recent'],
                    'unseen' => $folder_info['unseen'],
                    'uidnext' => null, // Not available in ddeboer library
                    'uidvalidity' => null // Not available in ddeboer library
                ];
                return ['success' => true, 'data' => $result];
            } else {
                return ['success' => false, 'errors' => ["Could not get information for folder: $folder_name"]];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Subscribe to a folder
     * Expected $json payload: ["function_to_call": "subscribe_folder", "folder_name": "INBOX", ...]
     */
    protected function subscribe_folder($json, $message_amqp) {
        extract($json);

        if (!isset($folder_name)) {
            return ['success' => false, 'errors' => ['"folder_name" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $result = $email_client->subscribe_folder($folder_name);
            return ['success' => $result, 'message' => "Subscribed to folder: $folder_name"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Unsubscribe from a folder
     * Expected $json payload: ["function_to_call": "unsubscribe_folder", "folder_name": "INBOX", ...]
     */
    protected function unsubscribe_folder($json, $message_amqp) {
        extract($json);

        if (!isset($folder_name)) {
            return ['success' => false, 'errors' => ['"folder_name" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $result = $email_client->unsubscribe_folder($folder_name);
            return ['success' => $result, 'message' => "Unsubscribed from folder: $folder_name"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Get folder tree structure
     * Expected $json payload: ["function_to_call": "get_folder_tree", ...]
     */
    protected function get_folder_tree($json, $message_amqp) {
        extract($json);

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $tree = $email_client->get_folder_tree();
            return ['success' => true, 'data' => $tree];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Empty a folder (delete all emails)
     * Expected $json payload: ["function_to_call": "empty_folder", "folder_name": "INBOX", ...]
     */
    protected function empty_folder($json, $message_amqp) {
        extract($json);

        if (!isset($folder_name)) {
            return ['success' => false, 'errors' => ['"folder_name" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $result = $email_client->empty_folder($folder_name);
            return ['success' => $result, 'message' => "Folder '$folder_name' has been emptied"];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Get account information
     * Expected $json payload: ["function_to_call": "get_account_info", ...]
     */
    protected function get_account_info($json, $message_amqp) {
        extract($json);

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $account_info = $email_client->get_account_info();
            return ['success' => true, 'data' => $account_info];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Get quota information
     * Expected $json payload: ["function_to_call": "get_quota", "quota_root": "user.username", ...]
     */
    protected function get_quota($json, $message_amqp) {
        extract($json);

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $quota_root = isset($quota_root) ? $quota_root : null;
            $quota_info = $email_client->get_quota($quota_root);
            return ['success' => true, 'data' => $quota_info];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Test connection to IMAP server
     * Expected $json payload: ["function_to_call": "test_connection", ...]
     */
    protected function test_connection($json, $message_amqp) {
        extract($json);

        // No need for persistent connection, use static method
        $result = \tmwe_email\service\email\Email_Client::test_connection(
            $imap_hostname,
            $imap_username,
            $imap_password,
            isset($imap_port) ? $imap_port : 993,
            isset($imap_use_ssl) ? $imap_use_ssl : true,
            isset($imap_use_tls) ? $imap_use_tls : false
        );

        return $result;
    }

    /**
     * Get messages (alias for get_email_list)
     * Expected $json payload: ["function_to_call": "get_messages", ...]
     */
    protected function get_messages($json, $message_amqp) {
        return $this->get_email_list($json, $message_amqp);
    }

    /**
     * Get a single message (alias for get_single_email)
     * Expected $json payload: ["function_to_call": "get_message", "uid": 123, ...]
     */
    protected function get_message($json, $message_amqp) {
        return $this->get_single_email($json, $message_amqp);
    }

    /**
     * Send a message (alias for send_email)
     * Expected $json payload: ["function_to_call": "send_message", ...]
     */
    protected function send_message($json, $message_amqp) {
        return $this->send_email($json, $message_amqp);
    }

    /**
     * Reply to a message (alias for reply_to_email)
     * Expected $json payload: ["function_to_call": "reply_message", "uid": 123, "reply_body": "...", ...]
     */
    protected function reply_message($json, $message_amqp) {
        return $this->reply_to_email($json, $message_amqp);
    }

    /**
     * Forward a message (alias for forward_email)
     * Expected $json payload: ["function_to_call": "forward_message", "uid": 123, "to_email": "...", ...]
     */
    protected function forward_message($json, $message_amqp) {
        return $this->forward_email($json, $message_amqp);
    }

    /**
     * Delete multiple messages
     * Expected $json payload: ["function_to_call": "delete_messages", "uids": [123, 456], "expunge": false, ...]
     */
    protected function delete_messages($json, $message_amqp) {
        extract($json);

        if (!isset($uids) || !is_array($uids) || empty($uids)) {
            return ['success' => false, 'errors' => ['"uids" array is required and cannot be empty.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $expunge = isset($expunge) ? (bool)$expunge : false;
            $result = $email_client->delete_messages($uids, $expunge);
            return ['success' => $result, 'message' => 'Messages deleted', 'processed_count' => count($uids)];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Move multiple messages
     * Expected $json payload: ["function_to_call": "move_messages", "uids": [123, 456], "target_folder": "Archive", ...]
     */
    protected function move_messages($json, $message_amqp) {
        extract($json);

        if (!isset($uids, $target_folder) || !is_array($uids) || empty($uids)) {
            return ['success' => false, 'errors' => ['"uids" array and "target_folder" are required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $result = $email_client->move_messages($uids, $target_folder);
            return ['success' => $result, 'message' => "Messages moved to $target_folder", 'processed_count' => count($uids)];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Mark multiple messages with flags
     * Expected $json payload: ["function_to_call": "mark_messages", "uids": [123, 456], "flag": "\\Seen", "set": true, ...]
     */
    protected function mark_messages($json, $message_amqp) {
        extract($json);

        if (!isset($uids, $flag) || !is_array($uids) || empty($uids)) {
            return ['success' => false, 'errors' => ['"uids" array and "flag" are required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $set = isset($set) ? (bool)$set : true;
            $result = $email_client->mark_messages($uids, $flag, $set);
            $action = $set ? 'set' : 'cleared';
            return ['success' => $result, 'message' => "Flag $flag $action on messages", 'processed_count' => count($uids)];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Search messages (alias for advanced_search)
     * Expected $json payload: ["function_to_call": "search_messages", "search_params": {...}, ...]
     */
    protected function search_messages($json, $message_amqp) {
        return $this->advanced_search($json, $message_amqp);
    }

    /**
     * Get attachment from a message
     * Expected $json payload: ["function_to_call": "get_attachment", "uid": 123, "attachment_index": 0, ...]
     */
    protected function get_attachment($json, $message_amqp) {
        extract($json);

        if (!isset($uid, $attachment_index)) {
            return ['success' => false, 'errors' => ['"uid" and "attachment_index" are required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $attachment = $email_client->get_attachment($uid, $attachment_index);
            if ($attachment) {
                return ['success' => true, 'data' => $attachment];
            } else {
                return ['success' => false, 'errors' => ["Attachment not found at index $attachment_index for message $uid"]];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Perform full synchronization of all folders
     * Expected $json payload: ["function_to_call": "full_sync", ...]
     */
    protected function full_sync($json, $message_amqp) {
        extract($json);

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            // Start full sync (this could be a long-running process)
            $result = $email_client->full_sync();
            return ['success' => true, 'data' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Perform incremental synchronization
     * Expected $json payload: ["function_to_call": "incremental_sync", "since_timestamp": 1234567890, ...]
     */
    protected function incremental_sync($json, $message_amqp) {
        extract($json);

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $since_timestamp = isset($since_timestamp) ? (int)$since_timestamp : null;
            $result = $email_client->incremental_sync($since_timestamp);
            return ['success' => true, 'data' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Synchronize a specific folder
     * Expected $json payload: ["function_to_call": "sync_folder", "folder_name": "INBOX", ...]
     */
    protected function sync_folder($json, $message_amqp) {
        extract($json);

        if (!isset($folder_name)) {
            return ['success' => false, 'errors' => ['"folder_name" is required.']];
        }

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();

        try {
            $email_client->connect($imap_hostname, $imap_username, $imap_password,
                isset($imap_port) ? $imap_port : 993, isset($imap_use_ssl) ? $imap_use_ssl : true, isset($imap_use_tls) ? $imap_use_tls : false);

            $result = $email_client->sync_folder($folder_name);
            return ['success' => true, 'data' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Get synchronization status
     * Expected $json payload: ["function_to_call": "get_sync_status", "sync_id": "sync_123", ...]
     */
    protected function get_sync_status($json, $message_amqp) {
        extract($json);

        if (!isset($sync_id)) {
            // Return all sync statuses if no specific sync_id provided
            $all_statuses = \tmwe_email\service\email\Email_Client::get_all_sync_statuses();
            return ['success' => true, 'data' => $all_statuses];
        }

        $status = \tmwe_email\service\email\Email_Client::get_sync_status($sync_id);
        if ($status !== false) {
            return ['success' => true, 'data' => $status];
        } else {
            return ['success' => false, 'errors' => ["Sync ID '$sync_id' not found."]];
        }
    }

    /**
     * Cancel a running synchronization
     * Expected $json payload: ["function_to_call": "cancel_sync", "sync_id": "sync_123", ...]
     */
    protected function cancel_sync($json, $message_amqp) {
        extract($json);

        if (!isset($sync_id)) {
            return ['success' => false, 'errors' => ['"sync_id" is required.']];
        }

        $result = \tmwe_email\service\email\Email_Client::cancel_sync($sync_id);
        if ($result) {
            return ['success' => true, 'message' => "Sync '$sync_id' has been cancelled."];
        } else {
            return ['success' => false, 'errors' => ["Sync ID '$sync_id' not found or cannot be cancelled."]];
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
            case 'get_folder_info':
                return $this->$function_to_call($json, $message_amqp);
            case 'subscribe_folder':
                return $this->$function_to_call($json, $message_amqp);
            case 'unsubscribe_folder':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_folder_tree':
                return $this->$function_to_call($json, $message_amqp);
            case 'empty_folder':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_account_info':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_quota':
                return $this->$function_to_call($json, $message_amqp);
            case 'test_connection':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_messages':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_message':
                return $this->$function_to_call($json, $message_amqp);
            case 'send_message':
                return $this->$function_to_call($json, $message_amqp);
            case 'reply_message':
                return $this->$function_to_call($json, $message_amqp);
            case 'forward_message':
                return $this->$function_to_call($json, $message_amqp);
            case 'delete_messages':
                return $this->$function_to_call($json, $message_amqp);
            case 'move_messages':
                return $this->$function_to_call($json, $message_amqp);
            case 'mark_messages':
                return $this->$function_to_call($json, $message_amqp);
            case 'search_messages':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_attachment':
                return $this->$function_to_call($json, $message_amqp);
            case 'full_sync':
                return $this->$function_to_call($json, $message_amqp);
            case 'incremental_sync':
                return $this->$function_to_call($json, $message_amqp);
            case 'sync_folder':
                return $this->$function_to_call($json, $message_amqp);
            case 'get_sync_status':
                return $this->$function_to_call($json, $message_amqp);
            case 'cancel_sync':
                return $this->$function_to_call($json, $message_amqp);

            default:
                return ['success' => false, 'errors' => ['Unknown function_to_call: ' . $function_to_call]];
        }
    }
}
