<?php
declare(strict_types=1);
namespace CB\Notes;
defined('ABSPATH') || exit;
/** Read-only WordPress Abilities; external MCP transport is never bundled. */
final class Abilities {
    public static function register(): void {
        if (!function_exists('wp_register_ability') || !function_exists('wp_register_ability_category')) return;
        wp_register_ability_category('core-blueprint-notes',[
            'label'=>__('Core Blueprint Notes','core-blueprint-notes'),
            'description'=>__('Permissioned access to WordPress Notes.','core-blueprint-notes'),
        ]);
        $enabled=(bool)get_option(Plugin::AI_OPTION,false);
        $meta=[
            'public'=>false,'show_in_rest'=>false,
            'mcp'=>['public'=>$enabled,'type'=>'tool'],
            'annotations'=>['readonly'=>true,'destructive'=>false,'idempotent'=>true],
        ];
        $schemas=[
            'list-notes'=>[
                'type'=>'object','properties'=>[
                    'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>20],
                    'page'=>['type'=>'integer','minimum'=>1,'maximum'=>50],
                ],'additionalProperties'=>false,
            ],
            'search-notes'=>[
                'type'=>'object','properties'=>[
                    'query'=>['type'=>'string','minLength'=>1,'maxLength'=>120],
                    'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>20],
                ],'required'=>['query'],'additionalProperties'=>false,
            ],
            'read-note'=>[
                'type'=>'object','properties'=>['id'=>['type'=>'integer','minimum'=>1]],
                'required'=>['id'],'additionalProperties'=>false,
            ],
        ];
        foreach($schemas as $name=>$schema) {
            $method=str_replace('-','_',$name);
            wp_register_ability('core-blueprint-notes/'.$name,[
                'label'=>ucwords(str_replace('-',' ',$name)),
                'description'=>__('Read only authorized private WordPress notes. No content is changed.','core-blueprint-notes'),
                'category'=>'core-blueprint-notes',
                'input_schema'=>$schema,
                'execute_callback'=>[self::class,$method],
                'permission_callback'=>[self::class,'can_execute'],
                'meta'=>$meta,
            ]);
        }
    }
    public static function can_execute(mixed $input=null): bool {
        unset($input);
        return (bool)get_option(Plugin::AI_OPTION,false) &&
            get_current_user_id()>0 && current_user_can('cb_notes_use_ai');
    }
    public static function list_notes(array $input=[]): array|\WP_Error {
        return self::search_list('',$input);
    }
    public static function search_notes(array $input=[]): array|\WP_Error {
        $query=trim((string)($input['query']??''));
        if($query===''||strlen($query)>120)
            return new \WP_Error('cb_notes_invalid_query',__('Invalid search.','core-blueprint-notes'));
        return self::search_list($query,$input);
    }
    public static function read_note(array $input=[]): array|\WP_Error {
        if(!self::can_execute()) return self::not_found();
        $id=absint($input['id']??0);
        $post=$id>0?get_post($id):null;
        if(!$post instanceof \WP_Post || (int)$post->post_author !== get_current_user_id() || !Access::can_read($post)) return self::not_found();
        return [
            'id'=>$post->ID,'title'=>$post->post_title,
            'notebook'=>(string)get_post_meta($id,Plugin::META_NOTEBOOK,true),
            'content'=>$post->post_content,'modified'=>$post->post_modified_gmt,
        ];
    }
    private static function search_list(string $search,array $input): array|\WP_Error {
        if(!self::can_execute()) return self::not_found();
        $limit=max(1,min(20,absint($input['limit']??10)));
        $page=max(1,min(50,absint($input['page']??1)));
        $args=[
            'post_type'=>Plugin::TYPE,
            'post_status'=>['draft','private','publish','pending','future'],
            'posts_per_page'=>$limit,'paged'=>$page,
            'orderby'=>'modified','order'=>'DESC','suppress_filters'=>false,
        ];
        $args['author']=get_current_user_id();
        if($search!=='') $args['s']=$search;
        $items=[];
        foreach(get_posts($args) as $post) {
            if(!$post instanceof \WP_Post||!Access::can_read($post)) continue;
            $items[]=[
                'id'=>(int)$post->ID,'title'=>(string)$post->post_title,
                'notebook'=>(string)get_post_meta($post->ID,Plugin::META_NOTEBOOK,true),
                'modified'=>(string)$post->post_modified_gmt,
            ];
        }
        return ['items'=>$items,'page'=>$page,'limit'=>$limit];
    }
    private static function not_found(): \WP_Error {
        return new \WP_Error('cb_notes_not_found',__('Note not found.','core-blueprint-notes'));
    }
}
