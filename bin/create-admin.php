<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Audit\AuditService;
use App\Database\Connection;
use App\Database\Migrator;
use Dotenv\Dotenv;

// Carrega variáveis de ambiente se existir o arquivo .env
if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

// Garante que o banco e migrações estejam inicializados
$migrator = new Migrator();
$migrator->migrate();

$pdo = Connection::getPdo();

echo "========================================================\n";
echo "   Plataforma Pinciara: Criação Segura de Administrador \n";
echo "========================================================\n\n";

// Analisa argumentos da linha de comando (--name, --email, --password)
$options = getopt('', ['name::', 'email::', 'password::', 'help']);

if (isset($options['help'])) {
    echo "Uso:\n";
    echo "  php bin/create-admin.php\n";
    echo "  php bin/create-admin.php --name=\"Admin\" --email=\"admin@pinciara.com\" --password=\"SenhaForte123\"\n\n";
    echo "Opções:\n";
    echo "  --name      Nome completo do administrador\n";
    echo "  --email     E-mail válido do administrador\n";
    echo "  --password  Senha de acesso (mínimo 8 caracteres). Se omitida, uma senha forte será gerada.\n";
    exit(0);
}

// Verifica se já existe algum administrador cadastrado
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM users u 
    JOIN roles r ON r.id = u.role_id 
    WHERE r.name = 'admin'
");
$adminCount = (int)$stmt->fetchColumn();

if ($adminCount > 0) {
    echo "[AVISO] Já existe(m) {$adminCount} administrador(es) cadastrado(s) no sistema.\n";
    if (empty($options['email'])) {
        echo "Deseja cadastrar outro administrador? (s/N): ";
        $handle = fopen('php://stdin', 'r');
        $answer = trim((string)fgets($handle));
        if (strtolower($answer) !== 's' && strtolower($answer) !== 'sim') {
            echo "Operação cancelada.\n";
            exit(0);
        }
    }
}

// Obtenção do Nome
$name = $options['name'] ?? null;
if (empty($name)) {
    echo "Informe o nome do Administrador [Administrador]: ";
    $handle = fopen('php://stdin', 'r');
    $input = trim((string)fgets($handle));
    $name = $input !== '' ? $input : 'Administrador';
}

// Obtenção do E-mail
$email = $options['email'] ?? null;
while (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Informe o e-mail do Administrador: ";
    $handle = fopen('php://stdin', 'r');
    $email = trim(mb_strtolower((string)fgets($handle)));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "[ERRO] E-mail inválido. Por favor, forneça um endereço de e-mail válido.\n";
        $email = null;
    }
}

// Verifica duplicidade de e-mail
$checkEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$checkEmail->execute([':email' => $email]);
if ($checkEmail->fetch()) {
    fwrite(STDERR, "[ERRO] O e-mail '{$email}' já está cadastrado no sistema.\n");
    exit(1);
}

// Obtenção da Senha
$password = $options['password'] ?? null;
$wasGenerated = false;

if (empty($password)) {
    echo "Informe a senha do Administrador (mínimo 8 caracteres) [deixe em branco para gerar aleatória segura]: ";
    $handle = fopen('php://stdin', 'r');
    $password = trim((string)fgets($handle));

    if ($password === '') {
        // Gera senha aleatória de 16 caracteres com alta entropia
        $password = bin2hex(random_bytes(8));
        $wasGenerated = true;
    }
}

if (strlen($password) < 8) {
    fwrite(STDERR, "[ERRO] A senha deve conter pelo menos 8 caracteres.\n");
    exit(1);
}

// Criação do usuário administrador com password_hash()
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare('
    INSERT INTO users (name, email, password_hash, role_id, is_active)
    VALUES (:name, :email, :password_hash, 1, 1)
');

$insert->execute([
    ':name' => $name,
    ':email' => $email,
    ':password_hash' => $passwordHash,
]);

$newAdminId = (int)$pdo->lastInsertId();

// Registro de Auditoria
AuditService::log(
    action: $adminCount === 0 ? 'user.create_first_admin' : 'user.create_admin',
    entityType: 'user',
    entityId: (string)$newAdminId,
    newValues: [
        'name' => $name,
        'email' => $email,
        'role' => 'admin',
    ],
    userId: $newAdminId
);

echo "\n[SUCESSO] Administrador cadastrado com êxito!\n";
echo "--------------------------------------------------------\n";
echo " ID:       {$newAdminId}\n";
echo " Nome:     {$name}\n";
echo " E-mail:   {$email}\n";
echo " Perfil:   Administrador (admin)\n";

if ($wasGenerated) {
    echo " Senha gerada: {$password}\n";
    echo " >> ATENÇÃO: Anote esta senha de forma segura. Ela não será exibida novamente! <<\n";
} else {
    echo " Senha:    [Definida pelo usuário - protegida com password_hash()]\n";
}
echo "--------------------------------------------------------\n";
exit(0);
