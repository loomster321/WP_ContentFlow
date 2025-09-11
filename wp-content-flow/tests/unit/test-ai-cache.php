<?php
/**
 * Unit Tests: AI Cache System
 * 
 * Tests the AI cache management system including multiple backends,
 * cache invalidation, and performance optimizations.
 *
 * @package WP_Content_Flow
 * @subpackage Tests\Unit
 */

class Test_AI_Cache extends WP_UnitTestCase {
    
    /**
     * Cache instance
     */
    private $original_settings;
    
    /**
     * Set up test environment
     */
    public function setUp() {
        parent::setUp();
        
        // Store original settings
        $this->original_settings = get_option( 'wp_content_flow_settings' );
        
        // Enable caching for tests
        update_option( 'wp_content_flow_settings', array(
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'cache_backend' => 'transients'
        ) );
        
        // Clear any existing cache
        WP_Content_Flow_AI_Cache::flush();
        
        // Reset stats
        $reflection = new ReflectionClass( 'WP_Content_Flow_AI_Cache' );
        $stats_property = $reflection->getProperty( 'stats' );
        $stats_property->setAccessible( true );
        $stats_property->setValue( null, array(
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0
        ) );
    }
    
    /**
     * Tear down test environment
     */
    public function tearDown() {
        // Restore original settings
        if ( $this->original_settings !== false ) {
            update_option( 'wp_content_flow_settings', $this->original_settings );
        } else {
            delete_option( 'wp_content_flow_settings' );
        }
        
        // Clear cache
        WP_Content_Flow_AI_Cache::flush();
        
        parent::tearDown();
    }
    
    /**
     * Test cache key generation
     */
    public function test_generate_cache_key() {
        $operation = 'generate';
        $data = array(
            'prompt' => 'Test prompt',
            'provider' => 'openai',
            'parameters' => array(
                'temperature' => 0.7,
                'max_tokens' => 100
            )
        );
        
        $key1 = WP_Content_Flow_AI_Cache::generate_key( $operation, $data );
        
        // Key should be consistent
        $key2 = WP_Content_Flow_AI_Cache::generate_key( $operation, $data );
        $this->assertEquals( $key1, $key2 );
        
        // Key should change with different data
        $data['prompt'] = 'Different prompt';
        $key3 = WP_Content_Flow_AI_Cache::generate_key( $operation, $data );
        $this->assertNotEquals( $key1, $key3 );
        
        // Key should include operation
        $this->assertContains( 'generate', $key1 );
        
        // Key should be valid length
        $this->assertLessThanOrEqual( 250, strlen( $key1 ) );
    }
    
    /**
     * Test basic cache operations
     */
    public function test_cache_set_and_get() {
        $key = 'test_cache_key';
        $value = array(
            'content' => 'Test content',
            'metadata' => array( 'tokens' => 100 )
        );
        
        // Set cache value
        $result = WP_Content_Flow_AI_Cache::set( $key, $value );
        $this->assertTrue( $result );
        
        // Get cache value
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNotNull( $cached );
        $this->assertEquals( $value, $cached['value'] );
        
        // Verify metadata
        $this->assertArrayHasKey( 'created', $cached );
        $this->assertArrayHasKey( 'expires', $cached );
        $this->assertGreaterThan( time(), $cached['expires'] );
    }
    
    /**
     * Test cache miss
     */
    public function test_cache_miss() {
        $key = 'non_existent_key';
        
        $value = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNull( $value );
        
        // Check stats
        $stats = WP_Content_Flow_AI_Cache::get_stats();
        $this->assertEquals( 0, $stats['hits'] );
        $this->assertEquals( 1, $stats['misses'] );
    }
    
    /**
     * Test cache hit
     */
    public function test_cache_hit() {
        $key = 'test_hit_key';
        $value = 'test_value';
        
        WP_Content_Flow_AI_Cache::set( $key, $value );
        
        // Reset stats
        $this->reset_cache_stats();
        
        // Get cached value
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNotNull( $cached );
        
        // Check stats
        $stats = WP_Content_Flow_AI_Cache::get_stats();
        $this->assertEquals( 1, $stats['hits'] );
        $this->assertEquals( 0, $stats['misses'] );
    }
    
