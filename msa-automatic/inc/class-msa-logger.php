<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Logger {
    /**
     * Instance of this class
     * @var MSA_Logger
     */
    private static $instance = null;

    /**
     * Log levels
     * @var array
     */
    private $levels = array(
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    );

    /**
     * Current minimum log level
     * @var string
     */
    private $min_level = 'info';

    /**
     * Database table name
     * @var string
     */
    private $table_name;

    /**
     * Get instance of this class
     *
     * @return MSA_Logger
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'msa_logs';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'create_tables'));
        add_action('msa_cleanup_logs', array($this, 'cleanup_logs'));

        // Set minimum log level based on debug mode
        $this->min_level = msa_settings_manager()->get_setting('general', 'debug_mode')
            ? 'debug'
            : 'info';
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            campaign_id bigint(20),
            PRIMARY KEY  (id),
            KEY level (level),
            KEY timestamp (timestamp),
            KEY campaign_id (campaign_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Additional context
     * @return bool Whether the message was logged
     */
    public function log($level, $message, $context = array()) {
        if (!$this->should_log($level)) {
            return false;
        }

        global $wpdb;

        $data = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => maybe_serialize($context),
            'campaign_id' => isset($context['campaign_id']) ? $context['campaign_id'] : null
        );

        $result = $wpdb->insert($this->table_name, $data);

        if (false === $result) {
            error_log(sprintf(
                'MSA Logger: Failed to write log entry: %s - %s',
                $message,
                $wpdb->last_error
            ));
            return false;
        }

        return true;
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function debug($message, $context = array()) {
        $this->log('debug', $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function info($message, $context = array()) {
        $this->log('info', $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
    }

    /**
     * Log a critical message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function critical($message, $context = array()) {
        $this->log('critical', $message, $context);
    }

    /**
     * Get logs
     *
     * @param array $args Query arguments
     * @return array Array of log entries
     */
    public function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'level' => null,
            'campaign_id' => null,
            'start_date' => null,
            'end_date' => null,
            'search' => null,
            'orderby' => 'timestamp',
            'order' => 'DESC',
            'per_page' => 50,
            'page' => 1,
            'count' => false
        );

        $args = wp_parse_args($args, $defaults);
        $where = array('1=1');
        $values = array();

        if ($args['level']) {
            $where[] = 'level = %s';
            $values[] = $args['level'];
        }

        if ($args['campaign_id']) {
            $where[] = 'campaign_id = %d';
            $values[] = $args['campaign_id'];
        }

        if ($args['start_date']) {
            $where[] = 'timestamp >= %s';
            $values[] = $args['start_date'];
        }

        if ($args['end_date']) {
            $where[] = 'timestamp <= %s';
            $values[] = $args['end_date'];
        }

        if ($args['search']) {
            $where[] = 'message LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $where = 'WHERE ' . implode(' AND ', $where);

        if ($args['count']) {
            $sql = "SELECT COUNT(*) FROM {$this->table_name} $where";
            return $wpdb->get_var($wpdb->prepare($sql, $values));
        }

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = $args['per_page'];
        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "SELECT * FROM {$this->table_name} 
                $where 
                ORDER BY $orderby 
                LIMIT %d OFFSET %d";

        $values[] = $limit;
        $values[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        foreach ($results as &$result) {
            $result['context'] = maybe_unserialize($result['context']);
        }

        return $results;
    }

    /**
     * Clear logs
     *
     * @param string $level Log level (optional)
     * @return int Number of logs deleted
     */
    public function clear_logs($level = null) {
        global $wpdb;

        if ($level && isset($this->levels[$level])) {
            return $wpdb->delete($this->table_name, array('level' => $level));
        }

        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * Clean up old logs
     */
    public function cleanup_logs() {
        global $wpdb;

        $retention = msa_settings_manager()->get_setting('general', 'log_retention', 30);
        $date = date('Y-m-d H:i:s', strtotime("-{$retention} days"));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE timestamp < %s",
            $date
        ));
    }

    /**
     * Export logs
     *
     * @param array $args Query arguments
     * @return string CSV content
     */
    public function export_logs($args = array()) {
        $logs = $this->get_logs($args);
        $output = fopen('php://temp', 'r+');

        // Add headers
        fputcsv($output, array(
            'ID',
            'Timestamp',
            'Level',
            'Message',
            'Campaign ID',
            'Context'
        ));

        // Add data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['id'],
                $log['timestamp'],
                $log['level'],
                $log['message'],
                $log['campaign_id'],
                is_array($log['context']) ? json_encode($log['context']) : $log['context']
            ));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Check if message should be logged
     *
     * @param string $level Log level
     * @return bool Whether message should be logged
     */
    private function should_log($level) {
        return $this->levels[$level] >= $this->levels[$this->min_level];
    }

    /**
     * Get available log levels
     *
     * @return array Log levels
     */
    public function get_levels() {
        return array_keys($this->levels);
    }

    /**
     * Get log level name
     *
     * @param string $level Log level
     * @return string Level name
     */
    public function get_level_name($level) {
        $names = array(
            'debug' => __('Debug', 'msa-automatic'),
            'info' => __('Info', 'msa-automatic'),
            'warning' => __('Warning', 'msa-automatic'),
            'error' => __('Error', 'msa-automatic'),
            'critical' => __('Critical', 'msa-automatic')
        );

        return isset($names[$level]) ? $names[$level] : $level;
    }

    /**
     * Get log level color
     *
     * @param string $level Log level
     * @return string CSS color class
     */
    public function get_level_color($level) {
        $colors = array(
            'debug' => 'gray',
            'info' => 'blue',
            'warning' => 'orange',
            'error' => 'red',
            'critical' => 'purple'
        );

        return isset($colors[$level]) ? $colors[$level] : 'gray';
    }
}

/**
 * Get instance of logger
 *
 * @return MSA_Logger
 */
function msa_logger() {
    return MSA_Logger::get_instance();
}
