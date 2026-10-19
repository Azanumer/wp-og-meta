<?php
/**
 * Plugin Name: WP OG Meta
 * Description: Lightweight Open Graph + Twitter Card tags with per-post overrides — proper link previews on Facebook, X, WhatsApp and LinkedIn. No schema.org (pair with a JSON-LD plugin), no bloat.
 * Version:     1.0.0
 * Author:      Azan Umer
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-og-meta
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WOGM_VERSION', '1.0.0' );
define( 'WOGM_OPTION', 'wogm_settings' );
define( 'WOGM_META_PREFIX', '_wogm_' );

/**
 * Settings merged with defaults.
 *
 * @return array
 */
function wogm_get_settings() {
    $defaults = array(
        'default_description' => '',
        'default_image'       => '', // full URL to fallback image (1200x630 recommended)
        'twitter_handle'      => '', // without @
        'facebook_app_id'     => '',
    );

    $saved = get_option( WOGM_OPTION, array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    return array_merge( $defaults, $saved );
}

/* ------------------------------------------------------------------------- *
 * Tag output
 * ------------------------------------------------------------------------- */

/**
 * Collect the meta values for the current page.
 *
 * @return array
 */
function wogm_collect() {
    $settings = wogm_get_settings();

    $data = array(
        'title'       => get_bloginfo( 'name' ),
        'description' => $settings['default_description'] !== '' ? $settings['default_description'] : get_bloginfo( 'description' ),
        'image'       => $settings['default_image'],
        'url'         => home_url( add_query_arg( null, null ) ),
        'type'        => 'website',
    );

    if ( is_singular() ) {
        $post = get_queried_object();
        if ( $post instanceof WP_Post ) {
            $override_title = get_post_meta( $post->ID, WOGM_META_PREFIX . 'title', true );
            $override_desc  = get_post_meta( $post->ID, WOGM_META_PREFIX . 'description', true );
            $override_image = get_post_meta( $post->ID, WOGM_META_PREFIX . 'image', true );

            $data['title'] = $override_title !== '' ? $override_title : get_the_title( $post );
            $data['type']  = 'article';

            if ( $override_desc !== '' ) {
                $data['description'] = $override_desc;
            } elseif ( has_excerpt( $post ) ) {
                $data['description'] = get_the_excerpt( $post );
            } else {
                // Fallback: first ~155 chars of the content, tags stripped.
                $text                = wp_strip_all_tags( $post->post_content );
                $text                = preg_replace( '/\s+/', ' ', $text );
                $data['description'] = mb_substr( trim( $text ), 0, 155 );
            }

            if ( $override_image !== '' ) {
                $data['image'] = $override_image;
            } elseif ( has_post_thumbnail( $post ) ) {
                $thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
                if ( is_array( $thumb ) ) {
                    $data['image'] = $thumb[0];
                }
            }

            $data['url'] = get_permalink( $post );
        }
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $term = get_queried_object();
        if ( $term instanceof WP_Term ) {
            $data['title'] = single_term_title( '', false );
            $desc          = term_description( $term );
            if ( '' !== $desc ) {
                $data['description'] = wp_trim_words( wp_strip_all_tags( $desc ), 25 );
            }
        }
    } elseif ( is_search() ) {
        $data['title'] = sprintf( 'Search results for "%s"', get_search_query() );
    } elseif ( is_404() ) {
        $data['title'] = 'Page not found';
    }

    /**
     * Filter the collected OG/Twitter values before output.
     *
     * @param array $data Keys: title, description, image, url, type.
     */
    return apply_filters( 'wogm_meta', $data );
}

/**
 * Print the tags in wp_head.
 */
function wogm_output_tags() {
    // Never on feeds, embeds, previews or REST responses.
    if ( is_feed() || is_embed() || is_preview() ) {
        return;
    }

    $data     = wogm_collect();
    $settings = wogm_get_settings();

    printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $data['title'] ) );
    printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $data['type'] ) );
    printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $data['url'] ) );
    printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );

    if ( '' !== trim( (string) $data['description'] ) ) {
        printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
        printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
    }

    if ( '' !== trim( (string) $data['image'] ) ) {
        printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $data['image'] ) );
        printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( $data['title'] ) );
    }

    if ( '' !== trim( $settings['facebook_app_id'] ) ) {
        printf( '<meta property="fb:app_id" content="%s" />' . "\n", esc_attr( $settings['facebook_app_id'] ) );
    }

    if ( is_singular() ) {
        $post = get_queried_object();
        if ( $post instanceof WP_Post ) {
            printf( '<meta property="article:published_time" content="%s" />' . "\n", esc_attr( get_the_date( 'c', $post ) ) );
            printf( '<meta property="article:modified_time" content="%s" />' . "\n", esc_attr( get_the_modified_date( 'c', $post ) ) );
        }
    }

    // Twitter cards.
    $card = '' !== trim( (string) $data['image'] ) ? 'summary_large_image' : 'summary';
    printf( '<meta name="twitter:card" content="%s" />' . "\n", esc_attr( $card ) );
    printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $data['title'] ) );
    if ( '' !== trim( (string) $data['description'] ) ) {
        printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
    }
    if ( '' !== trim( (string) $data['image'] ) ) {
        printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $data['image'] ) );
    }
    if ( '' !== trim( $settings['twitter_handle'] ) ) {
        $handle = '@' . ltrim( $settings['twitter_handle'], '@' );
        printf( '<meta name="twitter:site" content="%s" />' . "\n", esc_attr( $handle ) );
    }
}
add_action( 'wp_head', 'wogm_output_tags', 5 );

