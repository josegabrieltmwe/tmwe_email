# Email RabbitMQ Consumer

A RabbitMQ-based email client that provides Outlook/Thunderbird-like functionality through RPC messaging. This consumer allows you to perform comprehensive email operations via AMQP messages.

## Features

- **Email Management**: Read, send, delete, move, copy emails
- **Folder Operations**: Create, delete, rename, navigate folders
- **Advanced Search**: Multi-criteria email search
- **Email Threading**: Conversation tracking and threading
- **Reply & Forward**: Email responses with proper quoting and attachments
- **Flag Management**: Mark as read/unread, flagged, answered
- **IMAP & SMTP Support**: Full email protocol support

## Architecture

The system uses RabbitMQ RPC pattern with:
- `Abstract_Consumer` - Base consumer class
- `Abstract_Consumer_Rpc` - RPC-enabled consumer
- `Email_Consumer` - Email-specific RPC consumer
- `Email_Client` - IMAP/SMTP client implementation

## Installation

1. Ensure PHP IMAP extension is installed
2. Install PHPMailer via Composer (if not already included)
3. Configure RabbitMQ connection in `rabbitmq_config.php`
4. Run the consumer: `php rabbit_consume_command.php`

## Usage Examples

All operations are performed by sending JSON messages to the RabbitMQ queue. Each message must include a `function_to_call` parameter.

### Basic Email Operations

#### Get Email List
```json
{
  "function_to_call": "get_email_list",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "imap_port": 993,
  "imap_use_ssl": true,
  "folder": "INBOX",
  "criteria": "UNSEEN",
  "limit": 10,
  "offset": 0
}
```

#### Get Single Email
```json
{
  "function_to_call": "get_single_email",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "uid": 123
}
```

#### Send Email
```json
{
  "function_to_call": "send_email",
  "smtp_config": {
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_username": "user@gmail.com",
    "smtp_password": "password",
    "use_ssl": true
  },
  "to": "recipient@example.com",
  "subject": "Test Subject",
  "body": "<h1>Hello World</h1>",
  "from": "user@gmail.com",
  "headers": {
    "X-Custom-Header": "CustomValue"
  }
}
```

### Folder Management

#### Get Folders List
```json
{
  "function_to_call": "get_folders",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password"
}
```

#### Create Folder
```json
{
  "function_to_call": "create_folder",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "folder_name": "MyNewFolder"
}
```

#### Rename Folder
```json
{
  "function_to_call": "rename_folder",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "old_name": "OldFolder",
  "new_name": "NewFolder"
}
```

#### Delete Folder
```json
{
  "function_to_call": "delete_folder",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "folder_name": "FolderToDelete"
}
```

### Email Organization

#### Mark as Read/Unread
```json
{
  "function_to_call": "mark_as_read",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "uid": 123,
  "read": true
}
```

#### Mark as Flagged (Star)
```json
{
  "function_to_call": "mark_as_flagged",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "uid": 123,
  "flagged": true
}
```

#### Move Email to Folder
```json
{
  "function_to_call": "move_email",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "uid": 123,
  "target_folder": "Archive"
}
```

#### Copy Email to Folder
```json
{
  "function_to_call": "copy_email",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "uid": 123,
  "target_folder": "Backup"
}
```

#### Delete Email
```json
{
  "function_to_call": "delete_email",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "uid": 123,
  "expunge": false
}
```

### Advanced Search

#### Search Emails
```json
{
  "function_to_call": "advanced_search",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "search_params": {
    "from": "sender@example.com",
    "to": "recipient@example.com",
    "subject": "Important",
    "body": "meeting",
    "since": "2024-01-01",
    "before": "2024-12-31",
    "seen": false,
    "flagged": true,
    "answered": false
  }
}
```

### Email Communication

#### Reply to Email
```json
{
  "function_to_call": "reply_to_email",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "smtp_config": {
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_username": "user@gmail.com",
    "smtp_password": "password",
    "use_ssl": true
  },
  "uid": 123,
  "reply_body": "Thank you for your message. Here is my response...",
  "reply_all": false
}
```

#### Forward Email
```json
{
  "function_to_call": "forward_email",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "smtp_config": {
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_username": "user@gmail.com",
    "smtp_password": "password",
    "use_ssl": true
  },
  "uid": 123,
  "to_email": "colleague@example.com",
  "forward_message": "Please see the message below for your information."
}
```

#### Get Email Thread
```json
{
  "function_to_call": "get_email_thread",
  "imap_hostname": "imap.gmail.com",
  "imap_username": "user@gmail.com",
  "imap_password": "password",
  "uid": 123
}
```

## Response Format

All operations return a standardized JSON response:

### Success Response
```json
{
  "success": true,
  "data": { /* operation-specific data */ },
  "message": "Operation completed successfully"
}
```

### Error Response
```json
{
  "success": false,
  "errors": ["Error message 1", "Error message 2"]
}
```

## Configuration

### IMAP Settings
- `imap_hostname`: IMAP server hostname
- `imap_username`: Email username
- `imap_password`: Email password
- `imap_port`: IMAP port (default: 993)
- `imap_use_ssl`: Use SSL connection (default: true)

### SMTP Settings
- `smtp_host`: SMTP server hostname
- `smtp_port`: SMTP port (587 for TLS, 465 for SSL)
- `smtp_username`: SMTP username
- `smtp_password`: SMTP password
- `use_ssl`: Use SSL/TLS (true/false)

## Search Criteria

The `advanced_search` function supports these parameters:
- `from`: Sender email address
- `to`: Recipient email address
- `subject`: Subject line text
- `body`: Body text
- `since`: Date from (YYYY-MM-DD format)
- `before`: Date until (YYYY-MM-DD format)
- `seen`: Read status (true/false)
- `flagged`: Flagged status (true/false)
- `answered`: Answered status (true/false)

## Email Flags

The system supports standard IMAP flags:
- `\Seen`: Email has been read
- `\Flagged`: Email is flagged/starred
- `\Answered`: Email has been replied to
- `\Deleted`: Email is marked for deletion

## Threading

Email threading works by analyzing:
- Message-ID headers
- References headers
- In-Reply-To headers

This allows for proper conversation tracking similar to modern email clients.

## Error Handling

The system includes comprehensive error handling:
- Connection failures
- Authentication errors
- IMAP/SMTP protocol errors
- Invalid parameters
- Missing required fields

## Performance Notes

- Connections are established per operation and closed automatically
- UIDs are used for stable email referencing
- Large searches are handled efficiently
- Attachments are processed in memory (consider size limits)

## Security Considerations

- Always use SSL/TLS for connections
- Store credentials securely
- Validate all input parameters
- Monitor for suspicious activity
- Regular security updates

## Troubleshooting

### Common Issues

1. **Connection Failed**: Check hostname, port, and SSL settings
2. **Authentication Failed**: Verify username and password
3. **Folder Not Found**: Ensure folder exists and name is correct
4. **UID Not Found**: Email may have been deleted or moved
5. **SMTP Errors**: Check SMTP configuration and authentication

### Debug Mode

Enable debugging by checking IMAP errors:
```php
echo imap_last_error();
```

## License

This project is part of the webcargo_rabbitmq system. Refer to the main project license for terms and conditions.