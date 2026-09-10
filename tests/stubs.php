<?php
/**
 * Stubs WordPress minimaux pour les tests Givoly.
 *
 * Permet d'exécuter les tests de régression avec le seul binaire PHP,
 * sans WordPress, MySQL, Composer ni npm.
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'DB_NAME' ) ) {
    define( 'DB_NAME', 'wordpress_test' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}

// ── Options / transients en mémoire ──────────────────────────────────────

$GLOBALS['givoly_test_options']   = [];
$GLOBALS['givoly_test_transients'] = [];

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return array_key_exists( $option, $GLOBALS['givoly_test_options'] )
            ? $GLOBALS['givoly_test_options'][ $option ]
            : $default;
    }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        $GLOBALS['givoly_test_options'][ $option ] = $value;
        return true;
    }
}
if ( ! function_exists( 'add_option' ) ) {
    function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
        if ( ! array_key_exists( $option, $GLOBALS['givoly_test_options'] ) ) {
            $GLOBALS['givoly_test_options'][ $option ] = $value;
        }
        return true;
    }
}
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        unset( $GLOBALS['givoly_test_options'][ $option ] );
        return true;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        return $GLOBALS['givoly_test_transients'][ $key ] ?? false;
    }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration = 0 ) {
        $GLOBALS['givoly_test_transients'][ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['givoly_test_transients'][ $key ] );
        return true;
    }
}

// ── i18n ─────────────────────────────────────────────────────────────────

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) {
        return $text;
    }
}
if ( ! function_exists( '_n' ) ) {
    function _n( $single, $plural, $number, $domain = null ) {
        return (int) $number === 1 ? $single : $plural;
    }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = null ) {
        return $text;
    }
}
if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = null ) {
        echo $text;
    }
}
if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = null ) {
        return $text;
    }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = null ) {
        echo $text;
    }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return (string) $url;
    }
}
if ( ! function_exists( 'esc_sql' ) ) {
    function esc_sql( $text ) {
        return addslashes( (string) $text );
    }
}

// ── Nettoyage ────────────────────────────────────────────────────────────

if ( ! function_exists( 'absint' ) ) {
    function absint( $value ) {
        return abs( (int) $value );
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        if ( is_array( $value ) ) {
            return '';
        }
        $value = (string) $value;
        $value = strip_tags( $value );
        $value = trim( preg_replace( '/[\r\n\t ]+/', ' ', $value ) );
        return $value;
    }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
    }
}
if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $title ) {
        $title = strtolower( (string) $title );
        $title = preg_replace( '/[^a-z0-9]+/', '-', $title );
        return trim( $title, '-' );
    }
}
if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
    }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $value ) {
        return trim( strip_tags( (string) $value ) );
    }
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
    function sanitize_html_class( $class ) {
        return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
    }
}

// ── Divers WordPress ─────────────────────────────────────────────────────

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = false ) {
        if ( $type === 'mysql' ) {
            return gmdate( 'Y-m-d H:i:s' );
        }
        return time();
    }
}
if ( ! function_exists( 'number_format_i18n' ) ) {
    function number_format_i18n( $number, $decimals = 0 ) {
        return number_format( (float) $number, (int) $decimals, ',', ' ' );
    }
}
if ( ! function_exists( 'wp_timezone' ) ) {
    function wp_timezone() {
        return new DateTimeZone( 'UTC' );
    }
}
if ( ! function_exists( 'wp_date' ) ) {
    function wp_date( $format, $timestamp = null ) {
        return gmdate( $format, $timestamp ?? time() );
    }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $text ) {
        return strip_tags( (string) $text, '<p><br><strong><em><ul><ol><li><a>' );
    }
}
if ( ! function_exists( 'wp_get_attachment_image' ) ) {
    function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = [] ) {
        if ( ! $id ) {
            return '';
        }
        $class = isset( $attr['class'] ) ? ' class="' . htmlspecialchars( (string) $attr['class'], ENT_QUOTES ) . '"' : '';
        $alt   = isset( $attr['alt'] ) ? ' alt="' . htmlspecialchars( (string) $attr['alt'], ENT_QUOTES ) . '"' : ' alt=""';
        return '<img src="attachment-' . (int) $id . '.jpg"' . $class . $alt . '>';
    }
}
if ( ! function_exists( 'wp_attachment_is_image' ) ) {
    function wp_attachment_is_image( $id ) {
        return (int) $id > 0;
    }
}

// ── Faux $wpdb pour les tests ────────────────────────────────────────────

/**
 * Faux client MySQL minimal : enregistre les écritures et rejoue des lectures
 * programmées. Ne prétend pas remplacer un test WordPress/MySQL réel.
 */
class Givoly_Fake_Wpdb {

    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $insert_id = 0;

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $tables = [];

