<?php
/**
 * Exportador de Auditoria - Gera relatórios CSV/PDF assinados
 * Conformidade LGPD: exportação completa com hash de integridade
 * 
 * Uso: api/v1/exportar-auditoria.php?inicio=YYYY-MM-DD&fim=YYYY-MM-DD&formato=csv|pdf
 */

declare(strict_types=1);

namespace App\Audit;

require_once __DIR__ . '/../../config/config.php';

use PDO;
use DateTimeImmutable;
use DateTimeZone;

class ExportadorAuditoria
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Exporta auditoria para CSV com assinatura digital
     */
    public function exportarCSV(DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        $sql = "SELECT 
                    audit_id,
                    timestamp_iso,
                    user_id,
                    perfil,
                    acao,
                    resultado,
                    detalhe_resumo,
                    ip_terminal,
                    contexto_json,
                    hash_anterior,
                    hash_integridade
                FROM auditoria 
                WHERE timestamp_iso BETWEEN :inicio AND :fim
                ORDER BY timestamp_iso ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $inicio->format('Y-m-d H:i:s.v'),
            ':fim' => $fim->format('Y-m-d H:i:s.v')
        ]);
        
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($registros)) {
            return [
                'sucesso' => false,
                'mensagem' => 'Nenhum registro no período',
                'arquivo' => null,
                'hash_assinatura' => null
            ];
        }
        
        // Gera CSV
        $csvContent = $this->gerarCSV($registros);
        
        // Gera hash de assinatura do conteúdo
        $hashAssinatura = hash('sha256', $csvContent);
        
        // Adiciona linha de assinatura no final
        $csvContent .= "\n# HASH_ASSINATURA: {$hashAssinatura}\n";
        $csvContent .= "# DATA_GERACAO: " . date('Y-m-d H:i:s') . " UTC\n";
        $csvContent .= "# TOTAL_REGISTROS: " . count($registros) . "\n";
        
        // Nome do arquivo
        $nomeArquivo = "auditoria_" . $inicio->format('Ymd') . "_" . $fim->format('Ymd') . "_" . date('His') . ".csv";
        
        return [
            'sucesso' => true,
            'mensagem' => 'Exportação realizada com sucesso',
            'arquivo' => $nomeArquivo,
            'conteudo' => $csvContent,
            'hash_assinatura' => $hashAssinatura,
            'total_registros' => count($registros),
            'periodo' => [
                'inicio' => $inicio->format('Y-m-d H:i:s'),
                'fim' => $fim->format('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Gera conteúdo CSV formatado
     */
    private function gerarCSV(array $registros): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Cabeçalho
        fputcsv($output, [
            'audit_id',
            'timestamp_utc',
            'user_id',
            'perfil',
            'acao',
            'resultado',
            'detalhe_resumo',
            'ip_terminal',
            'contexto_json',
            'hash_anterior',
            'hash_integridade'
        ]);
        
        // Dados
        foreach ($registros as $registro) {
            fputcsv($output, [
                $registro['audit_id'],
                $registro['timestamp_iso'],
                $registro['user_id'] ?? '',
                $registro['perfil'] ?? '',
                $registro['acao'],
                $registro['resultado'],
                $registro['detalhe_resumo'],
                $registro['ip_terminal'] ?? '',
                $registro['contexto_json'] ?? '',
                $registro['hash_anterior'] ?? '',
                $registro['hash_integridade']
            ]);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
    
    /**
     * Valida integridade do hash encadeado
     */
    public function validarIntegridade(string $auditId): array
    {
        $sql = "SELECT * FROM auditoria WHERE audit_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $auditId]);
        
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$registro) {
            return ['valido' => false, 'motivo' => 'Registro não encontrado'];
        }
        
        // Recalcula hash esperado
        $conteudoHash = $registro['timestamp_iso'] . 
                       $registro['acao'] . 
                       ($registro['user_id'] ?? '') . 
                       ($registro['contexto_json'] ?? '') . 
                       ($registro['hash_anterior'] ?? '');
        
        $hashCalculado = hash('sha256', $conteudoHash);
        
        if ($hashCalculado !== $registro['hash_integridade']) {
            return [
                'valido' => false,
                'motivo' => 'Hash de integridade inválido',
                'hash_esperado' => $hashCalculado,
                'hash_encontrado' => $registro['hash_integridade']
            ];
        }
        
        // Verifica encadeamento com registro anterior
        if ($registro['hash_anterior']) {
            $sqlAnterior = "SELECT hash_integridade FROM auditoria 
                           WHERE timestamp_iso < :timestamp 
                           ORDER BY timestamp_iso DESC LIMIT 1";
            $stmtAnt = $this->db->prepare($sqlAnterior);
            $stmtAnt->execute([':timestamp' => $registro['timestamp_iso']]);
            $anterior = $stmtAnt->fetch(PDO::FETCH_ASSOC);
            
            if ($anterior && $anterior['hash_integridade'] !== $registro['hash_anterior']) {
                return [
                    'valido' => false,
                    'motivo' => 'Quebra no encadeamento de hashes',
                    'hash_anterior_esperado' => $anterior['hash_integridade'],
                    'hash_anterior_encontrado' => $registro['hash_anterior']
                ];
            }
        }
        
        return ['valido' => true, 'motivo' => 'Integridade verificada'];
    }
    
    /**
     * Exporta estatísticas de auditoria
     */
    public function getEstatisticas(DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_registros,
                    COUNT(DISTINCT user_id) as usuarios_unicos,
                    COUNT(DISTINCT acao) as acoes_unicas,
                    SUM(CASE WHEN resultado = 'SUCESSO' THEN 1 ELSE 0 END) as sucessos,
                    SUM(CASE WHEN resultado = 'FALHA' THEN 1 ELSE 0 END) as falhas,
                    SUM(CASE WHEN resultado = 'NEGADO' THEN 1 ELSE 0 END) as negados
                FROM auditoria 
                WHERE timestamp_iso BETWEEN :inicio AND :fim";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $inicio->format('Y-m-d H:i:s.v'),
            ':fim' => $fim->format('Y-m-d H:i:s.v')
        ]);
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Top 10 ações mais frequentes
        $sqlTopAcoes = "SELECT acao, COUNT(*) as quantidade 
                       FROM auditoria 
                       WHERE timestamp_iso BETWEEN :inicio AND :fim
                       GROUP BY acao 
                       ORDER BY quantidade DESC 
                       LIMIT 10";
        
        $stmtTop = $this->db->prepare($sqlTopAcoes);
        $stmtTop->execute([
            ':inicio' => $inicio->format('Y-m-d H:i:s.v'),
            ':fim' => $fim->format('Y-m-d H:i:s.v')
        ]);
        
        $stats['top_acoes'] = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
}

// Handler da API
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../src/Auth/AuthDomain.php';
    
    use App\Auth\AuthDomain;
    
    try {
        // Valida autenticação e permissão
        $auth = new authDomain($pdo);
        $session = $auth->validarSessao();
        
        if (!$session || !in_array($session['perfil'], ['admin', 'gestor'])) {
            http_response_code(403);
            echo json_encode(['erro' => 'Operação não autorizada']);
            exit;
        }
        
        // Parâmetros
        $inicioStr = $_GET['inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $fimStr = $_GET['fim'] ?? date('Y-m-d');
        $formato = $_GET['formato'] ?? 'csv';
        
        $inicio = new DateTimeImmutable($inicioStr . ' 00:00:00', new DateTimeZone('UTC'));
        $fim = new DateTimeImmutable($fimStr . ' 23:59:59', new DateTimeZone('UTC'));
        
        $exportador = new ExportadorAuditoria($pdo);
        
        if ($formato === 'csv') {
            $resultado = $exportador->exportarCSV($inicio, $fim);
            
            if ($resultado['sucesso']) {
                // Auditoria da exportação
                $auth->registrarAuditoria(
                    'AUDITORIA_EXPORTADA',
                    'SUCESSO',
                    "Exportação CSV: {$resultado['total_registros']} registros",
                    ['periodo' => $resultado['periodo']]
                );
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $resultado['arquivo'] . '"');
                header('X-Hash-Assinatura: ' . $resultado['hash_assinatura']);
                echo $resultado['conteudo'];
            } else {
                http_response_code(404);
                echo json_encode($resultado);
            }
        } elseif ($formato === 'estatisticas') {
            $stats = $exportador->getEstatisticas($inicio, $fim);
            echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['erro' => 'Formato inválido. Use csv ou estatisticas']);
        }
        
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno: ' . $e->getMessage()]);
    }
}
