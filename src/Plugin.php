<?php
declare(strict_types=1);
namespace CB\Notes;
use CoreBlueprint\Core\ExtensionRegistry;
use CoreBlueprint\Core\Admin\SettingsRegistry;
use CB\Notes\Admin\SettingsPage;
use CB\Notes\Sharing\Controller;
use CB\Notes\Integration\SecureProvider;
use CB\Notes\Organization\Catalog;
use CB\Notes\Library\Page as LibraryPage;
use CB\Notes\Library\Markers;
use CB\Notes\Library\Bulk;
use CoreBlueprint\Core\UI\Assets;
use CoreBlueprint\Core\Governance\Audit;
use CoreBlueprint\Core\Governance\EventRegistry;
defined('ABSPATH') || exit;
final class Plugin {
    public const TYPE = 'cb_note';
    public const META_NOTEBOOK = 'cb_notes_notebook';
    public const AI_OPTION = 'cb_notes_mcp_read_enabled';
    private static bool $booted = false;
    public static function grant_roles(): void {
        foreach (['administrator','editor'] as $name) {
            $role=get_role($name);
            if ($role) foreach (['cb_notes_edit','cb_notes_delete','cb_notes_share_public','cb_notes_share_secure'] as $cap) $role->add_cap($cap);
        }
        $admin=get_role('administrator');
        if ($admin) foreach (['cb_notes_manage_all','cb_notes_use_ai'] as $cap) $admin->add_cap($cap);
    }
    public static function boot(): void {
        if (self::$booted) return;
        self::$booted=true;
        add_action('init',[self::class,'register_content'],6);
        add_action('init',[Controller::class,'register'],7);
        add_action('init',[self::class,'upgrade_capabilities'],8);
        add_action('init',[SecureProvider::class,'register'],15);
        add_action('cb_secure_share_register_providers',[SecureProvider::class,'register']);
        add_action('rest_api_init',[Controller::class,'rest']);
        add_action('template_redirect',[Controller::class,'deliver_public'],-99);
        add_action('init',[self::class,'register_events'],9);
        add_action('core_blueprint_register_extensions',[self::class,'register_extension']);
        SettingsPage::init();
        Catalog::init();
        LibraryPage::init();
        Markers::init();
        Bulk::init();
        add_filter('core_blueprint_module_status_definitions',[self::class,'status_definitions']);
        add_filter('map_meta_cap',[Access::class,'map_meta_cap'],20,4);
        add_filter('wp_insert_post_data',[Access::class,'force_private_status'],50,2);
        add_filter('rest_request_before_callbacks',[Access::class,'before_rest'],10,3);
        add_action('pre_get_posts',[Access::class,'scope_query']);
        add_filter('rest_cb_note_query',[Access::class,'scope_rest']);
        add_filter('rest_prepare_cb_note',[Access::class,'protect_rest'],10,2);
        add_action('wp_after_insert_post',[self::class,'audit_save'],20,4);
        add_action('trashed_post',[self::class,'audit_trash'],20);
        add_action('untrashed_post',[self::class,'audit_restore'],20);
        add_action('before_delete_post',[self::class,'audit_delete'],20,2);
        add_action('enqueue_block_editor_assets',[self::class,'editor_assets']);
        add_action('admin_menu',[self::class,'admin_menu']);
        add_action('admin_init',[self::class,'admin_settings']);
        add_action('admin_init',[self::class,'ensure_share_routes'],20);
        add_action('admin_init',[self::class,'migrate_legacy_published_notes'],21);
        add_filter('views_edit-cb_note',[self::class,'hide_global_status_counts'],50);
        add_filter('manage_cb_note_posts_columns',[self::class,'note_columns']);
        add_filter('plugin_action_links_'.CB_NOTES_BASENAME,[self::class,'action_links']);
        add_action('wp_abilities_api_init',[Abilities::class,'register']);
    }
    public static function upgrade_capabilities(): void {
        if (get_option('cb_notes_caps_migration', '') === 'alpha3') return;
        self::grant_roles();
        // Alpha2 -> alpha3 updates do not call the activation hook.
        // Flush the newly registered public-share rewrite exactly once.
        flush_rewrite_rules(false);
        update_option('cb_notes_caps_migration', 'alpha3', false);
    }
    /** Plugin updates do not rerun activation hooks; reconcile rewrite once. */
    public static function ensure_share_routes(): void {
        if (!current_user_can('manage_options') ||
            get_option('cb_notes_share_rewrite_v1', '') === '1') {
            return;
        }
        flush_rewrite_rules(false);
        update_option('cb_notes_share_rewrite_v1', '1', false);
    }
    /** Reclassify alpha1/2 legacy "publish" Notes as private in bounded batches. */
    public static function migrate_legacy_published_notes(): void {
        if (!current_user_can('manage_options')
            || get_option('cb_notes_alpha3_private_migration', '') === 'complete') {
            return;
        }
        $ids = get_posts([
            'post_type' => self::TYPE, 'post_status' => 'publish',
            'fields' => 'ids', 'posts_per_page' => 25, 'orderby' => 'ID',
            'order' => 'ASC', 'suppress_filters' => true,
        ]);
        if (!is_array($ids) || $ids === []) {
            if ($ids === []) {
                update_option('cb_notes_alpha3_private_migration', 'complete', false);
            }
            return;
        }
        foreach ($ids as $id) {
            $result = wp_update_post(['ID' => (int)$id, 'post_status' => 'private'], true);
            if (is_wp_error($result) || (int)$result < 1) {
                error_log('[Core Blueprint Notes] Legacy private-status migration needs operator review.');
                return;
            }
        }
        // Continue next administrator page load if another batch remains.
    }

