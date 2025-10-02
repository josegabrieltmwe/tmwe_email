# Documentation: Email Queue Task Message Parameters

## Base Message Structure

All messages sent to RabbitMQ include the email account configuration plus function-specific parameters:

```php
[
    // Base account configuration (present in all)
    'imap_hostname' => string,
    'imap_username' => string,
    'imap_password' => string,
    'imap_port' => int,
    'imap_use_ssl' => bool,
    'smtp_host' => string,
    'smtp_port' => int,
    'smtp_username' => string,
    'smtp_password' => string,
    'smtp_use_ssl' => bool,

    // Parameter that identifies the function to execute
    'function_to_call' => string,

    // Function-specific parameters...
]
```

---

## 1. Message Operations (Email Message)

### 1.1 `get_messages` - Get message list

**Queue**: `tmwe_email_client_queue`

**Parameters**:
```php
[
    'function_to_call' => 'get_messages',
    'options' => [
        'folder' => string,              // Default: 'INBOX'
        'limit' => int,                  // Number of messages to retrieve
        'offset' => int,                 // Offset for pagination
        'page' => int,                   // Page (calculated as offset = (page-1) * limit)
        'include_attachments' => bool,   // Default: false
        'format' => string               // Default: 'text'
    ]
]
```

### 1.2 `get_message` - Get individual message

**Parameters**:
```php
[
    'function_to_call' => 'get_message',
    'uid' => int|string,                // Message UID
    'options' => [
        'folder' => string,              // Default: 'INBOX'
        'include_attachments' => bool,
        'format' => string
    ]
]
```

### 1.3 `send_message` - Send new message

**Parameters**:
```php
[
    'function_to_call' => 'send_message',
    'message_data' => [
        'to' => string,                  // REQUIRED: recipients
        'cc' => string,                  // Default: ''
        'bcc' => string,                 // Default: ''
        'subject' => string,             // REQUIRED: subject
        'body' => string,                // REQUIRED: plain text body
        'body_html' => string,           // Default: ''
        'attachments' => array           // Default: []
    ]
]
```

### 1.4 `reply_message` - Reply to message

**Parameters**:
```php
[
    'function_to_call' => 'reply_message',
    'uid' => int|string,                // Original message UID
    'reply_data' => [
        'body' => string,                // REQUIRED: reply body
        'body_html' => string,           // Default: ''
        'reply_all' => bool,             // Default: false
        'attachments' => array           // Default: []
    ]
]
```

### 1.5 `forward_message` - Forward message

**Parameters**:
```php
[
    'function_to_call' => 'forward_message',
    'uid' => int|string,                // Original message UID
    'forward_data' => [
        'to' => string,                  // REQUIRED: recipients
        'cc' => string,                  // Default: ''
        'bcc' => string,                 // Default: ''
        'body' => string,                // REQUIRED: additional comment
        'body_html' => string,           // Default: ''
        'attachments' => array           // Default: []
    ]
]
```

### 1.6 `delete_email` - Delete message permanently

**Parameters**:
```php
[
    'function_to_call' => 'delete_email',
    'uid' => int|string                 // Message UID to delete
]
```

### 1.7 `move_to_trash` - Move message to trash

**Parameters**:
```php
[
    'function_to_call' => 'move_to_trash',
    'uid' => int|string                 // Message UID
]
```

### 1.8 `delete_messages` - Delete multiple messages

**Parameters**:
```php
[
    'function_to_call' => 'delete_messages',
    'uids' => array                     // Array of UIDs: [123, 456, 789]
]
```

### 1.9 `move_messages_to_trash` - Move multiple messages to trash

**Parameters**:
```php
[
    'function_to_call' => 'move_messages_to_trash',
    'uids' => array                     // Array of UIDs
]
```

### 1.10 `move_messages` - Move messages to folder

**Parameters**:
```php
[
    'function_to_call' => 'move_messages',
    'uids' => array,                    // Array of UIDs
    'target_folder' => string           // Target folder name
]
```

### 1.11 `mark_messages` - Mark messages as read/unread

**Parameters**:
```php
[
    'function_to_call' => 'mark_messages',
    'uids' => array,                    // Array of UIDs
    'read' => bool                      // true: mark read, false: mark unread
]
```

### 1.12 `search_messages` - Search messages

**Parameters**:
```php
[
    'function_to_call' => 'search_messages',
    'search_criteria' => [
        'from' => string,                // Search by sender
        'to' => string,                  // Search by recipient
        'subject' => string,             // Search in subject
        'body' => string,                // Search in body
        'date_from' => string,           // Date from (IMAP format)
        'date_to' => string,             // Date to
        'unread' => bool                 // Unread only
    ]
]
```

### 1.13 `get_attachment` - Download attachment

**Parameters**:
```php
[
    'function_to_call' => 'get_attachment',
    'uid' => int|string,                // Message UID
    'attachment_id' => string           // Attachment ID
]
```

---

## 2. Synchronization Operations (Email Sync)

### 2.1 `full_sync` - Full synchronization

**Queue**: `tmwe_email_client_queue`

**Parameters**:
```php
[
    'function_to_call' => 'full_sync',
    'options' => [
        'folders' => array,              // Array of folders or comma-separated string
        'date_from' => string,           // Date from
        'date_to' => string              // Date to
    ]
]
```

### 2.2 `incremental_sync` - Incremental synchronization

**Parameters**:
```php
[
    'function_to_call' => 'incremental_sync',
    'options' => [
        'folders' => array,
        'date_from' => string,
        'date_to' => string,
        'last_sync_date' => string       // Last synchronization date
    ]
]
```

### 2.3 `sync_folder` - Synchronize specific folder

**Parameters**:
```php
[
    'function_to_call' => 'sync_folder',
    'folder_name' => string,            // REQUIRED: folder name
    'options' => [
        'date_from' => string,
        'date_to' => string,
        'last_sync_date' => string
    ]
]
```

### 2.4 `get_sync_status` - Get synchronization status

**Parameters**:
```php
[
    'function_to_call' => 'get_sync_status'
    // No additional parameters required
]
```

### 2.5 `cancel_sync` - Cancel ongoing synchronization

**Parameters**:
```php
[
    'function_to_call' => 'cancel_sync'
    // No additional parameters required
]
```

---

## Available Queues

According to `rabbitmq_config.php`:

- **`tmwe_email_client_queue`**: Main queue for email operations
- **`tmwe_email_client_queue_ia`**: Queue for smart email processing (Smart Email)

---

## Important Notes

1. **Account configuration**: The complete IMAP and SMTP configuration must always be included in each message
2. **UIDs vs Message Numbers**: The system uses IMAP UIDs for stable references
3. **Required parameters**: Parameters marked as "REQUIRED" must be present or the operation will fail
4. **Default values**: Parameters with "Default" are optional
5. **Array format**: The `uids` and `folders` parameters can be received as array or comma-separated string, the system converts them internally

---

## Reference Files

- `controller/api/email/email_message_controller.php` - Message controller
- `controller/api/email/email_sync_controller.php` - Synchronization controller
- `service/email/email_message_service.php` - Message service
- `service/email/email_sync_service.php` - Synchronization service
- `rabbitmq_config.php` - Queue configuration

**Last updated**: 2025-10-02
