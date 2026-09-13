<?php
/**
 * Plugin Name:       Supaul Conference Hall Requisition System
 * Plugin URI:        https://supaul.nic.in
 * Description:       Dedicated 3-Tier Conference Hall Requisition System for District Administration Supaul
 * Version:           4.3.0
 * Author:            District Administration Supaul / NIC
 * Author URI:        https://supaul.nic.in
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       supaul-conference-hall
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * GitHub Plugin URI: https://github.com/YOUR_GITHUB_USERNAME/YOUR_REPOSITORY_NAME
 * GitHub Branch:     main
 * Update URI:        https://github.com/YOUR_GITHUB_USERNAME/YOUR_REPOSITORY_NAME
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
define('SUPAUL_HALLS_TABLE', $wpdb->prefix . 'supaul_halls');
define('SUPAUL_REQS_TABLE', $wpdb->prefix . 'supaul_requisitions');
define('SUPAUL_USERS_TABLE', $wpdb->prefix . 'supaul_portal_users');
define('SUPAUL_NOTIFS_TABLE', $wpdb->prefix . 'supaul_notifications');
define('SUPAUL_SESSION_COOKIE', 'supaul_portal_session_token');

register_activation_hook(__FILE__, 'supaul_hall_booking_activate');

function supaul_hall_booking_activate() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();

    // Table 1: Conference Halls
    $sql_halls = "CREATE TABLE " . SUPAUL_HALLS_TABLE . " (
        id varchar(50) NOT NULL,
        name varchar(255) NOT NULL,
        name_hi varchar(255) NOT NULL,
        wings varchar(255) NOT NULL,
        capacity int(11) NOT NULL,
        in_charge varchar(255) NOT NULL,
        badge varchar(100) NOT NULL,
        description text NOT NULL,
        features text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_halls);

    // Table 2: Requisitions / Bookings
    $sql_reqs = "CREATE TABLE " . SUPAUL_REQS_TABLE . " (
        id varchar(50) NOT NULL,
        hall_id varchar(50) NOT NULL,
        booking_date date NOT NULL,
        hours text NOT NULL,
        subject varchar(255) NOT NULL,
        agenda text,
        chairing_officer varchar(255) NOT NULL,
        requisitioning_officer varchar(255) NOT NULL,
        officer_email varchar(150) NOT NULL,
        department varchar(200) NOT NULL,
        memo_no varchar(100) DEFAULT '',
        priority varchar(50) DEFAULT 'Standard',
        attendees int(11) DEFAULT 25,
        vc_required tinyint(1) DEFAULT 0,
        ppt_required tinyint(1) DEFAULT 0,
        pa_required tinyint(1) DEFAULT 1,
        projector_required tinyint(1) DEFAULT 1,
        refreshments varchar(255) DEFAULT 'Executive Tea & Biscuits',
        status varchar(50) DEFAULT 'Pending',
        rejection_reason text,
        approved_by varchar(255) DEFAULT '',
        approved_by_name varchar(255) DEFAULT '',
        approved_by_designation varchar(255) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_reqs);

    // Table 3: Dedicated Portal Users
    $sql_users = "CREATE TABLE " . SUPAUL_USERS_TABLE . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        designation varchar(255) NOT NULL,
        department varchar(255) NOT NULL,
        email varchar(150) NOT NULL,
        password varchar(255) NOT NULL,
        plain_password varchar(255) DEFAULT '',
        role varchar(50) NOT NULL DEFAULT 'officer',
        status varchar(50) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY email_idx (email)
    ) $charset_collate;";
    dbDelta($sql_users);

    // Table 4: Notifications
    $sql_notifs = "CREATE TABLE " . SUPAUL_NOTIFS_TABLE . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        req_id varchar(50) NOT NULL,
        recipient_email varchar(150) DEFAULT '',
        recipient_role varchar(50) DEFAULT '',
        message text NOT NULL,
        status_change varchar(50) NOT NULL,
        is_read tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql_notifs);

    supaul_seed_default_hall_if_empty();
    supaul_seed_default_portal_admin();
    supaul_auto_create_booking_page();
}

function supaul_ensure_notifications_table() {
    global $wpdb;
    $table = SUPAUL_NOTIFS_TABLE;
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            req_id varchar(50) NOT NULL,
            recipient_email varchar(150) DEFAULT '',
            recipient_role varchar(50) DEFAULT '',
            message text NOT NULL,
            status_change varchar(50) NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql);
    }
}
add_action('plugins_loaded', 'supaul_ensure_notifications_table');

function supaul_seed_default_hall_if_empty() {
    global $wpdb;
    $table = SUPAUL_HALLS_TABLE;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    if ($count == 0) {
        $single_hall = [
            'id' => 'hall-lahtan-choudhary-sabhagar',
            'name' => 'Lahtan Choudhary Sabhagar',
            'name_hi' => 'लहटन चौधरी सभागार',
            'wings' => 'समाहरणालय मुख्य परिसर, सुपौल - 852131',
            'capacity' => 120,
            'in_charge' => 'नजारत उप समाहर्ता, सुपौल',
            'badge' => 'समाहरणालय मुख्य परिसर',
            'description' => 'जिला समाहरणालय सुपौल का प्रधान सभागार - जिलाधिकारी (DM) महोदय की समीक्षा बैठकों, कैबिनेट/मुख्य सचिव वीडियो कॉन्फ्रेंसिंग एवं जिला समन्वय बैठकों हेतु।',
            'features' => json_encode([
                'Polycom Studio HD VC Link',
                'Dual 85" Smart Interactive Screens',
                'Gooseneck Delegate Mics (x45)',
                'High Power PA Console & Acoustic Hall',
                '10 kVA Dedicated Online UPS Backup'
            ])
        ];
        $wpdb->insert($table, $single_hall);
    }
}
add_action('plugins_loaded', 'supaul_seed_default_hall_if_empty');

function supaul_seed_default_portal_admin() {
    global $wpdb;
    $table = SUPAUL_USERS_TABLE;

    $default_accounts = [
        [
            'email'          => 'itmanager@nic.in',
            'name'           => 'आईटी मैनेजर / IT Manager',
            'designation'    => 'IT Manager / तकनीकी नोडल पदाधिकारी (ITM)',
            'department'     => 'IT Department / आईटी विभाग',
            'role'           => 'itm',
            'password'       => 'admin@12345',
            'status'         => 'active'
        ],
        [
            'email'          => 'ndcsupaul@nic.in',
            'name'           => 'नजारत उप समाहर्ता (NDC) / Nazarat Deputy Collector (NDC) / Admin',
            'designation'    => 'Nazarat Deputy Collector (NDC) / Admin',
            'department'     => 'Nazarat Section / नजारत शाखा',
            'role'           => 'nazarat',
            'password'       => 'admin@12345',
            'status'         => 'active'
        ],
        [
            'email'          => 'techteam@nic.in',
            'name'           => 'तकनीकी टीम (Technical Team) / Network Engineer',
            'designation'    => 'Network Engineer / तकनीकी टीम',
            'department'     => 'BSWAN / बिस्वान',
            'role'           => 'technical',
            'password'       => 'admin@12345',
            'status'         => 'active'
        ],
        [
            'email'          => 'dio-spl@nic.in',
            'name'           => 'जिला सूचना विज्ञान पदाधिकारी (DIO) / District Informatics Officer / Super Admin',
            'designation'    => 'District Informatics Officer / Super Admin',
            'department'     => 'National Informatics Centre / राष्ट्रीय सूचना-विज्ञान केंद्र',
            'role'           => 'admin',
            'password'       => 'admin@12345',
            'status'         => 'active'
        ]
    ];

    foreach ($default_accounts as $acc) {
        $exists = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $acc['email']));
        if (!$exists) {
            $wpdb->insert($table, [
                'name'           => $acc['name'],
                'designation'    => $acc['designation'],
                'department'     => $acc['department'],
                'email'          => $acc['email'],
                'password'       => wp_hash_password($acc['password']),
                'plain_password' => $acc['password'],
                'role'           => $acc['role'],
                'status'         => $acc['status']
            ]);
        }
    }
}
add_action('plugins_loaded', 'supaul_seed_default_portal_admin');

function supaul_get_current_portal_user() {
    if (empty($_COOKIE[SUPAUL_SESSION_COOKIE])) {
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $wp_user = wp_get_current_user();
            return [
                'id'          => 0,
                'name'        => $wp_user->display_name ?: 'जिला सूचना विज्ञान पदाधिकारी (DIO)',
                'designation' => 'District Informatics Officer / Admin',
                'department'  => 'समाहरणालय सुपौल / NIC',
                'email'       => $wp_user->user_email ?: 'dio-spl@nic.in',
                'role'        => 'admin'
            ];
        }
        return null;
    }

    $cookie_val = sanitize_text_field(stripslashes($_COOKIE[SUPAUL_SESSION_COOKIE]));
    $parts = explode('|', $cookie_val);
    if (count($parts) !== 3) {
        return null;
    }

    list($user_id, $expiry, $hash) = $parts;
    if (time() > intval($expiry)) {
        return null;
    }

    $expected_hash = hash_hmac('sha256', $user_id . '|' . $expiry, wp_salt('auth'));
    if (!hash_equals($expected_hash, $hash)) {
        return null;
    }

    global $wpdb;
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, designation, department, email, role, status FROM " . SUPAUL_USERS_TABLE . " WHERE id = %d AND status = 'active'",
        intval($user_id)
    ), ARRAY_A);

    return $user ?: null;
}

function supaul_set_portal_session($user_id) {
    $expiry = time() + (86400 * 3);
    $hash = hash_hmac('sha256', $user_id . '|' . $expiry, wp_salt('auth'));
    $cookie_val = $user_id . '|' . $expiry . '|' . $hash;

    setcookie(SUPAUL_SESSION_COOKIE, $cookie_val, [
        'expires'  => $expiry,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function supaul_clear_portal_session() {
    setcookie(SUPAUL_SESSION_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function supaul_auto_create_booking_page() {
    $page_slug = 'hall-booking';
    $page_title = 'Supaul Conference Hall Requisition System';
    $existing_page_id = get_option('supaul_hall_booking_page_id');
    $existing_page = $existing_page_id ? get_post($existing_page_id) : get_page_by_path($page_slug);

    if (!$existing_page) {
        $page_data = [
            'post_title'     => $page_title,
            'post_name'      => $page_slug,
            'post_content'   => '[supaul_hall_booking]',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ];
        $page_id = wp_insert_post($page_data);
        if (!is_wp_error($page_id)) {
            update_option('supaul_hall_booking_page_id', $page_id);
        }
    } else {
        $update_payload = ['ID' => $existing_page->ID];
        $needs_update = false;

        if ($existing_page->post_title !== $page_title) {
            $update_payload['post_title'] = $page_title;
            $needs_update = true;
        }
        if ($existing_page->post_name !== $page_slug) {
            $update_payload['post_name'] = $page_slug;
            $needs_update = true;
        }
        if ($existing_page->post_status !== 'publish') {
            $update_payload['post_status'] = 'publish';
            $needs_update = true;
        }

        if ($needs_update) {
            wp_update_post($update_payload);
        }
        update_option('supaul_hall_booking_page_id', $existing_page->ID);
    }
}

add_action('admin_init', 'supaul_ensure_page_exists');
function supaul_ensure_page_exists() {
    $page_id = get_option('supaul_hall_booking_page_id');
    if (!$page_id || !get_post($page_id)) {
        supaul_auto_create_booking_page();
    }
}

function supaul_ensure_columns_exist() {
    global $wpdb;
    $table = SUPAUL_REQS_TABLE;
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
        $cols = $wpdb->get_col("SHOW COLUMNS FROM $table");
        if (!in_array('ppt_required', $cols)) {
            $wpdb->query("ALTER TABLE $table ADD ppt_required tinyint(1) DEFAULT 0 AFTER vc_required");
        }
        if (!in_array('approved_by_name', $cols)) {
            $wpdb->query("ALTER TABLE $table ADD approved_by_name varchar(255) DEFAULT '' AFTER approved_by");
        }
        if (!in_array('approved_by_designation', $cols)) {
            $wpdb->query("ALTER TABLE $table ADD approved_by_designation varchar(255) DEFAULT '' AFTER approved_by_name");
        }
    }

    $users_table = SUPAUL_USERS_TABLE;
    if ($wpdb->get_var("SHOW TABLES LIKE '$users_table'") === $users_table) {
        $user_cols = $wpdb->get_col("SHOW COLUMNS FROM $users_table");
        if (!in_array('plain_password', $user_cols)) {
            $wpdb->query("ALTER TABLE $users_table ADD plain_password varchar(255) DEFAULT '' AFTER password");
        }
        $wpdb->query("UPDATE $users_table SET plain_password = 'admin@12345' WHERE email IN ('itmanager@nic.in', 'ndcsupaul@nic.in', 'techteam@nic.in', 'dio-spl@nic.in') AND (plain_password = '' OR plain_password IS NULL)");
    }
}
add_action('plugins_loaded', 'supaul_ensure_columns_exist');

add_filter('the_title', 'supaul_suppress_page_title_on_portal', 10, 2);
function supaul_suppress_page_title_on_portal($title, $id = null) {
    if (!is_admin() && is_page() && in_the_loop()) {
        $portal_page_id = get_option('supaul_hall_booking_page_id');
        if ($portal_page_id && $id == $portal_page_id) {
            return '';
        }
    }
    return $title;
}

add_action('admin_notices', 'supaul_hall_booking_admin_notice');
function supaul_hall_booking_admin_notice() {
    $page_id = get_option('supaul_hall_booking_page_id');
    if ($page_id) {
        $permalink = get_permalink($page_id);
        ?>
        <div class="notice notice-info is-dismissible" style="padding: 12px; border-left-color: #0b3b60;">
            <p style="font-size: 14px; margin: 0 0 6px 0; color: #0b3b60;">
                <strong>🏛️ जिला प्रशासन सुपौल | सभागार आरक्षण पोर्टल सक्रिय है (Isolated 3-Tier Portal)</strong>
            </p>
            <p style="margin: 0; font-size: 13px; color: #334155;">
                Portal URL: <code><?php echo esc_html($permalink); ?></code>
                <a href="<?php echo esc_url($permalink); ?>" target="_blank" class="button button-primary" style="margin-left: 10px; background: #0b3b60; border-color: #082842;">
                    पोर्टल खोलें / Open Portal &rarr;
                </a>
            </p>
        </div>
        <?php
    }
}

add_action('admin_menu', 'supaul_hall_booking_admin_menu');
function supaul_hall_booking_admin_menu() {
    add_menu_page(
        'Supaul Hall Booking',
        'सभागार आरक्षण',
        'read',
        'supaul-hall-portal',
        'supaul_redirect_to_frontend_portal',
        'dashicons-calendar-alt',
        4
    );
}

function supaul_redirect_to_frontend_portal() {
    $page_id = get_option('supaul_hall_booking_page_id');
    $url = $page_id ? get_permalink($page_id) : home_url('/hall-booking/');
    echo '<div style="padding: 30px; font-family: sans-serif; text-align: center;">';
    echo '<h2 style="color: #0b3b60;">सभागार आरक्षण पोर्टल पर ले जाया जा रहा है...</h2>';
    echo '<p><a href="' . esc_url($url) . '" class="button button-primary button-hero" style="background: #0b3b60;">Click here if not redirected automatically</a></p>';
    echo '</div>';
    echo '<script>window.location.href = "' . esc_url($url) . '";</script>';
}

/**
 * =========================================================
 * WPBAKERY PAGE BUILDER INTEGRATION (Visual Composer Map)
 * =========================================================
 */
add_action('vc_before_init', 'supaul_register_wpbakery_element');

