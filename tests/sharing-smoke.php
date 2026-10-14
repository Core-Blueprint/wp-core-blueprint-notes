<?php
declare(strict_types=1);
/** Alpha3 no-WordPress contract smoke. Does NOT replace real multi-user runtime testing. */

define('ABSPATH', __DIR__ . '/');
$GLOBALS['current_user'] = 4;
$GLOBALS['posts'] = [];
$GLOBALS['notes_meta'] = [];
$GLOBALS['capabilities'] = [
    4 => ['edit_post', 'cb_notes_edit', 'cb_notes_share_public', 'cb_notes_share_secure'],
    5 => ['edit_post', 'cb_notes_edit'],
];
class WP_Post {
    public function __construct(
        public int $ID, public string $post_type, public int $post_author,
        public string $post_status, public string $post_title,
        public string $post_content, public string $post_modified_gmt = '2026-10-13 10:00:00'
    ) {}
}
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data = []) {}
}
class WP_REST_Request {
    public function __construct(private string $route, private array $params = []) {}
    public function get_route(): string { return $this->route; }
    public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
}
class WP_REST_Server { public const READABLE = 'GET'; public const CREATABLE = 'POST'; }
class WP_Block_Type_Registry {
    public static function get_instance(): self { return new self(); }
    public function get_registered(string $name): object { return new class {
        public function is_dynamic(): bool { return false; }
    }; }
}
function __(string $text, string $domain): string { return $text; }
function absint(mixed $value): int { return abs((int)$value); }
function sanitize_text_field(string $value): string { return strip_tags($value); }
function get_current_user_id(): int { return $GLOBALS['current_user']; }
function user_can(int $id,string $cap): bool { return in_array($cap,$GLOBALS['capabilities'][$id]??[],true); }
function current_user_can(string $cap,int $id=0): bool {
    if($cap==='edit_post') return $id>0 && (int)(get_post($id)?->post_author??0)===get_current_user_id() && user_can(get_current_user_id(),$cap);
    return user_can(get_current_user_id(),$cap);
}
function get_post(int $id): ?WP_Post { return $GLOBALS['posts'][$id]??null; }
function get_post_meta(int $id,string $key,bool $single=true): mixed {
    return $GLOBALS['notes_meta'][$id][$key]??'';
}
function update_post_meta(int $id,string $key,mixed $value): bool {
    if (($GLOBALS['fail_share_meta_once'] ?? '') === $key) {
        $GLOBALS['fail_share_meta_once'] = '';
        return false;
    }
    if (($GLOBALS['fail_share_meta_second_key'] ?? '') === $key) {
        $GLOBALS['fail_share_meta_second_count'] = ($GLOBALS['fail_share_meta_second_count'] ?? 0) + 1;
        if ($GLOBALS['fail_share_meta_second_count'] === 2) {
            $GLOBALS['fail_share_meta_second_key'] = '';
            $GLOBALS['fail_share_meta_second_count'] = 0;
            return false;
        }
    }
    $GLOBALS['notes_meta'][$id][$key]=$value; return true;
}
function delete_post_meta(int $id,string $key): bool {
    unset($GLOBALS['notes_meta'][$id][$key]); return true;
}
function is_wp_error(mixed $obj): bool { return $obj instanceof WP_Error; }
function parse_blocks(string $html): array {
    if(str_contains($html,'UNSAFE')) return [['blockName'=>'core/embed','innerBlocks'=>[],'innerHTML'=>'']];
    return [['blockName'=>'core/paragraph','innerBlocks'=>[],'innerHTML'=>$html]];
}
function render_block(array $block): string { return (string)$block['innerHTML']; }
function wp_kses(string $html,array $allowed,array $protocols=[]): string { return strip_tags($html,'<p><strong><em><h2>'); }
function home_url(string $path): string {return 'https://example.test'.$path;}
function gmdate_unused(): void {}
function esc_html(string $text): string{return htmlspecialchars($text,ENT_QUOTES);}
function esc_attr(string $text): string{return esc_html($text);}
function get_bloginfo(string $key): string{return 'en-US';}
class WP_Query {public function __construct(array $args=[]) {}}
class MockAudit { public static function record(string $event,string $severity,array $details): void {} }
class_alias(MockAudit::class, 'CoreBlueprint\\Core\\Governance\\Audit');
require dirname(__DIR__).'/src/Access.php';
require dirname(__DIR__).'/src/Plugin.php';
require dirname(__DIR__).'/src/Sharing/Snapshot.php';
require dirname(__DIR__).'/src/Integration/SecureProvider.php';
require dirname(__DIR__).'/src/Sharing/Controller.php';
function verify(bool $ok,string $message): void {
    if (!$ok) {fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
$note=new WP_Post(42,'cb_note',4,'private','Private note','<p>Not for everyone</p>');
$GLOBALS['posts'][42]=$note;
verify(\CB\Notes\Access::force_private_status(['post_type'=>'cb_note','post_status'=>'publish'],[])['post_status']==='private','Gutenberg publish stays private');
verify(\CB\Notes\Access::force_private_status(['post_type'=>'post','post_status'=>'publish'],[])['post_status']==='publish','normal posts unaffected');
verify(\CB\Notes\Access::before_rest(null,null,new WP_REST_Request('/wp/v2/cb-notes/42/revisions'))===null,'authorized revisions accepted');
$GLOBALS['current_user']=5;
verify(\CB\Notes\Access::before_rest(null,null,new WP_REST_Request('/wp/v2/cb-notes/42')) instanceof WP_Error,'cross-user note REST refused');
verify(\CB\Notes\Access::before_rest(null,null,new WP_REST_Request('/wp/v2/cb-notes/42/revisions')) instanceof WP_Error,'cross-user revisions REST refused');
$GLOBALS['current_user']=0;
verify(\CB\Notes\Access::before_rest(null,null,new WP_REST_Request('/wp/v2/cb-notes/42/autosaves')) instanceof WP_Error,'anonymous autosaves refused');
$GLOBALS['current_user']=4;
verify(!\CB\Notes\Integration\SecureProvider::available(),'missing Secure Share fails soft');
$version=\CB\Notes\Sharing\Controller::read(new WP_REST_Request('/core-blueprint-notes/v1/sharing/42',['id'=>42]))['content_version'];
$payload=new WP_REST_Request('/core-blueprint-notes/v1/sharing/42',[
    'id'=>42, 'mode'=>'public', 'expected_modified_gmt'=>$note->post_modified_gmt, 'expected_mode'=>'private', 'expected_content_version'=>$version, 'confirmation'=>''
]);
verify(\CB\Notes\Sharing\Controller::can_manage($payload),'author may manage own note');
verify(\CB\Notes\Sharing\Controller::change($payload) instanceof WP_Error,'public without explicit acknowledgement denied');
$payload=new WP_REST_Request('/core-blueprint-notes/v1/sharing/42',[
    'id'=>42, 'mode'=>'public', 'expected_modified_gmt'=>'stale', 'expected_mode'=>'private', 'expected_content_version'=>$version, 'confirmation'=>'PUBLIC'
]);
verify(\CB\Notes\Sharing\Controller::change($payload) instanceof WP_Error,'stale revision denied');
$staleContent=new WP_REST_Request('/core-blueprint-notes/v1/sharing/42',[
    'id'=>42,'mode'=>'public','expected_modified_gmt'=>$note->post_modified_gmt,
    'expected_mode'=>'private','expected_content_version'=>'outdated','confirmation'=>'PUBLIC'
]);
verify(\CB\Notes\Sharing\Controller::change($staleContent) instanceof WP_Error,'stale content digest denied');
$payload=new WP_REST_Request('/core-blueprint-notes/v1/sharing/42',[
    'id'=>42, 'mode'=>'public', 'expected_modified_gmt'=>$note->post_modified_gmt, 'expected_mode'=>'private', 'expected_content_version'=>$version, 'confirmation'=>'PUBLIC'
]);
foreach ([
    \CB\Notes\Sharing\Controller::PUBLIC_META,
    \CB\Notes\Sharing\Controller::PUBLIC_KEY,
    \CB\Notes\Sharing\Controller::MODE_META,
] as $failed_key) {
    $GLOBALS['fail_share_meta_once'] = $failed_key;
    $failed = \CB\Notes\Sharing\Controller::change($payload);
    verify($failed instanceof WP_Error, 'RC1: failed public-share meta write rejected');
    verify(get_post_meta(42, \CB\Notes\Sharing\Controller::MODE_META) === 'private'
        && get_post_meta(42, \CB\Notes\Sharing\Controller::PUBLIC_KEY) === ''
        && get_post_meta(42, \CB\Notes\Sharing\Controller::PUBLIC_META) === '',
        'RC1: failed public-share write leaves no active link');
}
$result=\CB\Notes\Sharing\Controller::change($payload);
verify(!($result instanceof WP_Error) && $result['mode']==='public','confirmed public frozen snapshot created');
verify(str_contains((string)$result['public_url'],'/cb-notes/shared/'),'public URL uses isolated route');
verify(get_post_meta(42,\CB\Notes\Sharing\Controller::PUBLIC_META)['html']==='<p>Not for everyone</p>','frozen content stored separately');
$key=(string)get_post_meta(42,\CB\Notes\Sharing\Controller::PUBLIC_KEY);
$snapshot=get_post_meta(42,\CB\Notes\Sharing\Controller::PUBLIC_META);
verify(\CB\Notes\Sharing\Controller::public_snapshot_allowed($note,$key,$snapshot),'explicit active public snapshot permitted');
$note->post_status='trash';
verify(!\CB\Notes\Sharing\Controller::public_snapshot_allowed($note,$key,$snapshot),'trashed notes cannot remain public');
$note->post_status='private';
verify(!\CB\Notes\Sharing\Controller::public_snapshot_allowed($note,'deadbeefdeadbeefdeadbeefdeadbeef',$snapshot),'invalid public key denied');

$note->post_content='<p>New private content</p>';
$latestVersion=\CB\Notes\Sharing\Controller::read(new WP_REST_Request('/core-blueprint-notes/v1/sharing/42',['id'=>42]))['content_version'];
verify($latestVersion!==$version,'content digest changes for same-timestamp note edit');
verify(get_post_meta(42,\CB\Notes\Sharing\Controller::PUBLIC_META)['html']==='<p>Not for everyone</p>','private edits do not change public copy');
$payload=new WP_REST_Request('/core-blueprint-notes/v1/sharing/42',[
    'id'=>42, 'mode'=>'private', 'expected_modified_gmt'=>'stale-is-okay-for-revocation',
    'expected_mode'=>'obsolete', 'expected_content_version'=>'obsolete'
]);
$result=\CB\Notes\Sharing\Controller::change($payload);
verify($result['mode']==='private' && get_post_meta(42,\CB\Notes\Sharing\Controller::PUBLIC_KEY)==='','private stops public URL authorization');
verify(!\CB\Notes\Sharing\Controller::public_snapshot_allowed($note,$key,$snapshot),'private mode invalidates previous share key');
update_post_meta(42,\CB\Notes\Sharing\Controller::MODE_META,'public');
update_post_meta(42,\CB\Notes\Sharing\Controller::PUBLIC_META,$snapshot);
update_post_meta(42,\CB\Notes\Sharing\Controller::PUBLIC_KEY,$key);
$note->post_status='trash';
\CB\Notes\Sharing\Controller::revoke_for_lifecycle(42);
$note->post_status='private';
verify(get_post_meta(42,\CB\Notes\Sharing\Controller::MODE_META)==='private','trash revokes share mode permanently');
verify(!\CB\Notes\Sharing\Controller::public_snapshot_allowed($note,$key,$snapshot),'restore does not reactivate old public link');
verify(get_post_meta(42,\CB\Notes\Sharing\Controller::PUBLIC_KEY)==='','trash removes shared URL key');
$note->post_content='UNSAFE';
verify(\CB\Notes\Sharing\Snapshot::capture($note) instanceof WP_Error,'dynamic/embedded blocks refused');
$GLOBALS['current_user']=5;
verify(!\CB\Notes\Sharing\Controller::can_manage($payload),'non-author cannot change sharing');
verify(\CB\Notes\Sharing\Controller::read($payload) instanceof WP_Error,'non-author cannot read sharing metadata');
verify(\CB\Notes\Sharing\Controller::change($payload) instanceof WP_Error,'non-author cannot mutate sharing directly');

// RC1: optional provider issues a grant only after pending metadata persists.
// Failed finalization must revoke even a grant not yet persisted on the Note.
interface NotesFakeResourceProvider {}
class_alias(NotesFakeResourceProvider::class, 'CB\SecureShare\PublicApi\ResourceProvider');
final class NotesFakeShareRequest {
    public function __construct(
        public string $provider, public string $resourceType, public string $resourceKey,
        public array $actions, public string $label, public ?string $password,
        public \DateTimeImmutable $expiresAt, public int $createdBy
    ) {}
}
class_alias(NotesFakeShareRequest::class, 'CB\SecureShare\PublicApi\ShareRequest');
final class NotesFakeIssuedGrant {
    public function __construct(private int $id) {}
    public function grantId(): string { return 'grant-' . $this->id; }
    public function revision(): int { return 1; }
    public function invitationUrl(): string { return 'https://example.test/secure/' . $this->id; }
}
final class NotesFakeSecureShare {
    public static int $issues = 0;
    public static array $revocations = [];
    public static function isAvailable(): bool { return true; }
    public static function issue(NotesFakeShareRequest $request): NotesFakeIssuedGrant {
        return new NotesFakeIssuedGrant(++self::$issues);
    }
    public static function revoke(string $grantId, int $revision): void {
        self::$revocations[] = [$grantId, $revision];
    }
}
class_alias(NotesFakeSecureShare::class, 'CB\SecureShare\PublicApi\SecureShare');
$GLOBALS['current_user'] = 4;
$note->post_status = 'private';
$note->post_content = '<p>Secure contract</p>';
$secure_version = \CB\Notes\Sharing\Controller::read(new WP_REST_Request(
    '/core-blueprint-notes/v1/sharing/42', ['id' => 42]
))['content_version'];
$secure_request = new WP_REST_Request('/core-blueprint-notes/v1/sharing/42', [
    'id' => 42, 'mode' => 'secure', 'expected_modified_gmt' => $note->post_modified_gmt,
    'expected_mode' => 'private', 'expected_content_version' => $secure_version,
    'expiry_days' => 7
]);
verify(\CB\Notes\Integration\SecureProvider::available(), 'RC1: fake Secure Share public API available for failure simulation');
$GLOBALS['fail_share_meta_once'] = \CB\Notes\Sharing\Controller::SECURE_META;
$before_issues = NotesFakeSecureShare::$issues;
$failed = \CB\Notes\Sharing\Controller::change($secure_request);
verify($failed instanceof WP_Error && NotesFakeSecureShare::$issues === $before_issues,
    'RC1: pending snapshot save failure cannot issue a grant');
verify(get_post_meta(42, \CB\Notes\Sharing\Controller::MODE_META) === 'private'
    && get_post_meta(42, \CB\Notes\Sharing\Controller::SECURE_META) === '',
    'RC1: failed pending snapshot is disarmed');
$GLOBALS['fail_share_meta_second_key'] = \CB\Notes\Sharing\Controller::SECURE_META;
$before_revocations = count(NotesFakeSecureShare::$revocations);
$failed = \CB\Notes\Sharing\Controller::change($secure_request);
verify($failed instanceof WP_Error && count(NotesFakeSecureShare::$revocations) > $before_revocations,
    'RC1: issued grant revoked when secure snapshot finalization fails');
verify(get_post_meta(42, \CB\Notes\Sharing\Controller::MODE_META) === 'private'
    && get_post_meta(42, \CB\Notes\Sharing\Controller::SECURE_META) === '',
    'RC1: grant-finalization failure leaves no active Notes snapshot');

echo "Notes alpha3 sharing smoke: PASS\n";
