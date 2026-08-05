
<?php
$username = "mk_braman";
$password = "mkbramhan@123";

$hash = password_hash(
    $password,
    PASSWORD_BCRYPT
);

echo $hash;

?>
