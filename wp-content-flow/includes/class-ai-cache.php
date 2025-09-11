<?php
/**
 * AI Cache Management Class
 * 
 * Handles caching of AI responses with support for multiple cache backends
 * including WordPress transients, Redis, Memcached, and object cache.
 *
 * @package WP_Content_Flow
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Cache class for managing cached AI responses
 */
class WP_Content_Flow_AI_Cache {
    
    /**
     * Cache group name
     * @var string
     */
    const CACHE_GROUP = 'wp_content_flow_ai';
    
    /**
     * Default cache expiration (1 hour)
     * @var int
     */
    const DEFAULT_EXPIRATION = 3600;
    
    /**
     * Cache backend instance
     * @var object
     */
    private static $cache_backend = null;
    
    /**
     * Cache statistics
     * @var array
     */
    private static $stats = array(
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    );
    
    /**
     * Initialize the cache system
     */
    public static function init() {
        // Determine and initialize cache backend
        self::initialize_backend();
        
        // Register cache management hooks
        add_action( 'wp_content_flow_cache_cleanup', array( __CLASS__, 'cleanup_expired' ) );
        add_action( 'wp_content_flow_cache_warmup', array( __CLASS__, 'warmup_cache' ) );
        add_filter( 'wp_content_flow_cache_key', array( __CLASS__, 'filter_cache_key' ), 10, 2 );
        
        // Schedule cache maintenance
        if ( ! wp_next_scheduled( 'wp_content_flow_cache_cleanup' ) ) {
            wp_schedule_event( time(), 'hourly', 'wp_content_flow_cache_cleanup' );
        }
    }
    
