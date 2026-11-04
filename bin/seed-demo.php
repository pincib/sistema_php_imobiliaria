<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;

$pdo = Connection::getPdo();

$today15 = date('Y-m-d 15:30:00');
$today17 = date('Y-m-d 17:00:00');
$yesterday = date('Y-m-d 10:00:00', strtotime('-1 day'));
$future = date('Y-m-d 14:00:00', strtotime('+3 days'));

// 1. Imóveis
$pdo->exec("
INSERT OR IGNORE INTO properties (id, code, title, type, price, status, neighborhood, city) VALUES
(1, 'AP102', 'Apartamento 3 Quartos Vista Mar', 'apartamento', 1250000.00, 'disponivel', 'Icaraí', 'Niterói'),
(2, 'CA304', 'Casa Linear em Condomínio', 'casa', 2400000.00, 'disponivel', 'Camboinhas', 'Niterói');
");

// 2. Leads
$pdo->exec("
INSERT OR IGNORE INTO leads (id, name, email, phone, stage, source, user_id, notes, next_follow_up_at) VALUES
(1, 'Mariana Vasconcellos', 'mariana@gmail.com', '(21) 98765-4321', 'visita_agendada', 'instagram', 2, 'Interessada em apartamento de 3 quartos com vista mar.', '{$today15}'),
(2, 'Carlos Eduardo Mendes', 'carlos.mendes@empresa.com.br', '(21) 99123-5566', 'proposta', 'indicacao', 2, 'Apresentou proposta de R$ 1.200.000 à vista.', '{$today17}'),
(3, 'Dra. Beatriz Albuquerque', 'beatriz@albuquerque.adv.br', '(21) 97654-9988', 'qualificacao', 'site', 1, 'Financiamento pré-aprovado pelo Santander.', '{$yesterday}'),
(4, 'Felipe Siqueira', 'felipe.siqueira@outlook.com', '(21) 98877-1122', 'negociacao', 'portal', 2, 'Negociando permuta de veículo.', '{$future}');
");

// 3. Lead Properties
$pdo->exec("
INSERT OR IGNORE INTO lead_properties (lead_id, property_id, notes) VALUES
(1, 1, 'Visita marcada para o AP102'),
(2, 1, 'Proposta em análise para o AP102');
");

// 4. Follow-ups
$pdo->exec("
INSERT OR IGNORE INTO lead_follow_ups (id, lead_id, user_id, created_by_user_id, scheduled_at, status, notes) VALUES
(1, 1, 2, 2, '{$today15}', 'pending', 'Confirmar presença do marido na visita de hoje.'),
(2, 2, 2, 2, '{$today17}', 'pending', 'Enviar minuta de proposta revisada pelo proprietário.'),
(3, 3, 1, 1, '{$yesterday}', 'pending', 'Cobrar envio do comprovante de renda atualizado.'),
(4, 4, 2, 2, '{$future}', 'pending', 'Reunião de alinhamento jurídico sobre o contrato.');
");

echo "Demo data seeded successfully!\n";

// 5. Proprietários Demo
$fiveDaysAgo = date('Y-m-d H:i:s', strtotime('-5 days'));
$fortyFiveDaysAgo = date('Y-m-d H:i:s', strtotime('-45 days'));

$pdo->exec("
INSERT OR IGNORE INTO owners (id, name, email, phone, notes, last_verified_at, verified_by_user_id) VALUES
(1, 'Dr. Arnaldo Antunes Ribeiro', 'arnaldo.ribeiro@advocacia.com.br', '(21) 99887-7665', 'Proprietário de apartamento em Icaraí. Prefere contato via WhatsApp à tarde.', '{$fiveDaysAgo}', 1),
(2, 'Cláudia Mendonça de Castro', 'claudia.mendonca@uol.com.br', '(21) 98776-5544', 'Proprietária da casa em Camboinhas. Solicitou revisão de valor do aluguel.', '{$fortyFiveDaysAgo}', 2),
(3, 'Eng. Marcelo Fagundes', 'marcelo.fagundes@construtora.com', '(21) 97665-4433', 'Novo proprietário captado. Precisa de primeira verificação cadastral.', NULL, NULL);
");

$pdo->exec("
INSERT OR IGNORE INTO owner_properties (owner_id, property_id, linked_by_user_id) VALUES
(1, 1, 1),
(2, 2, 2);
");

echo "Owners demo data seeded successfully!\n";
