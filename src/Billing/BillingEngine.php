<?php
/**
 * Motor de Regras Determinístico - Cálculo de Cobrança
 * Sistema Estacionamento v3.0
 * 
 * 100% determinístico, reprodutível, auditável
 * Sem LLM/IA para cálculos financeiros críticos
 */

declare(strict_types=1);

class BillingEngine {
    
    /**
     * Calcula valor da cobrança baseado no modo tarifário
     * 
     * @param int $tempoMinutos Tempo total em minutos
     * @param string $modoCobranca Modo de cobrança (POR_MINUTO, HORA_FRACIONADA, etc)
     * @param float $valorTarifa Valor da tarifa base
     * @param int|null $granularidade Granularidade em minutos (5, 10, 15, 30)
     * @param int|null $tolerancia Tolerância em minutos para cortesia
     * @param string $regraArredondamento Regra de arredondamento (TETO, PISO, MATEMATICO)
     * @return array{valor: float, tempo_cobrado: int, descricao: string}
     */
    public function calcular(
        int $tempoMinutos,
        string $modoCobranca,
        float $valorTarifa,
        ?int $granularidade = null,
        ?int $tolerancia = null,
        string $regraArredondamento = REGRA_TETO
    ): array {
        // Verifica tolerância/cortesia
        if ($tolerancia !== null && $tempoMinutos <= $tolerancia) {
            return [
                'valor' => 0.00,
                'tempo_cobrado' => 0,
                'descricao' => 'CORTESIA (dentro da tolerância de ' . $tolerancia . ' min)',
                'motivo' => 'CORTESIA'
            ];
        }
        
        $valorFinal = 0.0;
        $tempoCobrado = 0;
        $descricao = '';
        
        switch ($modoCobranca) {
            case MODO_POR_MINUTO:
                [$valorFinal, $tempoCobrado, $descricao] = $this->calcularPorMinuto(
                    $tempoMinutos, $valorTarifa, $granularidade, $regraArredondamento
                );
                break;
                
            case MODO_HORA_FRACIONADA:
                [$valorFinal, $tempoCobrado, $descricao] = $this->calcularHoraFracionada(
                    $tempoMinutos, $valorTarifa, $regraArredondamento
                );
                break;
                
            case MODO_HORA_CHEIA:
                [$valorFinal, $tempoCobrado, $descricao] = $this->calcularHoraCheia(
                    $tempoMinutos, $valorTarifa, $regraArredondamento
                );
                break;
                
            case MODO_TARIFA_FIXA:
                [$valorFinal, $tempoCobrado, $descricao] = $this->calcularTarifaFixa(
                    $tempoMinutos, $valorTarifa
                );
                break;
                
            case MODO_DIARIA:
                [$valorFinal, $tempoCobrado, $descricao] = $this->calcularDiaria(
                    $tempoMinutos, $valorTarifa
                );
                break;
                
            default:
                throw new InvalidArgumentException("Modo de cobrança inválido: {$modoCobranca}");
        }
        
        return [
            'valor' => round($valorFinal, 2),
            'tempo_cobrado' => $tempoCobrado,
            'descricao' => $descricao,
            'moeda' => 'BRL',
            'metodo' => 'MANUAL'
        ];
    }
    
    /**
     * Modo: Cobrança por minuto com granularidade
     */
    private function calcularPorMinuto(
        int $tempoMinutos,
        float $valorTarifa,
        ?int $granularidade,
        string $regraArredondamento
    ): array {
        $tempoCobrado = $tempoMinutos;
        
        // Aplica granularidade se definida
        if ($granularidade !== null && $granularidade > 1) {
            $tempoCobrado = $this->aplicarGranularidade(
                $tempoMinutos, $granularidade, $regraArredondamento
            );
        }
        
        $valorFinal = $tempoCobrado * $valorTarifa;
        $descricao = sprintf(
            '%d minutos x R$ %.2f/min',
            $tempoCobrado,
            $valorTarifa
        );
        
        return [$valorFinal, $tempoCobrado, $descricao];
    }
    