    /**
     * Test cache expiration
     */
    public function test_cache_expiration() {
        $key = 'test_expiry_key';
        $value = 'test_value';
        
        // Set with 1 second expiration
        WP_Content_Flow_AI_Cache::set( $key, $value, 1 );
        
        // Should be available immediately
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNotNull( $cached );
        
        // Wait for expiration
        sleep( 2 );
        
        // Should be expired
        $expired = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNull( $expired );
    }
    
    /**
     * Test cache deletion
     */
    public function test_cache_delete() {
        $key = 'test_delete_key';
        $value = 'test_value';
        
        // Set cache
        WP_Content_Flow_AI_Cache::set( $key, $value );
        
        // Verify it exists
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNotNull( $cached );
        
        // Delete cache
        $result = WP_Content_Flow_AI_Cache::delete( $key );
        $this->assertTrue( $result );
        
        // Verify it's deleted
        $deleted = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNull( $deleted );
        
        // Check stats
        $stats = WP_Content_Flow_AI_Cache::get_stats();
        $this->assertEquals( 1, $stats['deletes'] );
    }
    
    /**
     * Test cache flush
     */
    public function test_cache_flush() {
        // Set multiple cache entries
        WP_Content_Flow_AI_Cache::set( 'key1', 'value1' );
        WP_Content_Flow_AI_Cache::set( 'key2', 'value2' );
        WP_Content_Flow_AI_Cache::set( 'key3', 'value3' );
        
        // Flush all cache
        $result = WP_Content_Flow_AI_Cache::flush();
        $this->assertTrue( $result );
        
        // Verify all entries are deleted
        $this->assertNull( WP_Content_Flow_AI_Cache::get( 'key1' ) );
        $this->assertNull( WP_Content_Flow_AI_Cache::get( 'key2' ) );
        $this->assertNull( WP_Content_Flow_AI_Cache::get( 'key3' ) );
    }
    
    /**
     * Test cache disabled
     */
    public function test_cache_disabled() {
        // Disable cache
        update_option( 'wp_content_flow_settings', array(
            'cache_enabled' => false
        ) );
        
        $key = 'test_disabled_key';
        $value = 'test_value';
        
        // Try to set cache
        $result = WP_Content_Flow_AI_Cache::set( $key, $value );
        $this->assertFalse( $result );
        
        // Try to get cache
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNull( $cached );
    }
    
    /**
     * Test cache statistics
     */
    public function test_cache_statistics() {
        // Perform various cache operations
        WP_Content_Flow_AI_Cache::set( 'stat_key1', 'value1' );
        WP_Content_Flow_AI_Cache::set( 'stat_key2', 'value2' );
        WP_Content_Flow_AI_Cache::get( 'stat_key1' ); // Hit
        WP_Content_Flow_AI_Cache::get( 'stat_key2' ); // Hit
        WP_Content_Flow_AI_Cache::get( 'missing_key' ); // Miss
        WP_Content_Flow_AI_Cache::delete( 'stat_key1' );
        
        $stats = WP_Content_Flow_AI_Cache::get_stats();
        
        $this->assertEquals( 2, $stats['hits'] );
        $this->assertEquals( 1, $stats['misses'] );
        $this->assertEquals( 2, $stats['writes'] );
        $this->assertEquals( 1, $stats['deletes'] );
        
        // Check hit ratio
        $expected_ratio = round( ( 2 / 3 ) * 100, 2 );
        $this->assertEquals( $expected_ratio, $stats['hit_ratio'] );
    }
    
    /**
     * Test transient backend
     */
    public function test_transient_backend() {
        update_option( 'wp_content_flow_settings', array(
            'cache_enabled' => true,
            'cache_backend' => 'transients'
        ) );
        
        // Reinitialize cache
        WP_Content_Flow_AI_Cache::init();
        
        $key = 'transient_test_key';
        $value = 'transient_value';
        
        WP_Content_Flow_AI_Cache::set( $key, $value );
        
        // Verify transient was created
        $transient_key = 'wp_content_flow_ai_generate_' . md5( $key );
        $transient_value = get_transient( $transient_key );
        $this->assertNotFalse( $transient_value );
    }
    