/* ------------------------------------------------------------------------- *
 * Per-post meta box
 * ------------------------------------------------------------------------- */

function wogm_add_meta_box() {
    $post_types = get_post_types( array( 'public' => true ), 'names' );
    foreach ( $post_types as $post_type ) {
        add_meta_box(
            'wogm_box',
            'OG / Twitter Preview',
            'wogm_render_meta_box',
            $post_type,
            'side',
            'default'
        );
    }
}
add_action( 'add_meta_boxes', 'wogm_add_meta_box' );

function wogm_render_meta_box( $post ) {
    wp_nonce_field( 'wogm_save', 'wogm_nonce' );

    $fields = array(
        'title'       => 'Custom title',
        'description' => 'Custom description',
        'image'       => 'Custom image URL',
    );

    foreach ( $fields as $key => $label ) {
        $value = get_post_meta( $post->ID, WOGM_META_PREFIX . $key, true );
        printf(
            '<p><label for="wogm_%1$s"><strong>%2$s</strong></label><br>
            <input type="text" id="wogm_%1$s" name="wogm_%1$s" value="%3$s" class="widefat" placeholder="Leave empty for auto"></p>',
            esc_attr( $key ),
            esc_html( $label ),
            esc_attr( $value )
        );
    }
    echo '<p class="description">Empty fields fall back to the post title, excerpt and featured image.</p>';
}

function wogm_save_meta_box( $post_id ) {
    if ( ! isset( $_POST['wogm_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wogm_nonce'] ), 'wogm_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    foreach ( array( 'title', 'description', 'image' ) as $key ) {
        $field = 'wogm_' . $key;
        if ( isset( $_POST[ $field ] ) ) {
            $value = 'image' === $key
                ? esc_url_raw( wp_unslash( $_POST[ $field ] ) )
                : sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
            if ( '' === $value ) {
                delete_post_meta( $post_id, WOGM_META_PREFIX . $key );
            } else {
                update_post_meta( $post_id, WOGM_META_PREFIX . $key, $value );
            }
        }
    }
}
add_action( 'save_post', 'wogm_save_meta_box' );

/* ------------------------------------------------------------------------- *
 * Settings page
 * ------------------------------------------------------------------------- */

function wogm_register_settings() {
    register_setting( 'wogm_group', WOGM_OPTION, 'wogm_sanitize' );
    add_settings_section( 'wogm_main', 'Defaults', '__return_null', 'wogm' );

    add_settings_field( 'default_description', 'Default description', 'wogm_text_field', 'wogm', 'wogm_main', array( 'key' => 'default_description' ) );
    add_settings_field( 'default_image', 'Default image URL (1200×630)', 'wogm_text_field', 'wogm', 'wogm_main', array( 'key' => 'default_image' ) );
    add_settings_field( 'twitter_handle', 'Twitter / X handle (no @)', 'wogm_text_field', 'wogm', 'wogm_main', array( 'key' => 'twitter_handle' ) );
    add_settings_field( 'facebook_app_id', 'Facebook App ID (optional)', 'wogm_text_field', 'wogm', 'wogm_main', array( 'key' => 'facebook_app_id' ) );
}
add_action( 'admin_init', 'wogm_register_settings' );

function wogm_sanitize( $input ) {
    $clean                        = array();
    $clean['default_description']  = isset( $input['default_description'] ) ? sanitize_text_field( $input['default_description'] ) : '';
    $clean['default_image']        = isset( $input['default_image'] ) ? esc_url_raw( $input['default_image'] ) : '';
    $clean['twitter_handle']       = isset( $input['twitter_handle'] ) ? sanitize_text_field( ltrim( $input['twitter_handle'], '@' ) ) : '';
    $clean['facebook_app_id']      = isset( $input['facebook_app_id'] ) ? sanitize_text_field( $input['facebook_app_id'] ) : '';
    return $clean;
}

function wogm_text_field( $args ) {
    $settings = wogm_get_settings();
    printf(
        '<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text">',
        esc_attr( WOGM_OPTION ),
        esc_attr( $args['key'] ),
        esc_attr( $settings[ $args['key'] ] )
    );
}

function wogm_add_menu() {
    add_options_page( 'OG Meta', 'OG Meta', 'manage_options', 'wogm', 'wogm_render_page' );
}
add_action( 'admin_menu', 'wogm_add_menu' );

function wogm_render_page() {
    ?>
    <div class="wrap">
        <h1>OG Meta</h1>
        <p>Site-wide defaults for Open Graph + Twitter Card tags. Individual posts and pages can override all three values from the <strong>OG / Twitter Preview</strong> box in the editor sidebar.</p>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'wogm_group' );
            do_settings_sections( 'wogm' );
            submit_button();
            ?>
        </form>
        <h2>Testing</h2>
        <p>Paste any URL into <a href="https://developers.facebook.com/tools/debug/" target="_blank" rel="noopener">Facebook Sharing Debugger</a> or the <a href="https://cards-dev.twitter.com/validator" target="_blank" rel="noopener">X Card Validator</a> to preview.</p>
    </div>
    <?php
}