    /**
     * Modo: Hora fracionada (proporcional aos minutos)
     * Ex: Tarifa hora = R$ 10,00 → 30min = R$ 5,00
     */
    private function calcularHoraFracionada(
        int $tempoMinutos,
        float $valorTarifa,
        string $regraArredondamento
    ): array {
        // Valor por minuto = tarifa_hora / 60
        $valorPorMinuto = $valorTarifa / 60.0;
        $valorFinal = $tempoMinutos * $valorPorMinuto;
        
        // Arredonda conforme regra
        $valorFinal = $this->aplicarArredondamento($valorFinal, $regraArredondamento);
        
        $descricao = sprintf(
            '%d minutos (proporcional à hora de R$ %.2f)',
            $tempoMinutos,
            $valorTarifa
        );
        
        return [$valorFinal, $tempoMinutos, $descricao];
    }
    
    /**
     * Modo: Hora cheia (teto - cada fração conta como hora completa)
     * Ex: 61 minutos = 2 horas
     */
    private function calcularHoraCheia(
        int $tempoMinutos,
        float $valorTarifa,
        string $regraArredondamento
    ): array {
        // Ceiling de horas
        $horas = (int) ceil($tempoMinutos / 60.0);
        $valorFinal = $horas * $valorTarifa;
        
        $descricao = sprintf(
            '%d hora(s) cheia(s) x R$ %.2f',
            $horas,
            $valorTarifa
        );
        
        return [$valorFinal, $horas * 60, $descricao];
    }
    
    /**
     * Modo: Tarifa fixa (valor único independente do tempo)
     */
    private function calcularTarifaFixa(
        int $tempoMinutos,
        float $valorTarifa
    ): array {
        $descricao = sprintf(
            'Tarifa fixa (permanência: %d min)',
            $tempoMinutos
        );
        
        return [$valorTarifa, $tempoMinutos, $descricao];
    }
    
    /**
     * Modo: Diária (com virada configurável)
     */
    private function calcularDiaria(
        int $tempoMinutos,
        float $valorTarifa
    ): array {
        // Considera diária completa a cada 24h (configurável)
        $horasDiaria = 24; // Pode ser lido de configuracoes_sistema.diaria_virada_horas
        $minutosDiaria = $horasDiaria * 60;
        
        $diarias = (int) ceil($tempoMinutos / $minutosDiaria);
        $valorFinal = $diarias * $valorTarifa;
        
        $descricao = sprintf(
            '%d diária(s) x R$ %.2f',
            $diarias,
            $valorTarifa
        );
        
        return [$valorFinal, $diarias * $minutosDiaria, $descricao];
    }
    
    /**
     * Aplica granularidade ao tempo
     * Ex: 23min com granularidade 15min e teto → 30min
     */
    private function aplicarGranularidade(
        int $minutos,
        int $granularidade,
        string $regra
    ): int {
        $resultado = 0;
        
        switch ($regra) {
            case REGRA_TETO:
                $resultado = (int) ceil($minutos / $granularidade) * $granularidade;
                break;
            case REGRA_PISO:
                $resultado = (int) floor($minutos / $granularidade) * $granularidade;
                break;
            case REGRA_MATEMATICO:
                $resultado = (int) round($minutos / $granularidade) * $granularidade;
                break;
        }
        
        return max($resultado, $granularidade); // Mínimo = 1 unidade de granularidade
    }
    
    /**
     * Aplica regra de arredondamento ao valor
     */
    private function aplicarArredondamento(
        float $valor,
        string $regra
    ): float {
        switch ($regra) {
            case REGRA_TETO:
                return ceil($valor * 100) / 100;
            case REGRA_PISO:
                return floor($valor * 100) / 100;
            case REGRA_MATEMATICO:
            default:
                return round($valor, 2);
        }
    }
    
    /**
     * Gera idempotency_key único para operação
     * UUIDv7-like (timestamp + random)
     */
    public function gerarIdempotencyKey(): string {
        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            time(),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffffffffffff)
        );
    }
    
    /**
     * Valida se cálculo é determinístico (mesmo input = mesmo output)
     * Usado para testes e auditoria
     */
    public function validarDeterminismo(array $params): bool {
        $resultado1 = $this->calcular(...$params);
        $resultado2 = $this->calcular(...$params);
        
        return $resultado1 === $resultado2;
    }
}