    /** @var array<int, array<string, mixed>> */
    public array $inserts = [];

    /** @var array<int, array<string, mixed>> */
    public array $updates = [];

    /** @var callable|null */
    public $get_var_handler = null;

    /** @var callable|null */
    public $get_results_handler = null;

    /** @var callable|null */
    public $get_row_handler = null;

    public bool $fail_next_write = false;

    public function prepare( $query, ...$args ) {
        // Substitution naïve mais déterministe des placeholders %s/%d.
        foreach ( $args as $arg ) {
            $replacement = is_int( $arg ) || ( is_string( $arg ) && preg_match( '/^-?\d+$/', $arg ) && str_contains( $query, '%d' ) )
                ? (string) (int) $arg
                : "'" . addslashes( (string) $arg ) . "'";
            $query = preg_replace( '/%[sd]/', $replacement, $query, 1 );
        }
        return $query;
    }

    public function esc_like( $text ) {
        return addcslashes( (string) $text, '_%\\' );
    }

    public function get_var( $query ) {
        if ( is_callable( $this->get_var_handler ) ) {
            return ( $this->get_var_handler )( $query, $this );
        }
        return null;
    }

    public function get_results( $query, $output = OBJECT ) {
        if ( is_callable( $this->get_results_handler ) ) {
            return ( $this->get_results_handler )( $query, $output, $this );
        }
        return [];
    }

    public function get_row( $query, $output = OBJECT ) {
        if ( is_callable( $this->get_row_handler ) ) {
            return ( $this->get_row_handler )( $query, $output, $this );
        }
        return null;
    }

    public function insert( $table, $data, $format = null ) {
        if ( $this->fail_next_write ) {
            $this->fail_next_write = false;
            $this->last_error      = 'Simulated write failure';
            return false;
        }
        // Détection de doublon sur la clé (gateway, gateway_transaction_id).
        if ( isset( $data['gateway'], $data['gateway_transaction_id'] ) ) {
            foreach ( $this->tables['donations'] ?? [] as $row ) {
                if ( ( $row['gateway'] ?? null ) === $data['gateway']
                    && ( $row['gateway_transaction_id'] ?? null ) === $data['gateway_transaction_id'] ) {
                    $this->last_error = "Duplicate entry for key 'uq_gateway_transaction'";
                    return false;
                }
            }
        }
        if ( isset( $data['stripe_subscription_id'] ) && $table === $this->prefix . 'givoly_subscriptions' ) {
            foreach ( $this->tables['subscriptions'] ?? [] as $row ) {
                if ( ( $row['stripe_subscription_id'] ?? null ) === $data['stripe_subscription_id'] ) {
                    $this->last_error = "Duplicate entry for key 'uq_stripe_subscription'";
                    return false;
                }
            }
        }
        $this->insert_id++;
        $data['id'] = $this->insert_id;
        $key        = $this->table_key( $table );
        $this->tables[ $key ][] = $data;
        $this->inserts[]         = [ 'table' => $table, 'data' => $data ];
        $this->last_error        = '';
        return 1;
    }

    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        if ( $this->fail_next_write ) {
            $this->fail_next_write = false;
            $this->last_error      = 'Simulated write failure';
            return false;
        }
        $this->updates[]  = [ 'table' => $table, 'data' => $data, 'where' => $where ];
        $this->last_error = '';
        return 1;
    }

    public function query( $query ) {
        if ( $this->fail_next_write ) {
            $this->fail_next_write = false;
            $this->last_error      = 'Simulated DDL failure';
            return false;
        }
        $this->last_error = '';
        return 1;
    }

    private function table_key( string $table ): string {
        if ( str_contains( $table, 'givoly_donations' ) ) {
            return 'donations';
        }
        if ( str_contains( $table, 'givoly_subscriptions' ) ) {
            return 'subscriptions';
        }
        if ( str_contains( $table, 'givoly_donors' ) ) {
            return 'donors';
        }
        return 'other';
    }
}

// ── Mini-framework d'assertions ──────────────────────────────────────────

$GLOBALS['givoly_test_total']  = 0;
$GLOBALS['givoly_test_failed'] = 0;

function givoly_assert( $condition, string $message ): void {
    $GLOBALS['givoly_test_total']++;
    if ( $condition ) {
        echo "  ok - {$message}\n";
    } else {
        $GLOBALS['givoly_test_failed']++;
        echo "  FAIL - {$message}\n";
    }
}

function givoly_assert_same( $expected, $actual, string $message ): void {
    $ok = $expected === $actual;
    givoly_assert( $ok, $message . ( $ok ? '' : ' (attendu ' . var_export( $expected, true ) . ', obtenu ' . var_export( $actual, true ) . ')' ) );
}
