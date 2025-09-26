<?php

namespace tmwe_email\rabbitmq;

/**
 * Description of abstract_consumer
 *
 * @author pepe
 */
abstract class Abstract_Consumer {

    protected static $instances;
    protected $connection;
    protected $channel;
    protected $queue;
    protected $config;

    /**
     * 
     * @param type $class
     * @return Abstract_Rabbitmq_Producer
     */
    public static function get_instance($class = '') {
        $class = !empty($class) ? $class : get_called_class();
        if (self::$instances == null || !isset(self::$instances[$class])) {
            if (self::$instances == null) {
                self::$instances = [];
            }
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    protected function __construct() {
        $this->config = \tmwe_email\Rabbitmq_Config::get_instance();
    }

    /**
     * 
     * @return AMQPStreamConnection
     */
    public function get_connection() {
        if ($this->connection == null) {
            try {
                $this->connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                        $this->config->get_server(),
                        $this->config->get_port(),
                        $this->config->get_user(),
                        $this->config->get_password()
                );
            } catch (\Exception $e) {
                \tmwe_email\log\Logger::get_instance()->log("Error al conectar a RabbitMQ: " . $e->getMessage());
            }
        }
        return $this->connection;
    }

    /**
     * 
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function get_channel() {
        if ($this->channel == null) {
            $this->channel = $this->get_connection()->channel();
        }
        return $this->channel;
    }

    public abstract function get_queue_name();

    protected function log_error($message) {
        \tmwe_email\log\Logger::get_instance()->error('Handle KO | ' . $message);
    }

    protected function log($message) {
        \tmwe_email\log\Logger::get_instance()->log($message);
    }

    public function get_queue() {
        if ($this->queue == null) {
            $args = ['x-max-priority' => ['I', 1]];
            $this->queue = $this->get_channel()->queue_declare(
                    $this->get_queue_name(),
                    false,
                    true, // Durable
                    false,
                    false,
                    false,
                    $args
            );
        }
        return $this->queue;
    }

    public function close_connection() {
        $this->get_connection()->close();
    }

    public function close_channel() {
        $this->get_channel()->close();
    }

    public function handle_request($message_amqp) {
        $message = $message_amqp->getBody();
        $this->log('Handled started | ' . $message);
        return json_decode($message, true);
    }

    public function consume() {
        $channel = $this->get_channel();

        $channel->basic_qos(null, 1, null);

        $callback = function (\PhpAmqpLib\Message\AMQPMessage $message_amqp) {
            // Obtener el delivery_tag
            $delivery_tag = $message_amqp->get('delivery_tag');
            // Procesar el mensaje
            $this->handle_request($message_amqp);
            //Enviar ACK manual
            $message_amqp->delivery_info['channel']->basic_ack($delivery_tag);
            $this->log('Handled OK');
        };
        
        $channel->basic_consume($this->get_queue_name(), '', false, false, false, false, $callback);
        //$channel->basic_consume($this->get_queue_name(), '', false, true, false, false, $callback);

        try {
            $channel->consume();
        } catch (\Throwable $exception) {
            $this->log_error($exception->getMessage());
        } catch (\Exception $exception) {
            $this->log_error($exception->getMessage());
        }

        $this->channel->close();
        $this->connection->close();
    }
}
