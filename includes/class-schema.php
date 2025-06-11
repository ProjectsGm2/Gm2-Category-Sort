<?php
class Gm2_Category_Sort_Schema {
    private static $schema_data = null;

    /**
     * Store categories and hook output in wp_head.
     *
     * @param array $categories Array of WP_Term objects.
     */
    public static function set_categories( $categories ) {
        self::$schema_data = self::build_schema( $categories );
        if ( ! has_action( 'wp_head', [ __CLASS__, 'output_schema' ] ) ) {
            add_action( 'wp_head', [ __CLASS__, 'output_schema' ] );
        }
    }

    /**
     * Build ItemList schema data from terms.
     *
     * @param array $categories Array of WP_Term objects.
     * @return array Schema data.
     */
    public static function build_schema( $categories ) {
        $items    = [];
        $position = 1;
        foreach ( $categories as $cat ) {
            if ( ! $cat || is_wp_error( $cat ) ) {
                continue;
            }
            $items[] = [
                '@type'   => 'ListItem',
                'position'=> $position++,
                'name'    => $cat->name,
                'id'      => $cat->term_id,
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Echo the JSON-LD schema.
     */
    public static function output_schema() {
        if ( empty( self::$schema_data ) ) {
            return;
        }
        echo '<script type="application/ld+json">' . wp_json_encode( self::$schema_data ) . '</script>' . "\n";
        self::$schema_data = null;
    }
}
