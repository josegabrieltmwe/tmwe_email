<?php

namespace tmwe_email\service\email;

/**
 * Service for evaluating email rules and conditions
 *
 * @author pepe
 */
class Email_Rule_Service extends \tmwe_email\service\Abstract_Service {

    /**
     * @return Email_Rule_Service
     */
    public static function get_instance() {
        return parent::get_instance();
    }

    /**
     * Evalúa si un correo cumple con una regla específica
     *
     * @param array $email Datos del correo
     * @param array $rule Regla a evaluar
     * @return bool True si el correo cumple la regla
     */
    public function evaluate_rule($email, $rule) {
        // Validar que la regla tenga condiciones
        if (!isset($rule['conditions']) || !is_array($rule['conditions']) || empty($rule['conditions'])) {
            $this->log_fail('Rule does not have valid conditions');
            return false;
        }

        // Todas las condiciones deben cumplirse (AND lógico)
        foreach ($rule['conditions'] as $condition) {
            if (!$this->evaluate_condition($email, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evalúa múltiples reglas contra un correo
     *
     * @param array $email Datos del correo
     * @param array $rules Array de reglas a evaluar
     * @return array Array de reglas que cumplió el correo
     */
    public function evaluate_multiple_rules($email, $rules) {
        $matched_rules = [];

        if (!is_array($rules) || empty($rules)) {
            $this->log_fail('No valid rules provided for evaluation');
            return $matched_rules;
        }

        foreach ($rules as $rule) {
            if ($this->evaluate_rule($email, $rule)) {
                $matched_rules[] = $rule;
            }
        }

        return $matched_rules;
    }

    /**
     * Evalúa una condición individual contra un correo
     *
     * @param array $email Datos del correo
     * @param array $condition Condición a evaluar
     * @return bool True si la condición se cumple
     */
    protected function evaluate_condition($email, $condition) {
        if (!isset($condition['field']) || !isset($condition['operator']) || !isset($condition['value'])) {
            $this->log_fail('Condition is missing required fields (field, operator, value)');
            return false;
        }

        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Obtener el valor del campo del email
        $email_value = $this->get_email_field_value($email, $field);

        if ($email_value === null) {
            return false;
        }

        // Evaluar según el operador
        return $this->apply_operator($email_value, $operator, $value);
    }

    /**
     * Aplica un operador de comparación entre dos valores
     *
     * @param mixed $email_value Valor del campo del email
     * @param string $operator Operador a aplicar
     * @param mixed $value Valor a comparar
     * @return bool Resultado de la comparación
     */
    protected function apply_operator($email_value, $operator, $value) {
        switch ($operator) {
            case 'equals':
                return strcasecmp($email_value, $value) === 0;

            case 'not_equals':
                return strcasecmp($email_value, $value) !== 0;

            case 'contains':
                return stripos($email_value, $value) !== false;

            case 'not_contains':
                return stripos($email_value, $value) === false;

            case 'starts_with':
                return stripos($email_value, $value) === 0;

            case 'ends_with':
                return stripos($email_value, $value) === (strlen($email_value) - strlen($value));

            case 'regex':
                return @preg_match($value, $email_value) === 1;

            case 'greater_than':
                return $email_value > $value;

            case 'less_than':
                return $email_value < $value;

            case 'greater_than_or_equal':
                return $email_value >= $value;

            case 'less_than_or_equal':
                return $email_value <= $value;

            case 'in':
                if (is_array($value)) {
                    return in_array($email_value, $value);
                }
                return false;

            case 'not_in':
                if (is_array($value)) {
                    return !in_array($email_value, $value);
                }
                return false;

            case 'is_empty':
                return empty($email_value);

            case 'is_not_empty':
                return !empty($email_value);

            default:
                $this->log_fail("Unknown operator: $operator");
                return false;
        }
    }

    /**
     * Obtiene el valor de un campo específico del correo
     *
     * @param array $email Datos del correo
     * @param string $field Nombre del campo
     * @return mixed Valor del campo o null si no existe
     */
    protected function get_email_field_value($email, $field) {
        switch ($field) {
            case 'from':
                return isset($email['from']) ? $email['from'] : null;

            case 'to':
                return isset($email['to']) ? $email['to'] : null;

            case 'subject':
                return isset($email['subject']) ? $email['subject'] : null;

            case 'body':
                return isset($email['body']) ? $email['body'] : null;

            case 'date':
                return isset($email['date']) ? $email['date'] : null;

            case 'cc':
                return isset($email['cc']) ? $email['cc'] : null;

            case 'bcc':
                return isset($email['bcc']) ? $email['bcc'] : null;

            case 'has_attachments':
                return isset($email['has_attachments']) ? $email['has_attachments'] : false;

            case 'is_seen':
                return isset($email['is_seen']) ? $email['is_seen'] : false;

            case 'is_flagged':
                return isset($email['is_flagged']) ? $email['is_flagged'] : false;

            case 'is_answered':
                return isset($email['is_answered']) ? $email['is_answered'] : false;

            case 'is_draft':
                return isset($email['is_draft']) ? $email['is_draft'] : false;

            case 'size':
                return isset($email['size']) ? $email['size'] : null;

            case 'uid':
                return isset($email['uid']) ? $email['uid'] : null;

            case 'message_id':
                return isset($email['message_id']) ? $email['message_id'] : null;

            case 'in_reply_to':
                return isset($email['in_reply_to']) ? $email['in_reply_to'] : null;

            case 'references':
                return isset($email['references']) ? $email['references'] : null;

            default:
                // Intentar obtener el campo directamente del array
                return isset($email[$field]) ? $email[$field] : null;
        }
    }

    /**
     * Valida que una regla tenga la estructura correcta
     *
     * @param array $rule Regla a validar
     * @return array Array con 'valid' (bool) y 'errors' (array de mensajes)
     */
    public function validate_rule($rule) {
        $errors = [];

        if (!is_array($rule)) {
            $errors[] = 'Rule must be an array';
            return ['valid' => false, 'errors' => $errors];
        }

        // Validar nombre de la regla
        if (!isset($rule['name']) || empty($rule['name'])) {
            $errors[] = 'Rule must have a name';
        }

        // Validar condiciones
        if (!isset($rule['conditions']) || !is_array($rule['conditions']) || empty($rule['conditions'])) {
            $errors[] = 'Rule must have at least one condition';
        } else {
            foreach ($rule['conditions'] as $index => $condition) {
                $condition_errors = $this->validate_condition($condition);
                if (!empty($condition_errors)) {
                    $errors[] = "Condition $index: " . implode(', ', $condition_errors);
                }
            }
        }

        // Validar acciones (opcional)
        if (isset($rule['actions'])) {
            if (!is_array($rule['actions'])) {
                $errors[] = 'Actions must be an array';
            } else if (empty($rule['actions'])) {
                $errors[] = 'If actions are provided, at least one action must be specified';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Valida que una condición tenga la estructura correcta
     *
     * @param array $condition Condición a validar
     * @return array Array de mensajes de error (vacío si es válida)
     */
    protected function validate_condition($condition) {
        $errors = [];

        if (!is_array($condition)) {
            $errors[] = 'Condition must be an array';
            return $errors;
        }

        // Validar campo
        if (!isset($condition['field']) || empty($condition['field'])) {
            $errors[] = 'Condition must have a field';
        }

        // Validar operador
        if (!isset($condition['operator']) || empty($condition['operator'])) {
            $errors[] = 'Condition must have an operator';
        } else {
            $valid_operators = [
                'equals', 'not_equals', 'contains', 'not_contains',
                'starts_with', 'ends_with', 'regex',
                'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal',
                'in', 'not_in', 'is_empty', 'is_not_empty'
            ];

            if (!in_array($condition['operator'], $valid_operators)) {
                $errors[] = "Invalid operator: {$condition['operator']}";
            }
        }

        // Validar valor (algunos operadores no requieren valor)
        $operators_without_value = ['is_empty', 'is_not_empty'];
        if (!isset($condition['value']) && !in_array($condition['operator'], $operators_without_value)) {
            $errors[] = 'Condition must have a value';
        }

        return $errors;
    }
}