    public static function register_content(): void {
        register_post_type(self::TYPE,[
            'labels'=>[
                'name'=>__('Notes','core-blueprint-notes'),
                'singular_name'=>__('Note','core-blueprint-notes'),
                'add_new_item'=>__('Add Note','core-blueprint-notes'),
                'edit_item'=>__('Edit Note','core-blueprint-notes'),
                'all_items'=>__('All Notes','core-blueprint-notes'),
                'not_found'=>__('No notes found.','core-blueprint-notes'),
            ],
            'description'=>__('Private personal notes edited in Gutenberg.','core-blueprint-notes'),
            'public'=>false,'publicly_queryable'=>false,'exclude_from_search'=>true,
            'show_ui'=>true,'show_in_menu'=>true,'show_in_rest'=>true,
            'rest_base'=>'cb-notes','has_archive'=>false,'rewrite'=>false,'query_var'=>false,
            'show_in_nav_menus'=>false,'show_in_admin_bar'=>false,
            'menu_icon'=>'dashicons-edit-page','menu_position'=>26.5,
            // WP REST only exposes registered meta for CPTs with custom-fields support.
            // Catalog::validate_note_rest and per-key auth_callbacks enforce the owner boundary.
            'supports'=>['title','editor','author','revisions','custom-fields'],
            'map_meta_cap'=>true,'capability_type'=>['cb_note','cb_notes'],
            'capabilities'=>[
                'edit_post'=>'edit_post','read_post'=>'read_post',
                'delete_post'=>'delete_post','edit_posts'=>'cb_notes_edit',
                'edit_others_posts'=>'cb_notes_manage_all','edit_private_posts'=>'cb_notes_edit',
                'edit_published_posts'=>'cb_notes_edit','publish_posts'=>'cb_notes_edit',
                'read_private_posts'=>'cb_notes_manage_all','delete_posts'=>'cb_notes_delete',
                'delete_private_posts'=>'cb_notes_delete',
                'delete_published_posts'=>'cb_notes_delete',
                'delete_others_posts'=>'cb_notes_manage_all','create_posts'=>'cb_notes_edit',
            ],
            'delete_with_user'=>false,
        ]);
        register_post_meta(self::TYPE,self::META_NOTEBOOK,[
            'single'=>true,'type'=>'string','default'=>'',
            'show_in_rest'=>['schema'=>['type'=>'string','maxLength'=>80]],
            'sanitize_callback'=>static function(mixed $value): string {
                $value=sanitize_text_field((string)$value);
                return function_exists('mb_substr')?mb_substr($value,0,80):substr($value,0,80);
            },
            'auth_callback'=>static function(bool $allowed,string $key,int $post_id,int $user_id): bool {
                unset($allowed, $key);
                $post=get_post($post_id);
                return $post instanceof \WP_Post && Access::allowed($post,'edit',$user_id);
            },
        ]);
    }
    public static function register_extension(): void {
        ExtensionRegistry::register([
            'id'=>'core-blueprint-notes','plugin_file'=>CB_NOTES_BASENAME,
            'requires_api'=>CB_NOTES_REQUIRED_API,
            'menu_url'=>admin_url('edit.php?post_type='.self::TYPE.'&page=cb-notes-library'),
            'status_id'=>'core-blueprint-notes',
        ]);
    }
    public static function status_definitions(array $definitions): array {
        $definitions['core-blueprint-notes']=[
            'provider'=>[self::class,'status'],
            'label'=>__('Notes','core-blueprint-notes'),
            'url'=>admin_url('edit.php?post_type='.self::TYPE.'&page=cb-notes-library'),
        ];
        return $definitions;
    }
    public static function status(): array {
        return ['state'=>'ok',
            'detail'=>__('Private Gutenberg notes enabled','core-blueprint-notes'),
            'url'=>admin_url('edit.php?post_type='.self::TYPE.'&page=cb-notes-library')];
    }
    public static function register_events(): void {
        foreach(['created','updated','trashed','restored','deleted'] as $kind) {
            EventRegistry::register([
                'id'=>'cbnotes.record.'.$kind,
                'label'=>sprintf(__('Notes: %s','core-blueprint-notes'),$kind),
                'retention_category'=>'general',
            ]);
        }
    }
    public static function audit_save(int $post_id,\WP_Post $post,bool $update,?\WP_Post $before): void {
        if($post->post_type!==self::TYPE ||
            in_array($post->post_status,['auto-draft','trash','inherit'],true) ||
            wp_is_post_revision($post_id)||wp_is_post_autosave($post_id)) return;
        $created=!$update||($before instanceof \WP_Post && $before->post_status==='auto-draft');
        Audit::record('cbnotes.record.'.($created?'created':'updated'),'notice',[
            'note_id'=>$post_id,'status'=>sanitize_key($post->post_status),
        ]);
    }
    public static function audit_trash(int $post_id): void {
        Controller::revoke_for_lifecycle($post_id);
        self::audit_lifecycle('trashed',$post_id);
    }
    public static function audit_restore(int $post_id): void {
        $post = get_post($post_id);
        // Defense in depth: a restored private note must not resurrect a share
        // if the original Trash lifecycle encountered a transient DB error.
        if ($post instanceof \WP_Post && $post->post_type === self::TYPE) {
            Controller::revoke_for_lifecycle($post_id);
        }
        self::audit_lifecycle('restored', $post_id);
    }
    public static function audit_delete(int $post_id,\WP_Post $post): void {
        Controller::revoke_for_lifecycle($post_id);
        if($post->post_type===self::TYPE)
            Audit::record('cbnotes.record.deleted','warning',['note_id'=>$post_id]);
    }
    private static function audit_lifecycle(string $kind,int $post_id): void {
        if(get_post_type($post_id)===self::TYPE)
            Audit::record('cbnotes.record.'.$kind,'notice',['note_id'=>$post_id]);
    }
    public static function editor_assets(): void {
        $screen=get_current_screen();
        if(!$screen||$screen->post_type!==self::TYPE) return;
        if (class_exists(Assets::class)) {
            Assets::enqueue_modals(Assets::MODAL_PRESENTATION_WP_NATIVE);
        }
        wp_enqueue_script('cb-notes-sharing',
            plugins_url('assets/sharing.js',CB_NOTES_FILE),
            ['wp-plugins','wp-edit-post','wp-components','wp-data','wp-element','wp-api-fetch','wp-i18n'],
            CB_NOTES_VERSION,true);
        wp_set_script_translations('cb-notes-sharing', 'core-blueprint-notes', CB_NOTES_DIR . 'languages');
        wp_enqueue_script('cb-notes-editor',
            plugins_url('assets/editor.js',CB_NOTES_FILE),
            ['wp-plugins','wp-edit-post','wp-components','wp-data','wp-element','wp-api-fetch','wp-i18n'],
            CB_NOTES_VERSION,true);
        wp_set_script_translations('cb-notes-editor', 'core-blueprint-notes', CB_NOTES_DIR . 'languages');
    }
    public static function admin_menu(): void {
        $hook=add_submenu_page('edit.php?post_type='.self::TYPE,
            __('Notes AI Access','core-blueprint-notes'),
            __('AI Access','core-blueprint-notes'),
            'manage_options','cb-notes-ai',[SettingsPage::class,'legacy_link']);
        if (is_string($hook) && '' !== $hook) {
            add_action('load-'.$hook,[SettingsPage::class,'redirect_legacy']);
        }
    }
    public static function admin_settings(): void {
        register_setting('cb_notes_settings',self::AI_OPTION,[
            'type'=>'boolean','default'=>false,
            'sanitize_callback'=>static fn(mixed $v): bool =>
                current_user_can('manage_options')&&(bool)$v,
        ]);
    }
    public static function note_columns(array $columns): array {
        $columns['cb_notes_notebook']=__('Notebook','core-blueprint-notes');
        return $columns;
    }
    /** WordPress core's status counts may include other users' drafts. */
    public static function hide_global_status_counts(array $views): array {
        if (current_user_can('cb_notes_manage_all')) return $views;
        foreach ($views as $key=>$markup) {
            $views[$key]=preg_replace('/\\s*<span class="count">\\([^)]*\\)<\\/span>/', '', $markup)??$markup;
        }
        return $views;
    }
    public static function action_links(array $links): array {
        $links[]='<a href="'.esc_url(admin_url('edit.php?post_type='.self::TYPE.'&page=cb-notes-library')).'">'.
            esc_html__('Notes','core-blueprint-notes').'</a>';
        if (current_user_can('manage_options')) {
            $links[]='<a href="'.esc_url(SettingsRegistry::url(SettingsPage::EXTENSION_ID)).'">'.
                esc_html__('Settings','core-blueprint-notes').'</a>';
        }
        return $links;
    }
}
