<?php
/**
 * Plugin Name: FAU-Studienangebot
 * Description: Studienangebotsverwaltung.
 * Version: 1.0
 * Author: Rolf v. d. Forst
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */
/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action('plugins_loaded', array('FAU_Studienangebot', 'instance'));

register_activation_hook(__FILE__, array('FAU_Studienangebot', 'activate'));
register_deactivation_hook(__FILE__, array('FAU_Studienangebot', 'deactivate'));

class FAU_Studienangebot {

    protected $version = '1.0';

    const post_type = 'studienangebot';
    const capability_type = 'studienangebot';
    const author_role = 'studienangebot_author';
    const editor_role = 'studienangebot_editor';
    
    public static $taxonomies = array(
        'studiengang',
        'semester',
        'abschluss',
        'faechergruppe',
        'fakultaet',
        'studienort',
        'saattribut',
        'satag'
    );

    protected static $instance = null;

    const textdomain = 'studienangebot';
    const php_version = '5.3'; // Minimal erforderliche PHP-Version
    const wp_version = '4.0'; // Minimal erforderliche WordPress-Version

    public static function instance() {

        if (null == self::$instance) {
            self::$instance = new self;
            self::$instance->init();
        }

        return self::$instance;
    }

    private function init() {
        define('SA_SETTINGS_ROOT', dirname(__FILE__));
        define('SA_SETTINGS_FILE_PATH', CMS_SETTINGS_ROOT . '/' . basename(__FILE__));
        define('SA_SETTINGS_URL', plugins_url('/', __FILE__));
        define('SA_TEXTDOMAIN', self::textdomain);

        // register post type
        add_action('init', array($this, 'register_post_type_studienangebot'));

        // register taxonomies
        add_action('init', array($this, 'register_taxonomy_studiengang'));
        add_action('init', array($this, 'register_taxonomy_abschluss'));
        add_action('init', array($this, 'register_taxonomy_semester'));
        add_action('init', array($this, 'register_taxonomy_studienort'));
        add_action('init', array($this, 'register_taxonomy_faechergruppe'));
        add_action('init', array($this, 'register_taxonomy_fakultaet'));
        add_action('init', array($this, 'register_taxonomy_saattribut'));
        add_action('init', array($this, 'register_taxonomy_sazvs'));
        add_action('init', array($this, 'register_taxonomy_saconstant'));
        add_action('init', array($this, 'register_taxonomy_satag'));

        // register the options
        add_action('admin_init', array($this, 'settings_init'));

        // Rename "featured image"
        add_action('admin_head-post-new.php', array($this, 'change_thumbnail_html'));
        add_action('admin_head-post.php', array($this, 'change_thumbnail_html'));

        // add a shortcode
        add_shortcode('studienangebot', array($this, 'create_shortcode'));

        // do i18n stuff
        add_action('plugins_loaded', array($this, 'setup_i18n'));

        // add term meta field
        add_action('abschluss_add_form_fields', array($this, 'abschluss_add_new_meta_field'), 10, 2);
        add_action('abschluss_edit_form_fields', array($this, 'abschluss_edit_meta_field'), 10, 2);
        add_action('edited_abschluss', array($this, 'save_abschluss_custom_meta'));
        add_action('create_abschluss', array($this, 'save_abschluss_custom_meta'));
        add_action('delete_abschluss', array($this, 'delete_abschluss_custom_meta'));
        
        add_action('sazvs_add_form_fields', array($this, 'sazvs_add_new_meta_field'), 10, 2);
        add_action('sazvs_edit_form_fields', array($this, 'sazvs_edit_meta_field'), 10, 2);
        add_action('edited_sazvs', array($this, 'save_sazvs_custom_meta'));
        add_action('create_sazvs', array($this, 'save_sazvs_custom_meta'));
        add_action('delete_sazvs', array($this, 'delete_sazvs_custom_meta'));
        
        add_action('saconstant_add_form_fields', array($this, 'saconstant_add_new_meta_field'), 10, 2);
        add_action('saconstant_edit_form_fields', array($this, 'saconstant_edit_meta_field'), 10, 2);
        add_action('edited_saconstant', array($this, 'save_saconstant_custom_meta'));
        add_action('create_saconstant', array($this, 'save_saconstant_custom_meta'));
        add_action('delete_saconstant', array($this, 'delete_saconstant_custom_meta'));
        
        // custom term columns
        add_filter('manage_edit-studienangebot_columns', array($this, 'term_columns'));
        add_filter('manage_edit-abschluss_columns', array($this, 'term_columns'));
        add_filter('manage_edit-faechergruppe_columns', array($this, 'term_columns'));
        add_filter('manage_edit-fakultaet_columns', array($this, 'term_columns'));
        add_filter('manage_edit-saattribut_columns', array($this, 'term_columns'));
        add_filter('manage_edit-sazvs_columns', array($this, 'term_columns'));
        add_filter('manage_edit-saconstant_columns', array($this, 'term_columns'));
        add_filter('manage_edit-semester_columns', array($this, 'term_columns'));
        add_filter('manage_edit-studiengang_columns', array($this, 'term_columns'));
        add_filter('manage_edit-studienort_columns', array($this, 'term_columns'));
                     
        add_filter('manage_abschluss_custom_column', array($this, 'abschluss_custom_column'), 15, 3);
        add_filter('manage_edit-abschluss_columns', array($this, 'abschluss_columns'));
        add_filter('manage_sazvs_custom_column', array($this, 'sazvs_custom_column'), 15, 3);
        add_filter('manage_edit-sazvs_columns', array($this, 'sazvs_columns'));        
        add_filter('manage_saconstant_custom_column', array($this, 'saconstant_custom_column'), 15, 3);
        add_filter('manage_edit-saconstant_columns', array($this, 'saconstant_columns'));

        // hide term fields
        add_action('admin_head', array($this, 'hide_term_fields'));
        
        // add rewrite endpoint
        add_action('init', array(__CLASS__, 'add_rewrite_endpoint'));
        add_action('permalink_structure_changed', array(__CLASS__, 'add_rewrite_endpoint'));
        
        // include custom post type template
        add_filter('template_include', array($this, 'include_studiengang_template'));
        add_filter('the_content', array($this, 'studiengang_content'));
                
        // initialize Meta Boxes
        add_action('add_meta_boxes', array($this, 'set_meta_boxes'));
        add_action('init', array($this, 'initialize_meta_boxes'), 999);

        // include Meta Boxes
        include_once(plugin_dir_path(__FILE__) . 'includes/metaboxes.php');
        
        // include Shortcodes
        include_once(plugin_dir_path(__FILE__) . 'includes/shortcodes/studienangebot.php');
        include_once(plugin_dir_path(__FILE__) . 'includes/shortcodes/studiengaenge.php'); 
    }

