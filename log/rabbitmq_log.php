<?php

namespace tmwe_email\log;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Description of Rabbitmq_Log
 *
 * @author pepe
 */
class Rabbitmq_Log extends AbstractProcessingHandler {

    private bool $initialized = false;
    protected static $instance;
    protected $channel;
    protected $queue;

    /**
     * 
     * @return Rabbitmq_Log
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Rabbitmq_Log(\Monolog\Logger::INFO);
        }
        return self::$instance;
    }

    protected function write(array $record): void {
        $this->produce(json_encode($record));
    }

    public function __construct($level = Logger::DEBUG, bool $bubble = true) {
        parent::__construct($level, $bubble);

        $config = \tmwe_email\Rabbitmq_Config::get_instance();
        $this->queue = $config->get_log_queue();
        $this->connection = new AMQPStreamConnection($config->get_server(), $config->get_port(), $config->get_user(), $config->get_password());

        $this->channel = $this->connection->channel();

        $args = [];
        $this->channel->queue_declare($this->queue, false, true, false, false, false, $args);
    }

    public function close(): void {
        $this->channel->close();
        $this->connection->close();
    }

    public function produce($message, $priority = 1) {
        $msg = new AMQPMessage($message, array('priority' => $priority, 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $this->channel->basic_publish($msg, '', $this->queue);
    }
}