    /**
     * Initialize cache backend based on availability
     */
    private static function initialize_backend() {
        $settings = get_option( 'wp_content_flow_settings', array() );
        $cache_backend = $settings['cache_backend'] ?? 'auto';
        
        switch ( $cache_backend ) {
            case 'redis':
                if ( self::is_redis_available() ) {
                    self::$cache_backend = new WP_Content_Flow_Redis_Cache();
                    break;
                }
                // Fall through if Redis not available
                
            case 'memcached':
                if ( self::is_memcached_available() ) {
                    self::$cache_backend = new WP_Content_Flow_Memcached_Cache();
                    break;
                }
                // Fall through if Memcached not available
                
            case 'object_cache':
                if ( wp_using_ext_object_cache() ) {
                    self::$cache_backend = new WP_Content_Flow_Object_Cache();
                    break;
                }
                // Fall through if no external object cache
                
            case 'transients':
            case 'auto':
            default:
                self::$cache_backend = new WP_Content_Flow_Transient_Cache();
                break;
        }
        
        // Log cache backend selection
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Content Flow: Using cache backend - ' . get_class( self::$cache_backend ) );
        }
    }
    
    /**
     * Check if Redis is available
     * @return bool
     */
    private static function is_redis_available() {
        if ( ! class_exists( 'Redis' ) && ! class_exists( 'Predis\Client' ) ) {
            return false;
        }
        
        try {
            if ( class_exists( 'Redis' ) ) {
                $redis = new Redis();
                return $redis->connect( '127.0.0.1', 6379, 1 );
            } elseif ( class_exists( 'Predis\Client' ) ) {
                $redis = new Predis\Client();
                $redis->ping();
                return true;
            }
        } catch ( Exception $e ) {
            return false;
        }
        
        return false;
    }
    
    /**
     * Check if Memcached is available
     * @return bool
     */
    private static function is_memcached_available() {
        if ( ! class_exists( 'Memcached' ) && ! class_exists( 'Memcache' ) ) {
            return false;
        }
        
        try {
            if ( class_exists( 'Memcached' ) ) {
                $memcached = new Memcached();
                $memcached->addServer( 'localhost', 11211 );
                return count( $memcached->getServerList() ) > 0;
            } elseif ( class_exists( 'Memcache' ) ) {
                $memcache = new Memcache();
                return @$memcache->connect( 'localhost', 11211 );
            }
        } catch ( Exception $e ) {
            return false;
        }
        
        return false;
    }
    
    /**
     * Generate cache key for AI request
     * 
     * @param string $operation Operation type (generate, improve, etc.)
     * @param array $data Request data
     * @return string Cache key
     */
    public static function generate_key( $operation, $data ) {
        // Sort data for consistent key generation
        ksort( $data );
        
        // Create base key
        $key_data = array(
            'operation' => $operation,
            'data' => $data,
            'version' => WP_CONTENT_FLOW_VERSION
        );
        
        // Apply filters for custom key generation
        $key_data = apply_filters( 'wp_content_flow_cache_key_data', $key_data, $operation );
        
        // Generate hash
        $hash = md5( serialize( $key_data ) );
        
        // Create readable key with prefix
        $key = sprintf( '%s_%s_%s', self::CACHE_GROUP, $operation, $hash );
        
        // Apply final filter
        return apply_filters( 'wp_content_flow_cache_key', $key, $operation );
    }
    
    /**
     * Get cached response
     * 
     * @param string $key Cache key
     * @return mixed|null Cached data or null
     */
    public static function get( $key ) {
        if ( ! self::is_enabled() ) {
            return null;
        }
        
        $value = self::$cache_backend->get( $key );
        
        if ( $value !== false ) {
            self::$stats['hits']++;
            
            // Track cache hit
            do_action( 'wp_content_flow_cache_hit', $key, $value );
            
            // Validate cached data
            if ( self::validate_cached_data( $value ) ) {
                return $value;
            }
        }
        
        self::$stats['misses']++;
        do_action( 'wp_content_flow_cache_miss', $key );
        
        return null;
    }
    
    /**
     * Set cache value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Expiration time in seconds
     * @return bool Success
     */
    public static function set( $key, $value, $expiration = null ) {
        if ( ! self::is_enabled() ) {
            return false;
        }
        
        if ( $expiration === null ) {
            $settings = get_option( 'wp_content_flow_settings', array() );
            $expiration = $settings['cache_ttl'] ?? self::DEFAULT_EXPIRATION;
        }
        
        // Add metadata to cached value
        $cache_data = array(
            'value' => $value,
            'created' => time(),
            'expires' => time() + $expiration,
            'key' => $key
        );
        
        $result = self::$cache_backend->set( $key, $cache_data, $expiration );
        
        if ( $result ) {
            self::$stats['writes']++;
            do_action( 'wp_content_flow_cache_set', $key, $value, $expiration );
        }
        
        return $result;
    }
    
    /**
     * Delete cache entry
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    public static function delete( $key ) {
        $result = self::$cache_backend->delete( $key );
        
        if ( $result ) {
            self::$stats['deletes']++;
            do_action( 'wp_content_flow_cache_delete', $key );
        }
        
        return $result;
    }
    
    /**
     * Clear all cache
     * 
     * @return bool Success
     */
    public static function flush() {
        $result = self::$cache_backend->flush();
        
        if ( $result ) {
            do_action( 'wp_content_flow_cache_flush' );
        }
        
        return $result;
    }
    
    /**
     * Check if caching is enabled
     * 
     * @return bool
     */
    public static function is_enabled() {
        $settings = get_option( 'wp_content_flow_settings', array() );
        return ! empty( $settings['cache_enabled'] );
    }
    
    /**
     * Validate cached data
     * 
     * @param mixed $data Cached data
     * @return bool
     */
    private static function validate_cached_data( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }
        
        // Check required fields
        if ( ! isset( $data['value'], $data['created'], $data['expires'] ) ) {
            return false;
        }
        
        // Check expiration
        if ( $data['expires'] < time() ) {
            return false;
        }
        
        // Additional validation
        return apply_filters( 'wp_content_flow_validate_cache', true, $data );
    }
    
    /**
     * Cleanup expired cache entries
     */
    public static function cleanup_expired() {
        self::$cache_backend->cleanup();
        do_action( 'wp_content_flow_cache_cleanup_complete' );
    }
    
    /**
     * Warmup cache with common requests
     */
    public static function warmup_cache() {
        $warmup_data = apply_filters( 'wp_content_flow_cache_warmup_data', array() );
        
        foreach ( $warmup_data as $item ) {
            if ( isset( $item['key'], $item['value'] ) ) {
                self::set( $item['key'], $item['value'], $item['expiration'] ?? null );
            }
        }
        
        do_action( 'wp_content_flow_cache_warmup_complete', count( $warmup_data ) );
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public static function get_stats() {
        $stats = self::$stats;
        
        // Calculate hit ratio
        $total_requests = $stats['hits'] + $stats['misses'];
        $stats['hit_ratio'] = $total_requests > 0 
            ? round( ( $stats['hits'] / $total_requests ) * 100, 2 ) 
            : 0;
        
        // Get backend info
        $stats['backend'] = get_class( self::$cache_backend );
        $stats['enabled'] = self::is_enabled();
        
        // Get size if available
        if ( method_exists( self::$cache_backend, 'get_size' ) ) {
            $stats['size'] = self::$cache_backend->get_size();
        }
        
        return apply_filters( 'wp_content_flow_cache_stats', $stats );
    }
    
    /**
     * Filter cache key
     * 
     * @param string $key Original key
     * @param string $operation Operation type
     * @return string Filtered key
     */
    public static function filter_cache_key( $key, $operation ) {
        // Ensure key length is within limits
        if ( strlen( $key ) > 250 ) {
            $key = substr( $key, 0, 200 ) . '_' . md5( $key );
        }
        
        // Sanitize key
        $key = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $key );
        
        return $key;
    }
}

