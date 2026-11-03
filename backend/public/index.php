<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out(array $data,int $status=200): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
function input(): array { try { $v=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR); } catch(Throwable $e){out(['erro'=>'JSON inválido'],400);} if(!is_array($v)||array_is_list($v))out(['erro'=>'Envie um objeto JSON'],400); return $v; }
function db(): PDO { return new PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s',getenv('DB_HOST')?:'postgres',getenv('DB_PORT')?:'5432',getenv('DB_NAME')?:'sistema'),getenv('DB_USER')?:'sistema',getenv('DB_PASSWORD')?:'sistema_dev',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); }
function query(PDO $db,string $sql,array $params=[]): PDOStatement { $s=$db->prepare($sql);$s->execute($params);return $s; }
function auth(PDO $db): array { $token=$_COOKIE['aula_session']??'';if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))out(['erro'=>'Não autenticado'],401);$u=query($db,'SELECT u.id,u.name,u.email,r.name AS role FROM sessions s JOIN users u ON u.id=s.user_id JOIN roles r ON r.id=u.role_id WHERE s.token_hash=? AND s.expires_at>NOW() AND u.active=TRUE',[hash('sha256',$token)])->fetch();if(!$u)out(['erro'=>'Sessão inválida ou expirada'],401);return $u; }
function permit(array $u,array $roles): void {if(!in_array($u['role'],$roles,true))out(['erro'=>'Acesso negado para este perfil'],403);}
function requiredText(array $v,string $key,int $max=180): string { $s=$v[$key]??null;if(!is_string($s)||trim($s)===''||strlen($s)>$max)out(['erro'=>"Campo $key inválido"],422);return trim($s); }
function idval(mixed $v): int {if(!is_int($v)&&!(is_string($v)&&ctype_digit($v)))out(['erro'=>'Identificador inválido'],422);$i=(int)$v;if($i<1)out(['erro'=>'Identificador inválido'],422);return $i;}
function productData(array $v): array { $price=$v['price']??null;$stock=$v['stock']??null;if(!(is_numeric($price)&&is_finite((float)$price)&&(float)$price>=0&&(float)$price<=9999999999))out(['erro'=>'Preço inválido'],422);if(filter_var($stock,FILTER_VALIDATE_INT)===false||(int)$stock<0)out(['erro'=>'Estoque inválido'],422);return [requiredText($v,'name'),requiredText($v,'sku',60),is_string($v['description']??null)?trim($v['description']):'',(string)$price,(int)$stock,idval($v['department_id']??null),idval($v['category_id']??null)]; }
$path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/', '/');$method=$_SERVER['REQUEST_METHOD'];
try {
 $db=db();
 if($path==='/api/health'&&$method==='GET'){ $db->query('SELECT 1');out(['status'=>'ok','banco'=>'conectado']); }
 if($path==='/api/login'&&$method==='POST'){
  $v=input();$email=strtolower(trim((string)($v['email']??'')));$password=$v['password']??'';
  $u=query($db,'SELECT u.*,r.name AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE lower(u.email)=? AND u.active=TRUE',[$email])->fetch();
  if(!is_string($password)||!$u||!password_verify($password,$u['password_hash']))out(['erro'=>'Credenciais inválidas'],401);
  $token=bin2hex(random_bytes(32));query($db,"INSERT INTO sessions(user_id,token_hash,expires_at) VALUES(?,?,NOW()+INTERVAL '8 hours')",[$u['id'],hash('sha256',$token)]);
  setcookie('aula_session',$token,['expires'=>time()+28800,'path'=>'/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
  out(['user'=>['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']]]);
 }
 $u=auth($db);
 if($path==='/api/me'&&$method==='GET')out(['user'=>$u]);
 if($path==='/api/logout'&&$method==='POST'){ $token=$_COOKIE['aula_session']??'';query($db,'DELETE FROM sessions WHERE token_hash=?',[hash('sha256',$token)]);setcookie('aula_session','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);out(['mensagem'=>'Sessão encerrada']); }
 if($path==='/api/users'&&$method==='GET'){permit($u,['admin']);out(['users'=>$db->query('SELECT u.id,u.name,u.email,u.active,r.name AS role FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id')->fetchAll()]);}
 if($path==='/api/users'&&$method==='POST'){permit($u,['admin']);$v=input();$password=$v['password']??'';if(!is_string($password)||strlen($password)<10)out(['erro'=>'Senha deve ter no mínimo 10 caracteres'],422);$role=requiredText($v,'role',30);if(!in_array($role,['admin','gerente','operador'],true))out(['erro'=>'Perfil inválido'],422);$email=strtolower(requiredText($v,'email',254));if(!filter_var($email,FILTER_VALIDATE_EMAIL))out(['erro'=>'Email inválido'],422);$r=query($db,'INSERT INTO users(name,email,password_hash,role_id) SELECT ?,?,?,id FROM roles WHERE name=? RETURNING id,name,email',[requiredText($v,'name',120),$email,password_hash($password,PASSWORD_DEFAULT),$role])->fetch();out(['user'=>$r],201);}
 if($path==='/api/departments'&&$method==='GET')out(['departments'=>$db->query('SELECT * FROM departments ORDER BY name')->fetchAll()]);
 if($path==='/api/departments'&&$method==='POST'){permit($u,['admin']);$v=input();out(['department'=>query($db,'INSERT INTO departments(name) VALUES(?) RETURNING *',[requiredText($v,'name',100)])->fetch()],201);}
 if($path==='/api/categories'&&$method==='GET')out(['categories'=>$db->query('SELECT c.*,d.name AS department_name FROM categories c JOIN departments d ON d.id=c.department_id ORDER BY d.name,c.name')->fetchAll()]);
 if($path==='/api/categories'&&$method==='POST'){permit($u,['admin','gerente']);$v=input();out(['category'=>query($db,'INSERT INTO categories(name,department_id) VALUES(?,?) RETURNING *',[requiredText($v,'name',100),idval($v['department_id']??null)])->fetch()],201);}
 if($path==='/api/products'&&$method==='GET')out(['products'=>$db->query('SELECT p.*,d.name AS department_name,c.name AS category_name FROM products p JOIN departments d ON d.id=p.department_id JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC')->fetchAll()]);
 if($path==='/api/products'&&$method==='POST'){permit($u,['admin','gerente']);$v=productData(input());$v[]=$u['id'];out(['product'=>query($db,'INSERT INTO products(name,sku,description,price,stock,department_id,category_id,created_by) VALUES(?,?,?,?,?,?,?,?) RETURNING *',$v)->fetch()],201);}
 if(preg_match('#^/api/products/([1-9][0-9]*)$#',$path,$m)){
  if($method==='PUT'){permit($u,['admin','gerente']);$v=productData(input());$v[]=(int)$m[1];$p=query($db,'UPDATE products SET name=?,sku=?,description=?,price=?,stock=?,department_id=?,category_id=? WHERE id=? RETURNING *',$v)->fetch();if(!$p)out(['erro'=>'Produto não encontrado'],404);out(['product'=>$p]);}
  if($method==='DELETE'){permit($u,['admin']);$p=query($db,'DELETE FROM products WHERE id=? RETURNING id',[(int)$m[1]])->fetch();if(!$p)out(['erro'=>'Produto não encontrado'],404);out(['mensagem'=>'Produto excluído']);}
 }
 out(['erro'=>'Rota não encontrada'],404);
} catch(PDOException $e){error_log((string)$e);if($e->getCode()==='23505')out(['erro'=>'Registro duplicado (nome, SKU ou e-mail)'],409);if($e->getCode()==='23503')out(['erro'=>'Relacionamento inválido ou registro em uso'],422);out(['erro'=>'Erro no banco de dados'],503);} catch(Throwable $e){error_log((string)$e);out(['erro'=>'Erro interno'],500);}