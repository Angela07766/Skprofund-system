<?php
// Test-only fixtures. Refuses every database name not ending in _test.
require __DIR__.'/../app/bootstrap.php';
if(!str_ends_with(one('SELECT DATABASE() n')['n'],'_test'))throw new RuntimeException('Use an isolated *_test database.');
$password=getenv('SK_TEST_PASSWORD');if(!$password || strlen($password)<16)throw new RuntimeException('Provide a generated test password.');
foreach(['admin','chairperson','treasurer','official','resident'] as $code){$role=one('SELECT id FROM roles WHERE code=?',[$code]);query('INSERT INTO users(role_id,name,email,password_hash,must_change_password) VALUES(?,?,?,?,0)',[$role['id'],ucfirst($code).' Test',$code.'@example.test',password_hash($password,PASSWORD_DEFAULT)]);}
echo "Created five isolated test accounts.\n";