/**
 * Transient-based cache backend
 */
class WP_Content_Flow_Transient_Cache {
    
    public function get( $key ) {
        return get_transient( $key );
    }
    
    public function set( $key, $value, $expiration ) {
        return set_transient( $key, $value, $expiration );
    }
    
    public function delete( $key ) {
        return delete_transient( $key );
    }
    
    public function flush() {
        global $wpdb;
        
        // Delete all transients with our prefix
        $prefix = '_transient_' . WP_Content_Flow_AI_Cache::CACHE_GROUP;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( $prefix ) . '%'
        ) );
        
        return true;
    }
    
    public function cleanup() {
        // WordPress handles transient cleanup automatically
        return true;
    }
}

/**
 * WordPress Object Cache backend
 */
class WP_Content_Flow_Object_Cache {
    
    public function get( $key ) {
        return wp_cache_get( $key, WP_Content_Flow_AI_Cache::CACHE_GROUP );
    }
    
    public function set( $key, $value, $expiration ) {
        return wp_cache_set( $key, $value, WP_Content_Flow_AI_Cache::CACHE_GROUP, $expiration );
    }
    
    public function delete( $key ) {
        return wp_cache_delete( $key, WP_Content_Flow_AI_Cache::CACHE_GROUP );
    }
    
    public function flush() {
        return wp_cache_flush();
    }
    
    public function cleanup() {
        // Object cache handles its own cleanup
        return true;
    }
    
    public function get_size() {
        // Try to get cache size if available
        if ( function_exists( 'wp_cache_get_stats' ) ) {
            $stats = wp_cache_get_stats();
            return $stats['bytes'] ?? 0;
        }
        return 0;
    }
}

/**
 * Redis cache backend
 */
class WP_Content_Flow_Redis_Cache {
    
    private $redis;
    private $prefix;
    
    public function __construct() {
        $this->prefix = WP_Content_Flow_AI_Cache::CACHE_GROUP . ':';
        
        if ( class_exists( 'Redis' ) ) {
            $this->redis = new Redis();
            $this->redis->connect( '127.0.0.1', 6379 );
        } elseif ( class_exists( 'Predis\Client' ) ) {
            $this->redis = new Predis\Client();
        }
    }
    
    public function get( $key ) {
        try {
            $value = $this->redis->get( $this->prefix . $key );
            return $value ? unserialize( $value ) : false;
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    public function set( $key, $value, $expiration ) {
        try {
            return $this->redis->setex(
                $this->prefix . $key,
                $expiration,
                serialize( $value )
            );
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    public function delete( $key ) {
        try {
            return $this->redis->del( $this->prefix . $key ) > 0;
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    public function flush() {
        try {
            $keys = $this->redis->keys( $this->prefix . '*' );
            if ( ! empty( $keys ) ) {
                return $this->redis->del( $keys ) > 0;
            }
            return true;
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    public function cleanup() {
        // Redis handles expiration automatically
        return true;
    }
    
    public function get_size() {
        try {
            $info = $this->redis->info( 'memory' );
            return $info['used_memory'] ?? 0;
        } catch ( Exception $e ) {
            return 0;
        }
    }
}

/**
 * Memcached cache backend
 */
class WP_Content_Flow_Memcached_Cache {
    
    private $memcached;
    private $prefix;
    
    public function __construct() {
        $this->prefix = WP_Content_Flow_AI_Cache::CACHE_GROUP . ':';
        
        if ( class_exists( 'Memcached' ) ) {
            $this->memcached = new Memcached();
            $this->memcached->addServer( 'localhost', 11211 );
        } elseif ( class_exists( 'Memcache' ) ) {
            $this->memcached = new Memcache();
            $this->memcached->connect( 'localhost', 11211 );
        }
    }
    
    public function get( $key ) {
        return $this->memcached->get( $this->prefix . $key );
    }
    
    public function set( $key, $value, $expiration ) {
        if ( $this->memcached instanceof Memcached ) {
            return $this->memcached->set( $this->prefix . $key, $value, $expiration );
        } else {
            return $this->memcached->set( $this->prefix . $key, $value, 0, $expiration );
        }
    }
    
    public function delete( $key ) {
        return $this->memcached->delete( $this->prefix . $key );
    }
    
    public function flush() {
        // Note: This flushes entire Memcached, not just our keys
        return $this->memcached->flush();
    }
    
    public function cleanup() {
        // Memcached handles expiration automatically
        return true;
    }
    
    public function get_size() {
        if ( $this->memcached instanceof Memcached ) {
            $stats = $this->memcached->getStats();
            $server_stats = reset( $stats );
            return $server_stats['bytes'] ?? 0;
        }
        return 0;
    }
}

// Initialize cache system
add_action( 'init', array( 'WP_Content_Flow_AI_Cache', 'init' ) );