    /**
     * Test object cache backend
     */
    public function test_object_cache_backend() {
        if ( ! wp_using_ext_object_cache() ) {
            $this->markTestSkipped( 'External object cache not available' );
        }
        
        update_option( 'wp_content_flow_settings', array(
            'cache_enabled' => true,
            'cache_backend' => 'object_cache'
        ) );
        
        // Reinitialize cache
        WP_Content_Flow_AI_Cache::init();
        
        $key = 'object_cache_test_key';
        $value = 'object_cache_value';
        
        WP_Content_Flow_AI_Cache::set( $key, $value );
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        
        $this->assertNotNull( $cached );
        $this->assertEquals( $value, $cached['value'] );
    }
    
    /**
     * Test cache key filtering
     */
    public function test_cache_key_filtering() {
        // Test with long key
        $long_key = str_repeat( 'a', 300 );
        $filtered = WP_Content_Flow_AI_Cache::filter_cache_key( $long_key, 'test' );
        
        $this->assertLessThanOrEqual( 250, strlen( $filtered ) );
        $this->assertContains( md5( $long_key ), $filtered );
        
        // Test with special characters
        $special_key = 'key with spaces & special@chars!';
        $filtered = WP_Content_Flow_AI_Cache::filter_cache_key( $special_key, 'test' );
        
        $this->assertNotContains( ' ', $filtered );
        $this->assertNotContains( '@', $filtered );
        $this->assertNotContains( '!', $filtered );
    }
    
    /**
     * Test cache warmup
     */
    public function test_cache_warmup() {
        $warmup_data = array(
            array(
                'key' => 'warmup_key1',
                'value' => 'warmup_value1'
            ),
            array(
                'key' => 'warmup_key2',
                'value' => 'warmup_value2',
                'expiration' => 7200
            )
        );
        
        // Add warmup data filter
        add_filter( 'wp_content_flow_cache_warmup_data', function() use ( $warmup_data ) {
            return $warmup_data;
        } );
        
        // Run warmup
        WP_Content_Flow_AI_Cache::warmup_cache();
        
        // Verify data was cached
        $cached1 = WP_Content_Flow_AI_Cache::get( 'warmup_key1' );
        $this->assertNotNull( $cached1 );
        $this->assertEquals( 'warmup_value1', $cached1['value'] );
        
        $cached2 = WP_Content_Flow_AI_Cache::get( 'warmup_key2' );
        $this->assertNotNull( $cached2 );
        $this->assertEquals( 'warmup_value2', $cached2['value'] );
    }
    
    /**
     * Test concurrent cache access
     */
    public function test_concurrent_cache_access() {
        $key = 'concurrent_key';
        $value = 'concurrent_value';
        
        // Simulate concurrent writes
        WP_Content_Flow_AI_Cache::set( $key, $value . '_1' );
        WP_Content_Flow_AI_Cache::set( $key, $value . '_2' );
        
        // Last write should win
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertEquals( $value . '_2', $cached['value'] );
    }
    
    /**
     * Test cache validation
     */
    public function test_cache_validation() {
        $key = 'validation_key';
        
        // Set invalid cache data directly (simulating corruption)
        set_transient( $key, 'invalid_data_not_array', 3600 );
        
        // Should return null for invalid data
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNull( $cached );
        
        // Set cache data missing required fields
        set_transient( $key, array( 'value' => 'test' ), 3600 );
        
        // Should return null for incomplete data
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        $this->assertNull( $cached );
    }
    
    /**
     * Test cache with complex data types
     */
    public function test_cache_complex_data() {
        $key = 'complex_key';
        $complex_data = array(
            'string' => 'test string',
            'number' => 123,
            'float' => 45.67,
            'boolean' => true,
            'null' => null,
            'array' => array( 1, 2, 3 ),
            'nested' => array(
                'level1' => array(
                    'level2' => array(
                        'deep' => 'value'
                    )
                )
            ),
            'object' => (object) array( 'prop' => 'value' )
        );
        
        WP_Content_Flow_AI_Cache::set( $key, $complex_data );
        $cached = WP_Content_Flow_AI_Cache::get( $key );
        
        $this->assertNotNull( $cached );
        $this->assertEquals( $complex_data, $cached['value'] );
    }
    
    /**
     * Helper: Reset cache statistics
     */
    private function reset_cache_stats() {
        $reflection = new ReflectionClass( 'WP_Content_Flow_AI_Cache' );
        $stats_property = $reflection->getProperty( 'stats' );
        $stats_property->setAccessible( true );
        $stats_property->setValue( null, array(
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0
        ) );
    }
}