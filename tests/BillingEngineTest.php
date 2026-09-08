<?php
/**
 * Testes Unitários - BillingEngine (Motor de Cobrança Determinístico)
 * Valida os 5 modos de cobrança com 20+ casos de teste
 * 
 * Requisitos: PHPUnit 9.x
 * Execução: php vendor/bin/phpunit tests/BillingEngineTest.php
 */

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Billing\BillingEngine;
use DateTimeImmutable;
use DateTimeZone;

class BillingEngineTest extends TestCase
{
    private BillingEngine $engine;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Mock PDO para configurações
        $pdo = $this->createMock(\PDO::class);
        $this->engine = new BillingEngine($pdo);
    }
    
    // ============================================
    // MODO 1: POR_MINUTO
    // ============================================
    
    public function testPorMinuto_Exato15Minutos_R$3_75(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 10:15:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25, // R$ 0.25 por minuto
            15,   // granularidade
            15,   // tolerância
            'TETO'
        );
        
        $this->assertEquals(15, $resultado['tempo_minutos']);
        $this->assertEquals(3.75, $resultado['valor_calculado']);
        $this->assertEquals('POR_MINUTO', $resultado['modo_cobranca']);
    }
    
    public function testPorMinuto_ComTolerancia_Cortesia(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 10:10:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25,
            15,
            15, // tolerância de 15 min
            'TETO'
        );
        
        $this->assertEquals(10, $resultado['tempo_minutos']);
        $this->assertEquals(0.00, $resultado['valor_calculado']);
        $this->assertEquals('CORTESIA', $resultado['status_pagamento']);
    }
    
    public function testPorMinuto_1HoraCompleta_R$15(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 11:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25,
            15,
            0, // sem tolerância
            'TETO'
        );
        
        $this->assertEquals(60, $resultado['tempo_minutos']);
        $this->assertEquals(15.00, $resultado['valor_calculado']);
    }
    
    // ============================================
    // MODO 2: HORA_FRACIONADA (proporcional)
    // ============================================
    
    public function testHoraFracionada_30Minutos_MeiaHora(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'HORA_FRACIONADA',
            10.00, // R$ 10/hora
            15,
            0,
            'TETO'
        );
        
        $this->assertEquals(30, $resultado['tempo_minutos']);
        $this->assertEquals(5.00, $resultado['valor_calculado']); // 0.5 hora * R$ 10
    }
    
    public function testHoraFracionada_1h45min_Proporcional(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 11:45:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'HORA_FRACIONADA',
            12.00, // R$ 12/hora
            15,
            0,
            'TETO'
        );
        
        $this->assertEquals(105, $resultado['tempo_minutos']);
        $this->assertEquals(21.00, $resultado['valor_calculado']); // 1.75 horas * R$ 12
    }
    
    // ============================================
    // MODO 3: HORA_CHEIA (teto)
    // ============================================
    
    public function testHoraCheia_31Minutos_Cobra1Hora(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 10:31:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'HORA_CHEIA',
            8.00, // R$ 8/hora cheia
            15,
            0,
            'TETO'
        );
        
        $this->assertEquals(31, $resultado['tempo_minutos']);
        $this->assertEquals(8.00, $resultado['valor_calculado']); // 1 hora cheia
    }
    
    public function testHoraCheia_1h01min_Cobra2Horas(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 11:01:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'HORA_CHEIA',
            10.00,
            15,
            0,
            'TETO'
        );
        
        $this->assertEquals(61, $resultado['tempo_minutos']);
        $this->assertEquals(20.00, $resultado['valor_calculado']); // 2 horas cheias
    }
    
    public function testHoraCheia_Exato1Hora_Cobra1Hora(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 11:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'HORA_CHEIA',
            12.00,
            15,
            0,
            'TETO'
        );
        
        $this->assertEquals(60, $resultado['tempo_minutos']);
        $this->assertEquals(12.00, $resultado['valor_calculado']); // 1 hora exata
    }
    
    // ============================================
    // MODO 4: TARIFA_FIXA
    // ============================================
    
    public function testTarifaFixa_QualquerTempo_ValorUnico(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'TARIFA_FIXA',
            25.00, // Valor fixo
            null,
            0,
            'TETO'
        );
        
        $this->assertEquals(30, $resultado['tempo_minutos']);
        $this->assertEquals(25.00, $resultado['valor_calculado']);
    }
    
    public function testTarifaFixa_TempoLongo_MesmoValor(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 08:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 20:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'TARIFA_FIXA',
            25.00,
            null,
            0,
            'TETO'
        );
        
        $this->assertEquals(720, $resultado['tempo_minutos']); // 12 horas
        $this->assertEquals(25.00, $resultado['valor_calculado']);
    }
    
    // ============================================
    // MODO 5: DIARIA
    // ============================================
    
    public function testDiaria_MenosDe24Horas_Cobra1Dia(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 22:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'DIARIA',
            50.00, // R$ 50/dia
            null,
            0,
            'TETO',
            24 // virada em 24h
        );
        
        $this->assertEquals(720, $resultado['tempo_minutos']); // 12 horas
        $this->assertEquals(50.00, $resultado['valor_calculado']); // 1 dia
    }
    
    public function testDiaria_30Horas_Cobra2Dias(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-16 16:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'DIARIA',
            50.00,
            null,
            0,
            'TETO',
            24
        );
        
        $this->assertEquals(1800, $resultado['tempo_minutos']); // 30 horas
        $this->assertEquals(100.00, $resultado['valor_calculado']); // 2 dias
    }
    
    public function testDiaria_ViradaPersonalizada_12Horas(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 23:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'DIARIA',
            40.00,
            null,
            0,
            'TETO',
            12 // virada em 12 horas
        );
        
        $this->assertEquals(780, $resultado['tempo_minutos']); // 13 horas
        $this->assertEquals(80.00, $resultado['valor_calculado']); // 2 dias (13h > 12h)
    }
    
    // ============================================
    // REGRAS DE ARREDONDAMENTO
    // ============================================
    
    public function testArredondamentoTeto_23Minutos_ComGranularidade15(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 10:23:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25,
            15, // granularidade 15 min
            0,
            'TETO'
        );
        
        $this->assertEquals(23, $resultado['tempo_minutos']);
        $this->assertEquals(6.00, $resultado['valor_calculado']); // 24 min arredondados (2 blocos de 15)
    }
    
    public function testArredondamentoPiso_23Minutos_ComGranularidade15(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 10:23:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25,
            15,
            0,
            'PISO'
        );
        
        $this->assertEquals(23, $resultado['tempo_minutos']);
        $this->assertEquals(3.75, $resultado['valor_calculado']); // 15 min (1 bloco)
    }
    
    public function testArredondamentoMatematico_23Minutos_ComGranularidade15(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 10:23:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25,
            15,
            0,
            'MATEMATICO'
        );
        
        $this->assertEquals(23, $resultado['tempo_minutos']);
        $this->assertEquals(3.75, $resultado['valor_calculado']); // 15 min (arredonda pra baixo)
    }
    
    // ============================================
    // CASOS ESPECIAIS E EDGE CASES
    // ============================================
    
    public function testMesmoDia_ViradaMeiaNoite(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 23:30:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-16 00:30:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25,
            15,
            0,
            'TETO'
        );
        
        $this->assertEquals(60, $resultado['tempo_minutos']);
        $this->assertEquals(15.00, $resultado['valor_calculado']);
    }
    
    public function testPeríodoLongo_7Dias(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-22 10:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'DIARIA',
            50.00,
            null,
            0,
            'TETO',
            24
        );
        
        $this->assertEquals(10080, $resultado['tempo_minutos']); // 7 dias * 24 * 60
        $this->assertEquals(350.00, $resultado['valor_calculado']); // 7 dias * R$ 50
    }
    
    public function testDeterministico_MesmosInputs_MesmoResultado(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 12:37:00', new DateTimeZone('UTC'));
        
        $resultado1 = $this->engine->calcular(
            $entrada,
            $saida,
            'HORA_FRACIONADA',
            15.00,
            15,
            0,
            'TETO'
        );
        
        $resultado2 = $this->engine->calcular(
            $entrada,
            $saida,
            'HORA_FRACIONADA',
            15.00,
            15,
            0,
            'TETO'
        );
        
        $this->assertSame($resultado1['valor_calculado'], $resultado2['valor_calculado']);
        $this->assertSame($resultado1['tempo_minutos'], $resultado2['tempo_minutos']);
        $this->assertSame($resultado1['payload_hash'], $resultado2['payload_hash']);
    }
    
    public function testIdempotencyKey_SempreGerada(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 11:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25,
            15,
            0,
            'TETO'
        );
        
        $this->assertNotEmpty($resultado['idempotency_key']);
        $this->assertEquals(36, strlen($resultado['idempotency_key'])); // UUID length
    }
    
    public function testPayloadAuditoria_EstruturaCompleta(): void
    {
        $entrada = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $saida = new DateTimeImmutable('2024-01-15 11:00:00', new DateTimeZone('UTC'));
        
        $resultado = $this->engine->calcular(
            $entrada,
            $saida,
            'POR_MINUTO',
            0.25,
            15,
            0,
            'TETO'
        );
        
        $this->assertArrayHasKey('valor_calculado', $resultado);
        $this->assertArrayHasKey('moeda', $resultado);
        $this->assertArrayHasKey('metodo', $resultado);
        $this->assertArrayHasKey('status', $resultado);
        $this->assertArrayHasKey('idempotency_key', $resultado);
        $this->assertArrayHasKey('payload_hash', $resultado);
        $this->assertArrayHasKey('timestamp_utc', $resultado);
        
        $this->assertEquals('BRL', $resultado['moeda']);
        $this->assertEquals('MANUAL', $resultado['metodo']);
    }
}