function supaul_register_wpbakery_element() {
    if (!defined('WPB_VC_VERSION') || !function_exists('vc_map')) {
        return;
    }

    vc_map([
        'name'        => __('District Conference Hall Booking Portal', 'supaul-conference-hall'),
        'base'        => 'supaul_hall_booking',
        'category'    => __('Government & Administration', 'supaul-conference-hall'),
        'description' => __('Interactive 3-tier hall requisition and vacancy system with real-time slots.', 'supaul-conference-hall'),
        'icon'        => 'dashicons-calendar-alt',
        'params'      => [
            [
                'type'        => 'textfield',
                'heading'     => __('Portal Heading Title (Hindi)', 'supaul-conference-hall'),
                'param_name'  => 'title_hi',
                'value'       => 'सुपौल | SUPAUL',
                'description' => __('The main heading on the banner.', 'supaul-conference-hall'),
                'admin_label' => true,
                'group'       => __('General Settings', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('Banner Subtitle', 'supaul-conference-hall'),
                'param_name'  => 'subtitle',
                'value'       => 'समाहरणालय सभागार आरक्षण प्रणाली | Conference Hall Management System',
                'description' => __('Subtitle shown directly under the heading.', 'supaul-conference-hall'),
                'group'       => __('General Settings', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('Government Subheader Left Label', 'supaul-conference-hall'),
                'param_name'  => 'gov_label',
                'value'       => 'बिहार सरकार | Government of Bihar',
                'group'       => __('General Settings', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('District Tag Label', 'supaul-conference-hall'),
                'param_name'  => 'district_label',
                'value'       => 'जिला प्रशासन सुपौल',
                'group'       => __('General Settings', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'colorpicker',
                'heading'     => __('Primary Brand Color', 'supaul-conference-hall'),
                'param_name'  => 'primary_color',
                'value'       => '#0b3b60',
                'description' => __('Used for portal top bar, active buttons, headings, and order slips.', 'supaul-conference-hall'),
                'group'       => __('Color Scheme', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'colorpicker',
                'heading'     => __('Accent / Highlight Color', 'supaul-conference-hall'),
                'param_name'  => 'accent_color',
                'value'       => '#f59e0b',
                'description' => __('Border line, highlighted buttons, and active tabs.', 'supaul-conference-hall'),
                'group'       => __('Color Scheme', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'colorpicker',
                'heading'     => __('Portal Container Background', 'supaul-conference-hall'),
                'param_name'  => 'bg_color',
                'value'       => '#f8fafc',
                'group'       => __('Color Scheme', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'dropdown',
                'heading'     => __('Corner Border Radius', 'supaul-conference-hall'),
                'param_name'  => 'border_radius',
                'value'       => [
                    __('Rounded Large (Default)', 'supaul-conference-hall') => 'rounded-xl',
                    __('Rounded Medium', 'supaul-conference-hall')          => 'rounded-lg',
                    __('Square (Strict Flat)', 'supaul-conference-hall')    => 'rounded-none',
                ],
                'group'       => __('Appearance', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'dropdown',
                'heading'     => __('Portal Container Shadow', 'supaul-conference-hall'),
                'param_name'  => 'shadow_depth',
                'value'       => [
                    __('Medium Shadow (Default)', 'supaul-conference-hall') => 'shadow-md',
                    __('Heavy Shadow (Elevated)', 'supaul-conference-hall') => 'shadow-2xl',
                    __('Subtle Shadow', 'supaul-conference-hall')           => 'shadow-sm',
                    __('No Shadow', 'supaul-conference-hall')               => 'shadow-none',
                ],
                'group'       => __('Appearance', 'supaul-conference-hall'),
            ],
            [
                'type'       => 'css_editor',
                'heading'    => __('Design Options (Margins, Padding, Borders)', 'supaul-conference-hall'),
                'param_name' => 'css',
                'group'      => __('Design Options', 'supaul-conference-hall'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('Extra CSS Class Name', 'supaul-conference-hall'),
                'param_name'  => 'el_class',
                'description' => __('Style this portal wrapper with custom CSS rules.', 'supaul-conference-hall'),
                'group'       => __('Design Options', 'supaul-conference-hall'),
            ],
        ],
    ]);
}

// Shortcode Renderer
add_shortcode('supaul_hall_booking', 'supaul_render_hall_booking_portal');

function supaul_render_hall_booking_portal($atts = []) {
    global $wpdb;

    $atts = shortcode_atts([
        'title_hi'       => 'सुपौल | SUPAUL',
        'subtitle'       => 'समाहरणालय सभागार आरक्षण प्रणाली | Conference Hall Management System',
        'gov_label'      => 'बिहार सरकार | Government of Bihar',
        'district_label' => 'जिला प्रशासन सुपौल',
        'primary_color'  => '#0b3b60',
        'accent_color'   => '#f59e0b',
        'bg_color'       => '#f8fafc',
        'border_radius'  => 'rounded-xl',
        'shadow_depth'   => 'shadow-md',
        'el_class'       => '',
        'css'            => '',
    ], $atts, 'supaul_hall_booking');

    $vc_css_class = '';
    if (function_exists('vc_shortcode_custom_css_class')) {
        $vc_css_class = vc_shortcode_custom_css_class($atts['css'], ' ');
    }
    $extra_classes = trim($vc_css_class . ' ' . esc_attr($atts['el_class']));

    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);
    wp_enqueue_script('lucide-icons', 'https://unpkg.com/lucide@latest', [], null, true);

    $halls = $wpdb->get_results("SELECT * FROM " . SUPAUL_HALLS_TABLE . " ORDER BY (id = 'hall-lahtan-choudhary-sabhagar') DESC, name ASC", ARRAY_A);
    foreach ($halls as &$h) {
        $h['features'] = json_decode($h['features'], true) ?: [];
    }

    $portal_user = supaul_get_current_portal_user();
    $is_logged_in = !empty($portal_user);
    $user_role = $is_logged_in ? $portal_user['role'] : 'public';
    $is_admin = ($user_role === 'admin');
    $is_nazarat = ($user_role === 'nazarat');
    $is_itm = ($user_role === 'itm');
    $is_officer = ($user_role === 'officer');
    $is_technical = ($user_role === 'technical');

    $today_date = current_time('Y-m-d');
    $nonce = wp_create_nonce('supaul_hall_booking_nonce');

    ob_start();
    ?>
    <style>
        :root {
            --portal-primary: <?php echo esc_attr($atts['primary_color']); ?>;
            --portal-accent: <?php echo esc_attr($atts['accent_color']); ?>;
            --portal-bg: <?php echo esc_attr($atts['bg_color']); ?>;
        }

        .portal-bg-primary { background-color: var(--portal-primary) !important; }
        .portal-text-primary { color: var(--portal-primary) !important; }
        .portal-border-primary { border-color: var(--portal-primary) !important; }

        .portal-bg-accent { background-color: var(--portal-accent) !important; }
        .portal-text-accent { color: var(--portal-accent) !important; }
        .portal-border-accent { border-color: var(--portal-accent) !important; }

        header.entry-header, .entry-header, .page-header,
        h1.entry-title, .entry-title, .page-title,
        .wp-block-post-title, h1.wp-block-post-title,
        header.wp-block-post-title, .post-title {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }

            html, body {
                height: auto !important;
                min-height: 100% !important;
                background: #ffffff !important;
                background-color: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }

            body > *:not(#supaul-portal-app) {
                display: none !important;
            }

            #supaul-portal-app {
                display: block !important;
                position: static !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }

            #supaul-portal-app > *:not(#modal-slip) {
                display: none !important;
                height: 0 !important;
                overflow: hidden !important;
            }

            #modal-slip {
                display: block !important;
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                margin: 0 auto !important;
                padding: 0 !important;
                background: #ffffff !important;
                background-color: #ffffff !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                box-shadow: none !important;
                border: none !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
            }

            #modal-slip > div {
                display: block !important;
                width: 100% !important;
                max-width: 700px !important;
                margin: 0 auto !important;
                padding: 20px 24px !important;
                border: 2px solid #0b3b60 !important;
                box-shadow: none !important;
                background: #ffffff !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
            }

            .print\:hidden, button, .modal-close-btn {
                display: none !important;
                visibility: hidden !important;
            }
        }
    </style>

    <div id="supaul-portal-app" style="background-color: var(--portal-bg);" class="font-sans antialiased text-slate-800 border border-slate-200 <?php echo esc_attr($atts['border_radius'] . ' ' . $atts['shadow_depth'] . ' ' . $extra_classes); ?> my-4 max-w-7xl mx-auto overflow-hidden">
        <div class="portal-bg-primary text-white border-b-4 portal-border-accent">
            <div class="bg-black/25 px-4 py-1.5 flex flex-wrap items-center justify-between text-[11px] text-slate-300 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <span><?php echo esc_html($atts['gov_label']); ?></span>
                    <span class="text-slate-500">|</span>
                    <span class="font-medium portal-text-accent"><?php echo esc_html($atts['district_label']); ?></span>
                </div>
                <div class="flex items-center gap-3 font-mono">
                    <span>supaul.nic.in</span>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-4 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3.5 w-full md:w-auto">
                    <div class="w-12 h-12 bg-white rounded-lg p-1.5 shadow flex items-center justify-center flex-shrink-0 border border-amber-400">
                        <div class="text-center leading-none portal-text-primary">
                            <span class="text-[16px] block font-bold">🏛️</span>
                            <span class="text-[8px] font-bold tracking-tighter uppercase block">BIHAR</span>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-lg sm:text-xl font-bold tracking-tight text-white m-0 leading-tight">
                                <?php echo esc_html($atts['title_hi']); ?>
                            </h1>
                            <?php if ($user_role === 'admin'): ?>
                                <span class="text-[10px] bg-rose-500/20 px-2 py-0.5 rounded text-rose-200 border border-rose-400/40 font-bold">जिला प्रशासक / DIO (Super Admin)</span>
                            <?php elseif ($user_role === 'nazarat'): ?>
                                <span class="text-[10px] bg-amber-500/20 px-2 py-0.5 rounded text-amber-200 border border-amber-400/40 font-bold">नजारत शाखा / NDC लॉगिन</span>
                            <?php elseif ($user_role === 'itm'): ?>
                                <span class="text-[10px] bg-purple-500/20 px-2 py-0.5 rounded text-purple-200 border border-purple-400/40 font-bold">तकनीकी नोडल पदाधिकारी (ITM)</span>
                            <?php elseif ($user_role === 'technical'): ?>
                                <span class="text-[10px] bg-cyan-500/20 px-2 py-0.5 rounded text-cyan-200 border border-cyan-400/40 font-bold">तकनीकी टीम (Technical Team)</span>
                            <?php elseif ($user_role === 'officer'): ?>
                                <span class="text-[10px] bg-emerald-500/20 px-2 py-0.5 rounded text-emerald-200 border border-emerald-400/40 font-bold">विभागीय नोडल लॉगिन</span>
                            <?php else: ?>
                                <span class="text-[10px] bg-white/10 px-2 py-0.5 rounded text-amber-200 border border-white/20 font-medium">सार्वजनिक पटल (Public View)</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-200 m-0 mt-0.5"><?php echo esc_html($atts['subtitle']); ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 w-full md:w-auto justify-end">
                    <?php if (!$is_logged_in): ?>
                        <button type="button" id="open-login-btn" class="px-4 py-2 rounded-lg text-xs font-semibold portal-bg-accent hover:opacity-90 text-slate-950 shadow flex items-center gap-1.5 transition">
                            <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                            <span>विभागीय / प्राधिकृत लॉगिन</span>
                        </button>
                    <?php else: ?>
                        <div class="relative">
                            <button type="button" id="notif-toggle-btn" class="p-2 rounded-lg bg-black/25 border border-white/15 hover:bg-black/40 portal-text-accent relative transition" title="सूचनाएं / Notifications">
                                <i data-lucide="bell" class="w-4 h-4"></i>
                                <span id="notif-unread-badge" class="hidden absolute -top-1 -right-1 bg-rose-600 text-white text-[9px] font-bold rounded-full w-4 h-4 flex items-center justify-center">0</span>
                            </button>
                            <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-slate-300 rounded-lg shadow-2xl z-50 overflow-hidden text-slate-800">
                                <div class="bg-slate-100 p-2.5 px-3 border-b border-slate-200 flex items-center justify-between">
                                    <span class="text-xs font-bold portal-text-primary flex items-center gap-1.5">
                                        <i data-lucide="bell-ring" class="w-3.5 h-3.5 text-amber-600"></i> अद्यतन सूचनाएं (Status Alerts)
                                    </span>
                                    <button type="button" id="notif-mark-read-btn" class="text-[10px] text-slate-500 hover:text-slate-800">सब पढ़ा हुआ चिह्नित करें</button>
                                </div>
                                <div id="notif-list-container" class="max-h-72 overflow-y-auto divide-y divide-slate-100 text-xs">
                                    <p class="p-3 text-center text-slate-500 text-xs">कोई नई सूचना उपलब्ध नहीं है।</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 bg-black/25 border border-white/15 px-3.5 py-1.5 rounded-lg text-xs">
                            <div class="text-right">
                                <span class="font-bold text-white block text-xs"><?php echo esc_html($portal_user['name']); ?></span>
                                <span class="text-[10px] portal-text-accent font-mono"><?php echo esc_html($portal_user['email']); ?></span>
                            </div>
                            <button type="button" id="open-change-pwd-btn" class="p-1.5 rounded bg-slate-700 hover:bg-slate-600 text-white transition ml-1" title="पासवर्ड बदलें / Change Password">
                                <i data-lucide="key" class="w-3.5 h-3.5"></i>
                            </button>
                            <button type="button" id="portal-logout-btn" class="p-1.5 rounded bg-rose-700 hover:bg-rose-800 text-white transition" title="लॉगआउट / Logout">
                                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-black/15 px-4 sm:px-6 flex items-center gap-1 overflow-x-auto text-xs font-medium border-t border-white/10">
                <button type="button" id="tab-btn-schedule" class="nav-tab-btn active px-4 py-2.5 border-b-2 portal-border-accent text-white font-bold flex items-center gap-1.5 whitespace-nowrap">
                    <i data-lucide="calendar-check" class="w-3.5 h-3.5"></i>
                    <span>उपलब्धता कैलेंडर / Vacancy Calendar</span>
                </button>

                <?php if ($user_role === 'officer' || $user_role === 'itm'): ?>
                    <button type="button" id="tab-btn-new-req" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        <span>मांग-पत्र भरें / Book Hall</span>
                    </button>
                    <button type="button" id="tab-btn-my-reqs" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        <span>मेरे मांग-पत्र / My Requisitions</span>
                    </button>
                <?php endif; ?>

                <?php if ($user_role === 'technical'): ?>
                    <button type="button" id="tab-btn-tech-dashboard" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
                        <span>तकनीकी सहायता डैशबोर्ड / VC & PPT Roster</span>
                        <span id="badge-tech-vc-count" class="w-4 h-4 bg-cyan-400 text-slate-950 font-bold text-[9px] rounded-full flex items-center justify-center ml-0.5">0</span>
                    </button>
                <?php endif; ?>

                <?php if ($user_role === 'nazarat' || $user_role === 'admin'): ?>
                    <button type="button" id="tab-btn-dashboard" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
                        <span>डैशबोर्ड / Dashboard</span>
                    </button>
                    <?php if ($user_role === 'admin'): ?>
                        <button type="button" id="tab-btn-halls" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                            <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                            <span>सभागार प्रबंधन / Manage Halls</span>
                        </button>
                    <?php endif; ?>
                    <button type="button" id="tab-btn-new-req" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="calendar-plus" class="w-3.5 h-3.5"></i>
                        <span>सीधा आवंटन / Sanction Slot</span>
                    </button>
                    <button type="button" id="tab-btn-requests" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="check-square" class="w-3.5 h-3.5"></i>
                        <span>आवेदन समीक्षा एवं स्थिति नियंत्रण</span>
                        <span id="badge-pending-count" class="w-4 h-4 portal-bg-accent text-slate-950 font-bold text-[9px] rounded-full flex items-center justify-center ml-0.5">0</span>
                    </button>
                    <button type="button" id="tab-btn-users" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                        <span>उपयोगकर्ता प्रबंधन / Users</span>
                    </button>
                    <button type="button" id="tab-btn-ledger" class="nav-tab-btn px-4 py-2.5 border-b-2 border-transparent text-slate-200 hover:text-white flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="clipboard-list" class="w-3.5 h-3.5"></i>
                        <span>मास्टर पंजी / Master Ledger</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-4 sm:p-6 space-y-6">
            <div id="section-schedule" class="portal-section space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                                <h2 class="text-xs uppercase font-bold tracking-wider portal-text-primary flex items-center gap-1.5 m-0">
                                    <i data-lucide="building" class="w-4 h-4 portal-text-primary"></i>
                                    सभागार विवरण (Facilities)
                                </h2>
                                <?php if ($is_admin): ?>
                                    <button type="button" id="quick-add-hall-btn" class="text-[10px] font-bold text-white portal-bg-primary hover:opacity-90 px-2.5 py-1 rounded flex items-center gap-1 shadow-xs">
                                        <i data-lucide="plus" class="w-3 h-3"></i> नया जोड़ें
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-2.5" id="venue-list-container"></div>
                        </div>

                        <div class="bg-amber-50/80 border border-amber-200 rounded-lg p-3.5 text-xs text-slate-700 space-y-1.5">
                            <div class="flex items-center gap-2 font-bold text-amber-900">
                                <i data-lucide="shield-alert" class="w-4 h-4 text-amber-700"></i>
                                <span>आरक्षण नियम एवं दिशा-निर्देश</span>
                            </div>
                            <ul class="text-[11px] text-slate-700 leading-relaxed space-y-1 list-disc list-inside m-0 pl-1">
                                <li><strong>समय सीमा:</strong> किसी भी स्लॉट का आरक्षण बैठक शुरू होने से कम से कम 1 घंटे पूर्व किया जाना अनिवार्य है।</li>
                                <li><strong>पिछला समय निषेध:</strong> आज के बीते हुए समय अथवा पूर्व तिथि (Back Date/Time) का स्लॉट आरक्षित नहीं किया जा सकता।</li>
                                <li><strong>प्राथमिकता:</strong> विधि-व्यवस्था, निर्वाचन व डीएम समीक्षा बैठकों को सर्वोच्च प्राथमिकता।</li>
                            </ul>
                        </div>
                    </div>

                    <div class="lg:col-span-8 space-y-4">
                        <div id="active-hall-banner" class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm flex flex-col sm:flex-row justify-between gap-4">
                            <div class="flex-1 space-y-3">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 id="current-hall-name" class="text-base font-bold portal-text-primary m-0">लहटन चौधरी सभागार</h2>
                                        <span id="current-hall-capacity-badge" class="text-xs px-2.5 py-0.5 rounded-full bg-blue-50 portal-text-primary border border-blue-200 font-semibold">क्षमता: 120 सीट्स</span>
                                        <?php if ($is_admin): ?>
                                            <button type="button" id="edit-current-hall-btn" class="text-[11px] px-2 py-0.5 bg-slate-100 hover:bg-slate-200 portal-text-primary rounded border border-slate-300 font-bold inline-flex items-center gap-1 transition">
                                                <i data-lucide="pencil" class="w-3 h-3"></i> सभागार संपादित करें
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <p id="current-hall-wings" class="text-[11px] text-slate-500 m-0 mt-0.5"></p>
                                </div>

                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                                    <span class="text-[10px] font-bold text-slate-600 uppercase tracking-wider block mb-0.5">सभागार का विवरण (Description):</span>
                                    <p id="current-hall-desc" class="text-xs text-slate-700 m-0 leading-relaxed"></p>
                                </div>

                                <div class="space-y-1.5">
                                    <span class="text-[11px] font-bold portal-text-primary uppercase tracking-wider flex items-center gap-1">
                                        <i data-lucide="cpu" class="w-3.5 h-3.5 portal-text-primary"></i> सुविधाएं एवं उपकरण (Facilities & Equipment)
                                    </span>
                                    <div id="current-hall-features" class="flex flex-wrap gap-1.5"></div>
                                </div>
                            </div>

                            <div class="bg-blue-50/60 p-3 rounded-lg border border-blue-200 text-xs text-slate-700 self-start sm:self-auto flex-shrink-0 w-full sm:w-56">
                                <span class="text-[10px] uppercase font-bold text-slate-500 block">नोडल प्राधिकार (In-Charge)</span>
                                <span id="current-hall-incharge" class="font-bold portal-text-primary block mt-1 text-xs">नजारत उप समाहर्ता, सुपौल</span>
                                <?php if ($is_admin): ?>
                                    <button type="button" id="quick-edit-incharge-btn" class="mt-2.5 w-full text-[10px] font-bold portal-text-primary bg-white hover:bg-blue-100 border border-blue-300 py-1 px-2 rounded flex items-center justify-center gap-1 transition shadow-xs">
                                        <i data-lucide="edit-3" class="w-3 h-3"></i> नोडल प्राधिकार बदलें
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg p-3 border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <div class="flex items-center bg-slate-100 border border-slate-300 rounded p-0.5">
                                    <button type="button" id="prev-day-btn" class="p-1 text-slate-600 hover:text-black hover:bg-slate-200 rounded transition" title="Previous Day">
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                    </button>
                                    <button type="button" id="today-btn" class="px-3 py-1 text-xs font-bold text-slate-700 hover:text-black rounded transition">आज / Today</button>
                                    <button type="button" id="next-day-btn" class="p-1 text-slate-600 hover:text-black hover:bg-slate-200 rounded transition" title="Next Day">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <span id="display-date-label" class="text-xs font-bold portal-text-primary flex items-center gap-1.5 ml-1">
                                    <i data-lucide="calendar" class="w-4 h-4 portal-text-primary"></i> Today
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <label for="calendar-date-picker" class="text-xs font-semibold text-slate-600">तारीख चुनें:</label>
                                <input type="date" id="calendar-date-picker" min="<?php echo esc_attr($today_date); ?>" value="<?php echo esc_attr($today_date); ?>" class="bg-white border border-slate-300 text-slate-800 text-xs px-2.5 py-1 rounded focus:outline-none portal-border-primary">
                            </div>
                        </div>

                        <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-2">
                                <div>
                                    <h3 class="text-xs font-bold portal-text-primary uppercase tracking-wider m-0">समय चक्र स्लॉट (08:00 AM – 08:00 PM)</h3>
                                    <p class="text-[11px] text-slate-500 m-0 mt-0.5">आवंटन हेतु स्लॉट का चयन करें (Click slot to select)</p>
                                </div>
                                <div class="flex items-center gap-3 text-[11px] text-slate-600 font-medium flex-wrap">
                                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-white border border-emerald-500 inline-block"></span> रिक्त</span>
                                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded portal-bg-primary inline-block"></span> चयनित</span>
                                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-100 border border-amber-400 inline-block"></span> समीक्षाधीन</span>
                                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-100 border border-rose-400 inline-block"></span> आरक्षित</span>
                                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-200 border border-slate-300 inline-block"></span> समाप्त/अनुपलब्ध</span>
                                </div>
                            </div>
                            <div id="slots-grid-container" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 mt-4"></div>
                        </div>

                        <div class="bg-slate-100 rounded-lg p-4 border border-slate-300 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div>
                                <span class="text-xs font-semibold text-slate-600">चयनित स्लॉट:</span>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span id="selected-slot-count-label" class="text-sm font-bold portal-text-primary">कोई स्लॉट चयनित नहीं</span>
                                    <span id="selected-slot-badges" class="flex gap-1"></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <button type="button" id="clear-selected-slots-btn" class="hidden px-3 py-1.5 rounded text-xs text-slate-600 hover:text-black bg-white border border-slate-300">रीसेट</button>
                                <?php if (!$is_logged_in): ?>
                                    <button type="button" id="schedule-login-prompt-btn" class="w-full sm:w-auto px-4 py-2 rounded font-semibold text-xs text-white portal-bg-primary hover:opacity-90 flex items-center justify-center gap-1.5 shadow">
                                        <i data-lucide="lock" class="w-3.5 h-3.5"></i> आरक्षण हेतु लॉगिन करें
                                    </button>
                                <?php else: ?>
                                    <button type="button" id="proceed-requisition-btn" disabled class="w-full sm:w-auto px-5 py-2 rounded font-semibold text-xs flex items-center justify-center gap-1.5 transition bg-slate-300 text-slate-500 cursor-not-allowed border border-slate-300">
                                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                                        <?php echo ($is_admin || $is_nazarat) ? 'प्रशासनिक सीधा आवंटन' : 'आरक्षण प्रपत्र भरें'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional DOM containers (forms, dashboards, modals) are loaded dynamically below -->
            <div id="section-new-req" class="portal-section hidden max-w-3xl mx-auto bg-white rounded-lg p-6 border border-slate-200 shadow-md space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h2 class="text-base font-bold portal-text-primary flex items-center gap-2 m-0">
                        <i data-lucide="file-text" class="w-4 h-4 portal-text-primary"></i> सभागार आरक्षण मांग-पत्र (Requisition Slip)
                    </h2>
                    <button type="button" id="cancel-req-btn" class="text-xs font-semibold portal-text-primary hover:underline">&larr; कैलेंडर पर वापस जाएं</button>
                </div>
                <div class="bg-blue-50/70 p-3.5 rounded border border-blue-200 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <div>
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">सभागार</span>
                        <span id="summary-venue" class="font-bold portal-text-primary truncate block">लहटन चौधरी सभागार</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">तारीख</span>
                        <span id="summary-date" class="font-bold text-slate-800 block"><?php echo esc_html($today_date); ?></span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">समय</span>
                        <span id="summary-hours" class="font-bold portal-text-primary block">None</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">क्षमता</span>
                        <span id="summary-capacity" class="font-bold text-slate-800 block">120 Seats</span>
                    </div>
                </div>
                <form id="requisition-form" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">बैठक का विषय / Agenda Title <span class="text-rose-600">*</span></label>
                        <input type="text" id="req-subject" required placeholder="उदा. अनुमंडल राजस्व एवं विधि-व्यवस्था समीक्षा बैठक" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 focus:outline-none portal-border-primary">
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">विस्तृत एजेंडा</label>
                        <textarea id="req-agenda" rows="2" placeholder="कार्यवाही के मुख्य बिंदु..." class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 focus:outline-none portal-border-primary"></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">अध्यक्षता करने वाले पदाधिकारी <span class="text-rose-600">*</span></label>
                            <input type="text" id="req-chair" required value="District Magistrate & Collector, Supaul" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">विभागीय पत्रांक / ज्ञापांक</label>
                            <input type="text" id="req-memo" placeholder="SPL/REV/2026/108" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">प्राथमिकता</label>
                            <select id="req-priority" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                                <option value="VVIP">अति विशिष्ट (VVIP)</option>
                                <option value="High" selected>उच्च प्राथमिकता (High)</option>
                                <option value="Standard">मानक बैठक (Standard)</option>
                                <option value="Routine">कार्यशाला / प्रशिक्षण (Workshop / Training)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">प्रतिभागी संख्या</label>
                            <input type="number" id="req-attendees" min="1" max="500" value="45" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded border border-slate-200 p-3.5 space-y-3">
                        <span class="block text-[11px] font-bold portal-text-primary uppercase tracking-wider">तकनीकी उपकरण एवं स्वल्पाहार</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <label class="flex items-center gap-2 text-slate-800 cursor-pointer bg-white p-2 rounded border border-slate-200">
                                <input type="checkbox" id="req-vc" class="rounded border-slate-300 portal-text-primary focus:ring-0 w-4 h-4">
                                <span class="font-semibold text-xs portal-text-primary">वी.सी. बुकिंग ज़रूरी है (VC Required)</span>
                            </label>
                            <label class="flex items-center gap-2 text-slate-800 cursor-pointer bg-white p-2 rounded border border-slate-200">
                                <input type="checkbox" id="req-ppt" class="rounded border-slate-300 portal-text-primary focus:ring-0 w-4 h-4">
                                <span class="font-semibold text-xs text-blue-800">पी.पी.टी का इस्तेमाल (PPT Use)</span>
                            </label>
                            <label class="flex items-center gap-2 text-slate-700 cursor-pointer bg-white p-2 rounded border border-slate-200">
                                <input type="checkbox" id="req-pa" checked class="rounded border-slate-300 portal-text-primary focus:ring-0 w-4 h-4">
                                <span>पी.ए. सिस्टम / माइक (PA Console)</span>
                            </label>
                            <label class="flex items-center gap-2 text-slate-700 cursor-pointer bg-white p-2 rounded border border-slate-200">
                                <input type="checkbox" id="req-proj" checked class="rounded border-slate-300 portal-text-primary focus:ring-0 w-4 h-4">
                                <span>स्मार्ट स्क्रीन / डिस्प्ले</span>
                            </label>
                        </div>
                        <div class="pt-2 border-t border-slate-200">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">स्वल्पाहार प्रोटोकॉल</label>
                            <select id="req-refreshments" class="w-full bg-white border border-slate-300 rounded px-3 py-1.5 text-slate-800">
                                <option value="Executive Tea & Biscuits">टी एवं बिस्कुट</option>
                                <option value="Formal Green Tea & Light Snacks">टी एवं अल्पाहार</option>
                                <option value="Working Lunch & Tea Protocol">टी एवं भोजन</option>
                                <option value="No Refreshments Required">स्वल्पाहार की आवश्यकता नहीं</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" id="cancel-req-btn-2" class="px-4 py-2 rounded text-slate-600 hover:text-black">रद्द करें</button>
                        <button type="submit" class="px-5 py-2.5 rounded font-bold portal-bg-primary hover:opacity-90 text-white shadow flex items-center gap-1.5">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            <?php echo ($is_admin || $is_nazarat) ? 'सीधा आवंटन आदेश जारी करें' : 'मांग-पत्र प्रस्तुत करें'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($user_role === 'officer' || $user_role === 'itm'): ?>
                <div id="section-my-reqs" class="portal-section hidden space-y-4">
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                        <h2 class="text-sm font-bold portal-text-primary flex items-center gap-2 m-0">
                            <i data-lucide="file-text" class="w-4 h-4 portal-text-primary"></i> मेरे विभागीय मांग-पत्र (My Requisitions)
                        </h2>
                        <p class="text-xs text-slate-500 m-0 mt-0.5">आपके द्वारा प्रेषित आवेदन एवं आवंटन आदेश (लॉगिन: <?php echo esc_html($portal_user['email']); ?>)</p>
                    </div>
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 portal-text-primary font-bold">
                                    <th class="p-3 pl-4">मांग संदर्भ / पत्रांक</th>
                                    <th class="p-3">सभागार एवं समय</th>
                                    <th class="p-3">बैठक का विषय</th>
                                    <th class="p-3">अध्यक्षता</th>
                                    <th class="p-3 text-center">स्थिति</th>
                                    <th class="p-3 text-right pr-4">आदेश पर्ची</th>
                                </tr>
                            </thead>
                            <tbody id="my-reqs-table-body" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($user_role === 'technical'): ?>
                <div id="section-tech-dashboard" class="portal-section hidden space-y-6">
                    <div class="bg-cyan-50 border-l-4 border-cyan-700 p-4 rounded-r-lg shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-cyan-700 text-white flex items-center justify-center flex-shrink-0">
                                <i data-lucide="video" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="font-bold text-cyan-900 text-sm m-0">सूचना: वी.सी. एवं पी.पी.टी. तकनीकी सहायता कार्य</p>
                                <p class="text-slate-600 m-0 mt-0.5">यह पटल केवल तकनीकी टीम के अवलोकन हेतु है।</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-cyan-100 text-cyan-800 rounded font-mono font-bold text-xs border border-cyan-300">लॉगिन: techteam@nic.in</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg p-4 border-l-4 border-cyan-600 border border-slate-200 shadow-sm">
                            <span class="text-xs text-slate-500 font-bold block uppercase">वी.सी. आवश्यक बैठकें</span>
                            <p id="stat-tech-vc" class="text-2xl font-extrabold text-cyan-700 m-0 mt-1">0</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 border-l-4 border-blue-600 border border-slate-200 shadow-sm">
                            <span class="text-xs text-slate-500 font-bold block uppercase">पी.पी.टी. आवश्यक बैठकें</span>
                            <p id="stat-tech-ppt" class="text-2xl font-extrabold text-blue-700 m-0 mt-1">0</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 border-l-4 border-emerald-600 border border-slate-200 shadow-sm">
                            <span class="text-xs text-slate-500 font-bold block uppercase">स्वीकृत सभागार सत्र</span>
                            <p id="stat-tech-total-approved" class="text-2xl font-extrabold text-emerald-700 m-0 mt-1">0</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-slate-200 bg-slate-50">
                            <h3 class="text-xs font-bold portal-text-primary uppercase tracking-wider m-0 flex items-center gap-1.5">
                                <i data-lucide="clipboard-check" class="w-4 h-4 text-cyan-700"></i> तकनीकी कार्य विवरण एवं उपकरण आवश्यकता
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-100 portal-text-primary font-bold">
                                        <th class="p-3 pl-4">संदर्भ / पत्रांक</th>
                                        <th class="p-3">सभागार व समय</th>
                                        <th class="p-3">बैठक का विषय एवं अध्यक्षता</th>
                                        <th class="p-3">मांगकर्ता विभाग</th>
                                        <th class="p-3 text-center">वी.सी.</th>
                                        <th class="p-3 text-center">पी.पी.टी.</th>
                                        <th class="p-3 text-center">स्थिति</th>
                                        <th class="p-3 text-right pr-4">आदेश पर्ची</th>
                                    </tr>
                                </thead>
                                <tbody id="tech-duty-table-body" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($user_role === 'admin' || $user_role === 'nazarat'): ?>
                <div id="section-dashboard" class="portal-section hidden space-y-6">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="admin-stats-container">
                        <div class="bg-white rounded-lg p-4 border-l-4 border-amber-500 border border-slate-200 shadow-sm">
                            <span class="text-xs text-slate-500 font-bold block uppercase">समीक्षाधीन आवेदन (Pending)</span>
                            <p id="stat-pending" class="text-2xl font-extrabold portal-text-primary m-0 mt-1">0</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 border-l-4 border-emerald-600 border border-slate-200 shadow-sm">
                            <span class="text-xs text-slate-500 font-bold block uppercase">स्वीकृत बैठकें (Sanctioned)</span>
                            <p id="stat-approved" class="text-2xl font-extrabold text-emerald-700 m-0 mt-1">0</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 border-l-4 portal-border-primary border border-slate-200 shadow-sm">
                            <span class="text-xs text-slate-500 font-bold block uppercase">उपलब्ध सभागार</span>
                            <p id="stat-halls-count" class="text-2xl font-extrabold portal-text-primary m-0 mt-1"><?php echo count($halls); ?></p>
                        </div>
                        <div class="bg-white rounded-lg p-4 border-l-4 border-indigo-600 border border-slate-200 shadow-sm">
                            <span class="text-xs text-slate-500 font-bold block uppercase">पंजीकृत अधिकारी</span>
                            <p id="stat-users" class="text-2xl font-extrabold text-indigo-900 m-0 mt-1">0</p>
                        </div>
                    </div>
                </div>

                <?php if ($user_role === 'admin'): ?>
                    <div id="section-halls" class="portal-section hidden space-y-4">
                        <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                            <h2 class="text-sm font-bold portal-text-primary flex items-center gap-2 m-0">
                                <i data-lucide="building-2" class="w-4 h-4 portal-text-primary"></i> सभागार एवं कक्ष प्रबंधन (Admin Exclusive)
                            </h2>
                            <button type="button" id="open-new-hall-modal-btn" class="px-4 py-2 portal-bg-primary hover:opacity-90 text-white font-bold text-xs rounded flex items-center gap-1.5 shadow">
                                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> नया सभागार जोड़ें
                            </button>
                        </div>
                        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50 portal-text-primary font-bold">
                                        <th class="p-3 pl-4">सभागार का नाम</th>
                                        <th class="p-3">स्थान</th>
                                        <th class="p-3 text-center">क्षमता</th>
                                        <th class="p-3">प्रभारी</th>
                                        <th class="p-3">सुविधाएं</th>
                                        <th class="p-3 text-right pr-4">कार्यवाही</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-halls-table-body" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="section-requests" class="portal-section hidden space-y-4">
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                        <h2 class="text-sm font-bold portal-text-primary flex items-center gap-2 m-0">
                            <i data-lucide="check-square" class="w-4 h-4 portal-text-primary"></i> प्रशासनिक समीक्षा एवं स्थिति नियंत्रण पटल
                        </h2>
                        <p class="text-xs text-slate-500 m-0 mt-0.5">नजारत शाखा एवं मुख्य प्रशासक किसी भी समय किसी भी स्लॉट की स्थिति बदल सकते हैं।</p>
                    </div>
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 portal-text-primary font-bold">
                                    <th class="p-3 pl-4">मांग संदर्भ / विभाग</th>
                                    <th class="p-3">सभागार एवं समय</th>
                                    <th class="p-3">विषय एवं अध्यक्षता</th>
                                    <th class="p-3 text-center">प्राथमिकता</th>
                                    <th class="p-3 text-center">वर्तमान स्थिति</th>
                                    <th class="p-3 text-center">स्थिति बदलें</th>
                                    <th class="p-3 text-right pr-4">कार्यवाही</th>
                                </tr>
                            </thead>
                            <tbody id="admin-requests-table-body" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>

                <div id="section-users" class="portal-section hidden space-y-4">
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                        <h2 class="text-sm font-bold portal-text-primary flex items-center gap-2 m-0">
                            <i data-lucide="users" class="w-4 h-4 portal-text-primary"></i>
                            <?php echo $user_role === 'admin' ? 'उपयोगकर्ता प्रबंधन (Super Admin - All Users)' : 'विभागीय नोडल खाता प्रबंधन'; ?>
                        </h2>
                        <button type="button" id="open-new-user-modal-btn" class="px-4 py-2 portal-bg-primary hover:opacity-90 text-white font-bold text-xs rounded flex items-center gap-1.5 shadow">
                            <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> नया खाता बनाएं
                        </button>
                    </div>
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 portal-text-primary font-bold">
                                    <th class="p-3 pl-4">पदाधिकारी का नाम एवं पदनाम</th>
                                    <th class="p-3">विभाग / शाखा</th>
                                    <th class="p-3">सरकारी ईमेल</th>
                                    <?php if ($is_admin): ?>
                                        <th class="p-3 text-center">पासवर्ड</th>
                                    <?php endif; ?>
                                    <th class="p-3 text-center">खाता स्तर</th>
                                    <th class="p-3 text-center">स्थिति</th>
                                    <?php if ($is_admin): ?>
                                        <th class="p-3 text-right pr-4">कार्यवाही</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="admin-users-table-body" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>

                <div id="section-ledger" class="portal-section hidden space-y-4">
                    <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold portal-text-primary flex items-center gap-2 m-0">
                                <i data-lucide="clipboard-list" class="w-4 h-4 portal-text-primary"></i> सभागार आवंटन मास्टर पंजी (Master Ledger)
                            </h2>
                            <p class="text-xs text-slate-500 m-0 mt-0.5">सभागारों के ऐतिहासिक एवं आगामी आवंटनों का संपूर्ण आधिकारिक अभिलेख</p>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <input type="text" id="ledger-search-input" placeholder="खोजें: पत्रांक, विषय..." class="bg-white border border-slate-300 rounded px-3 py-1.5 text-xs text-slate-800 focus:outline-none portal-border-primary w-full sm:w-56">
                            <button type="button" id="export-csv-btn" class="px-3.5 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs rounded flex items-center gap-1.5 shadow whitespace-nowrap">
                                <i data-lucide="download" class="w-3.5 h-3.5"></i> <span>CSV डाउनलोड</span>
                            </button>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 portal-text-primary font-bold">
                                    <th class="p-3 pl-4">मांग संदर्भ / पत्रांक</th>
                                    <th class="p-3">सभागार एवं समय</th>
                                    <th class="p-3">अध्यक्षता</th>
                                    <th class="p-3">बैठक का विषय एवं विभाग</th>
                                    <th class="p-3 text-center">स्थिति</th>
                                    <th class="p-3 text-center">स्थिति नियंत्रण</th>
                                    <th class="p-3 text-right pr-4">कार्यवाही</th>
                                </tr>
                            </thead>
                            <tbody id="admin-ledger-table-body" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- MODAL: LOGIN -->
        <div id="modal-login" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white border-2 portal-border-primary rounded-lg max-w-md w-full p-6 shadow-2xl relative">
                <button type="button" class="modal-close-btn absolute top-3.5 right-3.5 text-slate-400 hover:text-black p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
                <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                    <div class="w-10 h-10 rounded portal-bg-primary text-white flex items-center justify-center flex-shrink-0">
                        <i data-lucide="lock" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold portal-text-primary m-0">विभागीय पोर्टल लॉगिन</h2>
                        <p class="text-xs text-slate-500 m-0">District Conference Hall Portal Login</p>
                    </div>
                </div>
                <form id="ajax-login-form" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">सरकारी ईमेल आईडी</label>
                        <input type="text" id="login-username" required placeholder="ndcsupaul@nic.in" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 focus:outline-none portal-border-primary">
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">पासवर्ड</label>
                        <input type="password" id="login-password" required class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 focus:outline-none portal-border-primary">
                    </div>
                    <div id="login-error-msg" class="hidden text-rose-700 text-xs p-2.5 rounded bg-rose-50 border border-rose-300 font-medium"></div>
                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button type="button" class="modal-close-btn px-4 py-2 rounded text-slate-600 hover:text-black font-medium">रद्द करें</button>
                        <button type="submit" class="px-5 py-2 rounded portal-bg-primary hover:opacity-90 text-white font-bold flex items-center gap-1.5 shadow">
                            <i data-lucide="log-in" class="w-3.5 h-3.5"></i> प्रवेश करें
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: CHANGE PASSWORD -->
        <?php if ($is_logged_in): ?>
            <div id="modal-change-pwd" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white border-2 portal-border-primary rounded-lg max-w-md w-full p-6 shadow-2xl relative">
                    <button type="button" class="modal-close-btn absolute top-3.5 right-3.5 text-slate-400 hover:text-black p-1">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                        <div class="w-10 h-10 rounded portal-bg-primary text-white flex items-center justify-center flex-shrink-0">
                            <i data-lucide="key" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-base font-bold portal-text-primary m-0">पासवर्ड बदलें</h2>
                    </div>
                    <form id="change-pwd-form" class="space-y-3.5 text-xs">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">वर्तमान पासवर्ड *</label>
                            <input type="password" id="cp-current-password" required class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">नया पासवर्ड *</label>
                            <input type="password" id="cp-new-password" required minlength="6" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">नया पासवर्ड पुनः दर्ज करें *</label>
                            <input type="password" id="cp-confirm-password" required minlength="6" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div id="cp-error-msg" class="hidden text-rose-700 text-xs p-2.5 rounded bg-rose-50 border border-rose-300 font-medium"></div>
                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button type="button" class="modal-close-btn px-4 py-2 rounded text-slate-600 hover:text-black">रद्द करें</button>
                            <button type="submit" id="cp-submit-btn" class="px-5 py-2 rounded portal-bg-primary hover:opacity-90 text-white font-bold flex items-center gap-1.5 shadow">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i> पासवर्ड अद्यतित करें
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- MODAL: QUICK INCHARGE -->
        <?php if ($is_admin): ?>
            <div id="modal-quick-incharge" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white border-2 portal-border-primary rounded-lg max-w-md w-full p-5 shadow-2xl relative">
                    <button type="button" class="modal-close-btn absolute top-3.5 right-3.5 text-slate-400 hover:text-black p-1">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                    <div class="flex items-center gap-3 mb-3 pb-2 border-b border-slate-200">
                        <div class="w-9 h-9 rounded portal-bg-primary text-white flex items-center justify-center flex-shrink-0">
                            <i data-lucide="user-check" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold portal-text-primary m-0">नोडल प्राधिकार संपादित करें</h2>
                            <p class="text-[11px] text-slate-500 m-0">सभागार के प्रभारी प्राधिकारी का नाम एवं पदनाम</p>
                        </div>
                    </div>
                    <form id="quick-incharge-form" class="space-y-3 text-xs">
                        <input type="hidden" id="qi-hall-id">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">सभागार: <span id="qi-hall-name" class="portal-text-primary"></span></label>
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">प्रभारी प्राधिकारी / पदनाम (Nodal In-Charge) *</label>
                            <input type="text" id="qi-incharge-input" required class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 focus:outline-none portal-border-primary">
                        </div>
                        <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-200">
                            <button type="button" class="modal-close-btn px-3 py-1.5 rounded text-slate-600 hover:text-black">रद्द करें</button>
                            <button type="submit" id="qi-submit-btn" class="px-4 py-1.5 rounded portal-bg-primary hover:opacity-90 text-white font-bold flex items-center gap-1 shadow-xs">
                                <i data-lucide="save" class="w-3.5 h-3.5"></i> सहेजें / Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- MODAL: HALL CREATE & EDIT -->
        <?php if ($is_admin): ?>
            <div id="modal-hall-edit" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white border-2 portal-border-primary rounded-lg max-w-xl w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                    <button type="button" class="modal-close-btn absolute top-3.5 right-3.5 text-slate-400 hover:text-black p-1">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                        <div class="w-10 h-10 rounded portal-bg-primary text-white flex items-center justify-center flex-shrink-0">
                            <i data-lucide="building" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h2 id="modal-hall-title" class="text-base font-bold portal-text-primary m-0">सभागार विवरण संपादित करें</h2>
                            <p class="text-xs text-slate-500 m-0">District Administration Conference Facility Settings</p>
                        </div>
                    </div>
                    <form id="hall-edit-form" class="space-y-3.5 text-xs">
                        <input type="hidden" id="edit-hall-id">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-700 font-bold mb-1">सभागार का नाम (हिंदी में) *</label>
                                <input type="text" id="edit-hall-name-hi" required placeholder="उदा. लहटन चौधरी सभागार" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                            </div>
                            <div>
                                <label class="block text-slate-700 font-bold mb-1">Hall Name (In English) *</label>
                                <input type="text" id="edit-hall-name" required placeholder="e.g. Lahtan Choudhary Sabhagar" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-700 font-bold mb-1">सीट क्षमता (Capacity) *</label>
                                <input type="number" id="edit-hall-capacity" required min="5" max="1000" placeholder="120" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                            </div>
                            <div>
                                <label class="block text-slate-700 font-bold mb-1">परिसर बैज / श्रेणी *</label>
                                <input type="text" id="edit-hall-badge" required placeholder="उदा. समाहरणालय मुख्य परिसर" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                            </div>
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">स्थान / पता (Wings & Location) *</label>
                            <input type="text" id="edit-hall-wings" required placeholder="उदा. समाहरणालय मुख्य परिसर, सुपौल - 852131" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">प्रभारी प्राधिकारी / पदनाम *</label>
                            <input type="text" id="edit-hall-incharge" required placeholder="उदा. नजारत उप समाहर्ता, सुपौल" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 font-medium">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">सभागार का विवरण (Description)</label>
                            <textarea id="edit-hall-desc" rows="2" placeholder="सभागार का संक्षिप्त विवरण..." class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800"></textarea>
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">सुविधाएं एवं उपकरण (Features - कॉमा से अलग करें)</label>
                            <textarea id="edit-hall-features" rows="2" placeholder="Polycom VC Link, Smart Display Screen, PA System, Dedicated UPS Backup" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800"></textarea>
                        </div>
                        <div id="hall-edit-error-msg" class="hidden text-rose-700 text-xs p-2.5 rounded bg-rose-50 border border-rose-300 font-medium"></div>
                        <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-200">
                            <button type="button" class="modal-close-btn px-4 py-2 rounded text-slate-600 hover:text-black">रद्द करें</button>
                            <button type="submit" id="save-hall-submit-btn" class="px-5 py-2 rounded portal-bg-primary hover:opacity-90 text-white font-bold flex items-center gap-1.5 shadow">
                                <i data-lucide="save" class="w-3.5 h-3.5"></i> सहेजें
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- MODAL: CREATE USER -->
        <?php if ($user_role === 'admin' || $user_role === 'nazarat'): ?>
            <div id="modal-new-user" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white border-2 portal-border-primary rounded-lg max-w-md w-full p-6 shadow-2xl relative">
                    <button type="button" class="modal-close-btn absolute top-3.5 right-3.5 text-slate-400 hover:text-black p-1">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                        <div class="w-10 h-10 rounded portal-bg-primary text-white flex items-center justify-center flex-shrink-0">
                            <i data-lucide="user-plus" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-base font-bold portal-text-primary m-0">
                            <?php echo $user_role === 'admin' ? 'नया उपयोगकर्ता खाता बनाएं' : 'नया विभागीय खाता जोड़ें'; ?>
                        </h2>
                    </div>
                    <form id="create-user-form" class="space-y-3 text-xs">
                        <?php if ($user_role === 'admin'): ?>
                            <div class="bg-blue-50/80 p-2.5 rounded border border-blue-200">
                                <label class="block text-slate-800 font-bold mb-1">खाता स्तर / Role Type *</label>
                                <select id="nu-role" class="w-full bg-white border border-slate-300 rounded px-3 py-1.5 text-slate-800 font-bold">
                                    <option value="officer" selected>1. विभागीय नोडल पदाधिकारी (Department Officer)</option>
                                    <option value="itm">2. तकनीकी नोडल पदाधिकारी (IT Manager / ITM)</option>
                                    <option value="nazarat">3. नजारत उप समाहर्ता (NDC / Nazarat Section)</option>
                                    <option value="technical">4. तकनीकी टीम (Technical Team)</option>
                                    <option value="admin">5. जिला मुख्य प्रशासक (Super Admin / DIO)</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" id="nu-role" value="officer">
                            <div class="bg-slate-100 p-2 rounded text-[11px] text-slate-700 font-semibold">
                                खाता प्रकार: <span class="portal-text-primary">विभागीय नोडल पदाधिकारी (Department Officer)</span>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">पदाधिकारी का पूरा नाम *</label>
                            <input type="text" id="nu-name" required placeholder="उदा. आलोक कुमार" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">पदनाम *</label>
                            <input type="text" id="nu-designation" required placeholder="उदा. अनुमंडल पदाधिकारी" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">विभागीय शाखा *</label>
                            <input type="text" id="nu-department" required placeholder="उदा. राजस्व शाखा, सुपौल" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">सरकारी ईमेल *</label>
                            <input type="email" id="nu-email" required placeholder="officer.supaul@bihar.gov.in" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 font-mono">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">लॉगिन पासवर्ड *</label>
                            <input type="password" id="nu-password" required minlength="6" placeholder="न्यूनतम 6 अक्षर" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div id="user-create-error-msg" class="hidden text-rose-700 text-xs p-2.5 rounded bg-rose-50 border border-rose-300 font-medium"></div>
                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button type="button" class="modal-close-btn px-4 py-2 rounded text-slate-600 hover:text-black">रद्द करें</button>
                            <button type="submit" id="nu-submit-btn" class="px-5 py-2 rounded portal-bg-primary hover:opacity-90 text-white font-bold flex items-center gap-1.5 shadow">
                                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> खाता पंजीकृत करें
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- MODAL: EDIT USER -->
        <?php if ($is_admin): ?>
            <div id="modal-edit-user" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white border-2 portal-border-primary rounded-lg max-w-md w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                    <button type="button" class="modal-close-btn absolute top-3.5 right-3.5 text-slate-400 hover:text-black p-1">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                        <div class="w-10 h-10 rounded portal-bg-primary text-white flex items-center justify-center flex-shrink-0">
                            <i data-lucide="user-cog" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-base font-bold portal-text-primary m-0">उपयोगकर्ता खाता संपादित करें</h2>
                    </div>
                    <form id="edit-user-form" class="space-y-3 text-xs">
                        <input type="hidden" id="eu-id">
                        <div class="bg-blue-50/80 p-2.5 rounded border border-blue-200">
                            <label class="block text-slate-800 font-bold mb-1">खाता स्तर *</label>
                            <select id="eu-role" class="w-full bg-white border border-slate-300 rounded px-3 py-1.5 text-slate-800 font-bold">
                                <option value="officer">1. विभागीय नोडल पदाधिकारी</option>
                                <option value="itm">2. तकनीकी नोडल पदाधिकारी (ITM)</option>
                                <option value="nazarat">3. नजारत उप समाहर्ता (NDC)</option>
                                <option value="technical">4. तकनीकी टीम</option>
                                <option value="admin">5. जिला मुख्य प्रशासक (DIO)</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-700 font-bold mb-1">पदाधिकारी का नाम *</label>
                                <input type="text" id="eu-name" required class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                            </div>
                            <div>
                                <label class="block text-slate-700 font-bold mb-1">खाता स्थिति *</label>
                                <select id="eu-status" class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 font-bold">
                                    <option value="active">सक्रिय (Active)</option>
                                    <option value="suspended">निलंबित (Suspended)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">पदनाम *</label>
                            <input type="text" id="eu-designation" required class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">विभागीय शाखा *</label>
                            <input type="text" id="eu-department" required class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">सरकारी ईमेल *</label>
                            <input type="email" id="eu-email" required class="w-full bg-white border border-slate-300 rounded px-3 py-2 text-slate-800 font-mono">
                        </div>
                        <div class="bg-amber-50/80 p-2.5 rounded border border-amber-200">
                            <label class="block text-amber-900 font-bold mb-1">
                                नया पासवर्ड रीसेट करें
                                <span class="text-slate-500 font-normal block text-[10px]">यदि पासवर्ड नहीं बदलना है तो इसे रिक्त छोड़ें</span>
                            </label>
                            <input type="text" id="eu-password" placeholder="नया पासवर्ड दर्ज करें" class="w-full bg-white border border-amber-300 rounded px-3 py-1.5 text-slate-800 font-mono">
                        </div>
                        <div id="user-edit-error-msg" class="hidden text-rose-700 text-xs p-2.5 rounded bg-rose-50 border border-rose-300 font-medium"></div>
                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button type="button" class="modal-close-btn px-4 py-2 rounded text-slate-600 hover:text-black">रद्द करें</button>
                            <button type="submit" id="eu-submit-btn" class="px-5 py-2 rounded portal-bg-primary hover:opacity-90 text-white font-bold flex items-center gap-1.5 shadow">
                                <i data-lucide="save" class="w-3.5 h-3.5"></i> विवरण सहेजें
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- MODAL: ORDER SLIP -->
        <div id="modal-slip" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white text-slate-900 rounded-lg max-w-xl w-full p-5 sm:p-6 shadow-2xl relative border-4 portal-border-primary">
                <button type="button" class="modal-close-btn absolute top-3.5 right-3.5 text-slate-400 hover:text-black p-1 print:hidden">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
                <div class="text-center pb-3 border-b-2 border-slate-900">
                    <span class="text-[11px] font-bold text-slate-600 uppercase tracking-widest block leading-tight">समाहरणालय सुपौल | जिला प्रशासन, सुपौल (बिहार)</span>
                    <h2 id="slip-heading-title" class="text-base font-black uppercase portal-text-primary mt-0.5 m-0 leading-tight">सभागार आवंटन आदेश</h2>
                    <p id="slip-meta-ref" class="text-[11px] font-mono font-bold text-slate-700 mt-0.5 m-0"></p>
                </div>
                <div class="py-3 space-y-2 text-xs">
                    <div class="flex justify-between border-b border-slate-200 pb-1">
                        <span class="font-bold text-slate-600">आवंटित सभागार:</span>
                        <span id="slip-venue" class="font-bold portal-text-primary text-right">लहटन चौधरी सभागार</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 pb-1">
                        <span class="font-bold text-slate-600">तिथि एवं समयावधि:</span>
                        <span id="slip-time" class="font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 pb-1">
                        <span class="font-bold text-slate-600">अध्यक्षता:</span>
                        <span id="slip-chair" class="font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 pb-1">
                        <span class="font-bold text-slate-600">मांगकर्ता विभाग:</span>
                        <span id="slip-department" class="font-medium text-slate-800"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 pb-1">
                        <span class="font-bold text-slate-600">बैठक का विषय:</span>
                        <span id="slip-subject" class="font-medium text-slate-900 text-right max-w-[280px]"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 pb-1">
                        <span class="font-bold text-slate-600">वी.सी. लिंक:</span>
                        <span id="slip-vc" class="font-bold portal-text-primary"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 pb-1">
                        <span class="font-bold text-slate-600">स्वल्पाहार:</span>
                        <span id="slip-refreshments" class="font-medium text-slate-800"></span>
                    </div>
                    <div class="flex justify-between pt-0.5" id="slip-authority-row">
                        <span class="font-bold text-slate-600">स्वीकृति प्राधिकार:</span>
                        <span id="slip-authority" class="font-bold text-emerald-800"></span>
                    </div>
                </div>
                <div class="pt-3 border-t-2 border-slate-900 flex items-center justify-between text-xs text-slate-700">
                    <div>
                        <span id="slip-dynamic-status" class="font-bold block">स्थिति: समीक्षाधीन (PENDING APPROVAL)</span>
                        <span class="text-[10px] text-slate-500 block">समाहरणालय, सुपौल (बिहार)</span>
                    </div>
                    <div class="text-right" id="slip-signature-block">
                        <span class="font-bold text-slate-900 block">ह०/- (Sd/-)</span>
                        <span id="slip-signer-name" class="font-bold text-slate-900 block"></span>
                        <span id="slip-signer-desig" class="text-[11px] font-semibold text-slate-800 block leading-tight">नजारत उप समाहर्ता</span>
                        <span class="text-[10px] text-slate-500 block leading-tight">सुपौल</span>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-end gap-2 print:hidden border-t border-slate-200 pt-2.5">
                    <button type="button" class="modal-close-btn px-4 py-2 rounded text-xs font-semibold bg-slate-200 text-slate-800 hover:bg-slate-300">बंद करें</button>
                    <button type="button" id="slip-print-btn" class="px-5 py-2 rounded text-xs font-bold portal-bg-primary hover:opacity-90 text-white flex items-center gap-1.5 shadow">
                        <i data-lucide="printer" class="w-4 h-4"></i> पर्ची प्रिंट करें
                    </button>
                </div>
            </div>
        </div>

        <div id="toast-box" class="hidden fixed bottom-6 right-6 z-50 px-4 py-3 rounded-lg shadow-xl border text-xs sm:text-sm font-semibold flex items-center gap-2"></div>
    </div>

    <script>
    (function() {
        const AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';
        const NONCE = '<?php echo esc_js($nonce); ?>';
        const USER_ROLE = '<?php echo esc_js($user_role); ?>';
        const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
        const IS_NAZARAT = <?php echo $is_nazarat ? 'true' : 'false'; ?>;
        const IS_TECHNICAL = <?php echo $is_technical ? 'true' : 'false'; ?>;
        const TODAY_DATE = '<?php echo esc_js($today_date); ?>';

        let hallsData = <?php echo json_encode($halls); ?>;
        let selectedHallId = hallsData.length > 0 ? hallsData[0].id : 'hall-lahtan-choudhary-sabhagar';
        let selectedDate = TODAY_DATE;
        let selectedHours = [];
        let currentDayBookings = [];
        let allRequisitionsCache = [];
        let currentViewingReqId = '';

        const TIME_SLOTS = [
            { hour: 8, label: '08:00 AM - 09:00 AM' },
            { hour: 9, label: '09:00 AM - 10:00 AM' },
            { hour: 10, label: '10:00 AM - 11:00 AM' },
            { hour: 11, label: '11:00 AM - 12:00 PM' },
            { hour: 12, label: '12:00 PM - 01:00 PM' },
            { hour: 13, label: '01:00 PM - 02:00 PM' },
            { hour: 14, label: '02:00 PM - 03:00 PM' },
            { hour: 15, label: '03:00 PM - 04:00 PM' },
            { hour: 16, label: '04:00 PM - 05:00 PM' },
            { hour: 17, label: '05:00 PM - 06:00 PM' },
            { hour: 18, label: '06:00 PM - 07:00 PM' },
            { hour: 19, label: '07:00 PM - 08:00 PM' }
        ];

        function initIcons() {
            if (window.lucide) window.lucide.createIcons();
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-box');
            if (!toast) return;
            toast.className = `fixed bottom-6 right-6 z-50 px-4 py-3 rounded-lg shadow-xl border text-xs sm:text-sm font-semibold flex items-center gap-2 ${
                type === 'error' ? 'bg-rose-50 text-rose-900 border-rose-300' : 'bg-emerald-50 text-emerald-900 border-emerald-300'
            }`;
            toast.innerHTML = `<i data-lucide="${type === 'error' ? 'alert-circle' : 'check-circle-2'}" class="w-4 h-4 text-${type === 'error' ? 'rose-700' : 'emerald-700'}"></i> <span>${message}</span>`;
            toast.classList.remove('hidden');
            initIcons();
            setTimeout(() => toast.classList.add('hidden'), 4500);
        }

        function isSlotExpired(slotHour, dateStr) {
            const now = new Date();
            const chosenDate = new Date(dateStr + 'T00:00:00');
            const todayMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate());

            if (chosenDate < todayMidnight) return true;
            if (chosenDate > todayMidnight) return false;

            const currentHour = now.getHours();
            return (slotHour <= currentHour);
        }

        function renderVenuesList() {
            const container = document.getElementById('venue-list-container');
            if (!container) return;
            container.innerHTML = '';

            if (hallsData.length === 0) {
                container.innerHTML = '<p class="text-xs text-slate-500">कोई सभागार उपलब्ध नहीं है।</p>';
                return;
            }

            if (!hallsData.some(h => h.id === selectedHallId)) {
                selectedHallId = hallsData[0].id;
            }

            hallsData.forEach(hall => {
                const isSelected = hall.id === selectedHallId;
                const card = document.createElement('div');
                card.className = `venue-card rounded-lg p-3.5 border transition-all text-left cursor-pointer ${
                    isSelected ? 'bg-blue-50/80 portal-border-primary shadow-sm ring-1 ring-slate-400' : 'bg-white hover:bg-slate-50 border-slate-200'
                }`;

                card.innerHTML = `
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-xs font-bold portal-text-primary m-0">${hall.name_hi || hall.name}</h3>
                            <p class="text-[11px] font-semibold text-slate-600 m-0">${hall.name}</p>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                            ${hall.badge || 'समाहरणालय'}
                        </span>
                    </div>
                    <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                        <span class="text-[11px] font-medium">क्षमता: ${hall.capacity} सीट्स</span>
                        <span class="text-[10px] portal-text-primary font-semibold truncate">${hall.in_charge}</span>
                    </div>
                `;

                card.addEventListener('click', () => {
                    selectedHallId = hall.id;
                    selectedHours = [];
                    updateSelectionBar();
                    renderVenuesList();
                    loadSchedule();
                });

                container.appendChild(card);
            });

            updateActiveHallBanner();
            initIcons();
        }

        function updateActiveHallBanner() {
            const hall = hallsData.find(h => h.id === selectedHallId);
            if (!hall) return;
            
            const nameEl = document.getElementById('current-hall-name');
            const capEl = document.getElementById('current-hall-capacity-badge');
            const wingsEl = document.getElementById('current-hall-wings');
            const descEl = document.getElementById('current-hall-desc');
            const inchargeEl = document.getElementById('current-hall-incharge');
            const sumVenueEl = document.getElementById('summary-venue');
            const sumCapEl = document.getElementById('summary-capacity');
            const featsContainer = document.getElementById('current-hall-features');

            if (nameEl) nameEl.textContent = `${hall.name_hi || hall.name} (${hall.name})`;
            if (capEl) capEl.textContent = `क्षमता: ${hall.capacity} सीट्स`;
            if (wingsEl) wingsEl.textContent = hall.wings || '';
            if (descEl) descEl.textContent = hall.description || 'जिला समाहरणालय सुपौल का सुसज्जित सभागार।';
            if (inchargeEl) inchargeEl.textContent = hall.in_charge || 'नजारत उप समाहर्ता, सुपौल';
            if (sumVenueEl) sumVenueEl.textContent = hall.name_hi || hall.name;
            if (sumCapEl) sumCapEl.textContent = `${hall.capacity} Seats`;

            if (featsContainer) {
                featsContainer.innerHTML = '';
                const features = Array.isArray(hall.features) ? hall.features : [];
                if (features.length === 0) {
                    featsContainer.innerHTML = '<span class="text-[11px] text-slate-500 italic">मानक बैठक व्यवस्था उपलब्ध</span>';
                } else {
                    features.forEach(f => {
                        const badge = document.createElement('span');
                        badge.className = 'text-[10px] px-2.5 py-1 rounded bg-slate-100 text-slate-700 border border-slate-200 flex items-center gap-1 font-medium shadow-2xs';
                        badge.innerHTML = `<i data-lucide="check-circle" class="w-3 h-3 text-emerald-600"></i> ${f}`;
                        featsContainer.appendChild(badge);
                    });
                }
            }
            initIcons();
        }

        const quickInchargeBtn = document.getElementById('quick-edit-incharge-btn');
        if (quickInchargeBtn) {
            quickInchargeBtn.addEventListener('click', () => {
                const hall = hallsData.find(h => h.id === selectedHallId);
                if (!hall) return;
                const modal = document.getElementById('modal-quick-incharge');
                if (!modal) return;

                document.getElementById('qi-hall-id').value = hall.id;
                document.getElementById('qi-hall-name').textContent = hall.name_hi || hall.name;
                document.getElementById('qi-incharge-input').value = hall.in_charge || '';

                modal.classList.remove('hidden');
                initIcons();
            });
        }

        const quickInchargeForm = document.getElementById('quick-incharge-form');
        if (quickInchargeForm) {
            quickInchargeForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const submitBtn = document.getElementById('qi-submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'अद्यतित हो रहा है...';
                }

                const formData = new FormData();
                formData.append('action', 'supaul_quick_update_incharge');
                formData.append('security', NONCE);
                formData.append('hall_id', document.getElementById('qi-hall-id').value);
                formData.append('in_charge', document.getElementById('qi-incharge-input').value.trim());

                fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i> सहेजें / Update';
                        }
                        if (res.success) {
                            showToast(res.data.message || 'नोडल प्राधिकार अद्यतित किया गया');
                            document.getElementById('modal-quick-incharge').classList.add('hidden');
                            loadAdminData();
                        } else {
                            showToast(res.data || 'त्रुटि हुई', 'error');
                        }
                    })
                    .catch(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i> सहेजें / Update';
                        }
                        showToast('नेटवर्क त्रुटि', 'error');
                    });
            });
        }

        function switchTab(tabId) {
            document.querySelectorAll('.portal-section').forEach(sec => sec.classList.add('hidden'));
            document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                btn.classList.remove('active', 'portal-border-accent', 'text-white', 'font-bold');
                btn.classList.add('border-transparent', 'text-slate-200');
            });

            const activeSec = document.getElementById('section-' + tabId);
            const activeBtn = document.getElementById('tab-btn-' + tabId);

            if (activeSec) activeSec.classList.remove('hidden');
            if (activeBtn) {
                activeBtn.classList.add('active', 'portal-border-accent', 'text-white', 'font-bold');
                activeBtn.classList.remove('border-transparent', 'text-slate-200');
            }

            if (tabId === 'dashboard' || tabId === 'requests' || tabId === 'ledger' || tabId === 'users' || tabId === 'halls' || tabId === 'tech-dashboard') {
                loadAdminData();
            } else if (tabId === 'my-reqs') {
                loadMyRequisitions();
            }
            initIcons();
        }

        document.querySelectorAll('.nav-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.id.replace('tab-btn-', '')));
        });

        function loadSchedule() {
            renderSlotsGrid();
            const formData = new FormData();
            formData.append('action', 'supaul_get_schedule');
            formData.append('security', NONCE);
            formData.append('hall_id', selectedHallId);
            formData.append('date', selectedDate);

            fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        currentDayBookings = res.data || [];
                        renderSlotsGrid();
                    }
                });
        }

        function renderSlotsGrid() {
            const container = document.getElementById('slots-grid-container');
            if (!container) return;
            container.innerHTML = '';

            const bookedMap = {};
            if (Array.isArray(currentDayBookings)) {
                currentDayBookings.forEach(b => {
                    let hrs = [];
                    try { hrs = typeof b.hours === 'string' ? JSON.parse(b.hours) : b.hours; } catch(e) { hrs = []; }
                    if (Array.isArray(hrs)) {
                        hrs.forEach(h => {
                            if (b.status === 'Approved') bookedMap[h] = { type: 'approved', booking: b };
                            else if (b.status === 'Pending' && !bookedMap[h]) bookedMap[h] = { type: 'pending', booking: b };
                        });
                    }
                });
            }

            TIME_SLOTS.forEach(slot => {
                const bookingInfo = bookedMap[slot.hour];
                const isApproved = bookingInfo && bookingInfo.type === 'approved';
                const isPending = bookingInfo && bookingInfo.type === 'pending';
                const isSelected = selectedHours.includes(slot.hour);
                const isExpired = isSlotExpired(slot.hour, selectedDate);

                const card = document.createElement('button');
                card.type = 'button';
                card.disabled = isApproved || isExpired;

                card.className = `p-3 rounded-lg border text-left transition-all flex flex-col justify-between min-h-[96px] ${
                    isApproved ? 'bg-rose-50 border-rose-300 cursor-not-allowed text-slate-700' :
                    isExpired ? 'bg-slate-100 border-slate-200 cursor-not-allowed text-slate-400 opacity-60' :
                    isSelected ? 'bg-blue-100 portal-border-primary ring-2 ring-slate-400' :
                    isPending ? 'bg-amber-50 border-amber-300 hover:border-amber-400' :
                    'bg-white hover:bg-slate-50 border-slate-200'
                }`;

                card.innerHTML = `
                    <div class="flex items-center justify-between w-full">
                        <span class="text-xs font-mono font-bold ${isSelected ? 'portal-text-primary' : isApproved ? 'text-rose-800' : 'text-slate-800'}">
                            ${slot.label}
                        </span>
                        ${isApproved ? '<span class="text-[9px] px-2 py-0.5 rounded bg-rose-100 text-rose-800 border border-rose-300 font-bold">आरक्षित</span>' :
                          isExpired ? '<span class="text-[9px] px-2 py-0.5 rounded bg-slate-200 text-slate-600 border border-slate-300 font-bold">समय समाप्त</span>' :
                          isPending ? '<span class="text-[9px] px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-300 font-bold">समीक्षाधीन</span>' :
                          isSelected ? '<span class="text-[9px] px-2 py-0.5 rounded portal-bg-primary text-white font-bold">चयनित</span>' :
                          '<span class="text-[9px] px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-300 font-bold">रिक्त</span>'}
                    </div>
                    <div class="mt-1.5 text-xs w-full">
                        ${isApproved ? `<span class="font-bold text-rose-950 text-[11px] block truncate">${bookingInfo.booking.subject}</span>` :
                          isExpired ? `<span class="text-[10px] text-slate-400">समय समाप्त / अनुपलब्ध</span>` :
                          isPending ? `<span class="text-amber-950 font-semibold text-[11px] block truncate">${bookingInfo.booking.subject}</span>` :
                          isSelected ? '<span class="portal-text-primary text-xs font-bold">स्लॉट चुना गया</span>' :
                          '<span class="text-slate-500 text-[11px]">चयन करने हेतु क्लिक करें</span>'}
                    </div>
                `;

                if (!isApproved && !isExpired) {
                    card.addEventListener('click', () => {
                        if (selectedHours.includes(slot.hour)) {
                            selectedHours = selectedHours.filter(h => h !== slot.hour);
                        } else {
                            selectedHours.push(slot.hour);
                            selectedHours.sort((a, b) => a - b);
                        }
                        updateSelectionBar();
                        renderSlotsGrid();
                    });
                }

                container.appendChild(card);
            });
            initIcons();
        }

        function updateSelectionBar() {
            const countLabel = document.getElementById('selected-slot-count-label');
            const badgesContainer = document.getElementById('selected-slot-badges');
            const clearBtn = document.getElementById('clear-selected-slots-btn');
            const proceedBtn = document.getElementById('proceed-requisition-btn');

            if (selectedHours.length > 0) {
                countLabel.textContent = `${selectedHours.length} घंटे का स्लॉट चयनित`;
                badgesContainer.innerHTML = `<span class="text-xs font-mono font-bold portal-text-primary bg-blue-50 px-2 py-0.5 rounded border border-blue-200">${selectedHours.map(h => `${h}:00`).join(', ')}</span>`;
                clearBtn.classList.remove('hidden');
                if (proceedBtn) {
                    proceedBtn.disabled = false;
                    proceedBtn.className = 'w-full sm:w-auto px-5 py-2 rounded font-bold text-xs flex items-center justify-center gap-1.5 transition portal-bg-primary hover:opacity-90 text-white shadow';
                }
            } else {
                countLabel.textContent = 'कोई स्लॉट चयनित नहीं';
                badgesContainer.innerHTML = '';
                clearBtn.classList.add('hidden');
                if (proceedBtn) {
                    proceedBtn.disabled = true;
                    proceedBtn.className = 'w-full sm:w-auto px-5 py-2 rounded font-semibold text-xs flex items-center justify-center gap-1.5 transition bg-slate-300 text-slate-500 cursor-not-allowed border border-slate-300';
                }
            }
        }

        const datePicker = document.getElementById('calendar-date-picker');
        const displayDateLabel = document.getElementById('display-date-label');

        function setDate(newDateStr) {
            if (newDateStr < TODAY_DATE) {
                showToast('पूर्व की तिथि (Back Date) का चयन मान्य नहीं है।', 'error');
                return;
            }
            selectedDate = newDateStr;
            if (datePicker) datePicker.value = newDateStr;
            const d = new Date(newDateStr + 'T00:00:00');
            if (displayDateLabel) {
                displayDateLabel.innerHTML = `<i data-lucide="calendar" class="w-4 h-4 portal-text-primary"></i> ${d.toLocaleDateString('hi-IN', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}`;
            }
            selectedHours = [];
            updateSelectionBar();
            loadSchedule();
            initIcons();
        }

        if (datePicker) datePicker.addEventListener('change', (e) => setDate(e.target.value));

        const todayBtn = document.getElementById('today-btn');
        if (todayBtn) todayBtn.addEventListener('click', () => setDate(TODAY_DATE));

        const prevDayBtn = document.getElementById('prev-day-btn');
        if (prevDayBtn) prevDayBtn.addEventListener('click', () => {
            const curr = new Date(selectedDate + 'T00:00:00');
            curr.setDate(curr.getDate() - 1);
            const prevStr = curr.toISOString().split('T')[0];
            setDate(prevStr);
        });

        const nextDayBtn = document.getElementById('next-day-btn');
        if (nextDayBtn) nextDayBtn.addEventListener('click', () => {
            const curr = new Date(selectedDate + 'T00:00:00');
            curr.setDate(curr.getDate() + 1);
            setDate(curr.toISOString().split('T')[0]);
        });

        const clearBtn = document.getElementById('clear-selected-slots-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                selectedHours = [];
                updateSelectionBar();
                renderSlotsGrid();
            });
        }

        const proceedBtn = document.getElementById('proceed-requisition-btn');
        if (proceedBtn) {
            proceedBtn.addEventListener('click', () => {
                if (selectedHours.length === 0) {
                    showToast('कृपया कम से कम एक खुला स्लॉट चुनें।', 'error');
                    return;
                }
                document.getElementById('summary-date').textContent = selectedDate;
                document.getElementById('summary-hours').textContent = selectedHours.map(h => `${h}:00`).join(', ');
                switchTab('new-req');
            });
        }

        const cancelReqBtn = document.getElementById('cancel-req-btn');
        const cancelReqBtn2 = document.getElementById('cancel-req-btn-2');
        if (cancelReqBtn) cancelReqBtn.addEventListener('click', () => switchTab('schedule'));
        if (cancelReqBtn2) cancelReqBtn2.addEventListener('click', () => switchTab('schedule'));

        const reqForm = document.getElementById('requisition-form');
        if (reqForm) {
            reqForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData();
                formData.append('action', 'supaul_submit_requisition');
                formData.append('security', NONCE);
                formData.append('hall_id', selectedHallId);
                formData.append('date', selectedDate);
                formData.append('hours', JSON.stringify(selectedHours));
                formData.append('subject', document.getElementById('req-subject').value.trim());
                formData.append('agenda', document.getElementById('req-agenda').value.trim());
                formData.append('chairing_officer', document.getElementById('req-chair').value.trim());
                formData.append('memo_no', document.getElementById('req-memo').value.trim());
                formData.append('priority', document.getElementById('req-priority').value);
                formData.append('attendees', document.getElementById('req-attendees').value);
                formData.append('vc_required', document.getElementById('req-vc')?.checked ? 1 : 0);
                formData.append('ppt_required', document.getElementById('req-ppt')?.checked ? 1 : 0);
                formData.append('pa_required', document.getElementById('req-pa')?.checked ? 1 : 0);
                formData.append('projector_required', document.getElementById('req-proj')?.checked ? 1 : 0);
                formData.append('refreshments', document.getElementById('req-refreshments').value);

                fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            showToast(res.data.message || 'मांग-पत्र सफलतापूर्वक दर्ज किया गया!');
                            reqForm.reset();
                            selectedHours = [];
                            updateSelectionBar();
                            loadSchedule();
                            loadNotifications();
                            if (USER_ROLE === 'admin' || USER_ROLE === 'nazarat') {
                                switchTab('requests');
                            } else {
                                switchTab('my-reqs');
                            }
                        } else {
                            showToast(res.data || 'मांग-पत्र दर्ज करने में त्रुटि हुई', 'error');
                        }
                    });
            });
        }

        function loadNotifications() {
            const formData = new FormData();
            formData.append('action', 'supaul_get_notifications');
            formData.append('security', NONCE);

            fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const notifs = res.data.notifications || [];
                        const unread = res.data.unread_count || 0;
                        const badge = document.getElementById('notif-unread-badge');
                        const list = document.getElementById('notif-list-container');

                        if (badge) {
                            if (unread > 0) {
                                badge.textContent = unread;
                                badge.classList.remove('hidden');
                            } else {
                                badge.classList.add('hidden');
                            }
                        }

                        if (list) {
                            if (notifs.length === 0) {
                                list.innerHTML = '<p class="p-3 text-center text-slate-500 text-xs">कोई नई सूचना नहीं है।</p>';
                            } else {
                                list.innerHTML = '';
                                notifs.forEach(n => {
                                    const item = document.createElement('div');
                                    item.className = `p-2.5 px-3 transition ${n.is_read == 0 ? 'bg-amber-50/70 font-semibold' : 'bg-white'}`;
                                    item.innerHTML = `
                                        <p class="text-slate-800 m-0 leading-tight">${n.message}</p>
                                        <span class="text-[10px] text-slate-400 mt-1 block">${n.created_at}</span>
                                    `;
                                    list.appendChild(item);
                                });
                            }
                        }
                    }
                });
        }

        const notifToggleBtn = document.getElementById('notif-toggle-btn');
        const notifDropdown = document.getElementById('notif-dropdown');
        if (notifToggleBtn && notifDropdown) {
            notifToggleBtn.addEventListener('click', () => notifDropdown.classList.toggle('hidden'));
        }

        const notifMarkReadBtn = document.getElementById('notif-mark-read-btn');
        if (notifMarkReadBtn) {
            notifMarkReadBtn.addEventListener('click', () => {
                const formData = new FormData();
                formData.append('action', 'supaul_mark_notifications_read');
                formData.append('security', NONCE);
                fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(() => loadNotifications());
            });
        }

        function loadMyRequisitions() {
            const formData = new FormData();
            formData.append('action', 'supaul_get_my_requisitions');
            formData.append('security', NONCE);

            fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const tbody = document.getElementById('my-reqs-table-body');
                        if (!tbody) return;
                        tbody.innerHTML = '';
                        if (res.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-6 text-slate-500">अभी तक कोई मांग-पत्र प्रस्तुत नहीं किया गया है।</td></tr>';
                            return;
                        }
                        res.data.forEach(r => {
                            let hrs = '';
                            try { hrs = JSON.parse(r.hours).map(h => `${h}:00`).join(', '); } catch(e) { hrs = r.hours; }

                            const tr = document.createElement('tr');
                            tr.className = 'hover:bg-slate-50 transition';
                            tr.innerHTML = `
                                <td class="p-3 pl-4 font-mono font-bold portal-text-primary">${r.id}</td>
                                <td class="p-3">${r.booking_date} (${hrs})</td>
                                <td class="p-3">${r.subject}</td>
                                <td class="p-3">${r.chairing_officer}</td>
                                <td class="p-3 text-center">
                                    <span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold ${
                                        r.status === 'Approved' ? 'bg-emerald-50 text-emerald-800 border border-emerald-300' :
                                        r.status === 'Pending' ? 'bg-amber-50 text-amber-800 border border-amber-300' :
                                        'bg-rose-50 text-rose-800 border border-rose-300'
                                    }">${r.status === 'Approved' ? 'स्वीकृत' : r.status === 'Pending' ? 'समीक्षाधीन' : 'अस्वीकृत'}</span>
                                </td>
                                <td class="p-3 text-right pr-4">
                                    <button type="button" class="view-slip-btn px-2.5 py-1 rounded portal-bg-primary text-white text-[11px] font-bold" data-req='${JSON.stringify(r)}'>आदेश पर्ची</button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });
                        bindSlipButtons();
                    }
                });
        }

        function loadAdminData() {
            const formData = new FormData();
            formData.append('action', 'supaul_get_admin_data');
            formData.append('security', NONCE);

            fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const { stats, requisitions, users, halls } = res.data;
                        allRequisitionsCache = requisitions;

                        if (IS_TECHNICAL) renderTechDutyBoard(requisitions);

                        if (halls && Array.isArray(halls)) {
                            hallsData = halls.map(h => {
                                h.features = typeof h.features === 'string' ? JSON.parse(h.features || '[]') : h.features;
                                return h;
                            });
                            renderVenuesList();
                            if (IS_ADMIN) renderAdminHallsTable();
                        }

                        if (document.getElementById('stat-pending')) document.getElementById('stat-pending').textContent = stats.pending;
                        if (document.getElementById('stat-approved')) document.getElementById('stat-approved').textContent = stats.approved;
                        if (document.getElementById('stat-users')) document.getElementById('stat-users').textContent = stats.users_count;
                        if (document.getElementById('badge-pending-count')) document.getElementById('badge-pending-count').textContent = stats.pending;
                        if (document.getElementById('stat-halls-count')) document.getElementById('stat-halls-count').textContent = hallsData.length;

                        const reqTbody = document.getElementById('admin-requests-table-body');
                        if (reqTbody) {
                            reqTbody.innerHTML = '';
                            requisitions.forEach(r => {
                                let hrs = '';
                                try { hrs = JSON.parse(r.hours).map(h => `${h}:00`).join(', '); } catch(e) { hrs = r.hours; }

                                const tr = document.createElement('tr');
                                tr.className = 'hover:bg-slate-50 transition';
                                tr.innerHTML = `
                                    <td class="p-3 pl-4">
                                        <span class="font-mono font-bold portal-text-primary block">${r.id}</span>
                                        <span class="text-[11px] text-slate-700 block">${r.department}</span>
                                    </td>
                                    <td class="p-3">${r.booking_date} (${hrs})</td>
                                    <td class="p-3">
                                        <span class="font-semibold block truncate max-w-xs">${r.subject}</span>
                                        <span class="text-[11px] portal-text-primary">अध्यक्षता: ${r.chairing_officer}</span>
                                    </td>
                                    <td class="p-3 text-center font-bold text-xs">${r.priority}</td>
                                    <td class="p-3 text-center">
                                        <span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold ${
                                            r.status === 'Approved' ? 'bg-emerald-50 text-emerald-800 border border-emerald-300' :
                                            r.status === 'Pending' ? 'bg-amber-50 text-amber-800 border border-amber-300' :
                                            'bg-rose-50 text-rose-800 border border-rose-300'
                                        }">${r.status === 'Approved' ? 'स्वीकृत' : r.status === 'Pending' ? 'समीक्षाधीन' : 'अस्वीकृत'}</span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <select class="change-status-select text-xs border border-slate-300 rounded px-2 py-1 bg-white font-medium" data-id="${r.id}">
                                            <option value="Pending" ${r.status === 'Pending' ? 'selected' : ''}>समीक्षाधीन (Pending)</option>
                                            <option value="Approved" ${r.status === 'Approved' ? 'selected' : ''}>स्वीकृत (Approved)</option>
                                            <option value="Rejected" ${r.status === 'Rejected' ? 'selected' : ''}>अस्वीकृत (Rejected)</option>
                                        </select>
                                    </td>
                                    <td class="p-3 text-right pr-4">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" class="view-slip-btn px-2.5 py-1 text-white portal-bg-primary rounded text-[11px] font-bold" data-req='${JSON.stringify(r)}'>पर्ची</button>
                                            ${IS_ADMIN ? `<button type="button" class="delete-booking-btn px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-[11px] font-bold" data-id="${r.id}" title="बुकिंग हमेशा के लिए हटाएं"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>` : ''}
                                        </div>
                                    </td>
                                `;
                                reqTbody.appendChild(tr);
                            });
                        }

                        const userTbody = document.getElementById('admin-users-table-body');
                        if (userTbody) {
                            userTbody.innerHTML = '';
                            users.forEach(u => {
                                const roleLabel = u.role === 'nazarat' ? 'नजारत उप समाहर्ता (NDC)' :
                                                  u.role === 'technical' ? 'तकनीकी टीम (Technical)' :
                                                  u.role === 'itm' ? 'तकनीकी नोडल पदाधिकारी (ITM)' :
                                                  u.role === 'admin' ? 'जिला मुख्य प्रशासक (DIO)' : 'विभागीय नोडल पदाधिकारी';
                                const roleBadgeClass = u.role === 'nazarat' ? 'bg-amber-50 text-amber-900 border-amber-300' :
                                                       u.role === 'technical' ? 'bg-cyan-50 text-cyan-900 border-cyan-300' :
                                                       u.role === 'itm' ? 'bg-purple-50 text-purple-900 border-purple-300' :
                                                       u.role === 'admin' ? 'bg-rose-50 text-rose-900 border-rose-300' :
                                                       'bg-blue-50 text-blue-900 border-blue-200';

                                const isSuspended = (u.status === 'suspended');
                                const tr = document.createElement('tr');
                                tr.className = 'hover:bg-slate-50 transition';

                                let credCellHtml = '';
                                if (IS_ADMIN) {
                                    const plainPwd = u.plain_password || '******';
                                    credCellHtml = `
                                        <td class="p-3 text-center">
                                            <div class="inline-flex items-center gap-1 bg-slate-100 border border-slate-300 px-2 py-1 rounded text-[11px] font-mono">
                                                <span class="cred-text" data-password="${plainPwd}">••••••••</span>
                                                <button type="button" class="toggle-pwd-view text-slate-500 hover:text-black p-0.5" title="पासवर्ड देखें / छुपाएं">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                </button>
                                                <button type="button" class="copy-pwd-btn text-slate-500 hover:text-emerald-700 p-0.5" data-pwd="${plainPwd}" title="पासवर्ड कॉपी करें">
                                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </td>
                                    `;
                                }

                                let actionsCellHtml = '';
                                if (IS_ADMIN) {
                                    actionsCellHtml = `
                                        <td class="p-3 text-right pr-4">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button type="button" class="edit-user-btn px-2.5 py-1 rounded portal-bg-primary hover:opacity-90 text-white text-[11px] font-bold inline-flex items-center gap-1 shadow-xs" data-user='${JSON.stringify(u)}'>
                                                    <i data-lucide="pencil" class="w-3 h-3"></i> संपादन
                                                </button>
                                                <button type="button" class="delete-user-btn px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 text-[11px] font-bold" data-id="${u.id}" data-name="${u.name}" title="खाता हटाएं">
                                                    <i data-lucide="trash-2" class="w-3 h-3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    `;
                                }

                                tr.innerHTML = `
                                    <td class="p-3 pl-4">
                                        <span class="font-bold text-slate-800 block">${u.name}</span>
                                        <span class="text-[11px] portal-text-primary font-semibold block">${u.designation || 'नोडल पदाधिकारी'}</span>
                                    </td>
                                    <td class="p-3 text-slate-700 font-medium">${u.department || 'सामान्य प्रशासन'}</td>
                                    <td class="p-3 font-mono text-slate-800">${u.email}</td>
                                    ${credCellHtml}
                                    <td class="p-3 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] border font-bold ${roleBadgeClass}">
                                            ${roleLabel}
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] ${isSuspended ? 'bg-rose-50 text-rose-800 border border-rose-300' : 'bg-emerald-50 text-emerald-800 border border-emerald-300'} font-bold">
                                            ${isSuspended ? 'निलंबित' : 'सक्रिय'}
                                        </span>
                                    </td>
                                    ${actionsCellHtml}
                                `;
                                userTbody.appendChild(tr);
                            });
                            bindUserTableActions();
                        }

                        renderLedgerTable(requisitions);
                        bindStatusChangeEvents();
                        bindDeleteBookingEvents();
                        bindSlipButtons();
                        initIcons();
                    }
                });
        }

        function renderAdminHallsTable() {
            if (!IS_ADMIN) return;
            const tbody = document.getElementById('admin-halls-table-body');
            if (!tbody) return;
            tbody.innerHTML = '';

            hallsData.forEach(hall => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 transition';
                const feats = Array.isArray(hall.features) ? hall.features.slice(0, 3).join(', ') : '';

                tr.innerHTML = `
                    <td class="p-3 pl-4">
                        <span class="font-bold portal-text-primary block text-xs">${hall.name_hi || hall.name}</span>
                        <span class="text-[11px] text-slate-600 font-semibold block">${hall.name}</span>
                        <span class="text-[9px] font-mono text-slate-500">ID: ${hall.id}</span>
                    </td>
                    <td class="p-3 text-slate-700 text-xs">
                        <span class="font-semibold block">${hall.badge}</span>
                        <span class="text-[11px] text-slate-500 block truncate max-w-xs">${hall.wings}</span>
                    </td>
                    <td class="p-3 text-center">
                        <span class="font-bold text-xs portal-text-primary bg-blue-50 px-2 py-0.5 rounded border border-blue-200">${hall.capacity} Seats</span>
                    </td>
                    <td class="p-3 text-slate-800 font-medium text-xs">${hall.in_charge}</td>
                    <td class="p-3 text-slate-600 text-[11px] max-w-xs truncate">${feats}</td>
                    <td class="p-3 text-right pr-4">
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" class="edit-hall-btn px-2.5 py-1 portal-bg-primary hover:opacity-90 text-white rounded text-[11px] font-bold inline-flex items-center gap-1 shadow-xs" data-hall-id="${hall.id}">
                                <i data-lucide="pencil" class="w-3 h-3"></i> संपादन
                            </button>
                            ${hallsData.length > 1 ? `
                                <button type="button" class="delete-hall-btn px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 rounded text-[11px] font-bold" data-hall-id="${hall.id}" title="हटाएं">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            bindHallManagementEvents();
            initIcons();
        }

        function openHallModal(hall = null) {
            if (!IS_ADMIN) return;
            const modal = document.getElementById('modal-hall-edit');
            const errBox = document.getElementById('hall-edit-error-msg');
            if (errBox) errBox.classList.add('hidden');
            if (!modal) return;

            if (hall) {
                document.getElementById('modal-hall-title').textContent = 'सभागार विवरण संपादित करें (Edit Hall)';
                document.getElementById('edit-hall-id').value = hall.id;
                document.getElementById('edit-hall-name').value = hall.name || '';
                document.getElementById('edit-hall-name-hi').value = hall.name_hi || '';
                document.getElementById('edit-hall-capacity').value = hall.capacity || 100;
                document.getElementById('edit-hall-badge').value = hall.badge || 'समाहरणालय परिसर';
                document.getElementById('edit-hall-wings').value = hall.wings || '';
                document.getElementById('edit-hall-incharge').value = hall.in_charge || '';
                document.getElementById('edit-hall-desc').value = hall.description || '';
                document.getElementById('edit-hall-features').value = Array.isArray(hall.features) ? hall.features.join(', ') : '';
            } else {
                document.getElementById('modal-hall-title').textContent = 'नया सभागार जोड़ें (Add New Facility)';
                document.getElementById('edit-hall-id').value = '';
                document.getElementById('edit-hall-name').value = '';
                document.getElementById('edit-hall-name-hi').value = '';
                document.getElementById('edit-hall-capacity').value = 80;
                document.getElementById('edit-hall-badge').value = 'समाहरणालय';
                document.getElementById('edit-hall-wings').value = 'समाहरणालय परिसर, सुपौल';
                document.getElementById('edit-hall-incharge').value = 'नजारत उप समाहर्ता, सुपौल';
                document.getElementById('edit-hall-desc').value = '';
                document.getElementById('edit-hall-features').value = 'Polycom VC Link, Smart Display Screen, PA System, Dedicated UPS Backup';
            }

            modal.classList.remove('hidden');
            initIcons();
        }

        function bindHallManagementEvents() {
            if (!IS_ADMIN) return;

            document.querySelectorAll('.edit-hall-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const hId = btn.dataset.hallId;
                    const hall = hallsData.find(h => h.id === hId);
                    if (hall) openHallModal(hall);
                });
            });

            document.querySelectorAll('.delete-hall-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const hId = btn.dataset.hallId;
                    const hall = hallsData.find(h => h.id === hId);
                    if (!hall) return;

                    if (!confirm(`क्या आप वाकई सभागार "${hall.name_hi || hall.name}" को हटाना चाहते हैं?`)) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'supaul_delete_hall');
                    formData.append('security', NONCE);
                    formData.append('id', hId);

                    fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                showToast(res.data.message || 'सभागार हटाया गया');
                                loadAdminData();
                                loadSchedule();
                            } else {
                                showToast(res.data || 'हटाने में त्रुटि', 'error');
                            }
                        });
                });
            });
        }

        const quickAddBtn = document.getElementById('quick-add-hall-btn');
        const openNewHallBtn = document.getElementById('open-new-hall-modal-btn');
        if (quickAddBtn) quickAddBtn.addEventListener('click', () => openHallModal(null));
        if (openNewHallBtn) openNewHallBtn.addEventListener('click', () => openHallModal(null));

        const editCurrentHallBtn = document.getElementById('edit-current-hall-btn');
        if (editCurrentHallBtn) {
            editCurrentHallBtn.addEventListener('click', () => {
                const hall = hallsData.find(h => h.id === selectedHallId);
                if (hall) openHallModal(hall);
            });
        }

        const hallEditForm = document.getElementById('hall-edit-form');
        if (hallEditForm) {
            hallEditForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const errBox = document.getElementById('hall-edit-error-msg');
                const submitBtn = document.getElementById('save-hall-submit-btn');
                if (errBox) errBox.classList.add('hidden');

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'सहेजा जा रहा है...';
                }

                const rawFeatures = document.getElementById('edit-hall-features').value;
                const featuresArray = rawFeatures.split(',').map(s => s.trim()).filter(s => s.length > 0);

                const formData = new FormData();
                formData.append('action', 'supaul_save_hall');
                formData.append('security', NONCE);
                formData.append('id', document.getElementById('edit-hall-id').value);
                formData.append('name', document.getElementById('edit-hall-name').value.trim());
                formData.append('name_hi', document.getElementById('edit-hall-name-hi').value.trim());
                formData.append('capacity', document.getElementById('edit-hall-capacity').value);
                formData.append('badge', document.getElementById('edit-hall-badge').value.trim());
                formData.append('wings', document.getElementById('edit-hall-wings').value.trim());
                formData.append('in_charge', document.getElementById('edit-hall-incharge').value.trim());
                formData.append('description', document.getElementById('edit-hall-desc').value.trim());
                formData.append('features', JSON.stringify(featuresArray));

                fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i> सहेजें';
                        }
                        if (res.success) {
                            showToast(res.data.message || 'सभागार विवरण सहेजा गया');
                            document.getElementById('modal-hall-edit').classList.add('hidden');
                            loadAdminData();
                            loadSchedule();
                        } else {
                            if (errBox) {
                                errBox.textContent = res.data || 'सभागार सहेजने में त्रुटि';
                                errBox.classList.remove('hidden');
                            }
                            showToast(res.data || 'सभागार सहेजने में त्रुटि', 'error');
                        }
                    })
                    .catch(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i> सहेजें';
                        }
                        showToast('नेटवर्क त्रुटि हुई', 'error');
                    });
            });
        }

        const openNewUserBtn = document.getElementById('open-new-user-modal-btn');
        if (openNewUserBtn) {
            openNewUserBtn.addEventListener('click', () => {
                const modal = document.getElementById('modal-new-user');
                const errBox = document.getElementById('user-create-error-msg');
                if (errBox) errBox.classList.add('hidden');
                if (modal) modal.classList.remove('hidden');
                initIcons();
            });
        }

        const createUserForm = document.getElementById('create-user-form');
        if (createUserForm) {
            createUserForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const errBox = document.getElementById('user-create-error-msg');
                const submitBtn = document.getElementById('nu-submit-btn');
                if (errBox) errBox.classList.add('hidden');

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'खाता बनाया जा रहा है...';
                }

                const roleField = document.getElementById('nu-role');
                const selectedRole = roleField ? roleField.value : 'officer';

                const formData = new FormData();
                formData.append('action', 'supaul_create_officer_user');
                formData.append('security', NONCE);
                formData.append('role_type', selectedRole);
                formData.append('name', document.getElementById('nu-name').value.trim());
                formData.append('designation', document.getElementById('nu-designation').value.trim());
                formData.append('department', document.getElementById('nu-department').value.trim());
                formData.append('email', document.getElementById('nu-email').value.trim());
                formData.append('password', document.getElementById('nu-password').value);

                fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i data-lucide="user-plus" class="w-3.5 h-3.5"></i> खाता पंजीकृत करें';
                        }
                        if (res.success) {
                            showToast(res.data.message || 'उपयोगकर्ता खाता सफलतापूर्वक बनाया गया');
                            createUserForm.reset();
                            document.getElementById('modal-new-user').classList.add('hidden');
                            loadAdminData();
                        } else {
                            if (errBox) {
                                errBox.textContent = res.data || 'खाता निर्माण में त्रुटि';
                                errBox.classList.remove('hidden');
                            }
                            showToast(res.data || 'खाता निर्माण में त्रुटि', 'error');
                        }
                    })
                    .catch(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i data-lucide="user-plus" class="w-3.5 h-3.5"></i> खाता पंजीकृत करें';
                        }
                        showToast('नेटवर्क त्रुटि हुई', 'error');
                    });
            });
        }

        function bindUserTableActions() {
            if (!IS_ADMIN) return;

            document.querySelectorAll('.toggle-pwd-view').forEach(btn => {
                btn.addEventListener('click', () => {
                    const span = btn.parentElement.querySelector('.cred-text');
                    if (!span) return;
                    const pwd = span.dataset.password || '';
                    if (span.textContent === '••••••••') {
                        span.textContent = pwd;
                        btn.innerHTML = '<i data-lucide="eye-off" class="w-3.5 h-3.5"></i>';
                    } else {
                        span.textContent = '••••••••';
                        btn.innerHTML = '<i data-lucide="eye" class="w-3.5 h-3.5"></i>';
                    }
                    initIcons();
                });
            });

            document.querySelectorAll('.copy-pwd-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const pwd = btn.dataset.pwd || '';
                    if (navigator.clipboard && pwd) {
                        navigator.clipboard.writeText(pwd).then(() => {
                            showToast('पासवर्ड क्लिपबोर्ड पर कॉपी किया गया: ' + pwd);
                        });
                    }
                });
            });

            document.querySelectorAll('.edit-user-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const u = JSON.parse(btn.dataset.user);
                    const modal = document.getElementById('modal-edit-user');
                    const errBox = document.getElementById('user-edit-error-msg');
                    if (errBox) errBox.classList.add('hidden');

                    document.getElementById('eu-id').value = u.id;
                    document.getElementById('eu-name').value = u.name || '';
                    document.getElementById('eu-designation').value = u.designation || '';
                    document.getElementById('eu-department').value = u.department || '';
                    document.getElementById('eu-email').value = u.email || '';
                    document.getElementById('eu-role').value = u.role || 'officer';
                    document.getElementById('eu-status').value = u.status || 'active';
                    document.getElementById('eu-password').value = u.plain_password || '';

                    if (modal) modal.classList.remove('hidden');
                    initIcons();
                });
            });

            document.querySelectorAll('.delete-user-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const uId = btn.dataset.id;
                    const uName = btn.dataset.name;

                    if (!confirm(`क्या आप वाकई उपयोगकर्ता "${uName}" का खाता हटाना चाहते हैं?`)) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'supaul_delete_portal_user');
                    formData.append('security', NONCE);
                    formData.append('user_id', uId);

                    fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                showToast(res.data.message || 'उपयोगकर्ता खाता हटाया गया।');
                                loadAdminData();
                            } else {
                                showToast(res.data || 'खाता हटाने में त्रुटि', 'error');
                            }
                        });
                });
            });
        }

        const editUserForm = document.getElementById('edit-user-form');
        if (editUserForm) {
            editUserForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const errBox = document.getElementById('user-edit-error-msg');
                const submitBtn = document.getElementById('eu-submit-btn');
                if (errBox) errBox.classList.add('hidden');

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'सहेजा जा रहा है...';
                }

                const formData = new FormData();
                formData.append('action', 'supaul_update_portal_user');
                formData.append('security', NONCE);
                formData.append('user_id', document.getElementById('eu-id').value);
                formData.append('name', document.getElementById('eu-name').value.trim());
                formData.append('designation', document.getElementById('eu-designation').value.trim());
                formData.append('department', document.getElementById('eu-department').value.trim());
                formData.append('email', document.getElementById('eu-email').value.trim());
                formData.append('role', document.getElementById('eu-role').value);
                formData.append('status', document.getElementById('eu-status').value);
                formData.append('password', document.getElementById('eu-password').value.trim());

                fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i> विवरण सहेजें';
                        }
                        if (res.success) {
                            showToast(res.data.message || 'उपयोगकर्ता विवरण सफलतापूर्वक अद्यतित किया गया।');
                            document.getElementById('modal-edit-user').classList.add('hidden');
                            loadAdminData();
                        } else {
                            if (errBox) {
                                errBox.textContent = res.data || 'अद्यतन में त्रुटि हुई।';
                                errBox.classList.remove('hidden');
                            }
                            showToast(res.data || 'अद्यतन में त्रुटि', 'error');
                        }
                    })
                    .catch(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i> विवरण सहेजें';
                        }
                        showToast('नेटवर्क त्रुटि हुई', 'error');
                    });
            });
        }

        function renderTechDutyBoard(items) {
            const tbody = document.getElementById('tech-duty-table-body');
            if (!tbody) return;
            tbody.innerHTML = '';

            let vcCount = 0;
            let pptCount = 0;
            let approvedCount = 0;

            items.forEach(r => {
                if (r.status === 'Approved') {
                    approvedCount++;
                    if (r.vc_required == 1) vcCount++;
                    if (r.ppt_required == 1) pptCount++;
                }

                let hrs = '';
                try { hrs = JSON.parse(r.hours).map(h => `${h}:00`).join(', '); } catch(e) { hrs = r.hours; }

                const hasTechReq = (r.vc_required == 1 || r.ppt_required == 1);
                let statusBadge = (r.status === 'Approved') ? '<span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">स्वीकृत</span>' :
                                  (r.status === 'Pending') ? '<span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold bg-amber-50 text-amber-800 border border-amber-300">समीक्षाधीन</span>' :
                                  '<span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold bg-rose-50 text-rose-800 border border-rose-300">अस्वीकृत</span>';

                const tr = document.createElement('tr');
                tr.className = `hover:bg-slate-50 transition ${hasTechReq ? 'bg-cyan-50/30' : ''}`;
                tr.innerHTML = `
                    <td class="p-3 pl-4">
                        <span class="font-mono font-bold portal-text-primary block">${r.id}</span>
                        <span class="text-[11px] font-mono text-slate-500 block">${r.memo_no || 'N/A'}</span>
                    </td>
                    <td class="p-3">
                        <span class="font-bold text-slate-800 block">${r.hall_name || 'सभागार'}</span>
                        <span class="text-[11px] portal-text-primary font-mono block">${r.booking_date} (${hrs})</span>
                    </td>
                    <td class="p-3">
                        <span class="font-bold text-slate-900 block">${r.subject}</span>
                        <span class="text-[11px] text-slate-600 block">अध्यक्षता: ${r.chairing_officer}</span>
                    </td>
                    <td class="p-3 text-slate-700">
                        <span class="font-semibold block">${r.department}</span>
                        <span class="text-[10px] text-slate-500 block">मांगकर्ता: ${r.requisitioning_officer}</span>
                    </td>
                    <td class="p-3 text-center">
                        ${r.vc_required == 1 ? '<span class="px-2 py-0.5 rounded bg-cyan-100 text-cyan-900 border border-cyan-300 font-bold text-[10px]">हाँ (VC)</span>' : '<span class="text-slate-400 text-[10px]">नहीं</span>'}
                    </td>
                    <td class="p-3 text-center">
                        ${r.ppt_required == 1 ? '<span class="px-2 py-0.5 rounded bg-blue-100 text-blue-900 border border-blue-300 font-bold text-[10px]">हाँ (PPT)</span>' : '<span class="text-slate-400 text-[10px]">नहीं</span>'}
                    </td>
                    <td class="p-3 text-center">${statusBadge}</td>
                    <td class="p-3 text-right pr-4">
                        <button type="button" class="view-slip-btn px-2.5 py-1 text-white portal-bg-primary rounded text-[11px] font-bold" data-req='${JSON.stringify(r)}'>विवरण पर्ची</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            if (document.getElementById('stat-tech-vc')) document.getElementById('stat-tech-vc').textContent = vcCount;
            if (document.getElementById('badge-tech-vc-count')) document.getElementById('badge-tech-vc-count').textContent = vcCount;
            if (document.getElementById('stat-tech-ppt')) document.getElementById('stat-tech-ppt').textContent = pptCount;
            if (document.getElementById('stat-tech-total-approved')) document.getElementById('stat-tech-total-approved').textContent = approvedCount;

            bindSlipButtons();
            initIcons();
        }

        function renderLedgerTable(items) {
            const tbody = document.getElementById('admin-ledger-table-body');
            if (!tbody) return;
            tbody.innerHTML = '';
            if (!items || items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-6 text-slate-500">मास्टर पंजी में कोई अभिलेख नहीं मिला।</td></tr>';
                return;
            }
            items.forEach(r => {
                let hrs = '';
                try { hrs = JSON.parse(r.hours).map(h => `${h}:00`).join(', '); } catch(e) { hrs = r.hours; }

                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 transition';
                tr.innerHTML = `
                    <td class="p-3 pl-4 font-mono font-bold portal-text-primary">${r.id}</td>
                    <td class="p-3">${r.booking_date} (${hrs})</td>
                    <td class="p-3">${r.chairing_officer}</td>
                    <td class="p-3">${r.subject} (${r.department})</td>
                    <td class="p-3 text-center">
                        <span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold ${
                            r.status === 'Approved' ? 'bg-emerald-50 text-emerald-800 border border-emerald-300' :
                            r.status === 'Pending' ? 'bg-amber-50 text-amber-800 border border-amber-300' :
                            'bg-rose-50 text-rose-800 border border-rose-300'
                        }">${r.status === 'Approved' ? 'स्वीकृत' : r.status === 'Pending' ? 'समीक्षाधीन' : 'अस्वीकृत'}</span>
                    </td>
                    <td class="p-3 text-center">
                        <select class="change-status-select text-xs border border-slate-300 rounded px-2 py-1 bg-white font-medium" data-id="${r.id}">
                            <option value="Pending" ${r.status === 'Pending' ? 'selected' : ''}>समीक्षाधीन</option>
                            <option value="Approved" ${r.status === 'Approved' ? 'selected' : ''}>स्वीकृत</option>
                            <option value="Rejected" ${r.status === 'Rejected' ? 'selected' : ''}>अस्वीकृत</option>
                        </select>
                    </td>
                    <td class="p-3 text-right pr-4">
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" class="view-slip-btn px-2.5 py-1 text-white portal-bg-primary rounded text-[11px] font-bold" data-req='${JSON.stringify(r)}'>पर्ची</button>
                            ${IS_ADMIN ? `<button type="button" class="delete-booking-btn px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-[11px] font-bold" data-id="${r.id}" title="बुकिंग हटाएं"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            bindStatusChangeEvents();
            bindDeleteBookingEvents();
            bindSlipButtons();
            initIcons();
        }

        const ledgerSearchInput = document.getElementById('ledger-search-input');
        if (ledgerSearchInput) {
            ledgerSearchInput.addEventListener('input', (e) => {
                const q = e.target.value.toLowerCase().trim();
                const filtered = allRequisitionsCache.filter(r => 
                    r.subject.toLowerCase().includes(q) ||
                    (r.memo_no && r.memo_no.toLowerCase().includes(q)) ||
                    r.chairing_officer.toLowerCase().includes(q) ||
                    (r.hall_name && r.hall_name.toLowerCase().includes(q)) ||
                    r.department.toLowerCase().includes(q)
                );
                renderLedgerTable(filtered);
            });
        }

        const exportCsvBtn = document.getElementById('export-csv-btn');
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', () => {
                if (!allRequisitionsCache || allRequisitionsCache.length === 0) {
                    showToast('निर्यात करने हेतु कोई डेटा उपलब्ध नहीं है।', 'error');
                    return;
                }

                const headers = [
                    "मांग संदर्भ (Requisition ID)",
                    "विभागीय पत्रांक (Memo No)",
                    "सभागार का नाम (Venue)",
                    "तिथि (Booking Date)",
                    "आवंटित समय (Sanctioned Hours)",
                    "अध्यक्षता (Chairing Officer)",
                    "मांगकर्ता पदाधिकारी (Requisitioning Officer)",
                    "विभाग / शाखा (Department)",
                    "बैठक का विषय (Subject)",
                    "प्राथमिकता (Priority)",
                    "प्रतिभागी संख्या (Attendees)",
                    "वी.सी. लिंक (VC Required)",
                    "स्वल्पाहार प्रोटोकॉल (Refreshments)",
                    "वर्तमान स्थिति (Status)",
                    "स्वीकृति प्राधिकार (Approved By)"
                ];

                const rows = allRequisitionsCache.map(r => {
                    let hrs = '';
                    try { hrs = JSON.parse(r.hours).map(h => `${h}:00`).join('; '); } catch(e) { hrs = r.hours; }

                    return [
                        `"${(r.id || '').replace(/"/g, '""')}"`,
                        `"${(r.memo_no || '').replace(/"/g, '""')}"`,
                        `"${(r.hall_name || 'सभागार').replace(/"/g, '""')}"`,
                        `"${(r.booking_date || '').replace(/"/g, '""')}"`,
                        `"${hrs.replace(/"/g, '""')}"`,
                        `"${(r.chairing_officer || '').replace(/"/g, '""')}"`,
                        `"${(r.requisitioning_officer || '').replace(/"/g, '""')}"`,
                        `"${(r.department || '').replace(/"/g, '""')}"`,
                        `"${(r.subject || '').replace(/"/g, '""')}"`,
                        `"${(r.priority || '').replace(/"/g, '""')}"`,
                        `"${r.attendees || 0}"`,
                        `"${r.vc_required == 1 ? 'हाँ' : 'नहीं'}"`,
                        `"${(r.refreshments || '').replace(/"/g, '""')}"`,
                        `"${r.status === 'Approved' ? 'स्वीकृत' : r.status === 'Pending' ? 'समीक्षाधीन' : 'अस्वीकृत'}"`,
                        `"${(r.approved_by || '').replace(/"/g, '""')}"`
                    ];
                });

                const csvString = "\uFEFF" + [headers.join(','), ...rows.map(row => row.join(','))].join('\r\n');
                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                const downloadUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `Supaul_Conference_Halls_Ledger_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(downloadUrl);
                showToast('मास्टर पंजी CSV फाइल सफलतापूर्वक डाउनलोड हुई।');
            });
        }

        function bindStatusChangeEvents() {
            document.querySelectorAll('.change-status-select').forEach(sel => {
                sel.addEventListener('change', (e) => {
                    const reqId = e.target.dataset.id;
                    const newStatus = e.target.value;

                    const formData = new FormData();
                    formData.append('action', 'supaul_update_booking_status');
                    formData.append('security', NONCE);
                    formData.append('id', reqId);
                    formData.append('new_status', newStatus);

                    fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                showToast(res.data.message || 'स्थिति सफलतापूर्वक अद्यतित की गई।');
                                loadAdminData();
                                loadSchedule();
                                loadNotifications();
                            } else {
                                showToast(res.data || 'त्रुटि हुई', 'error');
                            }
                        });
                });
            });
        }

        function bindDeleteBookingEvents() {
            if (!IS_ADMIN) return;
            document.querySelectorAll('.delete-booking-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const reqId = btn.dataset.id;
                    if (!confirm(`क्या आप वाकई बुकिंग "${reqId}" को स्थायी रूप से हटाना चाहते हैं? यह स्लॉट पुनः रिक्त हो जाएगा और मास्टर पंजी से हट जाएगा।`)) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'supaul_delete_requisition');
                    formData.append('security', NONCE);
                    formData.append('id', reqId);

                    fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                showToast(res.data.message || 'बुकिंग सफलतापूर्वक हटाई गई।');
                                loadAdminData();
                                loadSchedule();
                                loadNotifications();
                            } else {
                                showToast(res.data || 'हटाने में त्रुटि हुई', 'error');
                            }
                        });
                });
            });
        }

        function openSlipModal(r) {
            const modal = document.getElementById('modal-slip');
            if (!modal) return;
            let hrs = '';
            try { hrs = JSON.parse(r.hours).map(h => `${h}:00`).join(', '); } catch(e) { hrs = r.hours; }

            currentViewingReqId = r.id || 'Supaul_Hall_Booking';

            document.getElementById('slip-meta-ref').textContent = `मांग संदर्भ: ${r.id} | पत्रांक: ${r.memo_no || 'N/A'}`;
            document.getElementById('slip-venue').textContent = r.hall_name || 'लहटन चौधरी सभागार';
            document.getElementById('slip-time').textContent = `${r.booking_date} (${hrs})`;
            document.getElementById('slip-chair').textContent = r.chairing_officer || '';
            document.getElementById('slip-department').textContent = r.department || '';
            document.getElementById('slip-subject').textContent = r.subject || '';
            document.getElementById('slip-vc').textContent = (r.vc_required == 1) ? 'हाँ (वी.सी. बुकिंग आवश्यक)' : 'नहीं';
            document.getElementById('slip-refreshments').textContent = r.refreshments || 'Executive Tea & Biscuits';

            const statusEl = document.getElementById('slip-dynamic-status');
            const headTitle = document.getElementById('slip-heading-title');
            const authorityRow = document.getElementById('slip-authority-row');
            const signatureBlock = document.getElementById('slip-signature-block');

            if (r.status === 'Approved') {
                if (headTitle) headTitle.textContent = 'सभागार आवंटन आदेश';
                if (statusEl) {
                    statusEl.className = 'font-bold text-emerald-800 block text-xs';
                    statusEl.textContent = 'स्थिति: स्वीकृत (SANCTIONED)';
                }
                if (authorityRow) authorityRow.style.display = 'flex';
                document.getElementById('slip-authority').textContent = r.approved_by || 'नजारत उप समाहर्ता, सुपौल';
                
                if (signatureBlock) {
                    signatureBlock.style.visibility = 'visible';
                    document.getElementById('slip-signer-name').textContent = r.approved_by_name || 'प्राधिकृत पदाधिकारी';
                    document.getElementById('slip-signer-desig').textContent = r.approved_by_designation || 'नजारत उप समाहर्ता';
                }
            } else if (r.status === 'Rejected') {
                if (headTitle) headTitle.textContent = 'सभागार मांग अस्वीकृति पर्ची';
                if (statusEl) {
                    statusEl.className = 'font-bold text-rose-700 block text-xs';
                    statusEl.textContent = 'स्थिति: अस्वीकृत (REJECTED)';
                }
                if (authorityRow) authorityRow.style.display = 'flex';
                document.getElementById('slip-authority').textContent = r.rejection_reason ? `अस्वीकृत (कारण: ${r.rejection_reason})` : 'सक्षम प्राधिकार द्वारा निरस्त';

                if (signatureBlock) {
                    signatureBlock.style.visibility = 'hidden';
                }
            } else {
                if (headTitle) headTitle.textContent = 'सभागार आरक्षण मांग-पत्र (अस्थायी)';
                if (statusEl) {
                    statusEl.className = 'font-bold text-amber-700 block text-xs';
                    statusEl.textContent = 'स्थिति: समीक्षाधीन (PENDING APPROVAL)';
                }
                if (authorityRow) authorityRow.style.display = 'flex';
                document.getElementById('slip-authority').textContent = 'नजारत शाखा / जिला प्रशासन से अनुमोदन प्रतीक्षारत';

                if (signatureBlock) {
                    signatureBlock.style.visibility = 'hidden';
                }
            }

            modal.classList.remove('hidden');
            initIcons();
        }

        let originalPageTitle = document.title;

        window.addEventListener('beforeprint', () => {
            if (currentViewingReqId) {
                originalPageTitle = document.title;
                document.title = currentViewingReqId;
            }
        });

        window.addEventListener('afterprint', () => {
            if (originalPageTitle) {
                document.title = originalPageTitle;
            }
        });

        const printBtn = document.getElementById('slip-print-btn');
        if (printBtn) {
            printBtn.addEventListener('click', () => {
                if (currentViewingReqId) {
                    originalPageTitle = document.title;
                    document.title = currentViewingReqId;
                }
                window.print();
                setTimeout(() => {
                    if (originalPageTitle) {
                        document.title = originalPageTitle;
                    }
                }, 1000);
            });
        }

        function bindSlipButtons() {
            document.querySelectorAll('.view-slip-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    try {
                        const req = JSON.parse(btn.dataset.req);
                        openSlipModal(req);
                    } catch(e) {}
                });
            });
        }

        document.querySelectorAll('.modal-close-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#modal-login, #modal-new-user, #modal-edit-user, #modal-reject, #modal-slip, #modal-hall-edit, #modal-change-pwd, #modal-quick-incharge').forEach(m => m.classList.add('hidden'));
            });
        });

        const openLoginBtn = document.getElementById('open-login-btn');
        const scheduleLoginBtn = document.getElementById('schedule-login-prompt-btn');
        const loginModal = document.getElementById('modal-login');
        [openLoginBtn, scheduleLoginBtn].forEach(btn => {
            if (btn) btn.addEventListener('click', () => loginModal && loginModal.classList.remove('hidden'));
        });

        const ajaxLoginForm = document.getElementById('ajax-login-form');
        if (ajaxLoginForm) {
            ajaxLoginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData();
                formData.append('action', 'supaul_ajax_login');
                formData.append('security', NONCE);
                formData.append('username', document.getElementById('login-username').value.trim());
                formData.append('password', document.getElementById('login-password').value);

                fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) window.location.reload();
                        else {
                            const err = document.getElementById('login-error-msg');
                            err.textContent = res.data || 'अमान्य लॉगिन।';
                            err.classList.remove('hidden');
                        }
                    });
            });
        }

        const logoutBtn = document.getElementById('portal-logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                const formData = new FormData();
                formData.append('action', 'supaul_ajax_logout');
                formData.append('security', NONCE);
                fetch(AJAX_URL, { credentials: 'same-origin', method: 'POST', body: formData })
                    .then(() => window.location.reload());
            });
        }

        renderVenuesList();
        loadSchedule();
        loadNotifications();
        initIcons();
    })();
    </script>
    <?php
    return ob_get_clean();
}

// AJAX HANDLERS

add_action('wp_ajax_supaul_get_schedule', 'supaul_ajax_get_schedule');
add_action('wp_ajax_nopriv_supaul_get_schedule', 'supaul_ajax_get_schedule');

function supaul_ajax_get_schedule() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');
    global $wpdb;

    $hall_id = sanitize_text_field($_POST['hall_id'] ?? '');
    $date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));

    $sql = "SELECT id, hall_id, booking_date, hours, subject, agenda, chairing_officer, requisitioning_officer, department, status, priority, vc_required, ppt_required 
            FROM " . SUPAUL_REQS_TABLE . " 
            WHERE booking_date = %s AND status IN ('Approved', 'Pending')";
    $params = [$date];

    if (!empty($hall_id)) {
        $sql .= " AND hall_id = %s";
        $params[] = $hall_id;
    }

    $bookings = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    wp_send_json_success($bookings);
}

add_action('wp_ajax_supaul_submit_requisition', 'supaul_ajax_submit_requisition');
add_action('wp_ajax_nopriv_supaul_submit_requisition', 'supaul_ajax_submit_requisition');

function supaul_ajax_submit_requisition() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user) {
        wp_send_json_error('सभागार आरक्षण हेतु अधिकृत पदाधिकारी के रूप में लॉगिन अनिवार्य है।');
    }

    global $wpdb;
    $is_admin = ($portal_user['role'] === 'admin');
    $is_nazarat = ($portal_user['role'] === 'nazarat');

    $hall_id = sanitize_text_field($_POST['hall_id'] ?? 'hall-lahtan-choudhary-sabhagar');
    $date = sanitize_text_field($_POST['date'] ?? '');
    $hours_raw = stripslashes($_POST['hours'] ?? '[]');
    $hours = json_decode($hours_raw, true);

    if (!is_array($hours) || empty($hours)) {
        wp_send_json_error('कृपया कम से कम एक स्लॉट चुनें।');
    }

    $today = current_time('Y-m-d');
    $current_hour = intval(current_time('H'));

    if ($date < $today) {
        wp_send_json_error('पिछली तिथि (Back Date) में आरक्षण नहीं लिया जा सकता।');
    }

    if ($date === $today) {
        foreach ($hours as $h) {
            if (intval($h) <= $current_hour) {
                wp_send_json_error('बीते हुए समय या 1 घंटे से कम समयावधि का स्लॉट आरक्षित नहीं किया जा सकता।');
            }
        }
    }

    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $agenda = sanitize_textarea_field($_POST['agenda'] ?? '');
    $chair = sanitize_text_field($_POST['chairing_officer'] ?? 'District Magistrate, Supaul');
    $memo = sanitize_text_field($_POST['memo_no'] ?? '');
    $priority = sanitize_text_field($_POST['priority'] ?? 'High');
    $attendees = intval($_POST['attendees'] ?? 40);
    $vc_req = intval($_POST['vc_required'] ?? 0);
    $ppt_req = intval($_POST['ppt_required'] ?? 0);
    $pa_req = intval($_POST['pa_required'] ?? 1);
    $proj_req = intval($_POST['projector_required'] ?? 1);
    $refreshments = sanitize_text_field($_POST['refreshments'] ?? 'Executive Tea & Biscuits');

    if (empty($subject)) {
        wp_send_json_error('बैठक का विषय रिक्त नहीं हो सकता।');
    }

    $req_id = 'SPL-REQ-' . current_time('Y') . '-' . wp_rand(1000, 9999);
    $department = $portal_user['department'] ?: 'जिला प्रशासन (सामान्य शाखा)';
    $designation = $portal_user['designation'] ?: 'नोडल पदाधिकारी';

    $is_direct_sanction = ($is_admin || $is_nazarat);
    $status = $is_direct_sanction ? 'Approved' : 'Pending';
    $approved_by = $is_admin ? 'जिला प्रशासन (Admin - DIO)' : ($is_nazarat ? 'नजारत उप समाहर्ता / NDC, सुपौल' : '');
    $approved_by_name = $is_direct_sanction ? $portal_user['name'] : '';
    $approved_by_desig = $is_direct_sanction ? $portal_user['designation'] : '';

    $inserted = $wpdb->insert(SUPAUL_REQS_TABLE, [
        'id' => $req_id,
        'hall_id' => $hall_id,
        'booking_date' => $date,
        'hours' => json_encode($hours),
        'subject' => $subject,
        'agenda' => $agenda,
        'chairing_officer' => $chair,
        'requisitioning_officer' => $portal_user['name'] . ' (' . $designation . ')',
        'officer_email' => $portal_user['email'],
        'department' => $department,
        'memo_no' => $memo ?: 'SPL/DEPT/' . current_time('Y') . '/' . wp_rand(100, 999),
        'priority' => $priority,
        'attendees' => $attendees,
        'vc_required' => $vc_req,
        'ppt_required' => $ppt_req,
        'pa_required' => $pa_req,
        'projector_required' => $proj_req,
        'refreshments' => $refreshments,
        'status' => $status,
        'approved_by' => $approved_by,
        'approved_by_name' => $approved_by_name,
        'approved_by_designation' => $approved_by_desig
    ]);

    if ($inserted) {
        $msg = "नया मांग-पत्र दर्ज: {$req_id} ({$subject}) तिथि: {$date}";
        $wpdb->insert(SUPAUL_NOTIFS_TABLE, [
            'req_id' => $req_id,
            'recipient_email' => $portal_user['email'],
            'recipient_role' => 'admin,nazarat,technical',
            'message' => $msg,
            'status_change' => $status
        ]);

        wp_send_json_success([
            'message' => $is_direct_sanction 
                ? "सभागार सीधा आवंटित किया गया। संदर्भ: {$req_id}" 
                : "मांग-पत्र सफलतापूर्वक दर्ज किया गया। संदर्भ: {$req_id} (नजारत शाखा अनुमोदन प्रतीक्षारत)",
            'id' => $req_id
        ]);
    } else {
        wp_send_json_error('डेटाबेस में प्रविष्टि दर्ज करने में त्रुटि हुई।');
    }
}

add_action('wp_ajax_supaul_update_booking_status', 'supaul_ajax_update_booking_status');
add_action('wp_ajax_nopriv_supaul_update_booking_status', 'supaul_ajax_update_booking_status');

function supaul_ajax_update_booking_status() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || ($portal_user['role'] !== 'admin' && $portal_user['role'] !== 'nazarat')) {
        wp_send_json_error('केवल नजारत शाखा अथवा मुख्य प्रशासक को स्थिति बदलने का अधिकार है।');
    }

    global $wpdb;
    $req_id = sanitize_text_field($_POST['id'] ?? '');
    $new_status = sanitize_text_field($_POST['new_status'] ?? 'Pending');

    if (!in_array($new_status, ['Approved', 'Pending', 'Rejected'])) {
        wp_send_json_error('अमान्य स्थिति।');
    }

    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . SUPAUL_REQS_TABLE . " WHERE id = %s", $req_id));
    if (!$existing) {
        wp_send_json_error('मांग-पत्र नहीं मिला।');
    }

    $authority = ($portal_user['role'] === 'admin') ? 'जिला प्रशासन (Super Admin - DIO)' : 'नजारत उप समाहर्ता / NDC, सुपौल';
    $approver_name = $portal_user['name'];
    $approver_desig = $portal_user['designation'];

    $updated = $wpdb->update(
        SUPAUL_REQS_TABLE,
        [
            'status' => $new_status,
            'approved_by' => ($new_status === 'Approved') ? $authority : '',
            'approved_by_name' => ($new_status === 'Approved') ? $approver_name : '',
            'approved_by_designation' => ($new_status === 'Approved') ? $approver_desig : ''
        ],
        ['id' => $req_id]
    );

    if ($updated !== false) {
        $status_label_hi = ($new_status === 'Approved') ? 'स्वीकृत (Approved)' : (($new_status === 'Pending') ? 'समीक्षाधीन (Pending)' : 'अस्वीकृत (Rejected)');
        $notif_text = "मांग संदर्भ {$req_id} ({$existing->subject}) की स्थिति नजारत/प्रशासक द्वारा बदलकर \"{$status_label_hi}\" कर दी गई है।";

        $wpdb->insert(SUPAUL_NOTIFS_TABLE, [
            'req_id' => $req_id,
            'recipient_email' => $existing->officer_email,
            'recipient_role' => 'admin,nazarat,technical',
            'message' => $notif_text,
            'status_change' => $new_status
        ]);

        wp_send_json_success(['message' => "मांग संदर्भ {$req_id} की स्थिति \"{$status_label_hi}\" कर दी गई है एवं संबंधित पदाधिकारी को सूचित किया गया।"]);
    } else {
        wp_send_json_error('स्थिति अद्यतन करने में डेटाबेस त्रुटि हुई।');
    }
}

add_action('wp_ajax_supaul_delete_requisition', 'supaul_ajax_delete_requisition');
add_action('wp_ajax_nopriv_supaul_delete_requisition', 'supaul_ajax_delete_requisition');

function supaul_ajax_delete_requisition() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || $portal_user['role'] !== 'admin') {
        wp_send_json_error('केवल जिला मुख्य प्रशासक (Super Admin/DIO) ही बुकिंग स्थायी रूप से हटा सकते हैं।');
    }

    global $wpdb;
    $req_id = sanitize_text_field($_POST['id'] ?? '');

    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . SUPAUL_REQS_TABLE . " WHERE id = %s", $req_id));
    if (!$booking) {
        wp_send_json_error('बुकिंग संदर्भ नहीं मिला।');
    }

    $deleted = $wpdb->delete(SUPAUL_REQS_TABLE, ['id' => $req_id]);

    if ($deleted) {
        $wpdb->delete(SUPAUL_NOTIFS_TABLE, ['req_id' => $req_id]);
        wp_send_json_success(['message' => "बुकिंग संदर्भ {$req_id} को पोर्टल, स्लॉट और मास्टर पंजी से पूर्णतः हटा दिया गया है।"]);
    } else {
        wp_send_json_error('बुकिंग हटाने में डेटाबेस त्रुटि हुई।');
    }
}

add_action('wp_ajax_supaul_quick_update_incharge', 'supaul_ajax_quick_update_incharge');
add_action('wp_ajax_nopriv_supaul_quick_update_incharge', 'supaul_ajax_quick_update_incharge');

function supaul_ajax_quick_update_incharge() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || $portal_user['role'] !== 'admin') {
        wp_send_json_error('केवल मुख्य प्रशासक (DIO) ही नोडल प्राधिकार को संशोधित कर सकते हैं।');
    }

    global $wpdb;
    $hall_id = sanitize_text_field($_POST['hall_id'] ?? '');
    $in_charge = sanitize_text_field($_POST['in_charge'] ?? '');

    if (empty($hall_id) || empty($in_charge)) {
        wp_send_json_error('सभागार एवं प्रभारी पदाधिकारी का नाम अनिवार्य है।');
    }

    $updated = $wpdb->update(
        SUPAUL_HALLS_TABLE,
        ['in_charge' => $in_charge],
        ['id' => $hall_id]
    );

    if ($updated !== false) {
        wp_send_json_success(['message' => 'नोडल प्राधिकारी का विवरण सफलतापूर्वक अद्यतित किया गया।']);
    } else {
        wp_send_json_error('डेटाबेस अद्यतन में त्रुटि हुई।');
    }
}

add_action('wp_ajax_supaul_get_notifications', 'supaul_ajax_get_notifications');
add_action('wp_ajax_nopriv_supaul_get_notifications', 'supaul_ajax_get_notifications');

function supaul_ajax_get_notifications() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user) {
        wp_send_json_success(['notifications' => [], 'unread_count' => 0]);
    }

    global $wpdb;
    $email = $portal_user['email'];
    $role = $portal_user['role'];

    $sql = "SELECT * FROM " . SUPAUL_NOTIFS_TABLE . " 
            WHERE recipient_email = %s OR recipient_role LIKE %s 
            ORDER BY id DESC LIMIT 15";
    $notifs = $wpdb->get_results($wpdb->prepare($sql, $email, '%' . $role . '%'), ARRAY_A);

    $unread_sql = "SELECT COUNT(*) FROM " . SUPAUL_NOTIFS_TABLE . " 
                   WHERE (recipient_email = %s OR recipient_role LIKE %s) AND is_read = 0";
    $unread = $wpdb->get_var($wpdb->prepare($unread_sql, $email, '%' . $role . '%'));

    wp_send_json_success(['notifications' => $notifs, 'unread_count' => intval($unread)]);
}

add_action('wp_ajax_supaul_mark_notifications_read', 'supaul_ajax_mark_notifications_read');
add_action('wp_ajax_nopriv_supaul_mark_notifications_read', 'supaul_ajax_mark_notifications_read');

function supaul_ajax_mark_notifications_read() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');
    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user) {
        wp_send_json_error();
    }
    global $wpdb;
    $email = $portal_user['email'];
    $role = $portal_user['role'];

    $wpdb->query($wpdb->prepare(
        "UPDATE " . SUPAUL_NOTIFS_TABLE . " SET is_read = 1 WHERE recipient_email = %s OR recipient_role LIKE %s",
        $email,
        '%' . $role . '%'
    ));
    wp_send_json_success();
}

add_action('wp_ajax_supaul_get_my_requisitions', 'supaul_ajax_get_my_requisitions');
add_action('wp_ajax_nopriv_supaul_get_my_requisitions', 'supaul_ajax_get_my_requisitions');

function supaul_ajax_get_my_requisitions() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user) {
        wp_send_json_error('अनधिकृत');
    }

    global $wpdb;
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, COALESCE(h.name_hi, h.name, 'सभागार') as hall_name 
         FROM " . SUPAUL_REQS_TABLE . " r 
         LEFT JOIN " . SUPAUL_HALLS_TABLE . " h ON r.hall_id = h.id 
         WHERE r.officer_email = %s 
         ORDER BY r.created_at DESC",
        $portal_user['email']
    ), ARRAY_A);

    wp_send_json_success($results);
}

add_action('wp_ajax_supaul_get_admin_data', 'supaul_ajax_get_admin_data');
add_action('wp_ajax_nopriv_supaul_get_admin_data', 'supaul_ajax_get_admin_data');

function supaul_ajax_get_admin_data() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || ($portal_user['role'] !== 'admin' && $portal_user['role'] !== 'nazarat' && $portal_user['role'] !== 'technical')) {
        wp_send_json_error('अनधिकृत');
    }

    global $wpdb;
    $is_admin = ($portal_user['role'] === 'admin');

    $halls = $wpdb->get_results("SELECT * FROM " . SUPAUL_HALLS_TABLE . " ORDER BY (id = 'hall-lahtan-choudhary-sabhagar') DESC, name ASC", ARRAY_A);
    $reqs = $wpdb->get_results(
        "SELECT r.*, COALESCE(h.name_hi, h.name, 'सभागार') as hall_name 
         FROM " . SUPAUL_REQS_TABLE . " r 
         LEFT JOIN " . SUPAUL_HALLS_TABLE . " h ON r.hall_id = h.id 
         ORDER BY r.created_at DESC",
        ARRAY_A
    );

    $pending = $wpdb->get_var("SELECT COUNT(*) FROM " . SUPAUL_REQS_TABLE . " WHERE status = 'Pending'");
    $approved = $wpdb->get_var("SELECT COUNT(*) FROM " . SUPAUL_REQS_TABLE . " WHERE status = 'Approved'");

    $users_table = SUPAUL_USERS_TABLE;
    if ($is_admin) {
        $users_list = $wpdb->get_results("SELECT id, name, designation, department, email, plain_password, role, status FROM $users_table ORDER BY id DESC", ARRAY_A);
    } else {
        $users_list = $wpdb->get_results("SELECT id, name, designation, department, email, '' as plain_password, role, status FROM $users_table WHERE role IN ('officer', 'itm') ORDER BY id DESC", ARRAY_A);
    }

    wp_send_json_success([
        'stats' => [
            'pending' => intval($pending),
            'approved' => intval($approved),
            'users_count' => count($users_list)
        ],
        'requisitions' => $reqs,
        'users' => $users_list,
        'halls' => $halls
    ]);
}

add_action('wp_ajax_supaul_save_hall', 'supaul_ajax_save_hall');
add_action('wp_ajax_nopriv_supaul_save_hall', 'supaul_ajax_save_hall');

function supaul_ajax_save_hall() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || $portal_user['role'] !== 'admin') {
        wp_send_json_error('सभागार संपादन अथवा नया सभागार जोड़ने का अधिकार केवल मुख्य प्रशासक (Admin) को है।');
    }

    global $wpdb;
    $id = sanitize_text_field($_POST['id'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $name_hi = sanitize_text_field($_POST['name_hi'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 100);
    $badge = sanitize_text_field($_POST['badge'] ?? 'समाहरणालय');
    $wings = sanitize_text_field($_POST['wings'] ?? '');
    $in_charge = sanitize_text_field($_POST['in_charge'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $features = $_POST['features'] ?? '[]';

    $decoded_features = json_decode(stripslashes($features), true);
    if (!is_array($decoded_features)) {
        $features = json_encode(['Polycom VC', 'Smart Display', 'PA System']);
    } else {
        $features = json_encode(array_map('sanitize_text_field', $decoded_features));
    }

    if (empty($name) && empty($name_hi)) {
        wp_send_json_error('सभागार का नाम भरना अनिवार्य है।');
    }
    if (empty($name)) $name = $name_hi;
    if (empty($name_hi)) $name_hi = $name;

    $table = SUPAUL_HALLS_TABLE;

    if (!empty($id)) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id = %s", $id));
        if ($exists) {
            $updated = $wpdb->update($table, [
                'name' => $name,
                'name_hi' => $name_hi,
                'capacity' => $capacity,
                'badge' => $badge,
                'wings' => $wings,
                'in_charge' => $in_charge,
                'description' => $description,
                'features' => $features
            ], ['id' => $id]);

            if ($updated !== false) {
                wp_send_json_success([
                    'message' => "सभागार \"{$name_hi}\" का विवरण सफलतापूर्वक अद्यतित किया गया।",
                    'hall_id' => $id
                ]);
            } else {
                wp_send_json_error('डेटाबेस अद्यतन में त्रुटि हुई।');
            }
        }
    }

    $slug_base = sanitize_title($name);
    if (empty($slug_base)) $slug_base = 'hall-' . wp_rand(100, 999);
    $new_id = 'hall-' . $slug_base;

    $counter = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id = %s", $new_id))) {
        $new_id = 'hall-' . $slug_base . '-' . $counter;
        $counter++;
    }

    $inserted = $wpdb->insert($table, [
        'id' => $new_id,
        'name' => $name,
        'name_hi' => $name_hi,
        'capacity' => $capacity,
        'badge' => $badge,
        'wings' => $wings,
        'in_charge' => $in_charge,
        'description' => $description,
        'features' => $features
    ]);

    if ($inserted) {
        wp_send_json_success([
            'message' => "नया सभागार \"{$name_hi}\" सफलतापूर्वक पंजीकृत किया गया।",
            'hall_id' => $new_id
        ]);
    } else {
        wp_send_json_error('नया सभागार जोड़ने में त्रुटि हुई।');
    }
}

add_action('wp_ajax_supaul_delete_hall', 'supaul_ajax_delete_hall');
add_action('wp_ajax_nopriv_supaul_delete_hall', 'supaul_ajax_delete_hall');

function supaul_ajax_delete_hall() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || $portal_user['role'] !== 'admin') {
        wp_send_json_error('सभागार हटाने का अधिकार केवल मुख्य प्रशासक (Admin) को है।');
    }

    global $wpdb;
    $id = sanitize_text_field($_POST['id'] ?? '');

    $count = $wpdb->get_var("SELECT COUNT(*) FROM " . SUPAUL_HALLS_TABLE);
    if ($count <= 1) {
        wp_send_json_error('कम से कम एक सभागार का होना अनिवार्य है। इसे हटाया नहीं जा सकता।');
    }

    $deleted = $wpdb->delete(SUPAUL_HALLS_TABLE, ['id' => $id]);

    if ($deleted) {
        wp_send_json_success(['message' => 'सभागार सफलतापूर्वक हटाया गया।']);
    } else {
        wp_send_json_error('सभागार हटाने में त्रुटि हुई।');
    }
}

add_action('wp_ajax_supaul_create_officer_user', 'supaul_ajax_create_officer_user');
add_action('wp_ajax_nopriv_supaul_create_officer_user', 'supaul_ajax_create_officer_user');

function supaul_ajax_create_officer_user() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || ($portal_user['role'] !== 'admin' && $portal_user['role'] !== 'nazarat')) {
        wp_send_json_error('केवल जिला प्रशासक (Admin) अथवा नजारत शाखा ही नया खाता बना सकते हैं।');
    }

    global $wpdb;
    $is_admin = ($portal_user['role'] === 'admin');

    $requested_role = sanitize_text_field($_POST['role_type'] ?? 'officer');
    if (!$is_admin && !in_array($requested_role, ['officer', 'itm'])) {
        $requested_role = 'officer';
    }

    $name = sanitize_text_field($_POST['name'] ?? '');
    $designation = sanitize_text_field($_POST['designation'] ?? '');
    $department = sanitize_text_field($_POST['department'] ?? '');
    $email = sanitize_email(strtolower(trim($_POST['email'] ?? '')));
    $password = $_POST['password'] ?? '';

    if (empty($name)) {
        wp_send_json_error('पदाधिकारी का नाम भरना अनिवार्य है।');
    }
    if (empty($email) || !is_email($email)) {
        wp_send_json_error('कृपया वैध सरकारी ईमेल आईडी दर्ज करें।');
    }
    if (empty($password) || strlen($password) < 6) {
        wp_send_json_error('पासवर्ड कम से कम 6 अक्षरों का होना चाहिए।');
    }

    $users_table = SUPAUL_USERS_TABLE;
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $users_table WHERE LOWER(email) = %s", $email));
    if ($exists) {
        wp_send_json_error("यह ईमेल पूर्व से पंजीकृत है: {$email}");
    }

    $inserted = $wpdb->insert($users_table, [
        'name'           => $name,
        'designation'    => $designation ?: 'विभागीय नोडल पदाधिकारी',
        'department'     => $department ?: 'सामान्य प्रशासन',
        'email'          => $email,
        'password'       => wp_hash_password($password),
        'plain_password' => $password,
        'role'           => $requested_role,
        'status'         => 'active'
    ]);

    if (!$inserted) {
        wp_send_json_error('खाता निर्माण में डेटाबेस त्रुटि हुई: ' . $wpdb->last_error);
    }

    $role_name = ($requested_role === 'nazarat') ? 'नजारत उप समाहर्ता (NDC)' :
                 ($requested_role === 'technical' ? 'तकनीकी टीम (Technical Team)' :
                 ($requested_role === 'itm' ? 'तकनीकी नोडल पदाधिकारी (ITM)' :
                 ($requested_role === 'admin' ? 'जिला मुख्य प्रशासक (Super Admin)' : 'विभागीय नोडल पदाधिकारी')));

    wp_send_json_success([
        'message' => "{$role_name} खाता ({$name}) सफलतापूर्वक पंजीकृत किया गया। लॉगिन आईडी: {$email}"
    ]);
}

add_action('wp_ajax_supaul_update_portal_user', 'supaul_ajax_update_portal_user');
add_action('wp_ajax_nopriv_supaul_update_portal_user', 'supaul_ajax_update_portal_user');

function supaul_ajax_update_portal_user() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || $portal_user['role'] !== 'admin') {
        wp_send_json_error('केवल जिला मुख्य प्रशासक (Super Admin) ही उपयोगकर्ताओं का संपादन कर सकते हैं।');
    }

    global $wpdb;
    $users_table = SUPAUL_USERS_TABLE;

    $user_id = intval($_POST['user_id'] ?? 0);
    $name = sanitize_text_field($_POST['name'] ?? '');
    $designation = sanitize_text_field($_POST['designation'] ?? '');
    $department = sanitize_text_field($_POST['department'] ?? '');
    $email = sanitize_email(strtolower(trim($_POST['email'] ?? '')));
    $role = sanitize_text_field($_POST['role'] ?? 'officer');
    $status = sanitize_text_field($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';

    if ($user_id <= 0) {
        wp_send_json_error('अमान्य उपयोगकर्ता आईडी।');
    }
    if (empty($name)) {
        wp_send_json_error('पदाधिकारी का नाम अनिवार्य है।');
    }
    if (empty($email) || !is_email($email)) {
        wp_send_json_error('कृपया वैध सरकारी ईमेल आईडी दर्ज करें।');
    }

    $duplicate = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $users_table WHERE LOWER(email) = %s AND id != %d",
        $email,
        $user_id
    ));
    if ($duplicate) {
        wp_send_json_error("यह ईमेल किसी अन्य खाते में पूर्व से पंजीकृत है: {$email}");
    }

    $update_data = [
        'name'        => $name,
        'designation' => $designation,
        'department'  => $department,
        'email'       => $email,
        'role'        => $role,
        'status'      => $status
    ];

    if (!empty($password)) {
        if (strlen($password) < 6) {
            wp_send_json_error('पासवर्ड कम से कम 6 अक्षरों का होना चाहिए।');
        }
        $update_data['password'] = wp_hash_password($password);
        $update_data['plain_password'] = $password;
    }

    $updated = $wpdb->update($users_table, $update_data, ['id' => $user_id]);

    if ($updated !== false) {
        wp_send_json_success(['message' => "उपयोगकर्ता खाता ({$name}) सफलतापूर्वक अद्यतित किया गया।"]);
    } else {
        wp_send_json_error('डेटाबेस अद्यतन में त्रुटि हुई।');
    }
}

add_action('wp_ajax_supaul_delete_portal_user', 'supaul_ajax_delete_portal_user');
add_action('wp_ajax_nopriv_supaul_delete_portal_user', 'supaul_ajax_delete_portal_user');

function supaul_ajax_delete_portal_user() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user || $portal_user['role'] !== 'admin') {
        wp_send_json_error('केवल जिला मुख्य प्रशासक (Super Admin) ही उपयोगकर्ताओं को हटा सकते हैं।');
    }

    global $wpdb;
    $users_table = SUPAUL_USERS_TABLE;
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        wp_send_json_error('अमान्य उपयोगकर्ता आईडी।');
    }

    if (!empty($portal_user['id']) && intval($portal_user['id']) === $user_id) {
        wp_send_json_error('आप अपने स्वयं के सक्रिय प्रशासक खाते को नहीं हटा सकते हैं।');
    }

    $deleted = $wpdb->delete($users_table, ['id' => $user_id]);

    if ($deleted) {
        wp_send_json_success(['message' => 'उपयोगकर्ता खाता सफलतापूर्वक हटाया गया।']);
    } else {
        wp_send_json_error('उपयोगकर्ता खाता हटाने में त्रुटि हुई।');
    }
}

add_action('wp_ajax_supaul_change_portal_password', 'supaul_ajax_change_portal_password');

function supaul_ajax_change_portal_password() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');

    $portal_user = supaul_get_current_portal_user();
    if (!$portal_user) {
        wp_send_json_error('अनधिकृत सत्र।');
    }

    global $wpdb;
    $current_pwd = $_POST['current_password'] ?? '';
    $new_pwd = $_POST['new_password'] ?? '';

    if (empty($current_pwd) || empty($new_pwd)) {
        wp_send_json_error('कृपया दोनों पासवर्ड दर्ज करें।');
    }
    if (strlen($new_pwd) < 6) {
        wp_send_json_error('नया पासवर्ड कम से कम 6 अक्षरों का होना चाहिए।');
    }

    $users_table = SUPAUL_USERS_TABLE;
    if (!empty($portal_user['id'])) {
        $db_user = $wpdb->get_row($wpdb->prepare("SELECT id, password FROM $users_table WHERE id = %d", intval($portal_user['id'])));
        if (!$db_user || !wp_check_password($current_pwd, $db_user->password)) {
            wp_send_json_error('वर्तमान पासवर्ड गलत है।');
        }

        $updated = $wpdb->update(
            $users_table,
            [
                'password'       => wp_hash_password($new_pwd),
                'plain_password' => $new_pwd
            ],
            ['id' => $db_user->id]
        );

        if ($updated !== false) {
            wp_send_json_success(['message' => 'पासवर्ड बदल दिया गया है।']);
        } else {
            wp_send_json_error('पासवर्ड अद्यतन करने में त्रुटि।');
        }
    } else {
        $wp_user = wp_get_current_user();
        if (!$wp_user || !wp_check_password($current_pwd, $wp_user->user_pass, $wp_user->ID)) {
            wp_send_json_error('वर्तमान पासवर्ड गलत है।');
        }
        wp_set_password($new_pwd, $wp_user->ID);
        wp_send_json_success(['message' => 'प्रशासक पासवर्ड सफलतापूर्वक बदल दिया गया।']);
    }
}

add_action('wp_ajax_nopriv_supaul_ajax_login', 'supaul_ajax_login');
add_action('wp_ajax_supaul_ajax_login', 'supaul_ajax_login');

function supaul_ajax_login() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');
    global $wpdb;

    $username_or_email = sanitize_email(strtolower(trim($_POST['username'] ?? '')));
    if (empty($username_or_email)) {
        $username_or_email = sanitize_text_field(trim($_POST['username'] ?? ''));
    }
    $password = $_POST['password'] ?? '';

    $users_table = SUPAUL_USERS_TABLE;
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT id, password, status FROM $users_table WHERE LOWER(email) = %s",
        $username_or_email
    ));

    if (!$user) {
        wp_send_json_error('अमान्य ईमेल आईडी अथवा यह खाता पंजीकृत नहीं है।');
    }
    if ($user->status !== 'active') {
        wp_send_json_error('यह खाता निष्क्रिय (Suspended) है।');
    }
    if (!wp_check_password($password, $user->password)) {
        wp_send_json_error('अमान्य पासवर्ड।');
    }

    supaul_set_portal_session($user->id);
    wp_send_json_success(['message' => 'पोर्टल पर सफलतापूर्वक लॉगिन किया गया।']);
}

add_action('wp_ajax_nopriv_supaul_ajax_logout', 'supaul_ajax_logout');
add_action('wp_ajax_supaul_ajax_logout', 'supaul_ajax_logout');

function supaul_ajax_logout() {
    check_ajax_referer('supaul_hall_booking_nonce', 'security');
    supaul_clear_portal_session();
    wp_send_json_success(['message' => 'सफलतापूर्वक लॉगआउट किया गया।']);
}