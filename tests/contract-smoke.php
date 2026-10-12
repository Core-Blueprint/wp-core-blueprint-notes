<?php
declare(strict_types=1);
/** Permission and Abilities contracts tested without a WordPress runtime. */
define('ABSPATH',__DIR__.'/');
define('CB_NOTES_DIR',dirname(__DIR__).'/');
$GLOBALS['test_uid']=0;
$GLOBALS['test_users']=[
  1=>['cb_notes_edit','cb_notes_delete'],
  2=>['cb_notes_edit','cb_notes_delete','cb_notes_use_ai'],
  3=>['cb_notes_manage_all','cb_notes_use_ai'],
];
$GLOBALS['test_posts']=[];
$GLOBALS['test_opts']=['cb_notes_mcp_read_enabled'=>false];
$GLOBALS['test_abilities']=[];
$GLOBALS['test_last_query']=[];
class WP_Post {
  public function __construct(
    public int $ID,public string $post_type,public int $post_author,
    public string $post_status='private',public string $post_title='',
    public string $post_content='',public string $post_modified_gmt='2026-10-13 00:00:00'
  ) {}
}
class WP_Error {
  public function __construct(public string $code,public string $message) {}
}
class WP_REST_Response {}
class WP_Query {
  private array $vars;
  public function __construct(array $vars) {$this->vars=$vars;}
  public function get(string $key): mixed {return $this->vars[$key]??null;}
  public function set(string $key,mixed $value): void {$this->vars[$key]=$value;}
}
function __(string $text,string $domain): string {return $text;}
function get_current_user_id(): int {return $GLOBALS['test_uid'];}
function user_can(int $id,string $cap): bool {
  return in_array($cap,$GLOBALS['test_users'][$id]??[],true);
}
function current_user_can(string $cap): bool {return user_can(get_current_user_id(),$cap);}
function get_post(int $id): ?WP_Post {return $GLOBALS['test_posts'][$id]??null;}
function get_option(string $key,mixed $default=null): mixed {
  return $GLOBALS['test_opts'][$key]??$default;
}
function get_post_meta(int $id,string $key,bool $single): string {return 'Personal';}
function absint(mixed $v): int {return abs((int)$v);}
function wp_register_ability_category(string $name,array $args): void {}
function wp_register_ability(string $name,array $args): void {$GLOBALS['test_abilities'][$name]=$args;}
function get_posts(array $args): array {
  $GLOBALS['test_last_query']=$args;
  return array_values(array_filter($GLOBALS['test_posts'],static function(WP_Post $post)use($args): bool {
    return $post->post_type===$args['post_type'] &&
      (!isset($args['author']) || $post->post_author===$args['author']) &&
      (!isset($args['s']) || str_contains($post->post_title,$args['s']));
  }));
}
require dirname(__DIR__).'/src/Access.php';
require dirname(__DIR__).'/src/Plugin.php';
require dirname(__DIR__).'/src/Abilities.php';
use CB\Notes\Access;
use CB\Notes\Abilities;
function expect(bool $condition,string $label): void {
  if(!$condition){fwrite(STDERR,"FAIL: $label\n");exit(1);}
  echo "PASS: $label\n";
}
$GLOBALS['test_posts']=[
  10=>new WP_Post(10,'cb_note',1,'private','Alpha','<!-- wp:paragraph --><p>Private</p><!-- /wp:paragraph -->'),
  20=>new WP_Post(20,'cb_note',2,'draft','Bravo','<!-- wp:paragraph --><p>Other</p><!-- /wp:paragraph -->'),
  30=>new WP_Post(30,'post',1,'publish','Public'),
];
$GLOBALS['test_uid']=1;
expect(Access::can_read($GLOBALS['test_posts'][10]),'owner reads own note');
expect(!Access::can_read($GLOBALS['test_posts'][20]),'cross-user read denied');
foreach(['read_post','edit_post','delete_post'] as $cap)
  expect(Access::map_meta_cap(['read'],$cap,1,[20])===['do_not_allow'],"cross-user $cap denied");
expect(Access::map_meta_cap(['read'],'read_post',1,[30])===['read'],'normal posts unaffected');
$q=new WP_Query(['post_type'=>'cb_note','author'=>2]);
Access::scope_query($q);
expect($q->get('author')===1,'list owner scoped');
expect(Access::scope_rest(['author'=>2])['author']===1,'REST cannot force alternate author');
$GLOBALS['test_uid']=0;
expect(!Access::can_read($GLOBALS['test_posts'][10]),'anonymous read denied');
$q=new WP_Query(['post_type'=>'cb_note']);
Access::scope_query($q);
expect($q->get('post__in')===[0],'anonymous list denied');
$GLOBALS['test_uid']=3;
expect(Access::can_read($GLOBALS['test_posts'][10]),'explicit admin override');
Abilities::register();
expect(count($GLOBALS['test_abilities'])===3,'three AI read abilities registered');
foreach($GLOBALS['test_abilities'] as $ability) {
  expect($ability['meta']['mcp']['public']===false,'MCP hidden by default');
  expect($ability['meta']['show_in_rest']===false,'Abilities REST hidden');
}
expect(Abilities::read_note(['id'=>10]) instanceof WP_Error,'AI denied while disabled');
$GLOBALS['test_opts']['cb_notes_mcp_read_enabled']=true;
$GLOBALS['test_abilities']=[];
Abilities::register();
expect($GLOBALS['test_abilities']['core-blueprint-notes/read-note']['meta']['mcp']['public']===true,'MCP enabled explicitly');
expect(Abilities::read_note(['id'=>10])['content']===$GLOBALS['test_posts'][10]->post_content,'block content preserved');
$GLOBALS['test_uid']=2;
expect(Abilities::read_note(['id'=>10]) instanceof WP_Error,'AI cross-user exact read denied');
expect(Abilities::read_note(['id'=>20])['id']===20,'AI own note read allowed');
expect(count(Abilities::list_notes(['limit'=>20])['items'])===1,'AI list owner scoped');
expect($GLOBALS['test_last_query']['author']===2,'AI database query owner scoped');
expect(Abilities::search_notes(['query'=>'']) instanceof WP_Error,'empty search denied');
expect(Abilities::search_notes(['query'=>'Bravo'])['items'][0]['id']===20,'AI own search works');
expect(Abilities::search_notes(['query'=>'Alpha'])['items']===[],'AI other owner search hidden');
$GLOBALS['test_uid']=1;
expect(Abilities::read_note(['id'=>10]) instanceof WP_Error,'AI capability required');
echo "Notes contract smoke: PASS\n";
