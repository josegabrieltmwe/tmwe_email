<?php

namespace tmwe_email\rabbitmq\email;

/**
 * Description of Email_Hypervisor_Consumer
 *
 * @author pepe
 */
class Email_Hypervisor_Consumer extends \tmwe_email\rabbitmq\Abstract_Consumer {

    public function get_queue_name() {
        return 'email_hypervisor_queue';
    }

    public function handle_request($message_amqp) {
        $json = parent::handle_request($message_amqp);

        try {
            $this->process_email_supervision($json);
        } catch (\Exception $e) {
            $this->log_error('Error processing email supervision: ' . $e->getMessage());
        }
    }

    /**
     * Procesa la supervisión de emails según las reglas recibidas
     *
     * Expected $json payload:
     * [
     *     "imap_hostname": "mail.example.com",
     *     "imap_username": "user@example.com",
     *     "imap_password": "password",
     *     "imap_port": 993,
     *     "imap_use_ssl": true,
     *     "imap_use_tls": false,
     *     "date_from": "2024-01-01", // Fecha desde la cual revisar correos
     *     "rules": [
     *         [
     *             "name": "Rule 1",
     *             "conditions": [
     *                 ["field": "from", "operator": "contains", "value": "example@domain.com"],
     *                 ["field": "subject", "operator": "contains", "value": "Invoice"]
     *             ],
     *             "actions": [
     *                 ["type": "move", "target_folder": "Archive"],
     *                 ["type": "mark_as_read"]
     *             ]
     *         ]
     *     ]
     * ]
     */
    protected function process_email_supervision($json) {
        extract($json);

        $email_client = \tmwe_email\service\email\Email_Client::get_instance();
        $rule_service = \tmwe_email\service\email\Email_Rule_Service::get_instance();

        try {
            // Conectar al servidor IMAP
            $email_client->connect(
                $imap_hostname,
                $imap_username,
                $imap_password,
                isset($imap_port) ? $imap_port : 993,
                isset($imap_use_ssl) ? $imap_use_ssl : true,
                isset($imap_use_tls) ? $imap_use_tls : false
            );

            $this->log('IMAP connection established successfully');

            // Construir criterio de búsqueda basado en la fecha
            $criteria = 'ALL';
            if (isset($date_from) && !empty($date_from)) {
                $date = new \DateTime($date_from);
                $criteria = 'SINCE "' . $date->format('d-M-Y') . '"';
            }

            $this->log("Searching emails with criteria: $criteria");

            // Obtener correos desde la fecha especificada
            $folder = isset($folder) ? $folder : 'INBOX';
            $email_list = $email_client->get_emails($folder, $criteria, 0, 1000);

            $this->log('Found ' . count($email_list) . ' emails to process');

            // Validar que se recibieron reglas
            if (!isset($rules) || !is_array($rules) || empty($rules)) {
                $this->log_error('No rules provided for email supervision');
                return;
            }

            $this->log('Processing ' . count($rules) . ' rules');

            // Procesar cada correo con cada regla
            $processed_count = 0;
            foreach ($email_list as $email) {
                foreach ($rules as $rule) {
                    if ($rule_service->evaluate_rule($email, $rule)) {
                        $this->log("Email UID {$email['uid']} matches rule: {$rule['name']}");
                        $this->send_to_rule_apply_queue($email, $rule, $json);
                        $processed_count++;
                    }
                }
            }

            $this->log("Email supervision completed. Processed $processed_count email-rule matches");

        } catch (\Exception $e) {
            $this->log_error('Email supervision failed: ' . $e->getMessage());
            throw $e;
        } finally {
            if ($email_client->is_connected()) {
                $email_client->disconnect();
            }
        }
    }

    /**
     * Envía información del correo y acciones a ejecutar a la cola secundaria
     *
     * @param array $email Datos del correo
     * @param array $rule Regla que se cumplió
     * @param array $original_params Parámetros originales de conexión
     */
    protected function send_to_rule_apply_queue($email, $rule, $original_params) {
        try {
            $channel = $this->get_channel();

            // Preparar el mensaje con la información del correo y las acciones a ejecutar
            $message_data = [
                'email' => $email,
                'rule' => $rule,
                'actions' => isset($rule['actions']) ? $rule['actions'] : [],
                'imap_connection' => [
                    'imap_hostname' => $original_params['imap_hostname'],
                    'imap_username' => $original_params['imap_username'],
                    'imap_password' => $original_params['imap_password'],
                    'imap_port' => isset($original_params['imap_port']) ? $original_params['imap_port'] : 993,
                    'imap_use_ssl' => isset($original_params['imap_use_ssl']) ? $original_params['imap_use_ssl'] : true,
                    'imap_use_tls' => isset($original_params['imap_use_tls']) ? $original_params['imap_use_tls'] : false
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $message_json = json_encode($message_data, JSON_UNESCAPED_UNICODE);

            // Declarar la cola si no existe
            $queue_name = 'email_rule_apply_queue';
            $args = ['x-max-priority' => ['I', 1]];
            $channel->queue_declare(
                $queue_name,
                false,
                true, // Durable
                false,
                false,
                false,
                $args
            );

            // Crear el mensaje AMQP
            $msg = new \PhpAmqpLib\Message\AMQPMessage(
                $message_json,
                ['delivery_mode' => 2] // Hacer el mensaje persistente
            );

            // Publicar el mensaje en la cola
            $channel->basic_publish($msg, '', $queue_name);

            $this->log("Message sent to $queue_name for email UID: {$email['uid']}");

        } catch (\Exception $e) {
            $this->log_error('Failed to send message to email_rule_apply_queue: ' . $e->getMessage());
            throw $e;
        }
    }
}
