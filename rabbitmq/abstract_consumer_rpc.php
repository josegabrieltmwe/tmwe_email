<?php

namespace tmwe_email\rabbitmq;

/**
 * Description of abstract_consumer
 *
 * @author pepe
 */
abstract class Abstract_Consumer_Rpc extends Abstract_Consumer {

    protected $callback_queue;
    
    abstract public function handle_rpc_request($json, $message_amqp);

    public function handle_request($message_amqp) {
        $json = parent::handle_request($message_amqp);
        $response =  $this->handle_rpc_request($json, $message_amqp);
        $this->send_rpc_response($response, $message_amqp);
    }

    protected function send_rpc_response($to_send, \PhpAmqpLib\Message\AMQPMessage $requested_amqp_message) {
        $this->log($requested_amqp_message->get('correlation_id'));
        $this->log($requested_amqp_message->get('reply_to'));
        $this->log($requested_amqp_message->get('delivery_tag'));
        
        $msg = new \PhpAmqpLib\Message\AMQPMessage(
                is_array($to_send) ? json_encode($to_send, JSON_UNESCAPED_UNICODE) : $to_send,
                [
            'correlation_id' => $requested_amqp_message->get('correlation_id')
                ]
        );

        $channel = $this->get_channel();

        $channel->basic_publish($msg, '', $requested_amqp_message->get('reply_to'));

        $this->log('RESPONSE SENT |'.$msg->getBody());
        //$channel->basic_ack($requested_amqp_message->get('delivery_tag'));
    }
}