    public static function add_rewrite_endpoint() {
        add_rewrite_endpoint('studiengang', EP_ROOT | EP_PAGES);
    }
    
    public function setup_i18n() {
        load_plugin_textdomain(self::textdomain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    private static function version_compare() {
        $error = '';

        if (version_compare(PHP_VERSION, self::php_version, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain), PHP_VERSION, self::php_version);
        }

        if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain), $GLOBALS['wp_version'], self::wp_version);
        }

        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), false, true);
            wp_die($error);
        }
    }

    public static function activate($network_wide) {
        self::version_compare();

        $administrator_role = get_role('administrator');
        
        if ($administrator_role) {
            $administrator_role->add_cap("edit_" . self::capability_type . "");
            $administrator_role->add_cap("read_" . self::capability_type . "");
            $administrator_role->add_cap("delete_" . self::capability_type . "");
            $administrator_role->add_cap("edit_" . self::capability_type . "s");
            $administrator_role->add_cap("edit_others_" . self::capability_type . "s");
            $administrator_role->add_cap("publish_" . self::capability_type . "s");
            $administrator_role->add_cap("read_private_" . self::capability_type . "s");
            $administrator_role->add_cap("delete_" . self::capability_type . "s");
            $administrator_role->add_cap("delete_private_" . self::capability_type . "s");
            $administrator_role->add_cap("delete_published_" . self::capability_type . "s");
            $administrator_role->add_cap("delete_others_" . self::capability_type . "s");
            $administrator_role->add_cap("edit_private_" . self::capability_type . "s");
            $administrator_role->add_cap("edit_published_" . self::capability_type . "s");
        }
        
        $author_role = get_role('author');
        
        if ($author_role) {
            $capabilities = $author_role->capabilities;
            $capabilities["edit_" . self::capability_type . ""] = true;
            $capabilities["read_" . self::capability_type . ""] = true;
            $capabilities["delete_" . self::capability_type . ""] = true;
            $capabilities["edit_" . self::capability_type . "s"] = true;
            $capabilities["edit_others_" . self::capability_type . "s"] = true;
            $capabilities["publish_" . self::capability_type . "s"] = true;
            $capabilities["read_private_" . self::capability_type . "s"] = true;
            $capabilities["delete_" . self::capability_type . "s"] = true;
            $capabilities["delete_private_" . self::capability_type . "s"] = true;
            $capabilities["delete_published_" . self::capability_type . "s"] = true;
            $capabilities["delete_others_" . self::capability_type . "s"] = true;
            $capabilities["edit_private_" . self::capability_type . "s"] = true;
            $capabilities["edit_published_" . self::capability_type . "s"] = true;
                        
            add_role(self::author_role, __('Studienangebotautor', self::textdomain), $capabilities);
        }
                    
        self::add_rewrite_endpoint();
        flush_rewrite_rules();
    }

    public static function deactivate($network_wide) {
        $administrator_role = get_role('administrator');
        
        if ($administrator_role) {
            $administrator_role->remove_cap("edit_" . self::capability_type . "");
            $administrator_role->remove_cap("read_" . self::capability_type . "");
            $administrator_role->remove_cap("delete_" . self::capability_type . "");
            $administrator_role->remove_cap("edit_" . self::capability_type . "s");
            $administrator_role->remove_cap("edit_others_" . self::capability_type . "s");
            $administrator_role->remove_cap("publish_" . self::capability_type . "s");
            $administrator_role->remove_cap("read_private_" . self::capability_type . "s");
            $administrator_role->remove_cap("delete_" . self::capability_type . "s");
            $administrator_role->remove_cap("delete_private_" . self::capability_type . "s");
            $administrator_role->remove_cap("delete_published_" . self::capability_type . "s");
            $administrator_role->remove_cap("delete_others_" . self::capability_type . "s");
            $administrator_role->remove_cap("edit_private_" . self::capability_type . "s");
            $administrator_role->remove_cap("edit_published_" . self::capability_type . "s");
        }
        
        remove_role(self::author_role);
        
        flush_rewrite_rules();
    }
    
    public function register_post_type_studienangebot() {
        $supports = array('title', 'author', 'thumbnail', 'revisions');
        
        $args = array(
            'labels' => array(
                'name' => __('Studienangebot', self::textdomain),
                'singular_name' => __('Studienangebot', self::textdomain),
                'menu_name' => __('Studienangebot', self::textdomain),
                'all_items' => 'Studienangebot',
                'add_new' => __('Erstellen', self::textdomain),
                'add_new_item' => __('Neues Studienangebot erstellen', self::textdomain),
                'edit_item' => __('Studienangebot bearbeiten', self::textdomain),
                'new_item' => __('Neues Studienangebot', self::textdomain),
                'view_item' => __('Studienangebot ansehen', self::textdomain),
                'search_items' => __('Studienangebot suchen', self::textdomain),
                'not_found' => __('Kein Studienangebot gefunden.', self::textdomain),
                'not_found_in_trash' => __('Kein Studienangebot im Papierkorb gefunden.', self::textdomain),
            ),
            'description' => 'Studienangebot',
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'exclude_from_search' => false,
            'show_in_nav_menus' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'capability_type' => self::capability_type,
            'capabilities' => array(
                'edit_post' => "edit_" . self::capability_type . "",
                'read_post' => "read_" . self::capability_type . "",
                'delete_post' => "delete_" . self::capability_type . "",
                'edit_posts' => "edit_" . self::capability_type . "s",
                'edit_others_posts' => "edit_others_" . self::capability_type . "s",
                'publish_posts' => "publish_" . self::capability_type . "s",
                'read_private_posts' => "read_private_" . self::capability_type . "s",
                'delete_posts' => "delete_" . self::capability_type . "s",
                'delete_private_posts' => "delete_private_" . self::capability_type . "s",
                'delete_published_posts' => "delete_published_" . self::capability_type . "s",
                'delete_others_posts' => "delete_others_" . self::capability_type . "s",
                'edit_private_posts' => "edit_private_" . self::capability_type . "s",
                'edit_published_posts' => "edit_published_" . self::capability_type . "s",                
            ),
            'map_meta_cap' => true,
            'supports' => $supports,
            'taxonomies' => array(implode(',', self::$taxonomies)),
            'has_archive' => false,
            'rewrite' => array( 'slug' => 'studiengang', 'with_front' => false),
            'query_var' => true,
            'can_export' => true,
        );

        register_post_type(self::post_type, $args);
    }

    public function register_taxonomy_studiengang() {
        register_taxonomy('studiengang', array(self::post_type), array(
            'label' => __('Studiengänge', self::textdomain),
            'labels' => array(
                'name' => __('Studiengänge', self::textdomain),
                'singular_name' => __('Studiengang', self::textdomain),
                'menu_name' => __('Studiengänge', self::textdomain),
                'all_items' => __('Alle Studiengänge', self::textdomain),
                'edit_item' => __('Studiengang bearbeiten', self::textdomain),
                'update_item' => __('Studiengänge aktualisieren', self::textdomain),
                'add_new_item' => __('Neuen Studiengang hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Studiengänge suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => true,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'studiengang',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }

    public function register_taxonomy_abschluss() {
        register_taxonomy('abschluss', array(self::post_type), array(
            'label' => __('Abschlüsse', self::textdomain),
            'labels' => array(
                'name' => __('Abschlüsse', self::textdomain),
                'singular_name' => __('Abschluss', self::textdomain),
                'menu_name' => __('Abschlüsse', self::textdomain),
                'all_items' => __('Alle Abschlüsse', self::textdomain),
                'edit_item' => __('Abschluss bearbeiten', self::textdomain),
                'update_item' => __('Abschlüsse aktualisieren', self::textdomain),
                'add_new_item' => __('Neuen Abschluss hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Abschlüsse suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => true,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'abschluss',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }

    public function register_taxonomy_semester() {
        register_taxonomy('semester', array(self::post_type), array(
            'label' => __('Semester', self::textdomain),
            'labels' => array(
                'name' => __('Semester', self::textdomain),
                'singular_name' => __('Semester', self::textdomain),
                'menu_name' => __('Semester', self::textdomain),
                'all_items' => __('Alle Semester', self::textdomain),
                'edit_item' => __('Semester bearbeiten', self::textdomain),
                'update_item' => __('Semester aktualisieren', self::textdomain),
                'add_new_item' => __('Neuen Semester hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Semester suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => true,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'semester',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }

    public function register_taxonomy_studienort() {
        register_taxonomy('studienort', array(self::post_type), array(
            'label' => __('Orte', self::textdomain),
            'labels' => array(
                'name' => __('Orte', self::textdomain),
                'singular_name' => __('Ort', self::textdomain),
                'menu_name' => __('Orte', self::textdomain),
                'all_items' => __('Alle Orte', self::textdomain),
                'edit_item' => __('Ort bearbeiten', self::textdomain),
                'update_item' => __('Ort aktualisieren', self::textdomain),
                'add_new_item' => __('Neuen Ort hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Ort suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => true,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'studienort',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }

    public function register_taxonomy_faechergruppe() {
        register_taxonomy('faechergruppe', array(self::post_type), array(
            'label' => __('Fächergruppen', self::textdomain),
            'labels' => array(
                'name' => __('Fächergruppen', self::textdomain),
                'singular_name' => __('Fächergruppe', self::textdomain),
                'menu_name' => __('Fächergruppen', self::textdomain),
                'all_items' => __('Alle Fächergruppen', self::textdomain),
                'edit_item' => __('Fächergruppe bearbeiten', self::textdomain),
                'update_item' => __('Fächergruppe aktualisieren', self::textdomain),
                'add_new_item' => __('Neue Fächergruppe hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Fächergruppe suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => false,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'faechergruppe',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }

    public function register_taxonomy_fakultaet() {
        register_taxonomy('fakultaet', array(self::post_type), array(
            'label' => __('Fakultäten', self::textdomain),
            'labels' => array(
                'name' => __('Fakultäten', self::textdomain),
                'singular_name' => __('Fakultät', self::textdomain),
                'menu_name' => __('Fakultäten', self::textdomain),
                'all_items' => __('Alle Fakultäten', self::textdomain),
                'edit_item' => __('Fakultät bearbeiten', self::textdomain),
                'update_item' => __('Fakultät aktualisieren', self::textdomain),
                'add_new_item' => __('Neue Fakultät hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Fakultät suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => false,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'fakultaet',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }

    public function register_taxonomy_saattribut() {
        register_taxonomy('saattribut', array(self::post_type), array(
            'label' => __('Attribut', self::textdomain),
            'labels' => array(
                'name' => __('Attribute', self::textdomain),
                'singular_name' => __('Attribut', self::textdomain),
                'menu_name' => __('Attribute', self::textdomain),
                'all_items' => __('Alle Attribute', self::textdomain),
                'edit_item' => __('Attribut bearbeiten', self::textdomain),
                'update_item' => __('Attribut aktualisieren', self::textdomain),
                'add_new_item' => __('Neue Attribut hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Attribut suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => false,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'saattribut',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }

    public function register_taxonomy_sazvs() {
        register_taxonomy('sazvs', array(self::post_type), array(
            'label' => __('ZVS', self::textdomain),
            'labels' => array(
                'name' => __('Zugangsvoraussetzungen', self::textdomain),
                'singular_name' => __('Zugangsvoraussetzung', self::textdomain),
                'menu_name' => __('ZVS', self::textdomain),
                'all_items' => __('Alle Zugangsvoraussetzungen', self::textdomain),
                'edit_item' => __('Zugangsvoraussetzung bearbeiten', self::textdomain),
                'update_item' => __('Zugangsvoraussetzung aktualisieren', self::textdomain),
                'add_new_item' => __('Neue Zugangsvoraussetzung hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Zugangsvoraussetzung suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => false,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'sazvs',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }
    
    public function register_taxonomy_saconstant() {
        register_taxonomy('saconstant', array(self::post_type), array(
            'label' => __('Konstante', self::textdomain),
            'labels' => array(
                'name' => __('Konstanten', self::textdomain),
                'singular_name' => __('Konstante', self::textdomain),
                'menu_name' => __('Konstanten', self::textdomain),
                'all_items' => __('Alle Konstante', self::textdomain),
                'edit_item' => __('Konstante bearbeiten', self::textdomain),
                'update_item' => __('Konstante aktualisieren', self::textdomain),
                'add_new_item' => __('Neue Konstante hinzufügen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'search_items' => __('Konstante suchen', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_admin_column' => false,
            'hierarchical' => true,
            'update_count_callback' => '',
            'query_var' => 'saconstant',
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"
            ),
        ));
    }
    
    public function register_taxonomy_satag() {
        register_taxonomy('satag', array(self::post_type), array(
            'label' => __('Schlagworte', self::textdomain),
            'labels' => array(
                'name' => __('Schlagworte', self::textdomain),
                'singular_name' => __('Schlagwort', self::textdomain),
                'search_items' => __('Schlagwörter suchen', self::textdomain),
                'popular_items' => __('Beliebte Schlagwörter', self::textdomain),
                'all_items' => __('Alle Schlagwörtern', self::textdomain),
                'parent_item' => null,
                'parent_item_colon' => null,
                'edit_item' => __('Schlagwort bearbeiten', self::textdomain),
                'update_item' => __('Schlagwort aktualisieren', self::textdomain),
                'add_new_item' => __('Neues Schlagwort erstellen', self::textdomain),
                'new_item_name' => __('Name', self::textdomain),
                'separate_items_with_commas' => __('Trenne Schlagwörter durch Kommas', self::textdomain),
                'add_or_remove_items' => __('Hinzu', self::textdomain),
                'choose_from_most_used' => __('Wähle aus den häufig genutzten Schlagwörtern', self::textdomain),
                'menu_name' => __('Schlagworte', self::textdomain),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => true,
            'show_admin_column' => false,
            'hierarchical' => false,
            'query_var' => 'satag',
            'rewrite' => true,
            'capabilities' => array(
                'manage_terms' => "edit_" . self::capability_type . "s",
                'edit_terms' => "edit_" . self::capability_type . "s",
                'delete_terms' => "edit_others_" . self::capability_type . "s",
                'assign_terms' => "edit_" . self::capability_type . "s"            
            ),
        ));
    }
    
    public function initialize_meta_boxes() {
        if ( !class_exists( 'rrze_Meta_Box' ) ) {
            include_once(plugin_dir_path(__FILE__) . 'includes/metaboxes/rrze_meta_box.php');
        }
    }        
    
    public function set_meta_boxes() {
        global $wp_meta_boxes;

        $screen = get_current_screen();
        if (self::post_type != $screen->post_type) {
            return;
        }
        
        unset($wp_meta_boxes[self::post_type]['normal']['core']['authordiv']);

        remove_meta_box('postimagediv', self::post_type, 'side');
        add_meta_box('postimagediv', __('Studienangebotsbild', self::textdomain), 'post_thumbnail_meta_box', self::post_type, 'side', 'default');

        unset($wp_meta_boxes[self::post_type]['side']['core']['studiengangdiv']);

        unset($wp_meta_boxes[self::post_type]['side']['core']['abschlussdiv']);

        unset($wp_meta_boxes[self::post_type]['side']['core']['semesterdiv']);

        unset($wp_meta_boxes[self::post_type]['side']['core']['studienortdiv']);

        unset($wp_meta_boxes[self::post_type]['side']['core']['faechergruppediv']);

        unset($wp_meta_boxes[self::post_type]['side']['core']['fakultaetdiv']);

        unset($wp_meta_boxes[self::post_type]['side']['core']['saattributdiv']);

        unset($wp_meta_boxes[self::post_type]['side']['core']['sazvsdiv']);
        
        unset($wp_meta_boxes[self::post_type]['side']['core']['saconstantdiv']);
    }

    public function settings_init() {
        register_setting('studienangebot_settings', 'studienangebot_settings');
    }

    public function change_thumbnail_html($content) {
        if (self::post_type == $GLOBALS['post_type'])
            add_filter('admin_post_thumbnail_html', array($this, 'replace_content'));
    }

    public function replace_content($content) {
        return str_replace(__('Set featured image'), __('Studienangebotsbild festlegen', self::textdomain), $content);
    }

    public function hide_term_fields() {
        global $pagenow, $post_type;
        
        if(isset($pagenow) && $pagenow == 'edit-tags.php' && isset($post_type) && $post_type == 'studienangebot') {
            echo "<style type=\"text/css\">div.form-required+div.form-field+div.form-field, tr.form-required+tr.form-field+tr.form-field { display: none; }</style>";
        }
    }
    
    public function abschluss_add_new_meta_field() {
        ?>
        <div class="form-field">
            <label for="abschlussgruppe"><?php _e('Abschlussgruppe', self::textdomain); ?></label>
            <select class="postform" id="abschlussgruppe" name="term_meta[abschlussgruppe]">
                <option value=""><?php _e('Keine', self::textdomain); ?></option>
                <?php $abschlussgruppe = self::get_abschlussgruppe();?>
                <?php foreach ($abschlussgruppe as $key => $label): ?>
                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <p>&nbsp;</p>
        </div>
        <?php
    }

    public function abschluss_edit_meta_field($term) {
        $t_id = $term->term_id;
        $term_meta = get_option("abschluss_category_$t_id");
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term_meta[abschlussgruppe]"><?php _e('Abschlussgruppe', self::textdomain); ?></label></th>
            <td>
                <select class="postform" id="abschlussgruppe" name="term_meta[abschlussgruppe]">
                    <option value=""><?php _e('Keine', self::textdomain); ?></option>
                    <?php $abschlussgruppe = self::get_abschlussgruppe();?>
                    <?php foreach ($abschlussgruppe as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php selected($term_meta['abschlussgruppe'], $key); ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>                
            </td>
        </tr>
        <?php
    }

    public function save_abschluss_custom_meta($term_id) {
        if (isset($_POST['term_meta'])) {

            $t_id = $term_id;
            $term_meta = (array) get_option("abschluss_category_$t_id");
            $cat_keys = array_keys($_POST['term_meta']);
            foreach ($cat_keys as $key) {
                if (isset($_POST['term_meta'][$key])) {
                    $term_meta[$key] = $_POST['term_meta'][$key];
                }
            }

            update_option("abschluss_category_$t_id", $term_meta);
        }
    }

    public function delete_abschluss_custom_meta($term_id) {
        if (isset($_POST['term_meta'])) {

            $t_id = $term_id;
            $term_meta = get_option("abschluss_category_$t_id");

            delete_option("abschluss_category_$t_id", $term_meta);
        }
    }

    public function sazvs_add_new_meta_field() {
        ?>
        <div class="form-field">
            <label for="term-linktext"><?php _e('Linktext', self::textdomain); ?></label>
            <input name="term_meta[linktext]" id="term-linktext" type="text" value="" size="40" aria-required="true" />
            <p>&nbsp;</p>
        </div>        
        <div class="form-field">
            <label for="term-linkurl"><?php _e('Linkurl', self::textdomain); ?></label>
            <input name="term_meta[linkurl]" id="term-linkurl" type="text" value="" size="40" aria-required="true" />
            <p>&nbsp;</p>
        </div>
        <?php
    }

    public function sazvs_edit_meta_field($term) {
        $t_id = $term->term_id;
        $term_meta = get_option("sazvs_category_$t_id");
        $linktext = !empty($term_meta['linktext']) ? $term_meta['linktext'] : '';
        $linkurl = !empty($term_meta['linkurl']) ? $term_meta['linkurl'] : '';
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term-linktext"><?php _e('Linktext', self::textdomain); ?></label></th>
            <td>
                <input name="term_meta[linktext]" id="term-linktext" type="text" value="<?php echo $linktext;?>" size="40" aria-required="true" />
             </td>
        </tr>        
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term-linkurl"><?php _e('Linkurl', self::textdomain); ?></label></th>
            <td>
                <input name="term_meta[linkurl]" id="term-linkurl" type="text" value="<?php echo $linkurl;?>" size="40" aria-required="true" />
             </td>
        </tr>
        <?php
    }

    public function save_sazvs_custom_meta($term_id) {
        if (isset($_POST['term_meta'])) {

            $t_id = $term_id;
            $term_meta = (array) get_option("sazvs_category_$t_id");
            $cat_keys = array_keys($_POST['term_meta']);
            foreach ($cat_keys as $key) {
                if (isset($_POST['term_meta'][$key])) {
                    $term_meta[$key] = $_POST['term_meta'][$key];
                }
            }

            update_option("sazvs_category_$t_id", $term_meta);
        }
    }

    public function delete_sazvs_custom_meta($term_id) {
        if (isset($_POST['term_meta'])) {

            $t_id = $term_id;
            $term_meta = get_option("sazvs_category_$t_id");

            delete_option("sazvs_category_$t_id", $term_meta);
        }
    }
    
    public function saconstant_add_new_meta_field() {
        ?>
        <div class="form-field">
            <label for="term-linktext"><?php _e('Linktext', self::textdomain); ?></label>
            <input name="term_meta[linktext]" id="term-linktext" type="text" value="" size="40" aria-required="true" />
            <p>&nbsp;</p>
        </div>        
        <div class="form-field">
            <label for="term-linkurl"><?php _e('Linkurl', self::textdomain); ?></label>
            <input name="term_meta[linkurl]" id="term-linkurl" type="text" value="" size="40" aria-required="true" />
            <p>&nbsp;</p>
        </div>
        <?php
    }

    public function saconstant_edit_meta_field($term) {
        $t_id = $term->term_id;
        $term_meta = get_option("saconstant_category_$t_id");
        $linktext = !empty($term_meta['linktext']) ? $term_meta['linktext'] : '';
        $linkurl = !empty($term_meta['linkurl']) ? $term_meta['linkurl'] : '';
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term-linktext"><?php _e('Linktext', self::textdomain); ?></label></th>
            <td>
                <input name="term_meta[linktext]" id="term-linktext" type="text" value="<?php echo $linktext;?>" size="40" aria-required="true" />
             </td>
        </tr>        
        <tr class="form-field">
            <th scope="row" valign="top"><label for="term-linkurl"><?php _e('Linkurl', self::textdomain); ?></label></th>
            <td>
                <input name="term_meta[linkurl]" id="term-linkurl" type="text" value="<?php echo $linkurl;?>" size="40" aria-required="true" />
             </td>
        </tr>
        <?php
    }

    public function save_saconstant_custom_meta($term_id) {
        if (isset($_POST['term_meta'])) {

            $t_id = $term_id;
            $term_meta = (array) get_option("saconstant_category_$t_id");
            $cat_keys = array_keys($_POST['term_meta']);
            foreach ($cat_keys as $key) {
                if (isset($_POST['term_meta'][$key])) {
                    $term_meta[$key] = $_POST['term_meta'][$key];
                }
            }

            update_option("saconstant_category_$t_id", $term_meta);
        }
    }

    public function delete_saconstant_custom_meta($term_id) {
        if (isset($_POST['term_meta'])) {

            $t_id = $term_id;
            $term_meta = get_option("saconstant_category_$t_id");

            delete_option("saconstant_category_$t_id", $term_meta);
        }
    }
    
    public function term_columns($columns) {
        unset($columns['description']);
        return $columns;
    }
    
    public function abschluss_columns($columns) {
        $new_columns = $columns;
        array_splice($new_columns, 2);
        $new_columns['abschluss'] = esc_html__('Gruppe', self::textdomain);
        return array_merge($new_columns, $columns);
    }

    public function abschluss_custom_column($row, $column_name, $term_id) {
        $t_id = $term_id;
        $term_meta = get_option("abschluss_category_$t_id");
        $abschlussgruppe = self::get_abschlussgruppe();
        if ($term_meta && !empty($abschlussgruppe[$term_meta['abschlussgruppe']]))
            return $abschlussgruppe[$term_meta['abschlussgruppe']];

        return '';
    }

    public static function get_abschlussgruppe() {
       $abschlussgruppe = array(
            'bachelor' => __('Bachelorstudiengänge', self::textdomain),
            'master' => __('Masterstudiengänge', self::textdomain),
            'lehramt' => __('Lehramt und Staatsexamen', self::textdomain),
        );
        
        return $abschlussgruppe;
    }

    public function sazvs_columns($columns) {
        $new_columns = $columns;
        array_splice($new_columns, 2);
        $new_columns['linktext'] = esc_html__('Linktext', self::textdomain);
        $new_columns['linkurl'] = esc_html__('Linkurl', self::textdomain);
        return array_merge($new_columns, $columns);
    }

    public function sazvs_custom_column($row, $column_name, $term_id) {
        $t_id = $term_id;
        $term_meta = get_option("sazvs_category_$t_id");
        if ($column_name == 'linktext' && !empty($term_meta[$column_name])) {
            return $term_meta[$column_name];
        } elseif ($column_name == 'linkurl' && !empty($term_meta[$column_name])) {
            return $term_meta[$column_name];
        }
        return '';
    }
    
    public function saconstant_columns($columns) {
        unset($columns['posts']);
        $new_columns = $columns;
        array_splice($new_columns, 2);
        $new_columns['linktext'] = esc_html__('Linktext', self::textdomain);
        $new_columns['linkurl'] = esc_html__('Linkurl', self::textdomain);
        return array_merge($new_columns, $columns);
    }

    public function saconstant_custom_column($row, $column_name, $term_id) {
        $t_id = $term_id;
        $term_meta = get_option("saconstant_category_$t_id");
        if ($column_name == 'linktext' && !empty($term_meta[$column_name])) {
            return $term_meta[$column_name];
        } elseif ($column_name == 'linkurl' && !empty($term_meta[$column_name])) {
            return $term_meta[$column_name];
        }
        return '';
    }
    
    public function include_studiengang_template($template_path) {
        if (is_singular(self::post_type)) {
            $template = sprintf('single-%s.php', self::post_type);
            $template_path = locate_template(array($template));
            if(!$template_path) {
                $template_path = plugin_dir_path(__FILE__) . 'includes/templates/' . $template;
            }
        }

        return $template_path;
    }
    
    public function studiengang_content($content) {
        
        if (is_singular(self::post_type)) {            
            ob_start();

            $post = get_post();

            if (isset($post)) {
                $post_id = $post->ID;

                $terms = wp_get_object_terms($post_id, self::$taxonomies);

                $faechergruppe = array();
                $fakultaet = array();
                $abschluss = array();
                $semester = array();
                $studienort = array();

                foreach ($terms as $term) {
                    ${$term->taxonomy}[] = $term->name;               
                }

                $faechergruppe = isset($faechergruppe) ? implode(', ', $faechergruppe) : '';
                $fakultaet = isset($fakultaet) ? implode(', ', $fakultaet) : '';
                $abschluss = isset($abschluss) ? implode(', ', $abschluss) : '';
                $semester = isset($semester) ? implode(', ', $semester) : '';
                $studienort = isset($studienort) ? implode(', ', $studienort) : '';

                $regelstudienzeit = get_post_meta($post_id, 'sa_regelstudienzeit', true);
                $studiengang_info = get_post_meta($post_id, 'sa_studiengang_info', true);
                $kombination_info = get_post_meta($post_id, 'sa_kombination_info', true);
                $kombination_info = trim($kombination_info);
                $kombination_info = !empty($kombination_info) ? $kombination_info : '-';

                $zvs_anfaenger = array();
                $zvs_hoeheres_semester = array();
                $zvs_terms = wp_get_object_terms($post_id, 'sazvs');
                if(!empty($zvs_terms)) {
                    if(!is_wp_error($zvs_terms )) {
                        foreach($zvs_terms as $term) {
                            $t_id = $term->term_id;
                            $meta = get_option("sazvs_category_$t_id");                       
                            if($meta && !empty($meta['linkurl'])) {
                                $sp = sprintf('<a href="%2$s">%1$s</a>', $meta['linktext'], $meta['linkurl']);
                            } elseif($meta) {
                                $sp = $meta['linktext'];
                            }
                            if(strpos($term->slug, 'studienanfaenger') === 0) {
                                $zvs_anfaenger[] = $sp;
                            } elseif(strpos($term->slug, 'hoeheres-semester') === 0) {
                                $zvs_hoeheres_semester[] = $sp;
                            }
                        }
                    }
                }
                $zvs_anfaenger = !empty($zvs_anfaenger) ? implode(', ', $zvs_anfaenger) : '-';
                $zvs_hoeheres_semester = !empty($zvs_hoeheres_semester) ? implode(', ', $zvs_hoeheres_semester) : '-';

                $zvs_weiteres = get_post_meta($post_id, 'sa_zvs_weiteres', true);
                $zvs_weiteres = trim($zvs_weiteres);
                $zvs_weiteres = !empty($zvs_weiteres) ? $zvs_weiteres : '-';

                $schwerpunkte = get_post_meta($post_id, 'sa_schwerpunkte', true);
                $sprachkenntnisse = get_post_meta($post_id, 'sa_sprachkenntnisse', true);

                $deutschkenntnisse = get_post_meta($post_id, 'sa_de_kenntnisse_info', true);
                $pruefungsamt = get_post_meta($post_id, 'sa_pruefungsamt_info', true);
                $pruefungsordnung = get_post_meta($post_id, 'sa_pruefungsordnung_info', true);

                $besondere_hinweise = get_post_meta($post_id, 'sa_besondere_hinweise', true);
                $besondere_hinweise = trim($besondere_hinweise);
                $besondere_hinweise = !empty($besondere_hinweise) ? $besondere_hinweise : '-';

                $fach = get_post_meta($post_id, 'sa_fach_info', true);

                $sb_allgemein_info = get_post_meta($post_id, 'sa_sb_allgemein_info', true);
                $ssc = get_post_meta($post_id, 'sa_ssc_info', true);
                $gebuehren = get_post_meta($post_id, 'sa_gebuehren', true);
                $bewerbung = get_post_meta($post_id, 'sa_bewerbung', true);
                $studiengangskoordination = get_post_meta($post_id, 'sa_studiengangskoordination', true);

                $einfuehrung = get_post_meta($post_id, 'sa_einfuehrung_info', true);

                $constant_terms = wp_get_object_terms($post_id, 'saconstant');

                $attribut_terms = wp_get_object_terms($post_id, 'saattribut');

                echo '<table>';
                echo '<tbody>';

                echo '<tr><td>' . __('Fächergruppe', self::textdomain) . '</td><td>' . $faechergruppe . '</td></tr>';
                echo '<tr><td>' . __('Fakultät', self::textdomain) . '</td><td>' . $fakultaet . '</td></tr>';
                echo '<tr><td>' . __('Abschluss', self::textdomain) . '</td><td>' . $abschluss . '</td></tr>';
                echo '<tr><td>' . __('Regelstudienzeit', self::textdomain) . '</td><td>' . $regelstudienzeit . '</td></tr>';
                echo '<tr><td>' . __('Studienbeginn', self::textdomain) . '</td><td>' . $semester . '</td></tr>';
                echo '<tr><td>' . __('Studienort', self::textdomain) . '</td><td>' . $studienort . '</td></tr>';
                echo '<tr><td>' . __('Kurzinformationen zum Studiengang', self::textdomain) . '</td><td>' . $studiengang_info . '</td></tr>';

                if(!isset($attribut_terms[0]->slug) || $attribut_terms[0]->slug != 'weiterbildungsstudiengang') {
                    echo '<tr><td colspan="2">' . __('Zugangsvoraussetzungen', self::textdomain) . '</td></tr>';
                    echo '<tr><td style="padding-left: 2em">' . __('für Studienanfänger', self::textdomain) . '</td><td>' . $zvs_anfaenger . '</td></tr>';
                    echo '<tr><td style="padding-left: 2em">' . __('höheres Semester', self::textdomain) . '</td><td>' . $zvs_hoeheres_semester . '</td></tr>';
                    echo '<tr><td style="padding-left: 2em">' . __('weitere Voraussetzungen', self::textdomain) . '</td><td>' . $zvs_weiteres . '</td></tr>';

                    echo '<tr><td>' . __('Kombination', self::textdomain) . '</td><td>' . $kombination_info . '</td></tr>';
                    echo '<tr><td>' . __('Studienrichtungen/ -schwerpunkte/ -inhalte', self::textdomain) . '</td><td>' . $schwerpunkte . '</td></tr>';
                    echo '<tr><td>' . __('Sprachkenntnisse', self::textdomain) . '</td><td>' . $sprachkenntnisse . '</td></tr>';
                    echo '<tr><td>' . __('Deutschkenntnisse für ausländische Studierende', self::textdomain) . '</td><td>' . $deutschkenntnisse . '</td></tr>';
                    echo '<tr><td>' . __('Studien-und Prüfungsordnung mit Studienplan', self::textdomain) . '</td><td>' . $pruefungsordnung . '</td></tr>';
                    echo '<tr><td>' . __('Prüfungsamt/Prüfungsbeauftragte', self::textdomain) . '</td><td>' . $pruefungsamt . '</td></tr>';
                    echo '<tr><td>' . __('Besondere Hinweise', self::textdomain) . '</td><td>' . $besondere_hinweise . '</td></tr>';
                    echo '<tr><td>' . __('Link zum Fach', self::textdomain) . '</td><td>' . $fach . '</td></tr>';

                    echo '<tr><td colspan="2">' . __('Studienberatung', self::textdomain) . '</td></tr>';
                    echo '<tr><td style="padding-left: 2em">' . __('Studienberatung allgemein', self::textdomain) . '</td><td>' . $sb_allgemein_info . '</td></tr>';
                    echo '<tr><td style="padding-left: 2em">' . __('Studien-Service-Center', self::textdomain) . '</td><td>' . $ssc . '</td></tr>';

                    echo '<tr><td>' . __('Einführungsveranstaltungen für Studienanfänger /Vorkurse', self::textdomain) . '</td><td>' . $einfuehrung . '</td></tr>';

                    if(!empty($constant_terms)) {
                        if(!is_wp_error($constant_terms )) {
                            foreach($constant_terms as $term) {
                                $t_id = $term->term_id;
                                $name = $term->name;
                                $meta = get_option("saconstant_category_$t_id");                       
                                if($meta && !empty($meta['linkurl'])) {
                                    printf('<tr><td>%1$s</td><td><a href="%3$s">%2$s</a></td></tr>', $term->name, $meta['linktext'], $meta['linkurl']);
                                } elseif($meta) {
                                    printf('<tr><tr><td>%1$s</td><td>%2$s</td></tr>', $term->name, $meta['linktext']);
                                } 
                            }
                        }
                    }

                } else {
                    echo '<tr><td>' . __('Voraussetzungen', self::textdomain) . '</td><td>' . $zvs_weiteres . '</td></tr>';

                    echo '<tr><td>' . __('Bewerbung', self::textdomain) . '</td><td>' . $bewerbung . '</td></tr>';

                    echo '<tr><td>' . __('Studienrichtungen/ -schwerpunkte/ -inhalte', self::textdomain) . '</td><td>' . $schwerpunkte . '</td></tr>';
                    echo '<tr><td>' . __('Sprachkenntnisse', self::textdomain) . '</td><td>' . $sprachkenntnisse . '</td></tr>';                
                    echo '<tr><td>' . __('Studien-und Prüfungsordnung mit Studienplan', self::textdomain) . '</td><td>' . $pruefungsordnung . '</td></tr>';
                    echo '<tr><td>' . __('Prüfungsamt/Prüfungsbeauftragte', self::textdomain) . '</td><td>' . $pruefungsamt . '</td></tr>';
                    echo '<tr><td>' . __('Besondere Hinweise', self::textdomain) . '</td><td>' . $besondere_hinweise . '</td></tr>';
                    echo '<tr><td>' . __('Link zum Fach', self::textdomain) . '</td><td>' . $fach . '</td></tr>';

                    echo '<tr><td>' . __('Studienberatung allgemein', self::textdomain) . '</td><td>' . $sb_allgemein_info . '</td></tr>';
                    echo '<tr><td>' . __('Studienfachberatung/Studienkoordination', self::textdomain) . '</td><td>' . $studiengangskoordination . '</td></tr>';
                    echo '<tr><td>' . __('Studiengebühren und Studentenwerksbeiträge', self::textdomain) . '</td><td>' . $gebuehren . '</td></tr>';

                }
                echo '</tbody>';
                echo '</table>';
            } 

            else {
                echo '<p>' . __('Es konnte nichts gefunden werden.', self::textdomain) . '</p>';
            }

            $content = ob_get_clean();
        }
        
        return $content;
        
    }
    
}