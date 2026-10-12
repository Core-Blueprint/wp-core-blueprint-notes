<?php
declare(strict_types=1);
namespace CB\Notes;
use CoreBlueprint\Core\ExtensionRegistry;
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
            if ($role) foreach (['cb_notes_edit','cb_notes_delete'] as $cap) $role->add_cap($cap);
        }
        $admin=get_role('administrator');
        if ($admin) foreach (['cb_notes_manage_all','cb_notes_use_ai'] as $cap) $admin->add_cap($cap);
    }
    public static function boot(): void {
        if (self::$booted) return;
        self::$booted=true;
        add_action('init',[self::class,'register_content'],6);
        add_action('init',[self::class,'register_events'],9);
        add_action('core_blueprint_register_extensions',[self::class,'register_extension']);
        add_filter('core_blueprint_module_status_definitions',[self::class,'status_definitions']);
        add_filter('map_meta_cap',[Access::class,'map_meta_cap'],20,4);
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
        add_filter('plugin_action_links_'.CB_NOTES_BASENAME,[self::class,'action_links']);
        add_action('wp_abilities_api_init',[Abilities::class,'register']);
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
            'supports'=>['title','editor','author','revisions'],
            'map_meta_cap'=>true,'capability_type'=>['cb_note','cb_notes'],
            'capabilities'=>[
                'edit_post'=>'edit_cb_note','read_post'=>'read_cb_note',
                'delete_post'=>'delete_cb_note','edit_posts'=>'cb_notes_edit',
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
            'auth_callback'=>static function(string $key,int $post_id,int $user_id): bool {
                $post=get_post($post_id);
                return $post instanceof \WP_Post && Access::allowed($post,'edit',$user_id);
            },
        ]);
    }
    public static function register_extension(): void {
        ExtensionRegistry::register([
            'id'=>'core-blueprint-notes','plugin_file'=>CB_NOTES_BASENAME,
            'requires_api'=>CB_NOTES_REQUIRED_API,
            'menu_url'=>admin_url('edit.php?post_type='.self::TYPE),
            'status_id'=>'core-blueprint-notes',
        ]);
    }
    public static function status_definitions(array $definitions): array {
        $definitions['core-blueprint-notes']=[
            'provider'=>[self::class,'status'],
            'label'=>__('Notes','core-blueprint-notes'),
            'url'=>admin_url('edit.php?post_type='.self::TYPE),
        ];
        return $definitions;
    }
    public static function status(): array {
        return ['state'=>'ok',
            'detail'=>__('Private Gutenberg notes enabled','core-blueprint-notes'),
            'url'=>admin_url('edit.php?post_type='.self::TYPE)];
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
    public static function audit_trash(int $post_id): void {self::audit_lifecycle('trashed',$post_id);}
    public static function audit_restore(int $post_id): void {self::audit_lifecycle('restored',$post_id);}
    public static function audit_delete(int $post_id,\WP_Post $post): void {
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
        wp_enqueue_script('cb-notes-editor',
            plugins_url('assets/editor.js',CB_NOTES_FILE),
            ['wp-plugins','wp-edit-post','wp-components','wp-data','wp-element'],
            CB_NOTES_VERSION,true);
    }
    public static function admin_menu(): void {
        add_submenu_page('edit.php?post_type='.self::TYPE,
            __('Notes AI Access','core-blueprint-notes'),
            __('AI Access','core-blueprint-notes'),
            'manage_options','cb-notes-ai',[self::class,'settings_page']);
    }
    public static function admin_settings(): void {
        register_setting('cb_notes_settings',self::AI_OPTION,[
            'type'=>'boolean','default'=>false,
            'sanitize_callback'=>static fn(mixed $v): bool =>
                current_user_can('manage_options')&&(bool)$v,
        ]);
    }
    public static function settings_page(): void {
        if(!current_user_can('manage_options'))
            wp_die(esc_html__('Access denied.','core-blueprint-notes'));
        echo '<div class="wrap"><h1>'.esc_html__('Notes AI Access','core-blueprint-notes').'</h1>';
        echo '<p>'.esc_html__('Read-only Notes abilities are disabled by default. Enabling them makes them discoverable through a separately installed, authenticated MCP Adapter. Only authorized notes can be read.','core-blueprint-notes').'</p>';
        echo '<p>'.esc_html__('Only users with cb_notes_use_ai (administrators by default) can invoke these abilities. No writing ability is registered.','core-blueprint-notes').'</p>';
        echo '<form action="options.php" method="post">';
        settings_fields('cb_notes_settings');
        echo '<label><input type="checkbox" name="'.esc_attr(self::AI_OPTION).'" value="1" '.
            checked((bool)get_option(self::AI_OPTION,false),true,false).'> '.
            esc_html__('Allow read-only Notes abilities in MCP','core-blueprint-notes').'</label>';
        submit_button();
        echo '</form></div>';
    }
    public static function action_links(array $links): array {
        $links[]='<a href="'.esc_url(admin_url('edit.php?post_type='.self::TYPE)).'">'.
            esc_html__('Notes','core-blueprint-notes').'</a>';
        return $links;
    }
}
